<?php
// config.php - Configurações do formulário de importação

require_once __DIR__ . '/WebhookHelper.php';

// Tenta obter webhook do banco de dados primeiro
$bitrixWebhook = WebhookHelper::obterWebhookBitrix();

// Se não conseguiu obter do banco, usa fallback do arquivo local
if (!$bitrixWebhook && file_exists(__DIR__ . '/config_secure.php')) {
    $webhook_config = require_once __DIR__ . '/config_secure.php';
    $bitrixWebhook = $webhook_config['bitrix_webhook'] ?? null;
}

// Valida se o webhook é válido
if (!WebhookHelper::validarWebhook($bitrixWebhook)) {
    // Em produção, lança erro. Em desenvolvimento, permite continuar com warning
    if (getenv('APP_ENV') === 'development') {
        error_log("WARNING: Webhook do Bitrix não configurado corretamente");
        $bitrixWebhook = null;
    } else {
        throw new Exception('Webhook do Bitrix não configurado. Verifique a configuração do cliente/aplicação.');
    }
}

// Define a constante apenas se webhook foi encontrado
if ($bitrixWebhook) {
    define('BITRIX_WEBHOOK', $bitrixWebhook);
}

// Funis disponíveis
$FUNIS_DISPONIVEIS = [
    '2' => 'Negócios',
    '84' => 'Postagens e Avisos',
    // Adicione mais funis conforme necessário
];

// Configurações de upload
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['.csv', '.xlsx']);

// Configurações de processamento
define('BATCH_SIZE', 25); // Tamanho do lote para processamento

return [
    'bitrix_webhook' => BITRIX_WEBHOOK,
    'funis' => $FUNIS_DISPONIVEIS,
    'upload' => [
        'max_size' => UPLOAD_MAX_SIZE,
        'extensions' => ALLOWED_EXTENSIONS
    ],
    'batch_size' => BATCH_SIZE
];
