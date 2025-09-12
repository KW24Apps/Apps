<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'BATCH_PROCESSOR');
}

require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../controllers/DealBatchController.php';

use Helpers\LogHelper;
use Controllers\DealBatchController;

// Gera traceId para toda execução do job
LogHelper::gerarTraceId();

try {
    $resultado = DealBatchController::processarProximoJob();
    $status = $resultado['status'] ?? 'unknown';
    $jobId = $resultado['job_id'] ?? null;

    // Log simples para monitoramento CRON
    if ($status === 'processado') {
        LogHelper::logCronMonitor('PROCESSO_INICIADO', $jobId);
    } elseif ($status === 'sem_jobs') {
        LogHelper::logCronMonitor('SEM_JOBS');
    } elseif ($status === 'job_em_andamento') {
        LogHelper::logCronMonitor('JOB_EM_ANDAMENTO');
    } else {
        LogHelper::logCronMonitor('ERRO');
    }

    // Se executado via linha de comando, mostra resultado
    if (php_sapi_name() === 'cli') {
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

} catch (Exception $e) {
    LogHelper::logCronMonitor('ERRO');
    LogHelper::logDealBatchController('EXCEÇÃO GERAL: ' . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
}
