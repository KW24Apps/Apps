<?php
require_once __DIR__ . '/../../../Repositories/BatchJobDAO.php';

use Repositories\BatchJobDAO;

header('Content-Type: application/json');

$jobIdsParam = $_GET['job_id'] ?? '';

if (empty($jobIdsParam)) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'job_id é obrigatório'
    ]);
    exit;
}

// Transforma a string de IDs separados por vírgula em um array
$jobIds = explode(',', $jobIdsParam);

// Limpa o array para garantir que não haja valores vazios
$jobIds = array_filter(array_map('trim', $jobIds));

if (empty($jobIds)) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Nenhum job_id válido fornecido'
    ]);
    exit;
}

try {
    $config = require __DIR__ . '/../../../config/configdashboard.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['usuario'],
        $config['senha'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Cria os placeholders (?) para a cláusula IN
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    
    // CORREÇÃO: Usa a cláusula IN para buscar múltiplos jobs
    $sql = "SELECT * FROM batch_jobs WHERE job_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($jobIds);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$jobs) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Nenhum job encontrado para os IDs fornecidos'
        ]);
        exit;
    }
    
    // Soma o progresso de todos os jobs encontrados
    $totalProcessado = 0;
    foreach ($jobs as $job) {
        $totalProcessado += $job['items_processados'] ?? 0;
    }
    
    echo json_encode([
        'sucesso' => true,
        'total_processado' => $totalProcessado,
        'jobs_encontrados' => count($jobs)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao consultar status: ' . $e->getMessage()
    ]);
}
