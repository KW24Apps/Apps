<?php
// check_database.php - Verificar registros no banco
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🗄️ Verificação do Banco de Dados</h2>";

$cliente = 'gnappC93fLq7RxKZVp28HswuAYMe1';

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
    
    echo "✅ <strong>Conexão com banco estabelecida</strong><br><br>";
    
    // 1. Verificar se existe o cliente
    echo "<h3>1. 🔍 Verificando cliente '$cliente'</h3>";
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE chave_acesso = ?");
    $stmt->execute([$cliente]);
    $clienteData = $stmt->fetch();
    
    if ($clienteData) {
        echo "✅ <strong>Cliente encontrado!</strong><br>";
        echo "ID: " . $clienteData['id'] . "<br>";
        echo "Nome: " . $clienteData['nome'] . "<br>";
        echo "Chave: " . $clienteData['chave_acesso'] . "<br><br>";
        
        // 2. Verificar aplicações do cliente
        echo "<h3>2. 📱 Aplicações do cliente</h3>";
        $stmt = $pdo->prepare("SELECT * FROM aplicacoes WHERE cliente_id = ?");
        $stmt->execute([$clienteData['id']]);
        $aplicacoes = $stmt->fetchAll();
        
        if ($aplicacoes) {
            echo "<strong>Aplicações encontradas:</strong><br>";
            foreach ($aplicacoes as $app) {
                echo "- ID: {$app['id']}, Slug: {$app['slug']}, Nome: {$app['nome']}<br>";
                
                // 3. Verificar webhooks de cada aplicação
                echo "<h4>🔗 Webhooks da aplicação '{$app['slug']}':</h4>";
                $stmt = $pdo->prepare("SELECT * FROM aplicacao_acesso WHERE aplicacao_id = ?");
                $stmt->execute([$app['id']]);
                $webhooks = $stmt->fetchAll();
                
                if ($webhooks) {
                    foreach ($webhooks as $webhook) {
                        echo "&nbsp;&nbsp;- ID: {$webhook['id']}<br>";
                        echo "&nbsp;&nbsp;- URL: {$webhook['url_webhook']}<br>";
                        echo "&nbsp;&nbsp;- Token: {$webhook['token_acesso']}<br>";
                        echo "&nbsp;&nbsp;- Ativo: " . ($webhook['ativo'] ? 'Sim' : 'Não') . "<br><br>";
                    }
                } else {
                    echo "&nbsp;&nbsp;❌ Nenhum webhook encontrado<br><br>";
                }
            }
        } else {
            echo "❌ <strong>Nenhuma aplicação encontrada para este cliente</strong><br><br>";
        }
        
        // 4. Busca específica por 'import' ou 'importar'
        echo "<h3>3. 🎯 Busca específica por slug 'import' ou 'importar'</h3>";
        $stmt = $pdo->prepare("
            SELECT a.*, aa.url_webhook, aa.token_acesso, aa.ativo
            FROM aplicacoes a
            LEFT JOIN aplicacao_acesso aa ON a.id = aa.aplicacao_id
            WHERE a.cliente_id = ? AND (a.slug LIKE '%import%' OR a.slug LIKE '%importar%')
        ");
        $stmt->execute([$clienteData['id']]);
        $importApps = $stmt->fetchAll();
        
        if ($importApps) {
            foreach ($importApps as $app) {
                echo "✅ <strong>Aplicação de importação encontrada:</strong><br>";
                echo "- Slug: {$app['slug']}<br>";
                echo "- Webhook: {$app['url_webhook']}<br>";
                echo "- Token: {$app['token_acesso']}<br>";
                echo "- Ativo: " . ($app['ativo'] ? 'Sim' : 'Não') . "<br><br>";
            }
        } else {
            echo "❌ <strong>Nenhuma aplicação de import encontrada</strong><br><br>";
        }
        
    } else {
        echo "❌ <strong>Cliente NÃO encontrado no banco!</strong><br><br>";
        
        // Listar alguns clientes para comparação
        echo "<h3>📋 Clientes existentes no banco:</h3>";
        $stmt = $pdo->query("SELECT chave_acesso, nome FROM clientes LIMIT 10");
        $clientes = $stmt->fetchAll();
        
        foreach ($clientes as $c) {
            echo "- Chave: {$c['chave_acesso']}, Nome: {$c['nome']}<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage();
}
?>
