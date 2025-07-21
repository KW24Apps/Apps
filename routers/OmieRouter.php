<?php
namespace routers;
$method = $_SERVER['REQUEST_METHOD'];

require_once __DIR__ . '/../controllers/OmieController.php';

use Controllers\OmieController;

if ($uri === 'omiedatabase' && $method === 'GET') {
    (new omieController())->database();
} else {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota não encontrada']);
} 
