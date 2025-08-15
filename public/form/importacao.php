<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Carrega configurações
$config = require_once __DIR__ . '/config.php';

// Não limpar a sessão ao acessar via GET
// O preenchimento dos campos será feito apenas pelo JavaScript (sessionStorage)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importação de Leads</title>
    <link rel="stylesheet" href="assets/css/importacao.css">
</head>
<body>
    <form id="importacaoForm" class="import-form" method="POST" action="api/importacao.php" enctype="multipart/form-data">
        <div class="import-form-title">Importação de Leads</div>
        <div class="file-upload">
            <label for="arquivo" class="file-upload-label">Escolher arquivo</label>
            <input type="file" id="arquivo" name="arquivo" accept=".csv, .xlsx" required onchange="document.getElementById('file-selected').textContent = this.files[0] ? this.files[0].name : 'Nenhum arquivo selecionado';">
            <span class="file-selected" id="file-selected">Nenhum arquivo selecionado</span>
        </div>
        <label for="funil">Qual Funil:</label>
        <select id="funil" name="funil" required>
            <option value="">Selecione...</option>
            <?php foreach ($config['funis'] as $id => $nome): ?>
                <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($nome); ?></option>
            <?php endforeach; ?>
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
    <script src="assets/js/importacao.js" defer></script>
</body>
</html>
