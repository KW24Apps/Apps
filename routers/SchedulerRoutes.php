<?php
namespace routers;

require_once __DIR__ . '/../controllers/SchedulerController.php';

use controllers\SchedulerController;

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/scheduler') !== false) {
    $controller = new SchedulerController();
    $controller->executar();
    exit;
}
