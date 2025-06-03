<?php

require_once __DIR__ . '/../controllers/TaskController.php';

$rota = $_GET['rota'] ?? '';

if ($rota === 'task/criar') {
    $controller = new TaskController();
    $controller->criar();
    return;
}

http_response_code(404);
echo json_encode(['erro' => 'Rota não encontrada.']);