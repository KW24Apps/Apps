<?php
$cliente = $_GET['cliente'] ?? '';
$job_id = $_GET['job_id'] ?? 'unknown';
$total = $_GET['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importação Iniciada</title>
    <link rel="stylesheet" href="/Apps/public/form/assets/css/importacao.css">
</head>
<body>
    <div class="import-form">
        <div class="import-form-title">✅ Importação Iniciada com Sucesso</div>
        
        <div class="import-summary">
            <h3>Detalhes da Importação:</h3>
            <p><strong>Job ID:</strong> <?php echo htmlspecialchars($job_id); ?></p>
            <p><strong>Total de registros:</strong> <?php echo htmlspecialchars($total); ?></p>
            <p><strong>Status:</strong> Em processamento</p>
        </div>

        <div class="confirmation-actions">
            <a href="/Apps/public/form/importacao.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="confirm-btn">🆕 Nova Importação</a>
            <a href="/Apps" class="back-btn">🏠 Voltar ao Sistema Principal</a>
        </div>
    </div>

    <script>
        // Auto-refresh da página a cada 30 segundos para acompanhar o progresso
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        
        console.log('Importação iniciada:', {
            job_id: '<?php echo addslashes($job_id); ?>',
            total: <?php echo $total; ?>,
            cliente: '<?php echo addslashes($cliente); ?>'
        });
    </script>
</body>
</html>
