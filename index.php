<?php
require_once __DIR__ . '/helpers/LogHelper.php';
require_once __DIR__ . '/dao/AplicacaoAcessoDAO.php';


use Helpers\LogHelper;
use dao\AplicacaoAcessoDAO;
use Helpers\UtilHelpers;

$slugAplicacao = UtilHelpers::detectarAplicacaoPorUri($uri);

// Gera o TRACE_ID uma única vez
LogHelper::gerarTraceId();

// Log de erros via função
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    LogHelper::registrarErroGlobal("[$errno] $errstr em $errfile na linha $errline", NOME_APLICACAO, 'Index::ErroGlobal');
});
set_exception_handler(function ($exception) {
    LogHelper::registrarErroGlobal("Exceção não capturada: " . $exception->getMessage(), NOME_APLICACAO, 'Index::ErroGlobal');
});

// Log de entrada global
LogHelper::registrarEntradaGlobal($uri, $method);

// --- Autenticação global: busca e valida cliente ---
$cliente = $_GET['cliente'] ?? null;
if ($cliente && $slugAplicacao) {
    AplicacaoAcessoDAO::obterWebhookPermitido($cliente, $slugAplicacao);
}
// --- Fim da autenticação global ---

// Direcionamento com base no prefixo
switch (NOME_APLICACAO) {
    case 'deal':
        require_once 'routers/dealRoutes.php';
        break;
    case 'extenso':
        require_once __DIR__ . '/routers/extensoRoutes.php';
        break;
    case 'bitrix-sync':
        require_once __DIR__ . '/routers/bitrixSyncRoutes.php';
        break;
    case 'task':
        require_once __DIR__ . '/routers/taskRoutes.php';
        break;
    case 'clicksign':
        require_once __DIR__ . '/routers/clicksignRoutes.php';
        break;
    case 'company':
        require_once __DIR__ . '/routers/companyRoutes.php';
        break;
    case 'mediahora':
        require_once __DIR__ . '/routers/mediaoraRouter.php';
        break;
    default:
        LogHelper::registrarRotaNaoEncontrada($uri, $method, __FILE__);
        http_response_code(404);
        echo json_encode(['erro' => 'Projeto não reconhecido']);
}
