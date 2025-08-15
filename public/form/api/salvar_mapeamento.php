<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$mapeamento = $_POST['map'] ?? [];

if (empty($mapeamento)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum mapeamento fornecido']);
    exit;
}

// Salva o mapeamento na sessão
$_SESSION['mapeamento'] = $mapeamento;

echo json_encode([
    'sucesso' => true,
    'mensagem' => 'Mapeamento salvo com sucesso'
]);
