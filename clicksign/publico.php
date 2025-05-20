<?php
// === SEGURANÇA ===
// Apenas aceita requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit('Acesso negado (método inválido).');
}

// Token esperado (pode ser alterado futuramente)
$tokenEsperado = 'segredo123';
$tokenRecebido = $_GET['token'] ?? '';

// Verifica o token recebido via parâmetro GET
if ($tokenRecebido !== $tokenEsperado) {
    http_response_code(403);
    exit('Acesso negado (token inválido).');
}

// === LOG DE ACESSO ===
$logPath = __DIR__ . '/log_publico.txt';
$data = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'IP desconhecido';
$entrada = "[$data] Acesso autorizado de $ip\n";
file_put_contents($logPath, $entrada, FILE_APPEND);

// === EXECUÇÃO DA PRÓXIMA ETAPA ===
// Requisição autorizada, prossegue para envio
include __DIR__ . '/enviar.php';

