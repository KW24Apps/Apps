<?php
// Verifica se cliente foi informado
$cliente = $_GET['cliente'] ?? $_POST['cliente'] ?? null;
if (!$cliente) {
    header('Content-Type: application/json');
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Parâmetro cliente é obrigatório'
    ]);
    exit;
}

try {
    // Conecta diretamente ao banco para buscar webhook
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

    $sql = "
        SELECT ca.webhook_bitrix
        FROM clientes c
        JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id
        JOIN aplicacoes a ON ca.aplicacao_id = a.id
        WHERE c.chave_acesso = :chave
        AND a.slug = 'import'
        AND ca.ativo = 1
        AND ca.webhook_bitrix IS NOT NULL
        AND ca.webhook_bitrix != ''
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':chave', $cliente);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $webhook = $resultado['webhook_bitrix'] ?? null;

    if (!$webhook) {
        throw new Exception('Webhook não encontrado para o cliente: ' . $cliente);
    }

    // Define globalmente para uso nos helpers
    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'erro' => 'Configuração inválida',
        'detalhes' => $e->getMessage()
    ]);
    exit;
}

require_once __DIR__ . '/../../../helpers/BitrixDealHelper.php';

use Helpers\BitrixDealHelper;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$entityId = $input['entityId'] ?? null;
$categoryId = $input['categoryId'] ?? null;
$deals = $input['deals'] ?? [];
$tipoJob = $input['tipoJob'] ?? 'criar_deals';

if (!$entityId || !$categoryId || !is_array($deals) || count($deals) === 0) {
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Parâmetros insuficientes. Necessário: entityId, categoryId, deals e tipoJob'
    ]);
    exit;
}

try {
    // Usa a função do BitrixDealHelper para criar job na fila
    $resultado = BitrixDealHelper::criarJobParaFila($entityId, $categoryId, $deals, $tipoJob);
    
    if ($resultado['status'] === 'job_criado') {
        echo json_encode([
            'sucesso' => true,
            'job_id' => $resultado['job_id'],
            'total_deals' => $resultado['total_deals'],
            'tipo_job' => $resultado['tipo_job'],
            'mensagem' => $resultado['mensagem'],
            'consultar_status' => $resultado['consultar_status']
        ]);
    } else {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => $resultado['mensagem'] ?? 'Erro desconhecido ao criar job'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao processar solicitação: ' . $e->getMessage()
    ]);
}
