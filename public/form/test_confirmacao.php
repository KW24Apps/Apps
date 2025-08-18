<?php
// test_confirmacao.php - Teste do endpoint de confirmação
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

echo "<h2>🧪 Teste do confirmacao_import.php</h2>";

echo "<h3>📋 Dados da sessão:</h3>";
echo "<strong>Mapeamento:</strong><br>";
var_dump($_SESSION['mapeamento'] ?? 'NÃO DEFINIDO');
echo "<br><br>";

echo "<strong>Form Data:</strong><br>";
var_dump($_SESSION['importacao_form'] ?? 'NÃO DEFINIDO');
echo "<br><br>";

echo "<h3>📁 Arquivos CSV disponíveis:</h3>";
$uploadDir = __DIR__ . '/../uploads/';
$files = glob($uploadDir . '*.csv');

if (empty($files)) {
    echo "❌ Nenhum arquivo CSV encontrado em: $uploadDir<br>";
} else {
    echo "✅ Arquivos encontrados:<br>";
    foreach ($files as $file) {
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        echo "- " . basename($file) . " ($size bytes, $modified)<br>";
    }
}

echo "<br><h3>🔍 Simulando chamada do confirmacao_import.php:</h3>";

// Simula o que o confirmacao_import.php faria
$mapeamento = $_SESSION['mapeamento'] ?? [];
$formData = $_SESSION['importacao_form'] ?? [];

if (empty($mapeamento)) {
    echo "❌ <strong>Problema:</strong> Mapeamento não encontrado na sessão<br>";
} else {
    echo "✅ Mapeamento encontrado: " . count($mapeamento) . " campos mapeados<br>";
}

if (empty($formData)) {
    echo "❌ <strong>Problema:</strong> Form data não encontrado na sessão<br>";
} else {
    echo "✅ Form data encontrado<br>";
}

if (empty($files)) {
    echo "❌ <strong>Problema:</strong> Nenhum arquivo CSV encontrado<br>";
} else {
    echo "✅ Arquivo CSV disponível<br>";
}

echo "<br><h3>🌐 Testando resposta JSON:</h3>";
if (!empty($mapeamento) && !empty($files)) {
    // Simula uma resposta de sucesso
    $response = [
        'sucesso' => true,
        'dados' => [['teste' => 'valor']],
        'dados_processamento' => [['ufCrm_123' => 'valor']],
        'total' => 1,
        'arquivo' => 'teste.csv',
        'spa' => 'test',
        'funil_id' => 'test'
    ];
    
    echo "<strong>JSON que seria retornado:</strong><br>";
    echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
} else {
    $response = [
        'sucesso' => false,
        'mensagem' => 'Dados insuficientes para processamento'
    ];
    
    echo "<strong>JSON de erro que seria retornado:</strong><br>";
    echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
}
?>
