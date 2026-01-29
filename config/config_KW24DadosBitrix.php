<?php

$ambiente = getenv('APP_ENV') ?: 'local';

$config_sync_bitrix = [];

if ($ambiente === 'local') {
    $config_sync_bitrix = [
        'host' => 'localhost',
        'dbname' => 'kw24_sync_bitrix',
        'usuario' => 'root',
        'senha' => ''
    ];
} else {
    $config_sync_bitrix = [
        'host' => '191.252.194.129',
        'dbname' => 'kw24co49_kw24_sync_bitrix',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];
}

// Configurações do sistema de sincronização KW24-Bitrix24
$config_sync_bitrix['sync'] = [
    // Entity IDs do Bitrix24
    'entity_ids' => [
        'cards' => 1054,
        'colaboradores' => 190,
        'colaboradores_category' => 157,
        'companies' => 4,
        'tasks_group' => 37
    ],
    
    // Tipos de entidade para o dicionário
    'entity_types' => [
        'cards' => 'cards',
        'tasks' => 'tasks', 
        'companies' => 'companies',
        'colaboradores' => 'colaboradores'
    ],
    
    // Estruturas de resposta da API
    'api_structures' => [
        'simple' => 'simples',
        'api_result' => 'api_result',
        'api_result_or_simple' => 'api_result_ou_simples'
    ],
    
    // Webhook padrão Bitrix24
    'webhook_bitrix' => 'https://gnapp.bitrix24.com.br/rest/21/g321gnxcrxnx4ing/',
    
    // Mensagens padronizadas
    'messages' => [
        'sync_start' => '=== INICIANDO SINCRONIZAÇÃO COMPLETA ===',
        'sync_success' => '=== SINCRONIZAÇÃO FINALIZADA COM SUCESSO ===',
        'step_1_start' => 'PASSO 1: Consultando dados do Bitrix24...',
        'step_1_success' => 'PASSO 1: Concluído com sucesso',
        'step_2_start' => 'PASSO 2: Criando/atualizando tabela dicionário...',
        'step_2_success' => 'PASSO 2: Concluído - Total campos: %d',
        'step_3_pending' => 'PASSO 3: Criação das tabelas de dados (pendente implementação)',
    ]
];

return $config_sync_bitrix;
