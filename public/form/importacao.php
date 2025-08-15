<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    // Carrega configurações
    $config = require_once __DIR__ . '/config.php';
    
    // Verifica se há parâmetro de cliente para mostrar informações
    $cliente = $_GET['cliente'] ?? null;
    $webhook_configurado = defined('BITRIX_WEBHOOK') && BITRIX_WEBHOOK;
    
} catch (Exception $e) {
    $webhook_configurado = false;
    $erro_configuracao = $e->getMessage();
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
        <div class="import-form-title">Importação de Leads</div>
        <div class="file-upload">
            <label for="arquivo" class="file-upload-label">Escolher arquivo</label>
            <input type="file" id="arquivo" name="arquivo" accept=".csv, .xlsx" required onchange="document.getElementById('file-selected').textContent = this.files[0] ? this.files[0].name : 'Nenhum arquivo selecionado';">
            <span class="file-selected" id="file-selected">Nenhum arquivo selecionado</span>
        </div>
        <label for="funil">Qual Funil:</label>
        <select id="funil" name="funil" required>
            <option value="">Selecione...</option>
            <?php 
            if (isset($config['funis']) && is_array($config['funis'])): 
                foreach ($config['funis'] as $id => $nome): ?>
                    <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($nome); ?></option>
                <?php endforeach; 
            else: ?>
                <option disabled>Erro: Funis não carregados</option>
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
    <script src="/Apps/public/form/assets/js/importacao.js" defer></script>
</body>
</html>
