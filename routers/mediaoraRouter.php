<?php
namespace routers;
$method = $_SERVER['REQUEST_METHOD'];

require_once __DIR__ . '/../controllers/MediaHoraController.php';

use Controllers\MediaHoraController;

if ($method === 'POST') {
    (new MediaHoraController())->executar();
} else {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
}
