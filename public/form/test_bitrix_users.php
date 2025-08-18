<?php
// test_bitrix_users.php - Teste direto da API do Bitrix
header('Content-Type: text/html; charset=utf-8');

echo "<h2>👥 Teste Busca Usuários Bitrix</h2>";

// Primeiro busca o webhook
$cliente = $_GET['cliente'] ?? 'gnappC93fLq7RxKZVp28HswuAYMe1';

try {
    // Conexão com banco
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
    
    // Busca webhook
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
        echo "❌ Webhook não encontrado para o cliente<br>";
        exit;
    }
    
    echo "✅ Webhook encontrado: " . htmlspecialchars(substr($webhook, 0, 60)) . "...<br><br>";
    
    // Testa API do Bitrix
    echo "<strong>🚀 Testando API do Bitrix...</strong><br>";
    
    $url = rtrim($webhook, '/') . '/user.get.json';
    echo "URL: " . htmlspecialchars($url) . "<br>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'KW24-Import-System/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode<br>";
    
    if ($curlError) {
        echo "❌ Erro cURL: " . htmlspecialchars($curlError) . "<br>";
        exit;
    }
    
    if ($httpCode !== 200) {
        echo "❌ Erro HTTP: $httpCode<br>";
        echo "Resposta: " . htmlspecialchars(substr($response, 0, 500)) . "<br>";
        exit;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Erro JSON: " . json_last_error_msg() . "<br>";
        echo "Resposta: " . htmlspecialchars(substr($response, 0, 500)) . "<br>";
        exit;
    }
    
    echo "✅ Resposta JSON válida<br>";
    
    if (isset($data['error'])) {
        echo "❌ Erro da API: " . htmlspecialchars($data['error_description'] ?? 'Erro desconhecido') . "<br>";
        echo "<pre style='background: #ffe8e8; padding: 10px;'>";
        print_r($data);
        echo "</pre>";
        exit;
    }
    
    if (!isset($data['result']) || !is_array($data['result'])) {
        echo "❌ Estrutura inesperada da resposta<br>";
        echo "<pre style='background: #ffe8e8; padding: 10px;'>";
        print_r($data);
        echo "</pre>";
        exit;
    }
    
    $users = $data['result'];
    echo "<strong style='color: green;'>🎉 " . count($users) . " USUÁRIOS ENCONTRADOS!</strong><br><br>";
    
    echo "<strong>👤 Primeiros 5 usuários:</strong><br>";
    foreach (array_slice($users, 0, 5) as $user) {
        $name = $user['NAME'] ?? 'Sem nome';
        $lastname = $user['LAST_NAME'] ?? '';
        $fullName = trim("$name $lastname");
        $id = $user['ID'] ?? 'Sem ID';
        
        echo "• ID: $id - " . htmlspecialchars($fullName) . "<br>";
    }
    
    // Formata resposta para JavaScript
    echo "<br><strong>📋 Resposta formatada para JavaScript:</strong><br>";
    $formattedUsers = [];
    foreach ($users as $user) {
        $name = $user['NAME'] ?? 'Sem nome';
        $lastname = $user['LAST_NAME'] ?? '';
        $fullName = trim("$name $lastname");
        
        if (!empty($fullName) && $fullName !== 'Sem nome') {
            $formattedUsers[] = [
                'id' => $user['ID'] ?? '',
                'name' => $fullName
            ];
        }
    }
    
    echo "<pre style='background: #e8f5e8; padding: 10px; max-height: 300px; overflow-y: auto;'>";
    echo json_encode($formattedUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "❌ Erro: " . htmlspecialchars($e->getMessage()) . "<br>";
}
?>
