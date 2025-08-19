<?php
// config.php - Configurações específicas do formulário de importação
// NOTA: Webhook vem do sistema principal, aqui apenas configurações de funis

// Funis disponíveis para importação  
// FORMATO: 'entityTypeId_categoryId_type' => 'Nome Exibido'
$FUNIS_DISPONIVEIS = [
    // DEALS TRADICIONAIS (entityTypeId = 2)
    '2_43_deal' => 'Pré venda - Nimbus Tax',
    '2_53_deal' => 'Comercial Nimbus Tax', 
    '2_65_deal' => 'Pré venda Contabilidade',
    '2_73_deal' => 'Pré venda Certificados',
    '2_75_deal' => 'Pré venda KW24',
    '2_69_deal' => 'Pré venda PDV Sys',
    
    // SPAs CUSTOMIZADAS
    '147_226_spa' => 'Espaço AMI - Envio de WhatsApp'
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
