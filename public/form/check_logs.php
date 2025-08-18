<?php
// check_logs.php - Verifica logs e configurações
header('Content-Type: text/html; charset=utf-8');

echo "<h2>📋 Verificação de Logs e Configurações</h2>";

// 1. Informações PHP
echo "<h3>🔧 Configurações PHP</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Error Reporting: " . error_reporting() . "<br>";
echo "Display Errors: " . ini_get('display_errors') . "<br>";
echo "Log Errors: " . ini_get('log_errors') . "<br>";
echo "Error Log: " . ini_get('error_log') . "<br>";

// 2. Verificar se logs existem
echo "<h3>📄 Verificação de Arquivos de Log</h3>";
$possibleLogs = [
    '/var/log/apache2/error.log',
    '/var/log/nginx/error.log', 
    '/var/log/php_errors.log',
    error_get_last(),
    __DIR__ . '/../logs/api_debug.log',
    __DIR__ . '/../logs/php_errors.log'
];

foreach ($possibleLogs as $log) {
    if (is_string($log) && file_exists($log)) {
        echo "✅ Encontrado: " . htmlspecialchars($log) . "<br>";
        if (is_readable($log)) {
            $size = filesize($log);
            echo "   Tamanho: " . number_format($size) . " bytes<br>";
            if ($size > 0 && $size < 50000) { // Mostra apenas se < 50KB
                echo "   <strong>Últimas linhas:</strong><br>";
                echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 200px; overflow-y: auto;'>";
                echo htmlspecialchars(shell_exec("tail -20 " . escapeshellarg($log)));
                echo "</pre>";
            }
        } else {
            echo "   ❌ Não legível<br>";
        }
    } else {
        echo "❌ Não encontrado: " . htmlspecialchars($log) . "<br>";
    }
}

// 3. Teste de conectividade com banco
echo "<h3>🗄️ Teste de Conectividade com Banco</h3>";
try {
    $config = require __DIR__ . '/../../config/config.php';
    echo "✅ Config carregado<br>";
    
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
        $config['usuario'],
        $config['senha']
    );
    echo "✅ Conexão com banco estabelecida<br>";
    
    $stmt = $pdo->query("SELECT VERSION()");
    $version = $stmt->fetchColumn();
    echo "MySQL Version: " . htmlspecialchars($version) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Erro de banco: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// 4. Teste da API original
echo "<h3>🧪 Teste da API Original</h3>";
echo "<strong>Testando bitrix_users.php...</strong><br>";

ob_start();
$_GET['cliente'] = 'gnappC93jLq7RxKZVp28HswuAYMe1';
$_GET['q'] = 'test';

try {
    include __DIR__ . '/bitrix_users.php';
} catch (Exception $e) {
    echo "❌ Erro capturado: " . htmlspecialchars($e->getMessage()) . "<br>";
} catch (Error $e) {
    echo "❌ Erro fatal: " . htmlspecialchars($e->getMessage()) . "<br>";
}

$output = ob_get_clean();
echo "Saída da API:<br>";
echo "<pre style='background: #f5f5f5; padding: 10px;'>" . htmlspecialchars($output) . "</pre>";

// 5. Último erro PHP
$lastError = error_get_last();
if ($lastError) {
    echo "<h3>⚠️ Último Erro PHP</h3>";
    echo "<pre style='background: #ffe6e6; padding: 10px;'>";
    print_r($lastError);
    echo "</pre>";
}
?>
