<?php
namespace routers;
// dealSchedulerRoutes.php
// Roteamento para a API de agendamento de Deal (Deal Scheduler)

require_once __DIR__ . '/../controllers/DealSchedulerController.php';

use controllers\DealSchedulerController;

// Parâmetros esperados: cliente, spa, deal
$cliente = $_GET['cliente'] ?? null;
$spa     = $_GET['spa'] ?? null;
$dealId  = $_GET['deal'] ?? null;

if (!$cliente || !$spa || !$dealId) {
    http_response_code(400);
    echo json_encode(['erro' => 'Parâmetros obrigatórios ausentes: cliente, spa, deal']);
    exit;
}

$controller = new DealSchedulerController();
$controller->handle($cliente, $spa, $dealId);
