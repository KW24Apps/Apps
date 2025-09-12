<?php

namespace Workers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\BitrixDealHelper;
use Helpers\LogHelper;
use Exception;

class EditarDealWorker
{
    private const QUEUE_FILE = __DIR__ . '/../queue/bitrix_updates.json';
    private const PROCESSED_QUEUE_FILE = __DIR__ . '/../queue/bitrix_updates_processed.json';
    private const ERROR_QUEUE_FILE = __DIR__ . '/../queue/bitrix_updates_error.json';
    private const BATCH_SIZE = 15; // Max items per Bitrix batch call (BitrixDealHelper already handles this, but for clarity)
    private const REQUEST_DELAY_SECONDS = 0.2; // 1/5 = 0.2 seconds per request to stay within 5 req/sec limit

    public function __construct()
    {
        // Ensure queue directories exist
        $queueDir = dirname(self::QUEUE_FILE);
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0777, true);
        }
        // Ensure processed and error queue files exist
        if (!file_exists(self::PROCESSED_QUEUE_FILE)) {
            file_put_contents(self::PROCESSED_QUEUE_FILE, '');
        }
        if (!file_exists(self::ERROR_QUEUE_FILE)) {
            file_put_contents(self::ERROR_QUEUE_FILE, '');
        }
    }

    public function processQueue(): void
    {
        LogHelper::logAcessoAplicacao(['mensagem' => 'Iniciando processamento da fila do Bitrix.'], 'INFO');

        $pendingUpdates = $this->readQueue();
        if (empty($pendingUpdates)) {
            LogHelper::logAcessoAplicacao(['mensagem' => 'Fila do Bitrix vazia. Encerrando.'], 'INFO');
            return;
        }

        $processedCount = 0;
        $errorCount = 0;

        foreach ($pendingUpdates as $updateData) {
            $spaId = $updateData['spaId'];
            $dealId = $updateData['dealId'];
            $fieldsToUpdate = $updateData['fieldsToUpdate'];
            $webhookBitrix = $updateData['webhookBitrix'] ?? null; // Recupera o webhook da fila

            LogHelper::logAcessoAplicacao(['mensagem' => "Processando atualização para Deal ID: $dealId (SPA ID: $spaId)."], 'INFO');

            // Configura o webhook na variável global antes de chamar BitrixDealHelper
            if ($webhookBitrix) {
                $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhookBitrix;
            } else {
                LogHelper::logAcessoAplicacao(['mensagem' => 'Webhook não encontrado na fila para o item.', 'dealId' => $dealId, 'update' => $updateData], 'ERROR');
                $errorCount++;
                $this->logErrorUpdates([$updateData], ['error' => 'Webhook não encontrado na fila.']);
                continue; // Pula para o próximo item da fila
            }

            try {
                // Chamar BitrixDealHelper::editarDeal para um único item.
                // O BitrixDealHelper já encapsula isso em uma chamada batch interna.
                $bitrixResult = BitrixDealHelper::editarDeal($spaId, $dealId, $fieldsToUpdate);

                if (isset($bitrixResult['status']) && $bitrixResult['status'] === 'sucesso') {
                    $processedCount++;
                    LogHelper::logAcessoAplicacao(['mensagem' => 'Atualização processada com sucesso.', 'dealId' => $dealId, 'result' => $bitrixResult], 'INFO');
                    $this->logProcessedUpdates([$updateData]); // Log como array para consistência
                } else {
                    $errorCount++;
                    LogHelper::logAcessoAplicacao(['mensagem' => 'Erro ao processar atualização no Bitrix.', 'dealId' => $dealId, 'result' => $bitrixResult, 'update' => $updateData], 'ERROR');
                    $this->logErrorUpdates([$updateData], $bitrixResult); // Log como array para consistência
                }
            } catch (Exception $e) {
                $errorCount++;
                LogHelper::logAcessoAplicacao(['mensagem' => 'Exceção ao processar atualização no Bitrix: ' . $e->getMessage(), 'dealId' => $dealId, 'update' => $updateData, 'trace' => $e->getTraceAsString()], 'ERROR');
                $this->logErrorUpdates([$updateData], ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]); // Log como array para consistência
            }

            usleep(self::REQUEST_DELAY_SECONDS * 1000000); // Atraso para respeitar o limite de taxa
        }

        $this->clearQueueFile(); // Limpa o arquivo da fila após processar tudo

        LogHelper::logAcessoAplicacao(['mensagem' => "Processamento da fila do Bitrix concluído. Sucessos: $processedCount, Erros: $errorCount."], 'INFO');
    }

    private function readQueue(): array
    {
        $file = self::QUEUE_FILE;
        $updates = [];

        if (!file_exists($file)) {
            return [];
        }

        $fp = fopen($file, 'r');
        if ($fp) {
            if (flock($fp, LOCK_SH)) { // Bloqueio compartilhado para leitura
                while (($line = fgets($fp)) !== false) {
                    $data = json_decode(trim($line), true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $updates[] = $data;
                    } else {
                        LogHelper::logAcessoAplicacao(['mensagem' => 'Erro ao decodificar JSON na fila.', 'line' => $line, 'error' => json_last_error_msg()], 'ERROR');
                    }
                }
                flock($fp, LOCK_UN); // Libera o bloqueio
            } else {
                LogHelper::logAcessoAplicacao(['mensagem' => 'Não foi possível obter o bloqueio do arquivo de fila para leitura.'], 'ERROR');
            }
            fclose($fp);
        } else {
            LogHelper::logAcessoAplicacao(['mensagem' => 'Não foi possível abrir o arquivo de fila para leitura.', 'file' => $file], 'ERROR');
        }
        return $updates;
    }

    private function clearQueueFile(): void
    {
        $file = self::QUEUE_FILE;
        $fp = fopen($file, 'w'); // Abre para escrita, truncando o arquivo
        if ($fp) {
            if (flock($fp, LOCK_EX)) { // Bloqueio exclusivo
                ftruncate($fp, 0); // Limpa o conteúdo
                flock($fp, LOCK_UN); // Libera o bloqueio
            } else {
                LogHelper::logAcessoAplicacao(['mensagem' => 'Não foi possível obter o bloqueio do arquivo de fila para limpar.', 'file' => $file], 'ERROR');
            }
            fclose($fp);
        } else {
            LogHelper::logAcessoAplicacao(['mensagem' => 'Não foi possível abrir o arquivo de fila para limpar.', 'file' => $file], 'ERROR');
        }
    }

    private function logProcessedUpdates(array $updates): void
    {
        $file = self::PROCESSED_QUEUE_FILE;
        $fp = fopen($file, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                foreach ($updates as $data) {
                    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL);
                }
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }

    private function logErrorUpdates(array $updates, array $errorInfo): void
    {
        $file = self::ERROR_QUEUE_FILE;
        $fp = fopen($file, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                foreach ($updates as $data) {
                    $errorData = array_merge($data, ['error_info' => $errorInfo, 'error_timestamp' => time()]);
                    fwrite($fp, json_encode($errorData, JSON_UNESCAPED_UNICODE) . PHP_EOL);
                }
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
}

// Para execução via CLI
if (php_sapi_name() === 'cli') {
    $worker = new EditarDealWorker();
    $worker->processQueue();
}
