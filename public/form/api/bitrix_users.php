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

    // Busca otimizada no Bitrix
    $url = rtrim($webhook, '/') . '/user.get.json';
    $allUsers = [];
    
    // Se há query de busca, faz busca direcionada
    if (!empty($q)) {
        error_log("DEBUG: Fazendo busca direcionada por: " . $q);
        
        // Busca usuários que contenham a query no nome ou sobrenome
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'FILTER' => [
                'ACTIVE' => 'Y'
                // Removendo filtros específicos pois podem não funcionar
            ],
            'ORDER' => ['NAME' => 'ASC'],
            'SELECT' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL']
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['result']) && is_array($data['result'])) {
                $allUsers = array_merge($allUsers, $data['result']);
                error_log("DEBUG: Primeira busca retornou " . count($data['result']) . " usuários");
            }
        }
        
    } else {
        // Se não há query, busca TODOS os usuários com paginação
        error_log("DEBUG: Fazendo busca completa de todos os usuários");
        
        $start = 0;
        $limit = 50;
        
        do {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'FILTER' => ['ACTIVE' => 'Y'],
                'ORDER' => ['NAME' => 'ASC'],
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
                error_log("ERRO cURL: " . $curlError);
                break;
            }
            
            if ($httpCode !== 200) {
                error_log("ERRO HTTP: " . $httpCode . " - " . substr($response, 0, 200));
                break;
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("ERRO JSON: " . json_last_error_msg());
                break;
            }
            
            if (isset($data['error'])) {
                error_log("ERRO Bitrix: " . ($data['error_description'] ?? 'Erro desconhecido'));
                break;
            }
            
            if (!isset($data['result']) || !is_array($data['result'])) {
                error_log("ERRO: Resposta inválida da API Bitrix");
                break;
            }
            
            // Adiciona usuários desta página
            $pageUsers = $data['result'];
            $allUsers = array_merge($allUsers, $pageUsers);
            
            // Prepara para próxima página
            $start += $limit;
            $hasMore = count($pageUsers) === $limit; // Se retornou 50, pode haver mais
            
            // Log de debug da paginação
            error_log("DEBUG: Página start=$start, recebidos=" . count($pageUsers) . ", total=" . count($allUsers) . ", hasMore=" . ($hasMore ? 'sim' : 'não'));
            
        } while ($hasMore && $start < 2000); // Aumenta limite para 2000 usuários
    }
    
    // Log para debug
    error_log("DEBUG: Buscados " . count($allUsers) . " usuários do Bitrix para cliente: " . $cliente);
    
    // Filtra e formata usuários (evitando duplicatas)
    $usuarios = [];
    $nomesJaAdicionados = [];
    
    foreach ($allUsers as $user) {
        $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
        $userId = $user['ID'] ?? '';
        
        // Pula se não tem nome ou ID
        if (!$nome || !$userId) {
            continue;
        }
        
        // Se há query, verifica se o nome corresponde
        if (!empty($q) && stripos($nome, $q) === false) {
            continue;
        }
        
        // Evita duplicatas pelo nome (case insensitive)
        $nomeLower = strtolower($nome);
        if (isset($nomesJaAdicionados[$nomeLower])) {
            continue;
        }
        
        $usuarios[] = [
            'id' => $userId,
            'name' => $nome
        ];
        
        $nomesJaAdicionados[$nomeLower] = true;
    }
    
    // Ordena por nome
    usort($usuarios, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    
    // Log final para debug
    error_log("DEBUG: Retornando " . count($usuarios) . " usuários únicos após filtros para query: '$q'");
    error_log("DEBUG: Primeiros 5 usuários: " . json_encode(array_slice(array_column($usuarios, 'name'), 0, 5)));
    
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
