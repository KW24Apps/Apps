<?php
// demo.php - Arquivo de demonstração e teste

try {
    // Carrega configurações (que já define o webhook globalmente)
    $config = require_once __DIR__ . '/config.php';
    
    // Verifica se o webhook foi configurado
    if (isset($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) && 
        $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) {
        $webhook_configurado = true;
        $webhook_url = $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'];
    } else {
        $webhook_configurado = false;
        $webhook_url = null;
    }
    
} catch (Exception $e) {
    $webhook_configurado = false;
    $erro_configuracao = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo - Sistema de Importação</title>
    <link rel="stylesheet" href="/Apps/public/form/assets/css/importacao.css">
    <style>
        .demo-section {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin: 20px auto;
            max-width: 600px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .demo-title {
            color: #3a4a5d;
            font-size: 1.4rem;
            margin-bottom: 15px;
        }
        .demo-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            margin: 10px 0;
        }
        .test-btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .test-btn:hover {
            background: #218838;
        }
        .status {
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
        }
        .status.success { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
        .status.info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="demo-section">
        <h2 class="demo-title">🧪 Demonstração do Sistema</h2>
        <div class="demo-info">
            <strong>Sistema de Importação KW24</strong><br>
            Este sistema permite importar dados de planilhas diretamente para o Bitrix24 usando o sistema de jobs assíncrono.
        </div>
        
        <h3>🔧 Configurações Atuais</h3>
        <div class="status <?php echo $webhook_configurado ? 'success' : 'error'; ?>">
            <?php if ($webhook_configurado): ?>
                <strong>Webhook:</strong> <?php echo substr($webhook_url, 0, 25) . '.../' . substr($webhook_url, -10); ?><br>
                <strong>Origem:</strong> <?php echo isset($chaveAcesso) && $chaveAcesso ? 'Banco de dados' : 'Arquivo local'; ?><br>
            <?php else: ?>
                <strong>Webhook:</strong> ❌ Não configurado<br>
                <?php if (isset($erro_configuracao)): ?>
                    <strong>Erro:</strong> <?php echo htmlspecialchars($erro_configuracao); ?><br>
                <?php endif; ?>
            <?php endif; ?>
            <strong>Cliente:</strong> <?php echo $_GET['cliente'] ?? 'não informado'; ?><br>
            <strong>Funis Disponíveis:</strong> <?php echo implode(', ', $config['funis']); ?><br>
            <strong>Tamanho do Lote:</strong> <?php echo $config['batch_size']; ?> registros
        </div>
        
        <h3>🧪 Testes Disponíveis</h3>
        <?php if ($webhook_configurado): ?>
            <a href="?test=webhook" class="test-btn">Testar Webhook Bitrix</a>
        <?php else: ?>
            <span class="test-btn disabled">Webhook não configurado</span>
        <?php endif; ?>
        <a href="?test=database" class="test-btn">Testar Conexão BD</a>
        <a href="?test=helpers" class="test-btn">Testar Helpers</a>
        
        <?php
        if (isset($_GET['test'])) {
            $test = $_GET['test'];
            echo "<h3>📊 Resultado do Teste: " . ucfirst($test) . "</h3>";
            
            try {
                switch ($test) {
                    case 'webhook':
                        require_once __DIR__ . '/../../helpers/BitrixHelper.php';
                        
                        $response = Helpers\BitrixHelper::chamarApi('user.current', []);
                        if (isset($response['result'])) {
                            echo '<div class="status success">✅ Webhook funcionando! Usuário: ' . ($response['result']['NAME'] ?? 'N/A') . '</div>';
                        } else {
                            echo '<div class="status error">❌ Erro no webhook: ' . ($response['error_description'] ?? 'Erro desconhecido') . '</div>';
                        }
                        break;
                        
                    case 'database':
                        require_once __DIR__ . '/../../dao/BatchJobDAO.php';
                        
                        $dao = new dao\BatchJobDAO();
                        $testData = ['test' => true];
                        $testId = 'test_' . time();
                        
                        if ($dao->criarJob($testId, 'test', $testData, 1)) {
                            echo '<div class="status success">✅ Conexão com banco OK! Job de teste criado.</div>';
                        } else {
                            echo '<div class="status error">❌ Erro na conexão com banco de dados.</div>';
                        }
                        break;
                        
                    case 'helpers':
                        require_once __DIR__ . '/../../helpers/BitrixDealHelper.php';
                        
                        // Teste simples de criação de job
                        $testDeals = [['TITLE' => 'Deal de Teste']];
                        $result = Helpers\BitrixDealHelper::criarJobParaFila('2', '2', $testDeals, 'criar_deals');
                        
                        if ($result['status'] === 'job_criado') {
                            echo '<div class="status success">✅ BitrixDealHelper OK! Job ID: ' . $result['job_id'] . '</div>';
                        } else {
                            echo '<div class="status error">❌ Erro no Helper: ' . ($result['mensagem'] ?? 'Erro desconhecido') . '</div>';
                        }
                        break;
                }
            } catch (Exception $e) {
                echo '<div class="status error">❌ Exceção: ' . $e->getMessage() . '</div>';
            }
        }
        ?>
        
        <h3>📝 Como Usar</h3>
        <ol>
            <li><a href="/Apps/importar/<?php echo isset($_GET['cliente']) ? '?cliente=' . urlencode($_GET['cliente']) : ''; ?>">Acesse a página inicial</a></li>
            <li>Clique em "Iniciar Importação"</li>
            <li>Faça upload do arquivo CSV/Excel</li>
            <li>Mapeie os campos conforme necessário</li>
            <li>Confirme a importação</li>
            <li>Aguarde o processamento via sistema de jobs</li>
        </ol>
        
        <h3>📋 Formato do CSV</h3>
        <div class="demo-info">
            <strong>Exemplo de cabeçalho:</strong><br>
            <code>Nome,Email,Telefone,Empresa,CNPJ</code><br><br>
            <strong>Exemplo de linha:</strong><br>
            <code>João Silva,joao@email.com,11999999999,Empresa XYZ,12.345.678/0001-90</code>
        </div>
    </div>
</body>
</html>
