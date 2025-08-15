<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$mapeamento = $_SESSION['mapeamento'] ?? [];
$formData = $_SESSION['importacao_form'] ?? [];

if (empty($mapeamento)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Mapeamento não encontrado na sessão']);
    exit;
}

// Busca o arquivo CSV mais recente
$uploadDir = __DIR__ . '/../uploads/';
$files = glob($uploadDir . '*.csv');
if (empty($files)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Arquivo CSV não encontrado']);
    exit;
}

usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
$csvFile = $files[0];
$nomeArquivo = basename($csvFile);

// Lê o arquivo CSV
$dados = [];
if (($handle = fopen($csvFile, 'r')) !== false) {
    $headers = fgetcsv($handle, 0, ',', '"', "\\");
    while (($row = fgetcsv($handle, 0, ',', '"', "\\")) !== false) {
        if (count($row) === count($headers)) {
            $linha = [];
            foreach ($headers as $index => $header) {
                $campoBitrix = $mapeamento[$header] ?? null;
                if ($campoBitrix) {
                    $linha[$campoBitrix] = $row[$index] ?? '';
                }
            }
            if (!empty($linha)) {
                $dados[] = $linha;
            }
        }
    }
    fclose($handle);
}

echo json_encode([
    'sucesso' => true,
    'spa' => $formData['funil'] ?? '',
    'funil_id' => $formData['funil'] ?? '',
    'arquivo' => $nomeArquivo,
    'linhas' => count($dados),
    'dados' => $dados
]);
