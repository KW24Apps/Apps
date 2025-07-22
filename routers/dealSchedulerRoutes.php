<?php
namespace routers;

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/dealscheduler') !== false) {
    require_once __DIR__ . '/../controllers/DealSchedulerController.php';
    $controller = new \controllers\DealSchedulerController();
    $controller->executar();
    exit;
}
