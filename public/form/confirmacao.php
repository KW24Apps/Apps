<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Inclui helper para mapear nomes de campos
require_once __DIR__ . '/helpers/field_mapper.php';

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
            FROM clientes c
            JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id
            JOIN aplicacoes a ON ca.aplicacao_id = a.id
            WHERE c.chave_acesso = ?
            AND a.slug = 'import'
            AND ca.ativo = 1
            AND ca.webhook_bitrix IS NOT NULL
            AND ca.webhook_bitrix != ''
            LIMIT 1
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
$spa = $dadosImportacao['spa'] ?? $dadosImportacao['identificador'] ?? 'SPA não identificado';
$colunas = $dadosImportacao['colunas'] ?? [];
$primeiraLinhas = $dadosImportacao['primeiras_linhas'] ?? [];

error_log("Dados para exibição:");
error_log("Nome arquivo: $nomeArquivo");
error_log("Total linhas: $totalLinhas");
error_log("SPA: $spa");
error_log("Colunas: " . print_r($colunas, true));
error_log("Primeiras linhas: " . print_r($primeiraLinhas, true));
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
                <h3>📄 Resumo da Importação</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <label>SPA:</label>
                        <span><?php echo htmlspecialchars($spa); ?></span>
                    </div>
                    <div class="summary-item">
                        <label>Arquivo:</label>
                        <span><?php echo htmlspecialchars($nomeArquivo); ?></span>
                    </div>
                    <div class="summary-item">
                        <label>Total de linhas:</label>
                        <span><?php echo number_format($totalLinhas, 0, ',', '.'); ?> registros</span>
                    </div>
                </div>
            </div>

            <div class="mapping-summary">
                <h3>🔗 Mapeamento de Campos</h3>
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
                                <td><?php echo htmlspecialchars(FieldMapper::getFieldName($campo)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($primeiraLinhas)): ?>
                <div class="preview-section">
                    <h3>👁️ Pré-visualização dos Dados</h3>
                    <div class="preview-note">Primeiras <?php echo count($primeiraLinhas); ?> linhas do arquivo:</div>
                    
                    <div class="preview-table-container">
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <?php foreach ($colunas as $coluna): ?>
                                        <th><?php echo htmlspecialchars($coluna); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($primeiraLinhas as $indice => $linha): ?>
                                    <tr>
                                        <?php foreach ($linha as $i => $valor): ?>
                                            <td><?php echo htmlspecialchars($valor); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="confirmation-actions">
                <form id="confirmForm" method="POST" action="/Apps/public/form/api/processar_importacao.php">
                    <input type="hidden" name="cliente" value="<?php echo htmlspecialchars($cliente); ?>">
                    <button type="submit" class="confirm-btn">🚀 Confirmar e Iniciar Importação</button>
                </form>
                
                <div class="action-links">
                    <a href="/Apps/public/form/mapeamento.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="back-btn">← Voltar ao Mapeamento</a>
                    <a href="/Apps/public/form/importacao.php<?php echo $cliente ? '?cliente=' . urlencode($cliente) : ''; ?>" class="back-btn">🏠 Voltar ao Início</a>
                </div>
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
