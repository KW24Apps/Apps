<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'CLICKSIGN_DEADLINE_JOB');
}

require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../controllers/ClickSignController.php';

use Helpers\LogHelper;
use Controllers\ClickSignController;

// Gera traceId para toda execução do job
LogHelper::gerarTraceId();

try {
    // Chama o método no controller que fará todo o processamento
    $resultado = ClickSignController::extendDeadlineForDueDocuments();
    
    LogHelper::logCronMonitor('EXECUCAO_FINALIZADA', json_encode($resultado));

    // Se executado via linha de comando, mostra resultado
    if (php_sapi_name() === 'cli') {
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

} catch (Exception $e) {
    LogHelper::logCronMonitor('ERRO_FATAL');
    LogHelper::logClickSignController('EXCECAO_GERAL_JOB: ' . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
}
