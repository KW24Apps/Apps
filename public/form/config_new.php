<?php
// config.php - Configurações específicas do formulário de importação
// NOTA: Webhook vem do sistema principal, aqui apenas configurações de funis

// Funis disponíveis para importação
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

// Retorna configurações (webhook vem do sistema principal)
return [
    'funis' => $FUNIS_DISPONIVEIS,
    'upload' => [
        'max_size' => UPLOAD_MAX_SIZE,
        'extensions' => ALLOWED_EXTENSIONS
    ],
    'batch_size' => BATCH_SIZE
];
