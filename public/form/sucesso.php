<?php
$cliente = $_GET['cliente'] ?? '';
$jobs = $_GET['jobs'] ?? $_GET['job_id'] ?? 'unknown';
$total = $_GET['total'] ?? 0;
$chunks = $_GET['chunks'] ?? 1;

// Se temos múltiplos jobs, separa os IDs
$jobIds = explode(',', $jobs);
$isMultipleJobs = count($jobIds) > 1;
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
            
            <?php if ($isMultipleJobs): ?>
                <p><strong>Total de registros:</strong> <?php echo htmlspecialchars($total); ?></p>
                <p><strong>Jobs criados:</strong> <?php echo htmlspecialchars($chunks); ?> jobs (máximo 50 registros cada)</p>
                <p><strong>Status:</strong> Em processamento</p>
                
                <h4>IDs dos Jobs:</h4>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; margin: 10px 0;">
                    <?php foreach ($jobIds as $index => $jobId): ?>
                        <div style="margin: 5px 0; font-family: monospace; font-size: 0.9em;">
                            <strong>Job <?php echo $index + 1; ?>:</strong> <?php echo htmlspecialchars($jobId); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><strong>Job ID:</strong> <?php echo htmlspecialchars($jobs); ?></p>
                <p><strong>Total de registros:</strong> <?php echo htmlspecialchars($total); ?></p>
                <p><strong>Status:</strong> Em processamento</p>
            <?php endif; ?>
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
            jobs: <?php echo json_encode($jobIds); ?>,
            total: <?php echo $total; ?>,
            chunks: <?php echo $chunks; ?>,
            isMultiple: <?php echo $isMultipleJobs ? 'true' : 'false'; ?>,
            cliente: '<?php echo addslashes($cliente); ?>'
        });
    </script>
</body>
</html>
