<?php
// config.php - Configurações do formulário de importação

// Carrega configurações seguras (webhook)
$webhook_config = require_once __DIR__ . '/config_secure.php';

// Configurações do Bitrix
define('BITRIX_WEBHOOK', $webhook_config['bitrix_webhook']);

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
