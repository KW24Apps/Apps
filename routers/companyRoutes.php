<?php
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

require_once __DIR__ . '/../controllers/CompanyController.php';

if ($uri === 'companycriar' && $method === 'POST') {
    (new CompanyController())->criar();
} elseif ($uri === 'companyconsultar' && $method === 'GET') {
    (new CompanyController())->consultar();
} elseif ($uri === 'companyeditar' && $method === 'POST') {
    (new CompanyController())->editar();
} else {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota não encontrada']);
}