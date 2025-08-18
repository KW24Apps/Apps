<?php
echo "<h2>🔍 Debug Upload de Arquivos</h2>";

$uploadDir = __DIR__ . '/../uploads/';
echo "Diretório de upload: " . htmlspecialchars($uploadDir) . "<br>";
echo "Caminho absoluto: " . htmlspecialchars(realpath($uploadDir)) . "<br>";
echo "Diretório existe: " . (is_dir($uploadDir) ? "✅ SIM" : "❌ NÃO") . "<br>";
echo "Diretório é gravável: " . (is_writable($uploadDir) ? "✅ SIM" : "❌ NÃO") . "<br><br>";

echo "<strong>Arquivos na pasta uploads:</strong><br>";
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $uploadDir . $file;
            $size = filesize($filePath);
            $modified = date('Y-m-d H:i:s', filemtime($filePath));
            echo "• " . htmlspecialchars($file) . " (" . number_format($size) . " bytes) - $modified<br>";
        }
    }
} else {
    echo "❌ Diretório não existe<br>";
}

echo "<br><strong>Arquivos CSV (usando glob):</strong><br>";
$csvFiles = glob($uploadDir . '*.csv');
if ($csvFiles) {
    foreach ($csvFiles as $csvFile) {
        $size = filesize($csvFile);
        $modified = date('Y-m-d H:i:s', filemtime($csvFile));
        echo "• " . htmlspecialchars(basename($csvFile)) . " (" . number_format($size) . " bytes) - $modified<br>";
        
        // Tenta ler primeira linha
        if (($handle = fopen($csvFile, 'r')) !== false) {
            $firstLine = fgetcsv($handle, 0, ',', '"', "\\");
            fclose($handle);
            echo "  Primeira linha: " . json_encode($firstLine) . "<br>";
        }
    }
} else {
    echo "❌ Nenhum arquivo CSV encontrado<br>";
}

echo "<br><strong>Teste de criação de diretório:</strong><br>";
if (!is_dir($uploadDir)) {
    echo "Tentando criar diretório...<br>";
    if (mkdir($uploadDir, 0755, true)) {
        echo "✅ Diretório criado com sucesso<br>";
    } else {
        echo "❌ Falha ao criar diretório<br>";
    }
}

echo "<br><strong>Sessão atual:</strong><br>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>
