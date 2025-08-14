<?php
header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/../config/configdashboard.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['usuario'],
        $config['senha'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("SELECT * FROM batch_jobs ORDER BY criado_em DESC LIMIT 10");
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $contadores = [];
    foreach (['pendente', 'processando', 'concluido', 'erro'] as $status) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM batch_jobs WHERE status = ?");
        $stmt->execute([$status]);
        $contadores[$status] = $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("SELECT concluido_em FROM batch_jobs WHERE concluido_em IS NOT NULL ORDER BY concluido_em DESC LIMIT 1");
    $stmt->execute();
    $ultimaExecucao = $stmt->fetchColumn();

    $cronAtivo = false;
    $minutosSemExecucao = null;
    if ($ultimaExecucao) {
        $agora = new DateTime();
        $ultima = new DateTime($ultimaExecucao);
        $diff = $agora->diff($ultima);
        $minutosSemExecucao = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        $cronAtivo = $minutosSemExecucao <= 3;
    }

    $jobsFormatados = array_map(function($job) {
        $dados = json_decode($job['dados_entrada'], true);
        $totalSolicitado = 0;
        if (isset($dados['deals']) && is_array($dados['deals'])) {
            $totalSolicitado = count($dados['deals']);
        }
        return [
            'job_id' => $job['job_id'],
            'status' => $job['status'],
            'total_solicitado' => $totalSolicitado,
            'deals_processados' => $job['items_processados'] ?? 0,
            'deals_sucesso' => $job['items_sucesso'] ?? 0,
            'created_at' => $job['criado_em'],
            'updated_at' => $job['concluido_em']
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
