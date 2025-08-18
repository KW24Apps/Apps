<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verifica se cliente foi informado
$cliente = $_GET['cliente'] ?? null;
if (!$cliente) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px;">
         <h2>❌ Parâmetro obrigatório</h2>
         <p>Esta aplicação requer o parâmetro <code>?cliente=CHAVE_CLIENTE</code> na URL.</p>
         </div>');
}

// Conecta diretamente ao banco para buscar webhook
try {
    $config = [
        'host' => 'localhost',
        'dbname' => 'kw24co49_api_kwconfig',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
        $config['usuario'],
        $config['senha']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
        SELECT ca.webhook_bitrix
        FROM clientes c
        JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id
        JOIN aplicacoes a ON ca.aplicacao_id = a.id
        WHERE c.chave_acesso = :chave
        AND a.slug = 'import'
        AND ca.ativo = 1
        AND ca.webhook_bitrix IS NOT NULL
        AND ca.webhook_bitrix != ''
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':chave', $cliente);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $webhook = $resultado['webhook_bitrix'] ?? null;

    if (!$webhook) {
        throw new Exception('Webhook não encontrado para o cliente: ' . $cliente);
    }

    // Define globalmente para uso nos helpers
    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;
    
    // Define constante para compatibilidade
    if (!defined('BITRIX_WEBHOOK')) {
        define('BITRIX_WEBHOOK', $webhook);
    }

} catch (Exception $e) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px;">
         <h2>❌ Erro de configuração</h2>
         <p>' . htmlspecialchars($e->getMessage()) . '</p>
         </div>');
}

// Captura warnings para exibir no console JS
ob_start();

// Caminho do arquivo CSV salvo na etapa anterior
$uploadDir = __DIR__ . '/uploads/';
$csvFile = null;

// Busca o arquivo mais recente enviado (simples, pode ser melhorado)
$files = glob($uploadDir . '*.csv');
if ($files) {
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    $csvFile = $files[0];
}

$colunas = [];
if ($csvFile && ($handle = fopen($csvFile, 'r')) !== false) {
    // Fornece o parâmetro $escape explicitamente para evitar deprecated
    $colunas = fgetcsv($handle, 0, ',', '"', "\\");
    fclose($handle);
}

// Puxa os campos do funil usando BitrixHelper (adapta para usar pasta Apps)
require_once __DIR__ . '/../../helpers/BitrixHelper.php';
use Helpers\BitrixHelper;

try {
    // O webhook já foi configurado anteriormente, apenas busca campos do Bitrix
    $camposBitrix = BitrixHelper::consultarCamposCrm(2); // 2 = Negócios
    $webhook_configurado = true;
    
    // Se não conseguiu buscar campos, usa campos padrão
    if (empty($camposBitrix)) {
        $camposBitrix = [
            'TITLE' => 'Título do Negócio',
            'CONTACT_ID' => 'Pessoa de Contato',
            'COMPANY_TITLE' => 'Empresa', 
            'PHONE' => 'Telefone',
            'EMAIL' => 'E-mail'
        ];
    }
    
} catch (Exception $e) {
    $webhook_configurado = false;
    $erro_configuracao = $e->getMessage();
    error_log("Erro ao buscar campos Bitrix: " . $e->getMessage());
    
    // Campos padrão para fallback
    $camposBitrix = [
        'TITLE' => 'Título do Negócio',
        'CONTACT_ID' => 'Pessoa de Contato',
        'COMPANY_TITLE' => 'Empresa',
        'PHONE' => 'Telefone',
        'EMAIL' => 'E-mail'
    ];
}

// Captura warnings
$php_warnings = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Mapeamento de Campos</title>
    <link rel="stylesheet" href="/Apps/public/form/assets/css/importacao.css">
    <script src="/Apps/public/form/assets/js/confirmacao.js" defer></script>
</head>
<body>
    <?php if (!$webhook_configurado): ?>
        <div class="import-form">
            <div class="import-form-title">❌ Configuração Necessária</div>
            <div class="error-message">
                <p><strong>Webhook do Bitrix não configurado.</strong></p>
                <p>Para usar o sistema de importação, é necessário:</p>
                <ul>
                    <li>✅ Configurar webhook no Bitrix24 para o cliente</li>
                    <li>✅ Passar parâmetro <code>?cliente=CHAVE_ACESSO</code> na URL</li>
                </ul>
                <p><strong>Erro:</strong> <?php echo htmlspecialchars($erro_configuracao ?? 'Configuração não encontrada'); ?></p>
                <a href="/Apps/importar/demo<?php echo isset($_GET['cliente']) ? '?cliente=' . urlencode($_GET['cliente']) : ''; ?>" class="back-btn">🧪 Ir para Demo</a>
                <a href="/Apps/importar/importacao<?php echo isset($_GET['cliente']) ? '?cliente=' . urlencode($_GET['cliente']) : ''; ?>" class="back-btn">← Voltar</a>
            </div>
        </div>
    <?php elseif ($colunas && count($colunas) > 0 && $colunas[0] !== null && $colunas[0] !== ''): ?>
        <form id="mapeamentoForm" class="import-form">
            <div class="import-form-title">Mapeamento de Campos</div>
            <p style="margin-bottom: 18px; color: #444; font-size: 1rem;">Associe cada coluna do arquivo a um campo do Bitrix (Negócios):</p>
            <?php
            foreach ($colunas as $col) {
                echo '<div>';
                echo '<label>' . htmlspecialchars($col) . ':</label>';
                echo '<select name="map[' . htmlspecialchars($col) . ']" required><option value="">Selecione...</option>';
                
                if ($camposBitrix && is_array($camposBitrix)) {
                    $temMatch = false;
                    foreach ($camposBitrix as $campoId => $campoInfo) {
                        $nome = $campoInfo['title'] ?? $campoId;
                        $selected = '';
                        
                        // Auto-matching apenas por nome 100% igual (case insensitive)
                        if (!$temMatch && strcasecmp(trim($col), trim($nome)) === 0) {
                            $selected = ' selected';
                            $temMatch = true;
                        }
                        
                        echo '<option value="' . htmlspecialchars($campoId) . '"' . $selected . '>' . htmlspecialchars($nome) . '</option>';
                    }
                }
                echo '</select>';
                echo '</div>';
            }
            ?>
            <button type="submit">Continuar</button>
        </form>
    <?php else: ?>
        <div class="import-form">
            <div class="import-form-title">Erro</div>
            <p>Não foi possível ler o arquivo CSV ou o arquivo está vazio.</p>
            <a href="/Apps/importar/importacao<?php echo isset($_GET['cliente']) ? '?cliente=' . urlencode($_GET['cliente']) : ''; ?>">Voltar</a>
        </div>
    <?php endif; ?>

    <?php if ($php_warnings): ?>
        <script>
            console.warn('PHP Warnings:', <?php echo json_encode($php_warnings); ?>);
        </script>
    <?php endif; ?>
</body>
</html>
