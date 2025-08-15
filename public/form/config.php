<?php
// config.php - Configurações do formulário de importação

require_once __DIR__ . '/WebhookHelper.php';

// Tenta obter webhook do banco de dados primeiro
$webhookHelper = new WebhookHelper();
$bitrixWebhook = $webhookHelper->obterWebhookBitrix();

// Se não conseguiu obter do banco, usa fallback do arquivo local
if (!$bitrixWebhook && file_exists(__DIR__ . '/config_secure.php')) {
    $webhook_config = require_once __DIR__ . '/config_secure.php';
    $bitrixWebhook = $webhook_config['bitrix_webhook'] ?? null;
}

// Valida se o webhook é válido
$webhookValido = WebhookHelper::validarWebhook($bitrixWebhook);

// Se não é válido, define como null mas não impede o carregamento da página
if (!$webhookValido) {
    if (getenv('APP_ENV') === 'development') {
        error_log("WARNING: Webhook do Bitrix não configurado corretamente");
    } else {
        error_log("ERROR: Webhook do Bitrix não configurado para cliente: " . ($_GET['cliente'] ?? 'não informado'));
    }
    $bitrixWebhook = null;
}

// Define a constante apenas se webhook foi encontrado e é válido
if ($bitrixWebhook && $webhookValido) {
    define('BITRIX_WEBHOOK', $bitrixWebhook);
}

// Funis disponíveis
$FUNIS_DISPONIVEIS = [
    '84' => 'Postagens e Avisos',
    '208' => 'KW24',
    // Adicione mais funis conforme necessário
];

// Configurações de upload
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['.csv', '.xlsx']);

// Configurações de processamento
define('BATCH_SIZE', 25); // Tamanho do lote para processamento

return [
    'bitrix_webhook' => $bitrixWebhook ?? null,
    'funis' => $FUNIS_DISPONIVEIS,
    'upload' => [
        'max_size' => UPLOAD_MAX_SIZE,
        'extensions' => ALLOWED_EXTENSIONS
    ],
    'batch_size' => BATCH_SIZE
];
