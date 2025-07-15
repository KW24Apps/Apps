<?php
namespace routers;

use Controllers\ExtensoController;

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/extenso') !== false) {
    require_once __DIR__ . '/../controllers/ExtensoController.php';
    $controller = new ExtensoController();
    $controller->executar(); // Remova o $_GET aqui
    exit;
}
