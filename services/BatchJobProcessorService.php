<?php

namespace Services;

require_once __DIR__ . '/../Repositories/BatchJobDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Repositories\BatchJobDAO;
use Helpers\BitrixDealHelper;
use Helpers\LogHelper;
use Exception;

class BatchJobProcessorService
{
    private $batchJobDAO;

    public function __construct()
    {
        $this->batchJobDAO = new BatchJobDAO();
    }

    /**
     * Processa um item individual de um job em lote.
     * Retorna o status do item processado.
     */
    public function processarItem(string $jobId, array $itemData, string $tipoJob, string $spaId, ?string $categoryId, ?string $webhookBitrix): array
    {
        $itemResult = [
            'item_id' => $itemData['id'] ?? uniqid('item_'), // ID único para o item, se não tiver um
            'status' => 'pendente',
            'mensagem' => 'Item não processado',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Configura o webhook na variável global antes de chamar BitrixDealHelper
        if ($webhookBitrix) {
            $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhookBitrix;
        } else {
            $itemResult['status'] = 'erro';
            $itemResult['mensagem'] = 'Webhook não informado para o item.';
            LogHelper::logDealBatchController("ERRO ITEM - Webhook não informado | Job: $jobId | Item: " . json_encode($itemData));
            return $itemResult;
        }

        try {
            $resultadoBitrix = null;
            if ($tipoJob === 'criar_deals') {
                // Para criar, itemData deve ser um array de campos
                $resultadoBitrix = BitrixDealHelper::criarDeal($spaId, $categoryId, [$itemData], 1); // Processa 1 item por vez
            } elseif ($tipoJob === 'editar_deals') {
                // Para editar, itemData deve conter 'id' e 'fields'
                $dealId = $itemData['id'] ?? null;
                $fields = $itemData['fields'] ?? [];
                if (!$dealId) {
                    throw new Exception("ID do deal não fornecido para edição do item.");
                }
                $resultadoBitrix = BitrixDealHelper::editarDeal($spaId, $dealId, $fields, 1); // Processa 1 item por vez
            } else {
                throw new Exception('Tipo de job não suportado para item individual: ' . $tipoJob);
            }

            if (isset($resultadoBitrix['status']) && $resultadoBitrix['status'] === 'sucesso') {
                $itemResult['status'] = 'sucesso';
                $itemResult['mensagem'] = 'Item processado com sucesso.';
                $itemResult['bitrix_response'] = $resultadoBitrix;
            } else {
                $itemResult['status'] = 'erro';
                $itemResult['mensagem'] = $resultadoBitrix['mensagem'] ?? 'Erro desconhecido no Bitrix.';
                $itemResult['bitrix_response'] = $resultadoBitrix;
                LogHelper::logDealBatchController("ERRO ITEM - Bitrix falhou | Job: $jobId | Item: " . json_encode($itemData) . " | Resposta Bitrix: " . json_encode($resultadoBitrix));
            }
        } catch (Exception $e) {
            $itemResult['status'] = 'erro';
            $itemResult['mensagem'] = 'Exceção no processamento do item: ' . $e->getMessage();
            LogHelper::logDealBatchController("EXCEÇÃO ITEM - Job: $jobId | Item: " . json_encode($itemData) . " | Erro: " . $e->getMessage());
        }

        return $itemResult;
    }
}
