<?php
// check_recent_logs.php - Verifica logs recentes de deals
header('Content-Type: text/html; charset=utf-8');

echo "<h2>📋 Logs Recentes do Sistema</h2>";

$logFiles = [
    'batch_jobs.log' => 'Logs de Jobs em Batch',
    'BitrixHelpers.log' => 'Logs do BitrixHelper',
    'batch_debug.log' => 'Debug de Batch',
    'debug_batch.log' => 'Debug Batch Alternativo'
];

foreach ($logFiles as $filename => $description) {
    $logPath = __DIR__ . '/../../logs/' . $filename;
    
    echo "<h3>📄 $description ($filename)</h3>";
    
    if (file_exists($logPath)) {
        $content = file_get_contents($logPath);
        $lines = explode("\n", $content);
        
        // Pega as últimas 20 linhas
        $recentLines = array_slice($lines, -20);
        $recentContent = implode("\n", $recentLines);
        
        if (trim($recentContent)) {
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: scroll;'>";
            echo htmlspecialchars($recentContent);
            echo "</pre>";
        } else {
            echo "<p><em>Arquivo vazio</em></p>";
        }
    } else {
        echo "<p><em>Arquivo não encontrado: $logPath</em></p>";
    }
    
    echo "<hr>";
}

// Também verifica se existem outros arquivos de log
echo "<h3>🗂️ Todos os arquivos de log disponíveis:</h3>";
$logDir = __DIR__ . '/../../logs/';
if (is_dir($logDir)) {
    $files = scandir($logDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
            $size = filesize($logDir . $file);
            $modified = date('Y-m-d H:i:s', filemtime($logDir . $file));
            echo "- <strong>$file</strong> ($size bytes, modificado: $modified)<br>";
        }
    }
} else {
    echo "<p><em>Diretório de logs não encontrado</em></p>";
}
?>
