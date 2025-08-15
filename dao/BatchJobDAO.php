<?php
namespace dao;

class BatchJobDAO
{
    private $pdo;

    public function __construct()
    {
        $logPath = __DIR__ . '/../../logs/batch_debug.log';
        file_put_contents($logPath, date('Y-m-d H:i:s') . " | DEBUG | BatchJobDAO: Antes do require configdashboard.php\n", FILE_APPEND);
        try {
            $config = require __DIR__ . '/../config/configdashboard.php';
            file_put_contents($logPath, date('Y-m-d H:i:s') . " | DEBUG | BatchJobDAO: Configdashboard carregado\n", FILE_APPEND);
            $this->pdo = new \PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                $config['usuario'],
                $config['senha'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            file_put_contents($logPath, date('Y-m-d H:i:s') . " | DEBUG | BatchJobDAO: PDO instanciado com sucesso\n", FILE_APPEND);
        } catch (\Throwable $e) {
            file_put_contents($logPath, date('Y-m-d H:i:s') . " | DEBUG | BatchJobDAO: EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            throw $e;
        }
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
     * Marca job como processando
     */
    public function marcarComoProcessando(string $jobId): bool
    {
        $sql = "UPDATE batch_jobs SET status = 'processando', iniciado_em = NOW() WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$jobId]);
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

}
