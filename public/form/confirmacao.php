<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Debug session
error_log("=== DEBUG CONFIRMACAO PAGE ===");
error_log("SESSION: " . print_r($_SESSION, true));
error_log("GET: " . print_r($_GET, true));

$cliente = $_GET['cliente'] ?? '';

// Verifica se tem dados na sessão
$dadosImportacao = $_SESSION['importacao_form'] ?? null;
$mapeamento = $_SESSION['mapeamento'] ?? null;

if (!$dadosImportacao || !$mapeamento) {
    error_log("ERRO: Dados de sessão não encontrados");
    error_log("Dados importação: " . ($dadosImportacao ? 'OK' : 'VAZIO'));
    error_log("Mapeamento: " . ($mapeamento ? 'OK' : 'VAZIO'));
    
    $redirect_url = '/Apps/public/form/importacao.php' . ($cliente ? '?cliente=' . urlencode($cliente) : '');
    header("Location: $redirect_url");
    exit;
}

// Configuração do webhook (conexão direta com banco)
$webhook_configurado = false;
$erro_configuracao = '';

if ($cliente) {
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=kw24co49_api_kwconfig;charset=utf8mb4',
            'kw24co49_kw24',
            'BlFOyf%X}#jXwrR-vi'
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("
            SELECT ca.webhook_bitrix
            FROM cliente_aplicacoes ca 
            WHERE ca.chave_acesso = ? AND ca.ativo = 1
        ");
        $stmt->execute([$cliente]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($webhook && !empty($webhook['webhook_bitrix'])) {
            $webhook_configurado = true;
        } else {
            $erro_configuracao = 'Webhook não encontrado para esta chave de cliente.';
        }
    } catch (Exception $e) {
        $erro_configuracao = 'Erro ao conectar com banco: ' . $e->getMessage();
        error_log("Erro DB confirmacao: " . $e->getMessage());
    }
} else {
    $erro_configuracao = 'Parâmetro cliente não fornecido.';
}

// Dados para exibição
$nomeArquivo = $dadosImportacao['nome_arquivo'] ?? 'Arquivo não identificado';
$totalLinhas = $dadosImportacao['total_linhas'] ?? 0;
$spa = $dadosImportacao['spa'] ?? 'SPA não identificado';
$colunas = $dadosImportacao['colunas'] ?? [];

error_log("Dados para exibição:");
error_log("Nome arquivo: $nomeArquivo");
error_log("Total linhas: $totalLinhas");
error_log("SPA: $spa");
error_log("Colunas: " . print_r($colunas, true));
error_log("Mapeamento: " . print_r($mapeamento, true));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Confirmação de Importação</title>
    <link rel="stylesheet" href="/Apps/public/form/assets/css/importacao.css">
    <script src="/Apps/public/form/assets/js/confirmacao.js" defer></script>
</head>
<body>
    <?php if (!$webhook_configurado): ?>
        <div class="import-form">
            <div class="import-form-title">❌ Configuração Necessária</div>
            <div class="error-message">
                <p><strong>Webhook do Bitrix não configurado.</strong></p>
                <p><strong>Erro:</strong> <?php echo htmlspecialchars($erro_configuracao); ?></p>
                <a href="/Apps/public/form/importacao.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="back-btn">← Voltar para Importação</a>
            </div>
        </div>
    <?php else: ?>
        <div class="import-form">
            <div class="import-form-title">Confirmação de Importação</div>
            
            <div class="import-summary">
                <h3>Resumo da Importação:</h3>
                <p><strong>SPA:</strong> <?php echo htmlspecialchars($spa); ?></p>
                <p><strong>Arquivo:</strong> <?php echo htmlspecialchars($nomeArquivo); ?></p>
                <p><strong>Total de linhas:</strong> <?php echo htmlspecialchars($totalLinhas); ?></p>
            </div>

            <div class="mapping-summary">
                <h3>Mapeamento de Campos:</h3>
                <table class="mapping-table">
                    <thead>
                        <tr>
                            <th>Coluna do CSV</th>
                            <th>Campo do Bitrix</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mapeamento as $coluna => $campo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($coluna); ?></td>
                                <td><?php echo htmlspecialchars($campo); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="confirmation-actions">
                <form id="confirmForm" method="POST" action="/Apps/public/form/api/importar_job.php">
                    <input type="hidden" name="cliente" value="<?php echo htmlspecialchars($cliente); ?>">
                    <button type="submit" class="confirm-btn">✅ Confirmar e Iniciar Importação</button>
                </form>
                
                <a href="/Apps/public/form/mapeamento.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="back-btn">← Voltar ao Mapeamento</a>
                <a href="/Apps/public/form/importacao.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="back-btn">🏠 Voltar ao Início</a>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Debug no console
        console.log('Dados da sessão:', {
            spa: '<?php echo addslashes($spa); ?>',
            arquivo: '<?php echo addslashes($nomeArquivo); ?>',
            linhas: <?php echo $totalLinhas; ?>,
            mapeamento: <?php echo json_encode($mapeamento); ?>
        });
    </script>
</body>
</html>
