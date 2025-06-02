<?php

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/bitrix-sync') !== false) {
    require_once __DIR__ . '/../controllers/BitrixSyncController.php';
    

    $controller = new BitrixSyncController();
    $controller->executar();
    exit;
}
