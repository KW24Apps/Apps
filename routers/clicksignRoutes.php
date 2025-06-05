<?php
// Ativa exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Identifica a URI e o método
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Controller
require_once __DIR__ . '/../controllers/ClickSignController.php';

// Rotas
if ($uri === 'clicksigncriar' && $method === 'POST') {
    (new ClickSignController())->criar();
} elseif ($uri === 'clicksignconsultar' && $method === 'GET') {
    (new ClickSignController())->consultar();
} else {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota não encontrada']);
}
// Fim do arquivo clicksignRoutes.php
