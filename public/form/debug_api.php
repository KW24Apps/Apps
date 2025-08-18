<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Debug API Bitrix Users</h2>";

$q = $_GET['q'] ?? 'test';
$cliente = $_GET['cliente'] ?? 'gnappC93fLq7RxKZVp28HswuAYMe1';

echo "Query: " . htmlspecialchars($q) . "<br>";
echo "Cliente: " . htmlspecialchars($cliente) . "<br><br>";

try {
    echo "<strong>1. Testando conexão com banco...</strong><br>";
    $config = [
        'host' => 'localhost',
        'dbname' => 'kw24co49_api_kwconfig',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];
    
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
        $config['usuario'],
        $config['senha']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexão OK<br><br>";
    
    echo "<strong>2. Buscando webhook...</strong><br>";
    $stmt = $pdo->prepare("
        SELECT aa.url_webhook
        FROM aplicacao_acesso aa
        JOIN aplicacoes a ON aa.aplicacao_id = a.id
        JOIN clientes c ON a.cliente_id = c.id
        WHERE c.chave_acesso = ? AND a.slug = 'import'
    ");
    $stmt->execute([$cliente]);
    $webhook = $stmt->fetchColumn();
    
    if (!$webhook) {
        echo "❌ Webhook não encontrado<br>";
        exit;
    }
    
    echo "✅ Webhook: " . htmlspecialchars(substr($webhook, 0, 60)) . "...<br><br>";
    
    echo "<strong>3. Testando API Bitrix...</strong><br>";
    $url = rtrim($webhook, '/') . '/user.get.json';
    echo "URL: " . htmlspecialchars($url) . "<br>";
    
    $postData = http_build_query([
        'ACTIVE' => 'Y',
        'ORDER' => ['ID' => 'ASC'],
        'SELECT' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL']
    ]);
    
    echo "POST Data: " . htmlspecialchars($postData) . "<br>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode<br>";
    echo "cURL Error: " . htmlspecialchars($curlError) . "<br>";
    echo "Response length: " . strlen($response) . " bytes<br>";
    
    if ($curlError) {
        echo "❌ Erro cURL: " . htmlspecialchars($curlError) . "<br>";
        exit;
    }
    
    if ($httpCode !== 200) {
        echo "❌ Erro HTTP: $httpCode<br>";
        echo "Response: " . htmlspecialchars(substr($response, 0, 500)) . "<br>";
        exit;
    }
    
    echo "<strong>4. Analisando resposta JSON...</strong><br>";
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Erro JSON: " . json_last_error_msg() . "<br>";
        echo "Response: " . htmlspecialchars(substr($response, 0, 500)) . "<br>";
        exit;
    }
    
    if (isset($data['error'])) {
        echo "❌ Erro Bitrix: " . htmlspecialchars($data['error_description'] ?? 'Erro desconhecido') . "<br>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        exit;
    }
    
    if (!isset($data['result']) || !is_array($data['result'])) {
        echo "❌ Resposta inválida<br>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        exit;
    }
    
    echo "✅ " . count($data['result']) . " usuários encontrados<br>";
    
    $usuarios = [];
    $count = 0;
    foreach ($data['result'] as $user) {
        $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
        if ($nome && ($q === '' || stripos($nome, $q) !== false)) {
            $usuarios[] = [
                'id' => $user['ID'],
                'name' => $nome
            ];
            $count++;
            if ($count >= 5) break; // Limita para debug
        }
    }
    
    echo "<strong>5. Usuários filtrados:</strong><br>";
    echo "<pre>";
    print_r($usuarios);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "❌ Exception: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
}
?>
