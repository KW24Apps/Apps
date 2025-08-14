<?php
// Teste direto da API batch do Bitrix

// Define o webhook diretamente
$GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = 'https://gnapp.bitrix24.com.br/rest/21/g321gnxcrxnx4ing/';

require_once __DIR__ . '/helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

$commands = [
    'deal_1' => 'crm.item.add?entityTypeId=1092&fields[title]=' . urlencode('Teste API Direto - ' . date('Y-m-d H:i:s')) . '&fields[opportunity]=1000.50&fields[currencyId]=BRL'
];

echo '🔗 WEBHOOK: ' . $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] . PHP_EOL;
echo '📊 DADOS: EntityType=1092, Category=195' . PHP_EOL;
echo '📋 COMANDO: ' . $commands['deal_1'] . PHP_EOL;
echo 'Enviando para API batch...' . PHP_EOL;
$resultado = BitrixHelper::chamarApi('batch', ['cmd' => $commands]);

echo 'RESULTADO COMPLETO:' . PHP_EOL;
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if (isset($resultado['result']['result']['deal_1']['item']['id'])) {
    echo 'SUCESSO! Deal criado com ID: ' . $resultado['result']['result']['deal_1']['item']['id'] . PHP_EOL;
} else {
    echo 'ERRO! Sem ID retornado.' . PHP_EOL;
    if (isset($resultado['result']['result_error'])) {
        echo 'Erros da API:' . PHP_EOL;
        print_r($resultado['result']['result_error']);
    }
}
