<?php
header('Content-Type: application/json');

// Dados mock para teste
$dados = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'cron' => [
        'ativo' => true,
        'ultima_execucao' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
        'minutos_sem_execucao' => 2
    ],
    'contadores' => [
        'pendente' => 1,
        'processando' => 0,
        'concluido' => 3,
        'erro' => 0
    ],
    'jobs' => [
        [
            'job_id' => 'job_20250814_172102_0aed7682',
            'status' => 'concluido',
            'total_solicitado' => 500,
            'deals_processados' => 500,
            'deals_sucesso' => 500,
            'created_at' => '2025-08-14 17:21:02'
        ]
    ]
];

echo json_encode($dados, JSON_UNESCAPED_UNICODE);
?>
