<?php
require_once __DIR__ . '/WebhookHelper.php';

$chaveAcesso = $_GET['cliente'] ?? 'gnappC93fLq7RxKZVp28HswuAYMe1';
$slugAplicacao = 'import';

echo "<h2>🔍 Debug Config.php</h2>";
echo "Cliente: " . htmlspecialchars($chaveAcesso) . "<br>";
echo "Slug: " . htmlspecialchars($slugAplicacao) . "<br><br>";

// Tenta obter webhook do banco de dados primeiro
$bitrixWebhook = null;
if ($chaveAcesso) {
    echo "<strong>1. Tentando obter webhook do banco...</strong><br>";
    $webhookHelper = new WebhookHelper();
    $bitrixWebhook = $webhookHelper->obterWebhookBitrix($chaveAcesso, $slugAplicacao);
    
    if ($bitrixWebhook) {
        echo "✅ Webhook encontrado: " . htmlspecialchars(substr($bitrixWebhook, 0, 60)) . "...<br>";
    } else {
        echo "❌ Webhook não encontrado<br>";
    }
}

echo "<br><strong>2. Validando webhook...</strong><br>";
$webhookValido = WebhookHelper::validarWebhook($bitrixWebhook);
echo "Webhook válido: " . ($webhookValido ? "✅ SIM" : "❌ NÃO") . "<br>";

if ($bitrixWebhook) {
    echo "URL válida: " . (filter_var($bitrixWebhook, FILTER_VALIDATE_URL) !== false ? "✅ SIM" : "❌ NÃO") . "<br>";
    echo "Contém 'bitrix24': " . (strpos($bitrixWebhook, 'bitrix24') !== false ? "✅ SIM" : "❌ NÃO") . "<br>";
}

echo "<br><strong>3. Definindo globais...</strong><br>";
if ($bitrixWebhook && $webhookValido) {
    define('BITRIX_WEBHOOK', $bitrixWebhook);
    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $bitrixWebhook;
    echo "✅ Constante e global definidas<br>";
} else {
    echo "❌ Constante e global NÃO definidas<br>";
}

echo "<br><strong>4. Verificando se estão definidas:</strong><br>";
echo "BITRIX_WEBHOOK: " . (defined('BITRIX_WEBHOOK') ? "✅ DEFINIDA" : "❌ NÃO DEFINIDA") . "<br>";
echo "Global webhook: " . (isset($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) ? "✅ DEFINIDA" : "❌ NÃO DEFINIDA") . "<br>";

if (isset($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'])) {
    echo "Valor: " . htmlspecialchars(substr($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'], 0, 60)) . "...<br>";
}
?>
