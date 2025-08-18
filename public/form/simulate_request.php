<?php
// simulate_request.php - Simula exatamente a requisição do JavaScript
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🎭 Simulação da Requisição JavaScript</h2>";

$cliente = $_GET['cliente'] ?? 'gnappC93jLq7RxKZVp28HswuAYMe1';
$q = $_GET['q'] ?? 'test';

echo "<strong>Parâmetros:</strong><br>";
echo "Cliente: " . htmlspecialchars($cliente) . "<br>";
echo "Query: " . htmlspecialchars($q) . "<br><br>";

// Simula o mesmo comportamento do fetch do JavaScript
echo "<h3>🔄 Simulando requisição para bitrix_users.php</h3>";

// Preparar ambiente como se fosse uma requisição real
$_GET = ['cliente' => $cliente, 'q' => $q];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = "/Apps/importar/api/bitrix_users?q=" . urlencode($q) . "&cliente=" . urlencode($cliente);

echo "URL simulada: " . htmlspecialchars($_SERVER['REQUEST_URI']) . "<br><br>";

// Capturar toda a saída
ob_start();

// Headers que seriam enviados
$headers = [];
$originalHeaderFunction = null;

// Substituir temporariamente a função header para capturar
if (!function_exists('header_override')) {
    function header_override($string, $replace = true, $http_response_code = null) {
        global $headers;
        $headers[] = $string;
        if ($http_response_code !== null) {
            http_response_code($http_response_code);
        }
    }
}

// Executar o código da API
try {
    // Definir que estamos em modo de debug
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    echo "Executando API...<br>";
    include __DIR__ . '/api/bitrix_users.php';
    
} catch (Exception $e) {
    echo "❌ Exception: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Stack trace:<br><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "❌ Fatal Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . htmlspecialchars($e->getFile()) . " Line: " . $e->getLine() . "<br>";
    echo "Stack trace:<br><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (ParseError $e) {
    echo "❌ Parse Error: " . htmlspecialchars($e->getMessage()) . "<br>";
}

$apiOutput = ob_get_clean();

echo "<h3>📊 Resultado da Simulação</h3>";
echo "<strong>Headers capturados:</strong><br>";
if (!empty($headers)) {
    foreach ($headers as $header) {
        echo "• " . htmlspecialchars($header) . "<br>";
    }
} else {
    echo "Nenhum header capturado<br>";
}

echo "<br><strong>Saída da API:</strong><br>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo htmlspecialchars($apiOutput);
echo "</pre>";

// Tentar decodificar como JSON
if (!empty($apiOutput)) {
    $json = json_decode($apiOutput, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<br><strong>JSON Decodificado:</strong><br>";
        echo "<pre style='background: #e8f5e8; padding: 10px; border: 1px solid #28a745;'>";
        print_r($json);
        echo "</pre>";
    } else {
        echo "<br><strong>❌ Erro JSON:</strong> " . json_last_error_msg() . "<br>";
    }
}

$lastError = error_get_last();
if ($lastError) {
    echo "<br><strong>⚠️ Último erro PHP:</strong><br>";
    echo "<pre style='background: #ffe6e6; padding: 10px; border: 1px solid #dc3545;'>";
    print_r($lastError);
    echo "</pre>";
}
?>
