<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$cliente = $_GET['cliente'] ?? '';
$jobs = $_GET['jobs'] ?? '';
$totalRegistros = (int)($_GET['total'] ?? 0);

// Carrega as configurações para encontrar o link do funil
$config = require_once __DIR__ . '/config.php';
$funilId = $_SESSION['importacao_form']['funil'] ?? '';
$linkFunil = $config['links_funis'][$funilId] ?? '#';

// Calcula o tempo estimado (2 segundos por registro)
$tempoEstimadoSegundos = $totalRegistros * 2;
$tempoEstimadoFormatado = gmdate("i:s", $tempoEstimadoSegundos);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importação em Andamento</title>
    <link rel="stylesheet" href="/Apps/public/form/assets/css/importacao.css">
</head>
<body>
    <div class="import-form">
        <div class="import-form-title">✅ Importação em Andamento</div>
        
        <div class="import-summary">
            <h3>Detalhes da Importação</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <label>Total de registros:</label>
                    <span><?php echo htmlspecialchars($totalRegistros); ?></span>
                </div>
                <div class="summary-item">
                    <label>Tempo estimado:</label>
                    <span>Aproximadamente <?php echo $tempoEstimadoFormatado; ?> minutos</span>
                </div>
            </div>
        </div>

        <div class="status-container">
            <h3>Status</h3>
            <div class="progress-bar-container">
                <div id="progressBar" class="progress-bar"></div>
            </div>
            <div id="statusText" class="status-text">Iniciando...</div>
        </div>

        <div class="user-guidance">
            <p>🚀 Sua importação foi iniciada e está sendo processada em segundo plano.</p>
            <p>🔔 **Não é necessário manter esta página aberta.** Você pode fechá-la e continuar trabalhando normalmente.</p>
        </div>

        <div class="form-actions">
            <a href="/Apps/public/form/importacao.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="btn-secondary">Nova Importação</a>
            <a href="<?php echo htmlspecialchars($linkFunil); ?>" target="_blank" class="btn-primary">Ver Funil no Bitrix</a>
        </div>
    </div>

    <script>
        // Passa os dados do PHP para o JavaScript
        const jobIds = <?php echo json_encode(explode(',', $jobs)); ?>;
        const totalRegistros = <?php echo $totalRegistros; ?>;
        const cliente = '<?php echo addslashes($cliente); ?>';
    </script>
    <script src="/Apps/public/form/assets/js/sucesso.js"></script>
</body>
</html>
