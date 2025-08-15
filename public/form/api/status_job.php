<?php
require_once __DIR__ . '/../../../dao/BatchJobDAO.php';

use dao\BatchJobDAO;

header('Content-Type: application/json');

$jobId = $_GET['job_id'] ?? '';

if (empty($jobId)) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'job_id é obrigatório'
    ]);
    exit;
}

try {
    // Como não temos o método buscarJobPorId, vamos fazer a consulta direta
    $config = require __DIR__ . '/../../../config/configdashboard.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['usuario'],
        $config['senha'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $sql = "SELECT * FROM batch_jobs WHERE job_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Job não encontrado'
        ]);
        exit;
    }
    
    echo json_encode([
        'sucesso' => true,
        'job' => [
            'id' => $job['job_id'],
            'status' => $job['status'],
            'tipo' => $job['tipo'],
            'total_items' => $job['total_items'],
            'items_processados' => $job['items_processados'] ?? 0,
            'criado_em' => $job['criado_em'],
            'atualizado_em' => $job['atualizado_em'] ?? null,
            'resultado' => $job['resultado'] ? json_decode($job['resultado'], true) : null
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao consultar status: ' . $e->getMessage()
    ]);
}
