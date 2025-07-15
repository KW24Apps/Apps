<?php
namespace routers;

require_once __DIR__ . '/../controllers/BitrixSyncController.php';

use Controllers\BitrixSyncController;

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/bitrix-sync') !== false) {
    $controller = new BitrixSyncController();
    $controller->syncCompany();
    exit;
}
