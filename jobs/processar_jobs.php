<?php
/**
 * Processador de Jobs em Batch - Bitrix24
 * 
 * Este arquivo deve ser executado via cron a cada minuto:
 * * * * * * php /caminho/para/processar_jobs.php
 * 
 * Processa jobs pendentes da tabela batch_jobs
 */

require_once __DIR__ . '/../helpers/BitrixBatchHelper.php';

use Helpers\BitrixBatchHelper;

try {
    // Log de execução explícito
    $logExecucao = date('Y-m-d H:i:s') . " | CRON JOB | INÍCIO da execução do processar_jobs.php\n";
    file_put_contents(__DIR__ . '/../../logs/cron_batch.log', $logExecucao, FILE_APPEND);
    // Log de verificação de jobs pendentes
    $logVerificacao = date('Y-m-d H:i:s') . " | CRON JOB | Verificando jobs pendentes...\n";
    file_put_contents(__DIR__ . '/../../logs/cron_batch.log', $logVerificacao, FILE_APPEND);

    // Processa jobs pendentes
    $resultado = BitrixBatchHelper::processarJobsPendentes();

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
    
    if (php_sapi_name() === 'cli') {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
}
