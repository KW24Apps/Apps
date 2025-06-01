<?php

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/extenso') !== false) {
    require_once __DIR__ . '/../controllers/ExtensoController.php';
    $controller = new ExtensoController();
    $controller->executar($_GET);
    exit;
}
