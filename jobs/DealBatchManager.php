<?php
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../helpers/LogHelper.php';
use Helpers\LogHelper;
// Gera traceId para toda execução do job
LogHelper::gerarTraceId();
/**
 * Processador de Jobs em Batch - Bitrix24
 * 
 * Este arquivo deve ser executado via cron a cada minuto:
 * * * * * * php /caminho/para/processar_jobs.php
 * 
 * Processa jobs pendentes da tabela batch_jobs
 */

require_once __DIR__ . '/../controllers/DealBatchController.php';
use Controllers\DealBatchController;

try {
    // Log de execução explícito
    $logExecucao = date('Y-m-d H:i:s') . " | CRON JOB | INÍCIO da execução do processar_jobs.php\n";
    file_put_contents(__DIR__ . '/../../logs/cron_batch.log', $logExecucao, FILE_APPEND);
    // Log de verificação de jobs pendentes
    $logVerificacao = date('Y-m-d H:i:s') . " | CRON JOB | Verificando jobs pendentes...\n";
    file_put_contents(__DIR__ . '/../../logs/cron_batch.log', $logVerificacao, FILE_APPEND);


    // Log: início da chamada do controller
    file_put_contents(__DIR__ . '/../../logs/cron_batch.log', date('Y-m-d H:i:s') . " | CRON JOB | Chamando DealBatchController::processarProximoJob()\n", FILE_APPEND);
    $resultado = null;
    try {
        $resultado = DealBatchController::processarProximoJob();
        file_put_contents(__DIR__ . '/../../logs/cron_batch.log', date('Y-m-d H:i:s') . " | CRON JOB | Retorno do controller: " . json_encode($resultado, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    } catch (\Throwable $e) {
        file_put_contents(__DIR__ . '/../../logs/cron_batch_error.log', date('Y-m-d H:i:s') . " | CRON JOB | EXCEÇÃO AO CHAMAR CONTROLLER: " . $e->getMessage() . "\n", FILE_APPEND);
        throw $e;
    }

    // Log do resultado
    $status = $resultado['status'] ?? 'unknown';
    $logResultado = date('Y-m-d H:i:s') . " | CRON JOB | Resultado: $status\n";
    if ($status === 'processado') {
        $jobId = $resultado['job_id'] ?? 'unknown';
        $logResultado .= date('Y-m-d H:i:s') . " | CRON JOB | Job processado: $jobId\n";
    } elseif ($status === 'sem_jobs') {
        $logResultado .= date('Y-m-d H:i:s') . " | CRON JOB | Nenhum job pendente encontrado\n";
    } elseif ($status === 'erro') {
        $mensagem = $resultado['mensagem'] ?? 'Erro desconhecido';
        $logResultado .= date('Y-m-d H:i:s') . " | CRON JOB | ERRO: $mensagem\n";
    }
    file_put_contents(__DIR__ . '/../../logs/cron_batch.log', $logResultado, FILE_APPEND);

    // Se executado via linha de comando, mostra resultado
    if (php_sapi_name() === 'cli') {
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

} catch (Exception $e) {
    $logErro = date('Y-m-d H:i:s') . " | CRON JOB | EXCEÇÃO: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/../../logs/cron_batch.log', $logErro, FILE_APPEND);
    file_put_contents(__DIR__ . '/../../logs/cron_batch_error.log', date('Y-m-d H:i:s') . " | CRON JOB | EXCEÇÃO GERAL: " . $e->getMessage() . "\n", FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
}
