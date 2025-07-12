<?php
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

require_once __DIR__ . '/../controllers/ClickSignController.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

LogHelper::logRotas($uri, $method, 'ClickSignRoute');

if ($uri === 'clicksignnew' && $method === 'POST') {
    (new ClickSignController())->GerarAssinatura();
} elseif ($uri === 'clicksignretorno' && $method === 'POST') {
    $requestData = json_decode(file_get_contents('php://input'), true); 
    $evento = $requestData['event']['name'] ?? 'evento_desconhecido';
    LogHelper::logRotas($uri, $method, "ClickSignRetorno ($evento)", json_encode($requestData));
    (new ClickSignController())->retornoClickSign($requestData); 
} else {
    LogHelper::logRotas($uri, $method, 'ClickSignErro', 'Rota não encontrada');
    http_response_code(404);
    echo json_encode(['erro' => 'Rota ClickSign não encontrada']);
}