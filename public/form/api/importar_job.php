<?php
try {
    // Conecta diretamente no banco para buscar webhook (igual aos outros arquivos)
    $cliente = $_GET['cliente'] ?? $_POST['cliente'] ?? 'gnappC93fLq7RxKZVp28HswuAYMe1';
    
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
        throw new Exception('Webhook não encontrado para o cliente: ' . $cliente);
    }
    
    // Define variável global para o BitrixDealHelper
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
