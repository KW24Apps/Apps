<?php

namespace Controllers;

require_once __DIR__ . '/../services/DateCalculatorService.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php'; // Adicionado
// require_once __DIR__ . '/../helpers/LogHelper.php'; // Removido conforme feedback

use Services\DateCalculatorService;
use Helpers\BitrixHelper;
use Helpers\BitrixDealHelper; // Adicionado
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
        // 1. Extrair parâmetros da URL (GET), conforme o exemplo do webhook
        $data01 = $_GET['data01'] ?? null;
        $data02 = $_GET['data02'] ?? null; // Opcional
        $retorno = $_GET['retorno'] ?? null;
        $spaId = $_GET['spa'] ?? null; // ID da SPA, que será o entityTypeId (corrigido para 'spa' minúsculo)
        $dealId = $_GET['deal'] ?? null; // ID do Deal, que será o ID do item

        // O cliente já foi validado no INDEX, e o webhook do Bitrix está disponível na variável global.

        // 2. Validar parâmetros essenciais
        if (!$data01 || !$retorno || !$spaId || !$dealId) { // Exigir spaId e dealId
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parâmetros data01, retorno, SPA e deal são obrigatórios.'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        }

        try {
            // 3. Calcular a diferença de datas
            $daysDifference = $this->dateCalculatorService->calculateDifferenceInDays($data01, $data02);

            // 4. Preparar dados para atualização no Bitrix
            $fieldsToUpdate = [
                $retorno => $daysDifference
            ];

            // 5. Chamar BitrixDealHelper para atualizar o campo
            // O primeiro parâmetro é o entityTypeId (SPA ID), o segundo é o dealId (ID do item)
            $bitrixResult = BitrixDealHelper::editarDeal($spaId, $dealId, $fieldsToUpdate);

            if (isset($bitrixResult['error']) || (isset($bitrixResult['status']) && $bitrixResult['status'] === 'erro')) {
                // LogHelper::logBitrixHelpers("Erro ao atualizar Bitrix: " . json_encode($bitrixResult, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__); // Removido conforme feedback
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao atualizar Bitrix.',
                    'bitrix_error' => $bitrixResult['mensagem'] ?? $bitrixResult['error_description'] ?? $bitrixResult['error']
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
