<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$q = $_GET['q'] ?? '';
$cliente = $_GET['cliente'] ?? 'gnappC93fLq7RxKZVp28HswuAYMe1';

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
    
    // Busca usuários do Bitrix via API direta com paginação
    $url = rtrim($webhook, '/') . '/user.get.json';
    $allUsers = [];
    $start = 0;
    $limit = 50; // Bitrix limita a 50 por request
    
    do {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'ACTIVE' => 'Y',
            'ORDER' => ['ID' => 'ASC'],
            'SELECT' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL'],
            'START' => $start
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
        
        // Adiciona usuários desta página
        $allUsers = array_merge($allUsers, $data['result']);
        
        // Prepara para próxima página
        $start += $limit;
        $hasMore = isset($data['next']) && $data['next'] > 0;
        
    } while ($hasMore && $start < 500); // Limita a 500 usuários para performance
    
    // Filtra e formata usuários
    $usuarios = [];
    $count = 0;
    foreach ($allUsers as $user) {
        $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
        if ($nome && ($q === '' || stripos($nome, $q) !== false)) {
            $usuarios[] = [
                'id' => $user['ID'],
                'name' => $nome
            ];
            $count++;
            // Limita a 50 resultados para performance no frontend
            if ($count >= 50) {
                break;
            }
        }
    }
    
    // Adiciona informação de debug
    $response = [
        'users' => $usuarios,
        'total_found' => count($usuarios),
        'total_searched' => count($allUsers),
        'query' => $q
    ];
    
    echo json_encode($usuarios); // Retorna apenas os usuários para compatibilidade
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro interno',
        'detalhes' => $e->getMessage(),
        'cliente' => $cliente
    ]);
}
?>
