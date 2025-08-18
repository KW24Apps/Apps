<?php
// debug_webhook.php - Script de diagnóstico completo
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnóstico Completo do Sistema de Webhook</h1>";

// Dados de entrada
$cliente = $_GET['cliente'] ?? 'gnappC93fLq7RxKZVp28HswuAYMe1';
$slug = 'import';

echo "<h2>📋 Parâmetros de Entrada</h2>";
echo "<strong>Cliente:</strong> " . htmlspecialchars($cliente) . "<br>";
echo "<strong>Slug:</strong> " . htmlspecialchars($slug) . "<br>";
echo "<strong>URL Completa:</strong> " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') . "<br><br>";

// 1. Teste de Conexão com Banco
echo "<h2>🗄️ Teste de Conexão com Banco de Dados</h2>";
try {
    $config_db = require __DIR__ . '/../../config/config.php';
    echo "<strong>✅ Config carregado:</strong><br>";
    echo "Host: " . htmlspecialchars($config_db['host']) . "<br>";
    echo "Database: " . htmlspecialchars($config_db['dbname']) . "<br>";
    echo "Usuário: " . htmlspecialchars($config_db['usuario']) . "<br>";
    echo "Senha: " . str_repeat('*', strlen($config_db['senha'])) . "<br><br>";
    
    $pdo = new PDO(
        "mysql:host={$config_db['host']};dbname={$config_db['dbname']};charset=utf8",
        $config_db['usuario'],
        $config_db['senha']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<strong>✅ Conexão estabelecida com sucesso!</strong><br><br>";
    
} catch (Exception $e) {
    echo "<strong>❌ ERRO na conexão:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
    exit;
}

// 2. Verificação das Tabelas
echo "<h2>🏗️ Verificação da Estrutura do Banco</h2>";
try {
    // Verifica se tabelas existem
    $tabelas = ['clientes', 'aplicacoes', 'cliente_aplicacoes'];
    foreach ($tabelas as $tabela) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Tabela '$tabela' existe<br>";
        } else {
            echo "❌ Tabela '$tabela' NÃO EXISTE<br>";
        }
    }
    echo "<br>";
    
    // Mostra estrutura da tabela cliente_aplicacoes
    echo "<h3>📊 Estrutura da tabela cliente_aplicacoes:</h3>";
    $stmt = $pdo->query("DESCRIBE cliente_aplicacoes");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($colunas as $coluna) {
        echo "- {$coluna['Field']} ({$coluna['Type']}) - {$coluna['Null']}<br>";
    }
    echo "<br>";
    
} catch (Exception $e) {
    echo "<strong>❌ ERRO ao verificar tabelas:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
}

// 3. Busca por Cliente específico
echo "<h2>👤 Busca pelo Cliente Específico</h2>";
try {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE chave_acesso = ?");
    $stmt->execute([$cliente]);
    $clienteDb = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($clienteDb) {
        echo "<strong>✅ Cliente encontrado:</strong><br>";
        echo "<pre>" . print_r($clienteDb, true) . "</pre>";
    } else {
        echo "<strong>❌ Cliente '$cliente' NÃO ENCONTRADO na tabela clientes</strong><br>";
        
        // Mostra clientes existentes
        $stmt = $pdo->query("SELECT id, chave_acesso, nome FROM clientes LIMIT 10");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h4>Clientes existentes (primeiros 10):</h4>";
        foreach ($clientes as $c) {
            echo "- ID: {$c['id']} | Chave: {$c['chave_acesso']} | Nome: {$c['nome']}<br>";
        }
    }
    echo "<br>";
    
} catch (Exception $e) {
    echo "<strong>❌ ERRO ao buscar cliente:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
}

// 4. Busca por Aplicação
echo "<h2>📱 Busca pela Aplicação 'importar'</h2>";
try {
    $stmt = $pdo->prepare("SELECT * FROM aplicacoes WHERE slug = ?");
    $stmt->execute([$slug]);
    $aplicacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($aplicacao) {
        echo "<strong>✅ Aplicação encontrada:</strong><br>";
        echo "<pre>" . print_r($aplicacao, true) . "</pre>";
    } else {
        echo "<strong>❌ Aplicação '$slug' NÃO ENCONTRADA na tabela aplicacoes</strong><br>";
        
        // Mostra aplicações existentes
        $stmt = $pdo->query("SELECT id, slug, nome FROM aplicacoes");
        $aplicacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h4>Aplicações existentes:</h4>";
        foreach ($aplicacoes as $app) {
            echo "- ID: {$app['id']} | Slug: {$app['slug']} | Nome: {$app['nome']}<br>";
        }
    }
    echo "<br>";
    
} catch (Exception $e) {
    echo "<strong>❌ ERRO ao buscar aplicação:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
}

// 5. Busca na tabela cliente_aplicacoes
echo "<h2>🔗 Busca na Relação cliente_aplicacoes</h2>";
try {
    $sql = "
        SELECT ca.*, c.chave_acesso, c.nome as nome_cliente, a.slug, a.nome as nome_aplicacao
        FROM cliente_aplicacoes ca
        LEFT JOIN clientes c ON ca.cliente_id = c.id
        LEFT JOIN aplicacoes a ON ca.aplicacao_id = a.id
        WHERE c.chave_acesso = ? AND a.slug = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente, $slug]);
    $relacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($relacao) {
        echo "<strong>✅ Relação encontrada:</strong><br>";
        echo "<pre>" . print_r($relacao, true) . "</pre>";
        
        if (!empty($relacao['webhook_bitrix'])) {
            echo "<strong>✅ Webhook configurado!</strong><br>";
            echo "Webhook: " . htmlspecialchars($relacao['webhook_bitrix']) . "<br>";
        } else {
            echo "<strong>⚠️ Webhook vazio ou NULL</strong><br>";
        }
        
    } else {
        echo "<strong>❌ Relação NÃO ENCONTRADA</strong><br>";
        
        // Mostra relações existentes para este cliente
        $stmt = $pdo->prepare("
            SELECT ca.*, c.chave_acesso, a.slug 
            FROM cliente_aplicacoes ca
            LEFT JOIN clientes c ON ca.cliente_id = c.id
            LEFT JOIN aplicacoes a ON ca.aplicacao_id = a.id
            WHERE c.chave_acesso = ?
        ");
        $stmt->execute([$cliente]);
        $relacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($relacoes) {
            echo "<h4>Relações existentes para este cliente:</h4>";
            foreach ($relacoes as $rel) {
                echo "- Slug: {$rel['slug']} | Ativo: {$rel['ativo']} | Webhook: " . 
                     (empty($rel['webhook_bitrix']) ? 'VAZIO' : 'CONFIGURADO') . "<br>";
            }
        } else {
            echo "<h4>❌ Nenhuma relação encontrada para este cliente</h4>";
        }
    }
    echo "<br>";
    
} catch (Exception $e) {
    echo "<strong>❌ ERRO ao buscar relação:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
}

// 6. Teste do WebhookHelper
echo "<h2>🔧 Teste do WebhookHelper</h2>";
try {
    require_once __DIR__ . '/WebhookHelper.php';
    
    $webhookHelper = new WebhookHelper();
    $webhook = $webhookHelper->obterWebhookBitrix($cliente, $slug);
    
    if ($webhook) {
        echo "<strong>✅ WebhookHelper retornou webhook:</strong><br>";
        echo "Webhook: " . htmlspecialchars($webhook) . "<br>";
        
        // Testa validação
        $valido = WebhookHelper::validarWebhook($webhook);
        echo "Válido: " . ($valido ? '✅ SIM' : '❌ NÃO') . "<br>";
        
    } else {
        echo "<strong>❌ WebhookHelper retornou NULL</strong><br>";
    }
    echo "<br>";
    
} catch (Exception $e) {
    echo "<strong>❌ ERRO no WebhookHelper:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
}

// 7. Teste do config.php
echo "<h2>⚙️ Teste do config.php</h2>";
try {
    // Simula $_GET para o teste
    $_GET['cliente'] = $cliente;
    
    ob_start();
    $config = require __DIR__ . '/config.php';
    $output = ob_get_clean();
    
    echo "Saída do config.php: " . htmlspecialchars($output) . "<br>";
    
    if (defined('BITRIX_WEBHOOK')) {
        echo "<strong>✅ BITRIX_WEBHOOK definida:</strong> " . htmlspecialchars(BITRIX_WEBHOOK) . "<br>";
    } else {
        echo "<strong>❌ BITRIX_WEBHOOK NÃO DEFINIDA</strong><br>";
    }
    
    if (isset($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'])) {
        echo "<strong>✅ Global webhook definido:</strong> " . 
             htmlspecialchars($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) . "<br>";
    } else {
        echo "<strong>❌ Global webhook NÃO DEFINIDO</strong><br>";
    }
    
    echo "<strong>Config retornado:</strong><br>";
    echo "<pre>" . print_r($config, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<strong>❌ ERRO no config.php:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
}

// 8. Teste de Fallback
echo "<h2>🔄 Teste de Fallback (config_secure.php)</h2>";
if (file_exists(__DIR__ . '/config_secure.php')) {
    try {
        $fallback_config = require __DIR__ . '/config_secure.php';
        echo "<strong>✅ config_secure.php existe e foi carregado:</strong><br>";
        if (isset($fallback_config['bitrix_webhook'])) {
            echo "Webhook fallback: " . htmlspecialchars($fallback_config['bitrix_webhook']) . "<br>";
        } else {
            echo "❌ Webhook não definido no config_secure.php<br>";
        }
    } catch (Exception $e) {
        echo "<strong>❌ ERRO ao carregar config_secure.php:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    }
} else {
    echo "<strong>⚠️ config_secure.php não existe</strong><br>";
}

echo "<br><h2>🏁 Fim do Diagnóstico</h2>";
echo "<p>Tempo de execução: " . (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) . " segundos</p>";
?>
