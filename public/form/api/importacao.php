<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

$arquivo = $_FILES['arquivo'] ?? null;
$funil = $_POST['funil'] ?? '';
$identificador = $_POST['identificador'] ?? '';
$responsavel_id = $_POST['responsavel_id'] ?? '';
$solicitante_id = $_POST['solicitante_id'] ?? '';

// Salva dados do formulário na sessão (exceto arquivo)
$_SESSION['importacao_form'] = [
    'funil' => $funil,
    'identificador' => $identificador,
    'responsavel' => $_POST['responsavel'] ?? '',
    'solicitante' => $_POST['solicitante'] ?? ''
];

// Permite envio mesmo com campos vazios
$uploadDir = __DIR__ . '/../uploads/';
if ($arquivo && isset($arquivo['tmp_name']) && $arquivo['tmp_name']) {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $filePath = $uploadDir . basename($arquivo['name']);
    move_uploaded_file($arquivo['tmp_name'], $filePath);
}

// Redireciona para a tela de mapeamento sempre
echo json_encode([
    'sucesso' => true,
    'next_url' => 'mapeamento.php',
    'mensagem' => 'Importação iniciada com sucesso!'
]);
