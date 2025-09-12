<?php

namespace Controllers;

require_once __DIR__ . '/../services/DateCalculatorService.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php'; // Adicionado para logging da fila

use Services\DateCalculatorService;
use Helpers\BitrixHelper;
use Helpers\BitrixDealHelper;
use Helpers\LogHelper; // Adicionado para logging da fila
use Exception;

class DateCalculatorController
{
    private $dateCalculatorService;
    private const QUEUE_FILE = __DIR__ . '/../queue/bitrix_updates.json';

    public function __construct()
    {
        $this->dateCalculatorService = new DateCalculatorService();
        // Garante que o diretório da fila exista
        $queueDir = dirname(self::QUEUE_FILE);
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0777, true);
        }
    }

    public function calculateDateDifferenceWebhook()
    {
        $data01 = $_GET['data01'] ?? null;
        $data02 = $_GET['data02'] ?? null;
        $retorno = $_GET['retorno'] ?? null;
        $spaId = $_GET['spa'] ?? null;
        $dealId = $_GET['deal'] ?? null;

        if (!$retorno || !$spaId || !$dealId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parâmetros retorno, SPA e deal são obrigatórios.'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        }

        try {
            $message = '';
            $daysDifference = null;

            if (!$data01) {
                $message = 'API: Sem data para realizar o calculo';
            } else {
                $daysDifference = $this->dateCalculatorService->calculateDifferenceInDays($data01, $data02);
                if ($daysDifference < 0) {
                    $message = 'API: Data de calculo inferior a de teste';
                }
            }

            $fieldsToUpdate = [];
            if (!empty($message)) {
                $fieldsToUpdate = [$retorno => $message];
            } else {
                $fieldsToUpdate = [$retorno => $daysDifference];
            }

            // Enfileirar a requisição em vez de chamar a API do Bitrix diretamente
            $queueData = [
                'spaId' => $spaId,
                'dealId' => $dealId,
                'fieldsToUpdate' => $fieldsToUpdate,
                'timestamp' => time()
            ];

            $this->enqueueBitrixUpdate($queueData);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Requisição enfileirada para atualização do Bitrix.',
                'queued_data' => $queueData
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        } catch (Exception $e) {
            LogHelper::logAcessoAplicacao(['mensagem' => 'Erro no cálculo ou enfileiramento: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()], 'ERROR');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno do servidor: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }

    private function enqueueBitrixUpdate(array $data): void
    {
        $file = self::QUEUE_FILE;
        $jsonLine = json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;

        $fp = fopen($file, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) { // Bloqueio exclusivo para escrita
                fwrite($fp, $jsonLine);
                flock($fp, LOCK_UN); // Libera o bloqueio
                LogHelper::logAcessoAplicacao(['mensagem' => 'Requisição adicionada à fila.', 'data' => $data], 'INFO');
            } else {
                LogHelper::logAcessoAplicacao(['mensagem' => 'Não foi possível obter o bloqueio do arquivo de fila para escrita.', 'data' => $data], 'ERROR');
                throw new Exception('Não foi possível enfileirar a requisição devido a um problema de bloqueio de arquivo.');
            }
            fclose($fp);
        } else {
            LogHelper::logAcessoAplicacao(['mensagem' => 'Não foi possível abrir o arquivo de fila para escrita.', 'file' => $file, 'data' => $data], 'ERROR');
            throw new Exception('Não foi possível enfileirar a requisição.');
        }
    }
}
