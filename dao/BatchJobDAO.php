<?php
namespace dao;

class BatchJobDAO
{
    private $pdo;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/config.php';
        $this->pdo = new \PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
            $config['usuario'],
            $config['senha'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * Cria um novo job no banco
     */
    public function criarJob(string $jobId, string $tipo, array $dados, int $totalItens): bool
    {
        $sql = "INSERT INTO batch_jobs (job_id, tipo, status, dados_entrada, total_items) 
                VALUES (?, ?, 'pendente', ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $dadosJson = json_encode($dados, JSON_UNESCAPED_UNICODE);
        
        return $stmt->execute([$jobId, $tipo, $dadosJson, $totalItens]);
    }

    /**
     * Busca job pendente mais antigo
     */
    public function buscarJobPendente(): ?array
    {
        $sql = "SELECT * FROM batch_jobs WHERE status = 'pendente' ORDER BY criado_em ASC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $job ?: null;
    }

    /**
     * Busca job por ID
     */
    public function buscarJobPorId(string $jobId): ?array
    {
        $sql = "SELECT * FROM batch_jobs WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$jobId]);
        
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $job ?: null;
    }

    /**
     * Atualiza status do job
     */
    public function atualizarStatus(string $jobId, string $status): bool
    {
        $sql = "UPDATE batch_jobs SET status = ? WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $jobId]);
    }

    /**
     * Marca job como processando
     */
    public function marcarComoProcessando(string $jobId): bool
    {
        $sql = "UPDATE batch_jobs SET status = 'processando', iniciado_em = NOW() WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$jobId]);
    }

    /**
     * Atualiza progresso do job
     */
    public function atualizarProgresso(string $jobId, int $processados, int $sucessos, int $erros): bool
    {
        $sql = "UPDATE batch_jobs SET items_processados = ?, items_sucesso = ?, items_erro = ? WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$processados, $sucessos, $erros, $jobId]);
    }

    /**
     * Marca job como concluído
     */
    public function marcarComoConcluido(string $jobId, array $resultado): bool
    {
        $sql = "UPDATE batch_jobs SET status = 'concluido', concluido_em = NOW(), resultado = ? WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $resultadoJson = json_encode($resultado, JSON_UNESCAPED_UNICODE);
        return $stmt->execute([$resultadoJson, $jobId]);
    }

    /**
     * Marca job como erro
     */
    public function marcarComoErro(string $jobId, string $mensagemErro): bool
    {
        $sql = "UPDATE batch_jobs SET status = 'erro', erro_mensagem = ? WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$mensagemErro, $jobId]);
    }

    /**
     * Busca jobs por status (para relatórios)
     */
    public function buscarJobsPorStatus(string $status, int $limit = 10): array
    {
        $sql = "SELECT * FROM batch_jobs WHERE status = ? ORDER BY criado_em DESC LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$status, $limit]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Conta jobs por status
     */
    public function contarJobsPorStatus(string $status): int
    {
        $sql = "SELECT COUNT(*) FROM batch_jobs WHERE status = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$status]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Remove jobs antigos (limpeza)
     */
    public function limparJobsAntigos(int $diasAtras = 30): int
    {
        $sql = "DELETE FROM batch_jobs WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY) AND status IN ('concluido', 'erro')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$diasAtras]);
        
        return $stmt->rowCount();
    }
}
