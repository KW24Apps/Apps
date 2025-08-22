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

// Recupera os dados de log da sessão
$logData = $_SESSION['importacao_log'] ?? [];
$linhasLidas = $logData['linhas_lidas'] ?? $totalRegistros; // Fallback para o total
$linhasPuladas = ($logData['linhas_vazias'] ?? 0) + ($logData['linhas_invalidas'] ?? 0);

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
            <h3>Resumo do Processamento</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <label>Linhas lidas do arquivo:</label>
                    <span><?php echo htmlspecialchars($linhasLidas); ?></span>
                </div>
                <div class="summary-item">
                    <label>Registros importados:</label>
                    <span><?php echo htmlspecialchars($totalRegistros); ?></span>
                </div>
                <div class="summary-item">
                    <label>Linhas puladas (vazias/inválidas):</label>
                    <span><?php echo htmlspecialchars($linhasPuladas); ?></span>
                </div>
                <div class="summary-item">
                    <label>Tempo estimado:</label>
                    <span>Aproximadamente <?php echo $tempoEstimadoFormatado; ?> minutos</span>
                </div>
            </div>
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
