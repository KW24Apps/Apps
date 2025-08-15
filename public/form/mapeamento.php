<?php
// Captura warnings para exibir no console JS
ob_start();

// Caminho do arquivo CSV salvo na etapa anterior
$uploadDir = __DIR__ . '/../uploads/';
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
    // Carrega configurações
    $config = require_once __DIR__ . '/config.php';
    
    // Verifica se o webhook foi carregado
    if (!defined('BITRIX_WEBHOOK') || !BITRIX_WEBHOOK) {
        throw new Exception('Webhook do Bitrix não configurado para este cliente/aplicação');
    }
    
    // Define o webhook a partir da configuração
    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = BITRIX_WEBHOOK;
    $camposBitrix = BitrixHelper::consultarCamposCrm(2); // 2 = Negócios
    $webhook_configurado = true;
    
} catch (Exception $e) {
    $webhook_configurado = false;
    $erro_configuracao = $e->getMessage();
    $camposBitrix = [];
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
                        
                        // Auto-matching por similaridade de nome
                        if (!$temMatch && (
                            stripos($nome, $col) !== false || 
                            stripos($col, $nome) !== false ||
                            levenshtein(strtolower($col), strtolower($nome)) <= 3
                        )) {
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
