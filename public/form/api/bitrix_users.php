<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

ini_set('log_errors', 1);
ini_set('error_log', '../logs/debug_bitrix.log');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../helpers/BitrixHelper.php';
use Helpers\BitrixHelper;

$q = $_GET['q'] ?? '';
$cliente = $_GET['cliente'] ?? null;

if (!$cliente) {
    http_response_code(400);
    echo json_encode(['erro' => 'Parâmetro cliente é obrigatório']);
    exit;
}

try {
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

    // --- Diagnóstico com Batch ---
    // Vamos testar várias sintaxes de filtro de uma vez para descobrir a correta.
    $batch_commands = [];

    // Teste 1: Sintaxe com % no nome da chave (user.get)
    $batch_commands['test1_get_percent_key'] = 'user.get?' . http_build_query([
        'FILTER' => ['ACTIVE' => 'Y', 'LOGIC' => 'OR', '%NAME' => $q, '%LAST_NAME' => $q, '%EMAIL' => $q],
        'SELECT' => ['ID', 'NAME', 'LAST_NAME']
    ]);

    // Teste 2: Sintaxe com % no valor (user.get)
    $batch_commands['test2_get_percent_value'] = 'user.get?' . http_build_query([
        'FILTER' => ['ACTIVE' => 'Y', 'LOGIC' => 'OR', 'NAME' => '%' . $q . '%', 'LAST_NAME' => '%' . $q . '%', 'EMAIL' => '%' . $q . '%'],
        'SELECT' => ['ID', 'NAME', 'LAST_NAME']
    ]);

    // Teste 3: Usando o método user.search com o parâmetro FIND
    $batch_commands['test3_search_find'] = 'user.search?' . http_build_query([
        'FILTER' => ['ACTIVE' => 'Y'],
        'FIND' => $q,
        'SELECT' => ['ID', 'NAME', 'LAST_NAME']
    ]);

    $params = ['cmd' => $batch_commands];
    $data = BitrixHelper::chamarApi('batch', $params);

    if (isset($data['error']) || !isset($data['result']['result'])) {
        throw new Exception("Erro na chamada batch da API: " . ($data['error_description'] ?? 'Resposta inválida'));
    }

    $resultados_batch = $data['result']['result'];
    error_log("Resultados do diagnóstico batch para query '$q': " . print_r($resultados_batch, true));

    // --- Consolidação dos Resultados ---
    $usuarios_encontrados = [];
    foreach ($resultados_batch as $key => $resultado_teste) {
        if (is_array($resultado_teste) && !empty($resultado_teste)) {
            foreach ($resultado_teste as $user) {
                $userId = $user['ID'] ?? null;
                if ($userId) {
                    $usuarios_encontrados[$userId] = $user; // Usa ID como chave para evitar duplicatas
                }
            }
        }
    }

    // Formata a saída final
    $usuarios = [];
    foreach ($usuarios_encontrados as $user) {
        $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
        $userId = $user['ID'] ?? '';

        if ($nome && $userId) {
            $usuarios[] = ['id' => $userId, 'name' => $nome];
        }
    }
    
    // Ordena por nome
    usort($usuarios, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    echo json_encode($usuarios);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro interno no diagnóstico',
        'detalhes' => $e->getMessage()
    ]);
}
?>
