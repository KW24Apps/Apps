<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Primeiro verifica se cliente foi informado
$cliente = $_GET['cliente'] ?? null;
if (!$cliente) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px;">
         <h2>❌ Parâmetro obrigatório</h2>
         <p>Esta aplicação requer o parâmetro <code>?cliente=CHAVE_CLIENTE</code> na URL.</p>
         <p>Exemplo: <code>importacao.php?cliente=sua_chave_aqui</code></p>
         </div>');
}

try {
    // Conecta diretamente ao banco para buscar webhook (como fazíamos antes)
    $config = [
        'host' => 'localhost',
        'dbname' => 'kw24co49_api_kwconfig',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
        $config['usuario'],
        $config['senha']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
        SELECT ca.webhook_bitrix
        FROM clientes c
        JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id
        JOIN aplicacoes a ON ca.aplicacao_id = a.id
        WHERE c.chave_acesso = :chave
        AND a.slug = 'import'
        AND ca.ativo = 1
        AND ca.webhook_bitrix IS NOT NULL
        AND ca.webhook_bitrix != ''
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':chave', $cliente);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $webhook = $resultado['webhook_bitrix'] ?? null;

    if (!$webhook) {
        throw new Exception('Webhook não encontrado para o cliente: ' . $cliente);
    }

    // Define globalmente para uso nos helpers
    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;
    
    // Define constante para compatibilidade
    if (!defined('BITRIX_WEBHOOK')) {
        define('BITRIX_WEBHOOK', $webhook);
    }
    
    $webhook_configurado = true;
    $webhook_value = $webhook;
    
    // Carrega configurações dos funis (ainda usando config local para funis específicos)
    $config = require_once __DIR__ . '/config.php';
    $config_carregado = is_array($config) && isset($config['funis']) && is_array($config['funis']);
    
    // Define variável para debug
    $bitrix_constant = defined('BITRIX_WEBHOOK') && BITRIX_WEBHOOK;
    
} catch (Exception $e) {
    $webhook_configurado = false;
    $erro_configuracao = $e->getMessage();
    $config = ['funis' => []]; // Config fallback
    $config_carregado = false;
    $bitrix_constant = false; // Define para debug
}

// Não limpar a sessão ao acessar via GET
// O preenchimento dos campos será feito apenas pelo JavaScript (sessionStorage)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importação de Leads</title>
    <link rel="stylesheet" href="/Apps/public/form/assets/css/importacao.css">
</head>
<body>
    <form id="importacaoForm" class="import-form" method="POST" action="/Apps/public/form/api/importacao.php?cliente=<?php echo urlencode($cliente); ?>" enctype="multipart/form-data">
        <div class="import-form-title">
            Importação de Leads
        </div>
        
        <div class="content-container">
            <div class="file-upload">
                <label for="arquivo" class="file-upload-label">Escolher arquivo</label>
                <input type="file" id="arquivo" name="arquivo" accept=".csv, .xlsx" required onchange="document.getElementById('file-selected').textContent = this.files[0] ? this.files[0].name : 'Nenhum arquivo selecionado';">
                <span class="file-selected" id="file-selected">Nenhum arquivo selecionado</span>
            </div>
            
            <label for="funil">Qual Funil:</label>
            <select id="funil" name="funil" required>
                <option value="">Selecione...</option>
                <?php 
                if ($config_carregado): 
                    foreach ($config['funis'] as $id => $nome): ?>
                        <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($nome); ?></option>
                    <?php endforeach; 
                else: ?>
                    <option disabled>❌ Config não carregado</option>
                    <?php if (isset($erro_configuracao)): ?>
                        <option disabled>Erro: <?php echo htmlspecialchars($erro_configuracao); ?></option>
                    <?php endif; ?>
                <?php endif; ?>
            </select>
            
            <label for="identificador">Identificador da Importação:</label>
            <input type="text" id="identificador" name="identificador" required>
            
            <label for="responsavel">Responsável pelo Lead Gerado:</label>
            <div class="autocomplete-wrapper">
                <input type="text" id="responsavel" name="responsavel" autocomplete="off" placeholder="Digite para buscar..." required>
                <div id="autocomplete-responsavel" class="autocomplete-list"></div>
            </div>
            
            <label for="solicitante">Solicitante do Import:</label>
            <div class="autocomplete-wrapper">
                <input type="text" id="solicitante" name="solicitante" autocomplete="off" placeholder="Digite para buscar..." required>
                <div id="autocomplete-solicitante" class="autocomplete-list"></div>
            </div>
        </div>
        
        <button type="submit">Enviar</button>
    </form>
    <div id="mensagem"></div>
    
    <script src="/Apps/public/form/assets/js/importacao.js?v=<?= time() . rand(1000, 9999) ?>" defer></script>
</body>
</html>
