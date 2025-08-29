<?php
set_time_limit(120); // Define o timeout para 120 segundos
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/helpers/UtilHelpers.php';
require_once __DIR__ . '/helpers/LogHelper.php';
require_once __DIR__ . '/dao/AplicacaoAcessoDAO.php';

use Helpers\LogHelper;
use dao\AplicacaoAcessoDAO;
use Helpers\UtilHelpers;

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

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
if ($cliente && $slugAplicacao && NOME_APLICACAO !== 'bitrix-sync') {
    $acesso = AplicacaoAcessoDAO::ValidarClienteAplicacao($cliente, $slugAplicacao);
    if (!$acesso) {
        http_response_code(401); // Unauthorized
        $response = [
            'success' => false,
            'message' => 'Acesso negado. Verifique a chave do cliente e as permissões da aplicação.'
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// Direcionamento com base no prefixo
switch (NOME_APLICACAO) {

    case 'dashboard':
        // Verificar se é requisição da API
        if (strpos($_SERVER['REQUEST_URI'], '/dashboard/api') !== false) {
            require_once __DIR__ . '/dashboard/api.php';
        } else {
            require_once __DIR__ . '/dashboard/index.php';
        }
        break;
    case 'scheduler':
        require_once __DIR__ . '/routers/SchedulerRoutes.php';
        break;
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
    case 'omie':
        require_once __DIR__ . '/routers/OmieRouter.php';
    break;    
    case 'geraroptnd':
        require_once __DIR__ . '/routers/GeraroptndRoutes.php';
        break;
    case 'importar':
        require_once __DIR__ . '/routers/importarRoutes.php';
        break;
    case 'disk':
        require_once __DIR__ . '/routers/diskRoutes.php';
        break;
    default:
        LogHelper::registrarRotaNaoEncontrada($uri, $method, __FILE__);
        http_response_code(404);
        echo json_encode(['erro' => 'Projeto não reconhecido', 'uri' => $uri, 'slugAplicacao' => $slugAplicacao, 'NOME_APLICACAO' => NOME_APLICACAO]);
}
