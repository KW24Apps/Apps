<?php
$cliente = $_GET['cliente'] ?? '';
$mensagem = $_GET['mensagem'] ?? 'Erro desconhecido';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Erro na Importação</title>
    <link rel="stylesheet" href="/Apps/public/form/assets/css/importacao.css">
</head>
<body>
    <div class="import-form">
        <div class="import-form-title">❌ Erro na Importação</div>
        
        <div class="error-message">
            <h3>Detalhes do Erro:</h3>
            <p><?php echo htmlspecialchars($mensagem); ?></p>
        </div>

        <div class="confirmation-actions">
            <a href="/Apps/public/form/confirmacao.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="confirm-btn">🔄 Tentar Novamente</a>
            <a href="/Apps/public/form/mapeamento.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="back-btn">← Voltar ao Mapeamento</a>
            <a href="/Apps/public/form/importacao.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="back-btn">🏠 Voltar ao Início</a>
        </div>
    </div>

    <script>
        console.error('Erro na importação:', '<?php echo addslashes($mensagem); ?>');
    </script>
</body>
</html>
