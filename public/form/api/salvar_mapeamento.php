<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Debug
error_log("=== DEBUG SALVAR MAPEAMENTO ===");
error_log("POST: " . print_r($_POST, true));
error_log("SESSION antes: " . print_r($_SESSION, true));

$mapeamento = $_POST['map'] ?? [];
$cliente = $_POST['cliente'] ?? $_GET['cliente'] ?? '';

if (empty($mapeamento)) {
    error_log("ERRO: Mapeamento vazio");
    
    // Se foi requisição AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum mapeamento fornecido']);
        exit;
    }
    
    // Se foi POST normal, volta para mapeamento
    $redirect_url = '/Apps/public/form/mapeamento.php' . ($cliente ? '?cliente=' . urlencode($cliente) : '');
    header("Location: $redirect_url");
    exit;
}

// Salva o mapeamento na sessão
$_SESSION['mapeamento'] = $mapeamento;
error_log("Mapeamento salvo: " . print_r($mapeamento, true));
error_log("SESSION depois: " . print_r($_SESSION, true));

// Se foi requisição AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Mapeamento salvo com sucesso',
        'debug' => [
            'mapeamento_count' => count($mapeamento),
            'session_id' => session_id()
        ]
    ]);
    exit;
}

// Se foi POST normal, redireciona para confirmação
$redirect_url = '/Apps/public/form/confirmacao.php' . ($cliente ? '?cliente=' . urlencode($cliente) : '');
header("Location: $redirect_url");
exit;
