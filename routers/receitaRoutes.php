<?php
namespace routers;

require_once __DIR__ . '/../controllers/ReceitaController.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;
use Controllers\ReceitaController;

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === 'receita' && $method === 'POST') {
    (new ReceitaController())->consultarCNPJWebhook();
} else {
    LogHelper::registrarRotaNaoEncontrada($uri, $method, 'receitaRoutes.php');
    http_response_code(404);
    echo json_encode(['erro' => 'Rota Receita não encontrada']);
}
