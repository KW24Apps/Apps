<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

ini_set('log_errors', 1);
ini_set('error_log', '../logs/debug_bitrix.log');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../helpers/BitrixHelper.php';
use Helpers\BitrixHelper;

// Inicia a sessão para usar o cache
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Parâmetro para forçar a limpeza do cache durante o desenvolvimento/depuração
if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === '1') {
    unset($_SESSION['bitrix_user_cache']);
    error_log("DEBUG: Cache de usuários foi forçadamente limpo.");
}

$q = $_GET['q'] ?? '';
$cliente = $_GET['cliente'] ?? null;

if (!$cliente) {
    http_response_code(400);
    echo json_encode(['erro' => 'Parâmetro cliente é obrigatório']);
    exit;
}

try {
    // --- Função para buscar e cachear todos os usuários ---
    function obter_usuarios_bitrix($cliente) {
        // Verifica se o cache existe e não está expirado (duração de 1 hora)
        if (isset($_SESSION['bitrix_user_cache']) && (time() - $_SESSION['bitrix_user_cache']['timestamp'] < 3600)) {
            error_log("DEBUG: Usando cache de usuários com " . count($_SESSION['bitrix_user_cache']['users']) . " registros.");
            return $_SESSION['bitrix_user_cache']['users'];
        }

        error_log("DEBUG: Cache de usuários vazio ou expirado. Buscando da API...");

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

        $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;

        // Lógica confiável de buscar todos os usuários com paginação
        $allUsers = [];
        $start = 0;
        do {
            $params = [
                'FILTER' => ['ACTIVE' => 'Y'],
                'ORDER' => ['NAME' => 'ASC'],
                'SELECT' => ['ID', 'NAME', 'LAST_NAME'],
                'START' => $start
            ];
            $data = BitrixHelper::chamarApi('user.get', $params);

            if (isset($data['error']) || !isset($data['result'])) {
                throw new Exception("Erro da API Bitrix: " . ($data['error_description'] ?? 'Resposta inválida'));
            }

            $pageUsers = $data['result'];
            $allUsers = array_merge($allUsers, $pageUsers);
            $start += 50;
            $hasMore = count($pageUsers) === 50;

        } while ($hasMore && $start < 2000); // Limite de segurança para evitar loops infinitos

        // Salva a lista completa no cache da sessão
        $_SESSION['bitrix_user_cache'] = [
            'users' => $allUsers,
            'timestamp' => time()
        ];
        
        error_log("DEBUG: Cache de usuários criado com " . count($allUsers) . " usuários.");
        return $allUsers;
    }

    // --- Lógica Principal ---
    $todos_usuarios = obter_usuarios_bitrix($cliente);

    $usuarios_filtrados = [];
    if (!empty($q)) {
        foreach ($todos_usuarios as $user) {
            $nome_completo = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
            if (stripos($nome_completo, $q) !== false) {
                $usuarios_filtrados[] = $user;
            }
        }
    } else {
        $usuarios_filtrados = $todos_usuarios;
    }

    $usuarios = [];
    $nomesJaAdicionados = [];
    foreach ($usuarios_filtrados as $user) {
        $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
        $userId = $user['ID'] ?? '';

        if (!$nome || !$userId) continue;

        $nomeLower = strtolower($nome);
        if (isset($nomesJaAdicionados[$nomeLower])) continue;

        $usuarios[] = ['id' => $userId, 'name' => $nome];
        $nomesJaAdicionados[$nomeLower] = true;
    }

    echo json_encode($usuarios);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro interno',
        'detalhes' => $e->getMessage()
    ]);
}
?>
