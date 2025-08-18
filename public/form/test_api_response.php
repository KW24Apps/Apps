<?php
// test_api_response.php - Teste direto da API
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🧪 Teste Direto da API bitrix_users</h2>";

$cliente = $_GET['cliente'] ?? 'gnappC93fLq7RxKZVp28HswuAYMe1';
$q = $_GET['q'] ?? 'test';

$url = "https://apis.kw24.com.br/Apps/importar/api/bitrix_users?q=" . urlencode($q) . "&cliente=" . urlencode($cliente);

echo "<strong>URL sendo testada:</strong><br>";
echo "<code>" . htmlspecialchars($url) . "</code><br><br>";

echo "<strong>🔄 Fazendo requisição...</strong><br>";

// Faz a requisição
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (compatible; Test/1.0)'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<strong>📊 Resultado:</strong><br>";
echo "Status HTTP: <span style='color: " . ($httpCode == 200 ? 'green' : 'red') . "'>{$httpCode}</span><br>";

if ($error) {
    echo "Erro cURL: <span style='color: red'>" . htmlspecialchars($error) . "</span><br>";
} else {
    echo "<strong>Resposta Bruta:</strong><br>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    // Tenta decodificar como JSON
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<strong>📝 JSON Decodificado:</strong><br>";
        echo "<pre style='background: #e8f5e8; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
        print_r($json);
        echo "</pre>";
        
        echo "<strong>🔍 Análise do Tipo:</strong><br>";
        echo "• Tipo: " . gettype($json) . "<br>";
        if (is_array($json)) {
            echo "• É array: ✅ SIM<br>";
            echo "• Elementos: " . count($json) . "<br>";
            if (count($json) > 0) {
                echo "• Primeiro elemento: " . gettype($json[0]) . "<br>";
            }
        } else {
            echo "• É array: ❌ NÃO<br>";
            if (is_object($json) || is_array($json)) {
                echo "• Chaves/propriedades: " . implode(', ', array_keys((array)$json)) . "<br>";
            }
        }
    } else {
        echo "<strong>❌ Erro ao decodificar JSON:</strong> " . json_last_error_msg() . "<br>";
    }
}

echo "<br><strong>💡 Teste rápido local:</strong><br>";
echo "<a href='?cliente=" . urlencode($cliente) . "&q=test' style='background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Testar com 'test'</a> ";
echo "<a href='?cliente=" . urlencode($cliente) . "&q=admin' style='background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Testar com 'admin'</a>";
?>
