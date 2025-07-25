<?php
namespace routers;

require_once __DIR__ . '/../controllers/DealController.php';

use Controllers\DealController;

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];



if ($uri === 'dealcriar' && $method === 'POST') {
    (new DealController())->criar();
} elseif ($uri === 'dealconsultar' && $method === 'GET') {
    (new DealController())->consultar();
} elseif ($uri === 'dealeditar' && $method === 'POST') {
    (new DealController())->editar();
} else {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota não encontrada']);
} 