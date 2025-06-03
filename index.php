<?php
// Log de Erros
ini_set('display_errors', 0);                          // Nunca mostra erros na tela
error_reporting(E_ALL);                                // Captura todos os tipos de erro
ini_set('log_errors', 1);                              // Ativa o log
ini_set('error_log', __DIR__ . '/logs/erros.log');     // Define o local do log

// Captura a URI e limpa barras
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Direcionamento com base no prefixo
if (strpos($uri, 'deal') === 0) {
    require_once 'routers/dealRoutes.php';
} elseif (strpos($uri, 'extenso') === 0) {
    require_once __DIR__ . '/routers/extensoRoutes.php';
} elseif (strpos($uri, 'bitrix-sync') === 0) {
    require_once __DIR__ .'/routers/bitrixSyncRoutes.php';
} elseif (strpos($uri, 'task') === 0) {
    require_once __DIR__ . '/routers/taskRoutes.php';
} else {
    http_response_code(404);
    echo json_encode(['erro' => 'Projeto não reconhecido']);
}