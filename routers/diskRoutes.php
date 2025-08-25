<?php

require_once __DIR__ . '/../controllers/DiskController.php';

use Controllers\DiskController;

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Rota para renomear a pasta e atualizar o deal
if ($uri === 'diskrename' && $method === 'GET') {
    (new DiskController())->RenomearPasta();
} else {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota não encontrada no módulo Disk.']);
}
