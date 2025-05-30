<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

require_once __DIR__ . '/../controllers/DealController.php';

if ($uri === '/criar' && $method === 'POST') {
    (new DealController())->criar();
} elseif ($uri === '/consultar' && $method === 'GET') {
    (new DealController())->consultar();
} elseif ($uri === '/editar' && $method === 'POST') {
    (new DealController())->editar();
} else {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota não encontrada']);
}

