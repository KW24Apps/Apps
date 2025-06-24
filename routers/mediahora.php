<?php
$method = $_SERVER['REQUEST_METHOD'];

require_once __DIR__ . '/../controllers/MediaHoraController.php';

if ($method === 'GET') {
    (new MediaHoraController())->executar();
} else {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
}
