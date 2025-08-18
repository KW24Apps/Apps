<?php
// debug_confirmacao_simple.php - Debug simples
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'teste' => 'OK',
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
