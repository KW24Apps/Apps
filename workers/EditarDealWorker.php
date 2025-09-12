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
    private const BATCH_SIZE = 10; // Número máximo de itens por lote para enviar ao Bitrix
    private const BATCH_DELAY_SECONDS = 1; // Atraso de 1 segundo entre cada lote

    public function __construct()
    {
        // Garante que os diretórios da fila existam
        $queueDir = dirname(self::QUEUE_FILE);
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0777, true);
        }
        // Garante que os arquivos de fila processados e de erro existam
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
        $currentBatch = [];

        foreach ($pendingUpdates as $index => $updateData) {
            $currentBatch[] = $updateData;

            // Processa o lote se atingir o tamanho máximo ou se for o último item
            if (count($currentBatch) >= self::BATCH_SIZE || $index === count($pendingUpdates) - 1) {
                LogHelper::logAcessoAplicacao(['mensagem' => 'Processando lote de ' . count($currentBatch) . ' atualizações.'], 'INFO');
                
                // Agrupar dealIds e fieldsToUpdate para a chamada batch
                $dealIds = [];
                $fieldsCollection = [];
                $spaId = null; // Será definido pelo primeiro item do lote
                $webhookBitrix = null; // Será definido pelo primeiro item do lote

                foreach ($currentBatch as $item) {
                    $dealIds[] = $item['dealId'];
                    $fieldsCollection[] = $item['fieldsToUpdate'];
                    if ($spaId === null) {
                        $spaId = $item['spaId']; // Assume que todos os itens no lote têm o mesmo spaId
                    }
                    if ($webhookBitrix === null) {
                        $webhookBitrix = $item['webhookBitrix'] ?? null; // Recupera o webhook do primeiro item do lote
                    }
                }

                // Configura o webhook na variável global antes de chamar BitrixDealHelper
                if ($webhookBitrix) {
                    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhookBitrix;
                } else {
                    LogHelper::logAcessoAplicacao(['mensagem' => 'Webhook não encontrado na fila para o lote. Pulando lote.', 'updates' => $currentBatch], 'ERROR');
                    $errorCount += count($currentBatch);
                    $this->logErrorUpdates($currentBatch, ['error' => 'Webhook não encontrado na fila para o lote.']);
                    $currentBatch = []; // Limpa o lote e continua para o próximo
                    continue;
                }

                try {
                    // Chamar BitrixDealHelper::editarDeal que já usa batch internamente
                    $bitrixResult = BitrixDealHelper::editarDeal($spaId, $dealIds, $fieldsCollection, self::BATCH_SIZE);

                    if (isset($bitrixResult['status']) && $bitrixResult['status'] === 'sucesso') {
                        $processedCount += $bitrixResult['quantidade'];
                        LogHelper::logAcessoAplicacao(['mensagem' => 'Lote processado com sucesso.', 'result' => $bitrixResult], 'INFO');
                        $this->logProcessedUpdates($currentBatch);
                    } else {
                        $errorCount += count($currentBatch);
                        LogHelper::logAcessoAplicacao(['mensagem' => 'Erro ao processar lote no Bitrix.', 'result' => $bitrixResult, 'updates' => $currentBatch], 'ERROR');
                        $this->logErrorUpdates($currentBatch, $bitrixResult);
                    }
                } catch (Exception $e) {
                    $errorCount += count($currentBatch);
                    LogHelper::logAcessoAplicacao(['mensagem' => 'Exceção ao processar lote no Bitrix: ' . $e->getMessage(), 'updates' => $currentBatch, 'trace' => $e->getTraceAsString()], 'ERROR');
                    $this->logErrorUpdates($currentBatch, ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                }

                $currentBatch = []; // Limpar lote para o próximo
                sleep(self::BATCH_DELAY_SECONDS); // Atraso de 1 segundo entre os lotes
            }
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
