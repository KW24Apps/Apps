<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/WebhookHelper.php';

$cliente = $_GET['cliente'] ?? 'gnappC93jLq7RxKZVp28HswuAYMe1';
$slug = 'importar';

echo "<h2>Teste de Webhook - Cliente: $cliente</h2>";

try {
    $webhookHelper = new WebhookHelper();
    $webhook = $webhookHelper->obterWebhookBitrix($cliente, $slug);
    
    if ($webhook) {
        echo "<p style='color: green;'><strong>✅ Webhook encontrado:</strong></p>";
        echo "<pre>" . htmlspecialchars($webhook) . "</pre>";
    } else {
        echo "<p style='color: red;'><strong>❌ Webhook não encontrado no banco</strong></p>";
        
        // Teste fallback
        $config = require_once __DIR__ . '/config.php';
        if (isset($config['bitrix_webhook']) && $config['bitrix_webhook']) {
            echo "<p style='color: orange;'><strong>⚠️ Usando fallback do config:</strong></p>";
            echo "<pre>" . htmlspecialchars($config['bitrix_webhook']) . "</pre>";
        } else {
            echo "<p style='color: red;'><strong>❌ Fallback também não encontrado</strong></p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Verificação de banco de dados
echo "<hr><h3>Informações do Banco de Dados</h3>";
try {
    $config_db = require __DIR__ . '/../../config/config.php';
    $pdo = new PDO(
        "mysql:host={$config_db['host']};dbname={$config_db['dbname']};charset=utf8",
        $config_db['usuario'],
        $config_db['senha']
    );
    
    $stmt = $pdo->prepare("SELECT * FROM cliente_aplicacoes WHERE chave_acesso = ? AND slug = ?");
    $stmt->execute([$cliente, $slug]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado) {
        echo "<p style='color: green;'><strong>✅ Registro encontrado no banco:</strong></p>";
        echo "<pre>" . print_r($resultado, true) . "</pre>";
    } else {
        echo "<p style='color: red;'><strong>❌ Nenhum registro encontrado para cliente '$cliente' e slug '$slug'</strong></p>";
        
        // Vamos ver o que existe no banco
        $stmt = $pdo->query("SELECT chave_acesso, slug, webhook_bitrix FROM cliente_aplicacoes LIMIT 10");
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Registros existentes (primeiros 10):</h4>";
        echo "<pre>" . print_r($registros, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Erro de banco:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
