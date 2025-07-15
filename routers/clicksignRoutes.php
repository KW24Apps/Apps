<?php
namespace routers;

require_once __DIR__ . '/../controllers/ClickSignController.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;
use Controllers\ClickSignController;    

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];


if ($uri === 'clicksignnew' && $method === 'POST') {
    (new ClickSignController())->GerarAssinatura();
} elseif ($uri === 'clicksignretorno' && $method === 'POST') {
    $requestData = json_decode(file_get_contents('php://input'), true); 
    $evento = $requestData['event']['name'] ?? 'evento_desconhecido';
    (new ClickSignController())->retornoClickSign($requestData); 
} else {
    LogHelper::registrarRotaNaoEncontrada($uri, $method, 'clicksignRoutes.php');
    http_response_code(404);
    echo json_encode(['erro' => 'Rota ClickSign não encontrada']);
}
