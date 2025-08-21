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

// Verifica se tem dados da importação na sessão
$dadosImportacao = $_SESSION['importacao_form'] ?? null;
if (!$dadosImportacao || !isset($dadosImportacao['funil'])) {
    die('<div style="font-family: Arial; text-align: center; margin-top: 50px;">
         <h2>❌ Dados de importação não encontrados</h2>
         <p>Por favor, volte para a tela de importação e selecione um funil.</p>
         <a href="/Apps/public/form/importacao.php?cliente=' . urlencode($cliente) . '">← Voltar para Importação</a>
         </div>');
}

// Extrai dados do funil selecionado
$funilSelecionado = $dadosImportacao['funil'];
$dadosFunil = explode('_', $funilSelecionado);
$entityTypeId = $dadosFunil[0] ?? 2;
$categoryId = $dadosFunil[1] ?? null;
$tipo = $dadosFunil[2] ?? 'deal';

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
    // O webhook já foi configurado anteriormente, busca campos específicos do funil selecionado
    error_log("Buscando campos para entityTypeId: $entityTypeId, tipo: $tipo");
    
    // Para SPAs (Smart Process Automation) usa entityTypeId específico
    $camposBitrix = BitrixHelper::consultarCamposCrm($entityTypeId);
    $webhook_configurado = true;
    
    error_log("Campos encontrados: " . json_encode(array_keys($camposBitrix ?? [])));
    
    // Se não conseguiu buscar campos, usa campos padrão baseado no tipo
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
        
        <!-- Loading Screen -->
        <div id="loadingScreen" class="import-form">
            <div class="import-form-title">Mapeamento de Campos - <?php echo $tipo === 'spa' ? 'SPA' : 'Negócios'; ?></div>
            <div class="loading-container">
                <div class="loading-spinner"></div>
                <div class="loading-text">Carregando campos do Bitrix...</div>
                <div class="loading-subtitle">Buscando campos disponíveis para <?php echo $tipo === 'spa' ? 'Smart Process (SPA)' : 'Negócios tradicionais'; ?><br>EntityTypeId: <?php echo $entityTypeId; ?><?php echo $categoryId ? " | Categoria: $categoryId" : ''; ?></div>
            </div>
        </div>

        <!-- Conteúdo Principal (inicialmente oculto) -->
        <form id="mapeamentoForm" class="import-form content-hidden" method="POST" action="/Apps/public/form/api/salvar_mapeamento.php">
            <?php echo isset($_GET['cliente']) ? '<input type="hidden" name="cliente" value="' . htmlspecialchars($_GET['cliente']) . '">' : ''; ?>
            <input type="hidden" name="entityTypeId" value="<?php echo htmlspecialchars($entityTypeId); ?>">
            <input type="hidden" name="categoryId" value="<?php echo htmlspecialchars($categoryId); ?>">
            <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">
            <input type="hidden" name="funil" value="<?php echo htmlspecialchars($funilSelecionado); ?>">
            <div class="import-form-title">Mapeamento de Campos - <?php echo $tipo === 'spa' ? 'SPA' : 'Negócios'; ?></div>
            <p>Associe cada coluna do arquivo a um campo do Bitrix (<?php echo $tipo === 'spa' ? 'Smart Process - EntityTypeId: ' . $entityTypeId : 'Negócios tradicionais'; ?>):</p>
            
            <div class="campos-container">
                <?php
                foreach ($colunas as $col) {
                    echo '<div class="campo-grupo">';
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
            </div>
            
            <div class="form-actions">
                <a href="/Apps/public/form/importacao.php?cliente=<?php echo urlencode($cliente); ?>" class="btn-secondary">← Voltar</a>
                <button type="submit" class="btn-primary">Continuar</button>
            </div>
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

    <script>
        // Simula o carregamento dos campos do Bitrix
        document.addEventListener('DOMContentLoaded', function() {
            const loadingScreen = document.getElementById('loadingScreen');
            const mapeamentoForm = document.getElementById('mapeamentoForm');
            
            // Simula um delay de carregamento (1.5 segundos)
            setTimeout(function() {
                if (loadingScreen) {
                    loadingScreen.style.display = 'none';
                }
                if (mapeamentoForm) {
                    mapeamentoForm.classList.remove('content-hidden');
                    mapeamentoForm.classList.add('fade-in');
                }
            }, 1500);
        });
    </script>
</body>
</html>
