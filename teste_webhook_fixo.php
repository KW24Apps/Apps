<?php
// Teste direto da API batch do Bitrix com webhook fixo

// Define o webhook diretamente
$GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = 'https://gnapp.bitrix24.com.br/rest/21/g321gnxcrxnx4ing/';

require_once __DIR__ . '/helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

$commands = [
    'deal_1' => [
        'method' => 'crm.item.add',
        'params' => [
            'entityTypeId' => 1092,
            'fields' => [
                'title' => 'Teste API Direto - ' . date('Y-m-d H:i:s'),
                'categoryId' => 195,
                'opportunity' => 1000.50,
                'currencyId' => 'BRL'
            ]
        ]
    ]
];

echo '🔗 WEBHOOK: ' . $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] . PHP_EOL;
echo '📊 DADOS: EntityType=1092, Category=195' . PHP_EOL;
echo 'Enviando para API batch...' . PHP_EOL;

$resultado = BitrixHelper::chamarApi('batch', ['cmd' => $commands]);

echo PHP_EOL . 'RESULTADO COMPLETO:' . PHP_EOL;
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if (isset($resultado['result']['result']['deal_1']['item']['id'])) {
    echo PHP_EOL . '✅ SUCESSO! Deal criado com ID: ' . $resultado['result']['result']['deal_1']['item']['id'] . PHP_EOL;
} else {
    echo PHP_EOL . '❌ ERRO! Sem ID retornado.' . PHP_EOL;
    if (isset($resultado['result']['result_error'])) {
        echo 'Erros da API:' . PHP_EOL;
        print_r($resultado['result']['result_error']);
    }
    if (isset($resultado['error'])) {
        echo 'Erro sistema: ' . $resultado['error'] . PHP_EOL;
    }
}
