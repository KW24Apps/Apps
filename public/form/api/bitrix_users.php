<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$q = $_GET['q'] ?? '';
$cliente = $_GET['cliente'] ?? 'gnappC93jLq7RxKZVp28HswuAYMe1';

try {
    // Conecta diretamente no banco para buscar webhook
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
        SELECT ca.webhook_bitrix
        FROM cliente_aplicacoes ca
        JOIN clientes c ON ca.cliente_id = c.id
        JOIN aplicacoes a ON ca.aplicacao_id = a.id
        WHERE c.chave_acesso = ? AND a.slug = 'import'
    ");
    $stmt->execute([$cliente]);
    $webhook = $stmt->fetchColumn();
    
    if (!$webhook) {
        throw new Exception('Webhook não encontrado para o cliente: ' . $cliente);
    }
    
    // Busca usuários do Bitrix via API direta
    $url = rtrim($webhook, '/') . '/user.get.json';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'ACTIVE' => 'Y',
        'ORDER' => ['ID' => 'ASC'],
        'SELECT' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL']
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Erro cURL: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('Erro HTTP: ' . $httpCode . ' - ' . substr($response, 0, 200));
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erro JSON: ' . json_last_error_msg());
    }
    
    if (isset($data['error'])) {
        throw new Exception('Erro Bitrix: ' . ($data['error_description'] ?? 'Erro desconhecido'));
    }
    
    if (!isset($data['result']) || !is_array($data['result'])) {
        throw new Exception('Resposta inválida da API Bitrix');
    }
    
    // Filtra e formata usuários
    $usuarios = [];
    foreach ($data['result'] as $user) {
        $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
        if ($nome && ($q === '' || stripos($nome, $q) !== false)) {
            $usuarios[] = [
                'id' => $user['ID'],
                'name' => $nome
            ];
        }
    }
    
    echo json_encode($usuarios);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro interno',
        'detalhes' => $e->getMessage(),
        'cliente' => $cliente
    ]);
}
?>
