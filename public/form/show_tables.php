<?php
// show_tables.php - Mostra todas as tabelas do banco
header('Content-Type: text/html; charset=utf-8');

echo "<h2>📋 Estrutura Real do Banco de Dados</h2>";

try {
    // Conexão direta com o banco
    $config = [
        'host' => 'localhost',
        'dbname' => 'kw24co49_api_kwconfig',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];
    
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['usuario'], $config['senha'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ <strong>Conexão estabelecida</strong><br><br>";
    
    // 1. Mostrar todas as tabelas
    echo "<h3>🗂️ Tabelas existentes no banco:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "- <strong>$table</strong><br>";
    }
    
    echo "<br>";
    
    // 2. Mostrar estrutura das tabelas principais
    $mainTables = ['clientes', 'aplicacoes'];
    
    foreach ($mainTables as $table) {
        if (in_array($table, $tables)) {
            echo "<h3>📊 Estrutura da tabela '$table':</h3>";
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            
            echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chave</th><th>Padrão</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>{$col['Field']}</td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // 3. Verificar tabelas que contém 'aplicacao' no nome
    echo "<h3>🔍 Tabelas relacionadas a 'aplicacao':</h3>";
    foreach ($tables as $table) {
        if (strpos(strtolower($table), 'aplicacao') !== false) {
            echo "- <strong>$table</strong><br>";
            
            // Mostrar estrutura desta tabela
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            
            echo "<div style='margin-left: 20px; margin-bottom: 10px;'>";
            echo "Campos: ";
            $fields = array_column($columns, 'Field');
            echo implode(', ', $fields);
            echo "</div>";
        }
    }
    
    // 4. Verificar se existe alguma tabela com webhook
    echo "<br><h3>🔗 Tabelas que podem conter webhooks:</h3>";
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll();
        $fields = array_column($columns, 'Field');
        
        $webhookFields = array_filter($fields, function($field) {
            return strpos(strtolower($field), 'webhook') !== false;
        });
        
        if (!empty($webhookFields)) {
            echo "- <strong>$table</strong>: " . implode(', ', $webhookFields) . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage();
}
?>
