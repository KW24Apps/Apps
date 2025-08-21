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

// Links dos funis para a página de sucesso
// FORMATO: 'entityTypeId_categoryId_type' => 'URL do Funil no Bitrix'
$LINKS_FUNIS = [
    '2_43_deal' => 'https://gnapp.bitrix24.com.br/crm/deal/kanban/category/43',
    '2_53_deal' => 'https://gnapp.bitrix24.com.br/crm/deal/kanban/category/53',
    '2_65_deal' => 'https://gnapp.bitrix24.com.br/crm/deal/kanban/category/65',
    '2_73_deal' => 'https://gnapp.bitrix24.com.br/crm/deal/kanban/category/73',
    '2_75_deal' => 'https://gnapp.bitrix24.com.br/crm/deal/kanban/category/75',
    '2_69_deal' => 'https://gnapp.bitrix24.com.br/crm/deal/kanban/category/69',
    '147_226_spa' => 'https://gnapp.bitrix24.com.br/crm/type/147/kanban/category/226'
];

// Configurações de upload
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['.csv', '.xlsx']);

// Configurações de processamento
define('BATCH_SIZE', 25); // Tamanho do lote para processamento

// Retorna configurações (webhook vem do sistema principal)
return [
    'funis' => $FUNIS_DISPONIVEIS,
    'links_funis' => $LINKS_FUNIS,
    'upload' => [
        'max_size' => UPLOAD_MAX_SIZE,
        'extensions' => ALLOWED_EXTENSIONS
    ],
    'batch_size' => BATCH_SIZE
];
