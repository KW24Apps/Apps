<?php
// test_webhook_db.php - Teste direto do webhook no banco
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🗄️ Teste Direto do Webhook no Banco</h2>";

$cliente = $_GET['cliente'] ?? 'gnappC93fLq7RxKZVp28HswuAYMe1';

try {
    // Usar configuração de produção diretamente
    $config = [
        'host' => 'localhost',
        'dbname' => 'kw24co49_api_kwconfig',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];
    
    echo "<strong>🔗 Testando conexão...</strong><br>";
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
        $config['usuario'],
        $config['senha']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexão estabelecida!<br><br>";
    
    echo "<strong>📊 Procurando cliente: " . htmlspecialchars($cliente) . "</strong><br>";
    
    // 1. Verifica se cliente existe
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE chave_acesso = ?");
    $stmt->execute([$cliente]);
    $clienteData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($clienteData) {
        echo "✅ Cliente encontrado: ID " . $clienteData['id'] . " - " . htmlspecialchars($clienteData['nome']) . "<br>";
    } else {
        echo "❌ Cliente NÃO encontrado<br>";
        
        // Mostra clientes existentes
        $stmt = $pdo->query("SELECT id, chave_acesso, nome FROM clientes LIMIT 5");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<strong>Clientes disponíveis:</strong><br>";
        foreach ($clientes as $c) {
            echo "• {$c['id']} - {$c['chave_acesso']} - {$c['nome']}<br>";
        }
        exit;
    }
    
    // 2. Verifica aplicação 'import'
    $stmt = $pdo->prepare("SELECT * FROM aplicacoes WHERE slug = 'import'");
    $stmt->execute();
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($app) {
        echo "✅ Aplicação 'import' encontrada: ID " . $app['id'] . "<br>";
    } else {
        echo "❌ Aplicação 'import' NÃO encontrada<br>";
        
        $stmt = $pdo->query("SELECT id, slug, nome FROM aplicacoes");
        $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<strong>Aplicações disponíveis:</strong><br>";
        foreach ($apps as $a) {
            echo "• {$a['id']} - {$a['slug']} - {$a['nome']}<br>";
        }
        exit;
    }
    
    // 3. Verifica relação cliente_aplicacoes
    $stmt = $pdo->prepare("
        SELECT ca.*, c.nome as cliente_nome, a.nome as app_nome
        FROM cliente_aplicacoes ca
        JOIN clientes c ON ca.cliente_id = c.id
        JOIN aplicacoes a ON ca.aplicacao_id = a.id
        WHERE c.chave_acesso = ? AND a.slug = 'import'
    ");
    $stmt->execute([$cliente]);
    $relacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($relacao) {
        echo "✅ Relação encontrada!<br>";
        echo "<pre style='background: #e8f5e8; padding: 10px;'>";
        print_r($relacao);
        echo "</pre>";
        
        if (!empty($relacao['webhook_bitrix'])) {
            echo "<strong style='color: green;'>🎉 WEBHOOK CONFIGURADO!</strong><br>";
            echo "Webhook: " . htmlspecialchars(substr($relacao['webhook_bitrix'], 0, 60)) . "...<br>";
        } else {
            echo "<strong style='color: red;'>❌ WEBHOOK VAZIO OU NULL</strong><br>";
        }
        
    } else {
        echo "❌ Relação cliente-aplicação NÃO encontrada<br>";
        
        // Criar a relação se não existir
        echo "<br><strong>🔧 Vou criar a relação...</strong><br>";
        try {
            $stmt = $pdo->prepare("
                INSERT INTO cliente_aplicacoes (cliente_id, aplicacao_id, ativo, webhook_bitrix) 
                VALUES (?, ?, 1, 'https://gnapp.bitrix24.com.br/rest/4743/bo89fqgov14qx8gl/')
            ");
            $stmt->execute([$clienteData['id'], $app['id']]);
            echo "✅ Relação criada com webhook padrão!<br>";
        } catch (Exception $e) {
            echo "❌ Erro ao criar relação: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . htmlspecialchars($e->getMessage()) . "<br>";
}
?>
