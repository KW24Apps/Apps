<?php
// Log de Erros
ini_set('display_errors', 0);                          // Nunca mostra erros na tela
error_reporting(E_ALL);                                // Captura todos os tipos de erro
ini_set('log_errors', 1);                              // Ativa o log
ini_set('error_log', __DIR__ . '/logs/erros.log');     // Define o local do log

// Chave esperada
$chaveCorreta = 'C93fLq7RxKZVp28HswuAYMe1';

// Verifica a chave na URL
if (!isset($_GET['key']) || $_GET['key'] !== $chaveCorreta) {
    http_response_code(403);
    echo json_encode(['erro' => 'Chave inválida']);
    exit;
}
echo '🚀 Chegamos no index da pasta Apps';
// Captura a URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Decide qual grupo de rotas carregar

if (strpos($uri, '/criar') === 0) {
    require_once 'routers/dealRoutes.php';
} elseif (strpos($uri, '/apps/chat/') === 0) {
    require_once 'routers/chatRoutes.php';
} else {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota não reconhecida']);
}
