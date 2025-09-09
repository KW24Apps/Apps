<?php

namespace Controllers;

require_once __DIR__ . '/../services/DateCalculatorService.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
// require_once __DIR__ . '/../helpers/LogHelper.php'; // Removido conforme feedback

use Services\DateCalculatorService;
use Helpers\BitrixHelper;
// use Helpers\LogHelper; // Removido conforme feedback
use Exception;

class DateCalculatorController
{
    private $dateCalculatorService;

    public function __construct()
    {
        $this->dateCalculatorService = new DateCalculatorService();
    }

    public function calculateDateDifferenceWebhook()
    {
        // 1. Extrair parâmetros do corpo da requisição (POST)
        $data01 = $_POST['data01'] ?? null;
        $data02 = $_POST['data02'] ?? null; // Opcional
        $retorno = $_POST['retorno'] ?? null;
        $spaId = $_POST['SPA'] ?? null; // ID da SPA, se aplicável
        $dealId = $_POST['deal'] ?? null; // ID do Deal, se aplicável

        // O cliente já foi validado no INDEX, e o webhook do Bitrix está disponível na variável global.

        // 2. Validar parâmetros essenciais
        if (!$data01 || !$retorno || (!$spaId && !$dealId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parâmetros data01, retorno e pelo menos um de SPA ou deal são obrigatórios.'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        }

        $targetEntityId = null;
        $targetEntityTypeId = null;

        if ($dealId) {
            $targetEntityId = $dealId;
            $targetEntityTypeId = 2; // ID para Deals no Bitrix
        } elseif ($spaId) {
            // Se for SPA, o ID da SPA é o entityTypeId e o ID do item da SPA é o entityId
            // Assumindo que o ID do item da SPA virá em um parâmetro 'itemId' ou similar,
            // mas como não foi especificado, vou assumir que 'SPA' é o entityTypeId e 'deal' é o itemId para SPAs.
            // Se 'SPA' é o entityTypeId, então precisamos de um 'itemId' para o ID do item.
            // Por enquanto, vou assumo que 'SPA' é o entityTypeId e 'deal' é o ID do item da SPA.
            // Se o usuário passar 'SPA=191&deal=123', então entityTypeId=191 e entityId=123.
            $targetEntityId = $dealId; // Reutilizando 'deal' para o ID do item da SPA
            $targetEntityTypeId = $spaId; // 'SPA' é o entityTypeId
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível determinar a entidade alvo (Deal ou SPA).'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        }

        if (!$targetEntityId || !$targetEntityTypeId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível determinar o ID ou o tipo da entidade alvo para atualização no Bitrix.'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        }

        try {
            // 4. Calcular a diferença de datas
            $daysDifference = $this->dateCalculatorService->calculateDifferenceInDays($data01, $data02);

            // 5. Preparar dados para atualização no Bitrix
            $fieldsToUpdate = [
                $retorno => $daysDifference
            ];

            // 6. Chamar BitrixHelper para atualizar o campo
            $bitrixResult = BitrixHelper::chamarApi('crm.item.update', [
                'entityTypeId' => $targetEntityTypeId,
                'id' => $targetEntityId,
                'fields' => $fieldsToUpdate
            ]);

            if (isset($bitrixResult['error'])) {
                // LogHelper::logBitrixHelpers("Erro ao atualizar Bitrix: " . json_encode($bitrixResult), __CLASS__ . '::' . __FUNCTION__); // Removido conforme feedback
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao atualizar Bitrix.',
                    'bitrix_error' => $bitrixResult['error_description'] ?? $bitrixResult['error']
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Diferença de dias calculada e Bitrix atualizado com sucesso.',
                'days_difference' => $daysDifference,
                'bitrix_response' => $bitrixResult
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        } catch (Exception $e) {
            // LogHelper::logAcessoAplicacao(['mensagem' => 'Erro no cálculo ou atualização: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()], 'ERROR'); // Removido conforme feedback
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno do servidor: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }
}
