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

// Agora conecta ao sistema principal que já tem o cliente definido
require_once __DIR__ . '/../../index.php';

try {
    // Verifica se webhook está configurado (já vem do sistema principal)
    $webhook_configurado = isset($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) && 
                          $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'];
    $webhook_value = $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] ?? 'NÃO DEFINIDO';
    
    if (!$webhook_configurado) {
        throw new Exception('Webhook do Bitrix não configurado para o cliente: ' . $cliente);
    }

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
    <form id="importacaoForm" class="import-form" method="POST" action="/Apps/importar/api/importacao" enctype="multipart/form-data">
        <div class="import-form-title">
            Importação de Leads 
            <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: normal; margin-left: 10px;">v2.1 - API Corrigida</span>
        </div>
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
        <button type="submit">Enviar</button>
    </form>
    <div id="mensagem"></div>
    
    <!-- Debug Info -->
    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 12px; color: #6c757d; border-left: 4px solid #28a745;">
        <strong>🔧 Info Técnica:</strong><br>
        • Cliente: <?php echo htmlspecialchars($cliente ?? 'Não informado'); ?><br>
        • Webhook Global: <?php echo $webhook_configurado ? '✅ Configurado' : '❌ Não configurado'; ?><br>
        • Constante BITRIX: <?php echo $bitrix_constant ? '✅ Definida' : '❌ Não definida'; ?><br>
        • Valor Webhook: <?php echo htmlspecialchars(substr($webhook_value, 0, 50)) . (strlen($webhook_value) > 50 ? '...' : ''); ?><br>
        • Config: <?php echo $config_carregado ? '✅ Carregado' : '❌ Erro'; ?><br>
        • Última atualização: 18/08/2025 - 16:45<br>
        <?php if (isset($erro_configuracao)): ?>
        • Erro: <?php echo htmlspecialchars($erro_configuracao); ?><br>
        <?php endif; ?>
    </div>
    
    <script src="/Apps/public/form/assets/js/importacao.js" defer></script>
</body>
</html>
