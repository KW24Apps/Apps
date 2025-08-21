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

    // Solução Definitiva: Usar o método user.search, que é o correto para busca textual.
    // Simplificamos os parâmetros para garantir que o filtro FIND seja aplicado.
    $params = [
        'FILTER' => ['ACTIVE' => 'Y'],
        'FIND' => $q
    ];

    $data = BitrixHelper::chamarApi('user.search', $params);

    if (isset($data['error']) || !isset($data['result'])) {
        throw new Exception("Erro da API Bitrix: " . ($data['error_description'] ?? 'Resposta inválida do helper'));
    }

    $usuarios = [];
    $nomesJaAdicionados = [];
    
    foreach ($data['result'] as $user) {
        $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
        $userId = $user['ID'] ?? '';

        if (!$nome || !$userId) {
            continue;
        }

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
    
    // Ordena os resultados em PHP, já que o parâmetro ORDER foi removido da chamada
    usort($usuarios, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    echo json_encode($usuarios);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro interno',
        'detalhes' => $e->getMessage()
    ]);
}
?>
