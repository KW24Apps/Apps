<?php
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

file_put_contents(__DIR__ . '/../logs/debug_routes.log', "[ROTA] URI: $uri | METHOD: $method" . PHP_EOL, FILE_APPEND);

require_once __DIR__ . '/../controllers/ClickSignController.php';

if ($uri === 'clicksignnew' && $method === 'POST') {
    (new ClickSignController())->GerarAssinatura();
} else {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota ClickSign não encontrada']);
}

// Fim do arquivo clicksignRoutes.php
