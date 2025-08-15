<?php
// Carrega configurações
$config = require_once __DIR__ . '/../config.php';

// Defina o webhook do Bitrix a partir da configuração
$GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $config['bitrix_webhook'];

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
