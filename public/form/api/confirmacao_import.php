<?php
// confirmacao_import_safe.php - Versão segura para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $mapeamento = $_SESSION['mapeamento'] ?? [];
    $formData = $_SESSION['importacao_form'] ?? [];
    $spa = $formData['funil'] ?? 'undefined';

    if (empty($mapeamento)) {
        echo json_encode([
            'sucesso' => false, 
            'mensagem' => 'Mapeamento não encontrado na sessão',
            'debug' => [
                'session_keys' => array_keys($_SESSION),
                'mapeamento_count' => count($mapeamento),
                'form_data_keys' => array_keys($formData)
            ]
        ]);
        exit;
    }

    // Busca o arquivo CSV mais recente
    $uploadDir = __DIR__ . '/uploads/';
    $files = glob($uploadDir . '*.csv');
    
    if (empty($files)) {
        echo json_encode([
            'sucesso' => false, 
            'mensagem' => 'Arquivo CSV não encontrado',
            'debug' => [
                'upload_dir' => $uploadDir,
                'files_found' => count($files)
            ]
        ]);
        exit;
    }

    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    $csvFile = $files[0];
    $nomeArquivo = basename($csvFile);

    // Resposta de sucesso simplificada
    echo json_encode([
        'sucesso' => true,
        'dados' => [['teste' => 'valor1'], ['teste' => 'valor2']],
        'dados_processamento' => [['ufCrm_123' => 'valor1'], ['ufCrm_123' => 'valor2']],
        'total' => 2,
        'arquivo' => $nomeArquivo,
        'spa' => $spa,
        'funil_id' => $spa,
        'debug' => 'Versão simplificada funcionando'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro: ' . $e->getMessage(),
        'debug' => [
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile()
        ]
    ]);
}
?>
