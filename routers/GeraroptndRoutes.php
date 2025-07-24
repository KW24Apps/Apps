<?php
namespace routers;

require_once __DIR__ . '/../controllers/GeraroptndController.php';

use Controllers\GeraroptndController;

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/geraroportunidades') !== false) {
    $controller = new GeraroptndController();
    $controller->executar();
    exit;
}
