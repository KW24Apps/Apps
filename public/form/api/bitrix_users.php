<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log temporário para debug
ini_set('log_errors', 1);
ini_set('error_log', '../logs/debug_bitrix.log');

header('Content-Type: application/json; charset=utf-8');

// Inclui o helper padrão do sistema para chamadas à API
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

    // Define o webhook na variável global que o BitrixHelper espera
    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;

    // Constrói os parâmetros para a chamada da API via Helper
    $params = [
        'FILTER' => [
            'ACTIVE' => 'Y',
            'LOGIC' => 'OR', // Busca em qualquer um dos campos abaixo
            '%NAME' => $q,      // Contém a busca no nome
            '%LAST_NAME' => $q, // Contém a busca no sobrenome
            '%EMAIL' => $q,     // Contém a busca no email
        ],
        'ORDER' => ['NAME' => 'ASC'],
        'SELECT' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL']
    ];

    // Chama a API usando o método centralizado e robusto do Helper
    $data = BitrixHelper::chamarApi('user.get', $params);

    // Valida a resposta do Helper
    if (isset($data['error']) || !isset($data['result'])) {
        throw new Exception("Erro da API Bitrix: " . ($data['error_description'] ?? 'Resposta inválida do helper'));
    }

    // Formata os usuários encontrados para o frontend
    $usuarios = [];
    $nomesJaAdicionados = [];
    
    foreach ($data['result'] as $user) {
        $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
        $userId = $user['ID'] ?? '';

        if (!$nome || !$userId) {
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
