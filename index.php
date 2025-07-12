<?php
require_once __DIR__ . '/helpers/LogHelper.php';

// Log de erros via função
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    LogHelper::registrarErroGlobal("[$errno] $errstr em $errfile na linha $errline");
});
set_exception_handler(function ($exception) {
    LogHelper::registrarErroGlobal("Exceção não capturada: " . $exception->getMessage());
});

// Captura a URI e limpa barras
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Log de entrada global
LogHelper::registrarEntradaGlobal($uri, $method);

// Direcionamento com base no prefixo
if (strpos($uri, 'deal') === 0) {
    require_once 'routers/dealRoutes.php';
} elseif (strpos($uri, 'extenso') === 0) {
    require_once __DIR__ . '/routers/extensoRoutes.php';
} elseif (strpos($uri, 'bitrix-sync') === 0) {
    require_once __DIR__ .'/routers/bitrixSyncRoutes.php';
} elseif (strpos($uri, 'task') === 0) {
    require_once __DIR__ . '/routers/taskRoutes.php';
} elseif (strpos($uri, 'clicksign') === 0) {
    require_once __DIR__ . '/routers/clicksignRoutes.php';    
} elseif (strpos($uri, 'company') === 0) {
    require_once __DIR__ . '/routers/companyRoutes.php';
} elseif (strpos($uri, 'mediahora') === 0) {
    require_once __DIR__ . '/routers/mediaoraRouter.php';    
} else {
    LogHelper::registrarErroGlobal("Rota não reconhecida | URI: $uri | Método: $method");
    http_response_code(404);
    echo json_encode(['erro' => 'Projeto não reconhecido']);
}
