<?php
header('Content-Type: application/json');

try {
    // Conectar ao banco usando configuração específica do dashboard (sempre produção)
    $config = require __DIR__ . '/config.php';
    
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['usuario'],
        $config['senha'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Buscar jobs recentes
    $stmt = $pdo->prepare("SELECT * FROM batch_jobs ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar por status
    $contadores = [];
    foreach (['pendente', 'processando', 'concluido', 'erro'] as $status) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM batch_jobs WHERE status = ?");
        $stmt->execute([$status]);
        $contadores[$status] = $stmt->fetchColumn();
    }
    
    // Verificar última execução (CRON)
    $stmt = $pdo->prepare("SELECT updated_at FROM batch_jobs ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $ultimaExecucao = $stmt->fetchColumn();
    
    $cronAtivo = false;
    $minutosSemExecucao = null;
    
    if ($ultimaExecucao) {
        $agora = new DateTime();
        $ultima = new DateTime($ultimaExecucao);
        $diff = $agora->diff($ultima);
        $minutosSemExecucao = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        $cronAtivo = $minutosSemExecucao <= 3; // Ativo se executou nos últimos 3 min
    }
    
    // Formatar jobs
    $jobsFormatados = array_map(function($job) {
        $dados = json_decode($job['dados_deals'], true);
        $totalSolicitado = is_array($dados) ? count($dados) : 0;
        
        return [
            'job_id' => $job['job_id'],
            'status' => $job['status'],
            'total_solicitado' => $totalSolicitado,
            'deals_processados' => $job['deals_processados'] ?? 0,
            'deals_sucesso' => $job['deals_sucesso'] ?? 0,
            'created_at' => $job['created_at'],
            'updated_at' => $job['updated_at']
        ];
    }, $jobs);
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'cron' => [
            'ativo' => $cronAtivo,
            'ultima_execucao' => $ultimaExecucao,
            'minutos_sem_execucao' => $minutosSemExecucao
        ],
        'contadores' => $contadores,
        'jobs' => $jobsFormatados
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>
