<?php
// debug_deal_creation.php - Debug da criação de deals
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🐛 Debug da Criação de Deals</h2>";

// Simula os dados do job que falhou
$dadosJob = [
    "spa" => "84",
    "category_id" => "84", 
    "deals" => [
        [
            "CNPJ/CPF" => "35.047.633/0001-30",
            "Pessoa de Contato" => "",
            "E-amail da Empresa" => "contdalpiva@outlook.com",
            "WhatsApp da Empresa" => "49988536887"
        ]
    ],
    "webhook" => "https://gnapp.bitrix24.com.br/rest/4743/2qfr9cuwpphtrgrm/"
];

echo "<h3>📋 Dados originais do job:</h3>";
echo "<pre>" . json_encode($dadosJob, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

// Simula o que o BitrixHelper::formatarCampos faria
require_once __DIR__ . '/../../helpers/BitrixHelper.php';
use Helpers\BitrixHelper;

$dealFields = $dadosJob['deals'][0];
echo "<h3>🔄 Campos antes da formatação:</h3>";
echo "<pre>" . json_encode($dealFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

$formattedFields = BitrixHelper::formatarCampos($dealFields);
echo "<h3>✨ Campos após formatação:</h3>";
echo "<pre>" . json_encode($formattedFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

// Adiciona categoryId
if ($dadosJob['category_id']) {
    $formattedFields['categoryId'] = $dadosJob['category_id'];
}

$params = [
    'entityTypeId' => $dadosJob['spa'],
    'fields' => $formattedFields
];

echo "<h3>🎯 Parâmetros finais para API:</h3>";
echo "<pre>" . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

// Simula a chamada da API
$webhook = $dadosJob['webhook'];
$endpoint = 'crm.item.add';
$url = $webhook . '/' . $endpoint . '.json';

echo "<h3>🌐 URL da API:</h3>";
echo "<code>$url</code><br><br>";

echo "<h3>📤 Fazendo teste da API...</h3>";

// Define o webhook globalmente
$GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;

// Testa a chamada da API
try {
    $resultado = BitrixHelper::chamarApi('crm.item.add', $params, ['log' => true]);
    
    echo "<h3>📥 Resposta da API:</h3>";
    echo "<pre>" . json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
    if (isset($resultado['error'])) {
        echo "<h3>❌ ERRO encontrado:</h3>";
        echo "<strong>Código:</strong> " . ($resultado['error'] ?? 'N/A') . "<br>";
        echo "<strong>Descrição:</strong> " . ($resultado['error_description'] ?? 'N/A') . "<br>";
    }
    
    if (isset($resultado['result'])) {
        echo "<h3>✅ Sucesso!</h3>";
        echo "<strong>ID criado:</strong> " . ($resultado['result']['item']['id'] ?? 'N/A') . "<br>";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Exceção:</h3>";
    echo $e->getMessage();
}
?>
