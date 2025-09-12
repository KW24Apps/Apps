<?php
namespace Repositories;

class BatchJobDAO
{
    private $pdo;

    public function __construct()
    {
        try {
            $config = require __DIR__ . '/../config/configdashboard.php';
            $this->pdo = new \PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                $config['usuario'],
                $config['senha'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Cria um novo job no banco
     */
    public function criarJob(string $jobId, string $tipo, array $dados, int $totalItens): bool
    {
        $sql = "INSERT INTO batch_jobs (job_id, tipo, status, dados_entrada, total_items, progresso_itens) 
                VALUES (?, ?, 'pendente', ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $dadosJson = json_encode($dados, JSON_UNESCAPED_UNICODE);
        
        // Inicializa progresso_itens com um array vazio ou estrutura inicial
        $progressoItens = json_encode([]); 
        
        return $stmt->execute([$jobId, $tipo, $dadosJson, $totalItens, $progressoItens]);
    }

    /**
     * Busca job pendente mais antigo (apenas se não há job em processamento)
     * ou um job em processamento que pode ser retomado (ex: travou)
     */
    public function buscarJobParaProcessar(): ?array
    {
        // Primeiro, tenta encontrar um job que está "processando" mas pode ter travado
        // Considera um job travado se iniciado_em for mais antigo que X minutos (ex: 10 minutos)
        $timeoutMinutes = 10; 
        $sqlTravado = "SELECT * FROM batch_jobs WHERE status = 'processando' AND iniciado_em < (NOW() - INTERVAL {$timeoutMinutes} MINUTE) ORDER BY iniciado_em ASC LIMIT 1";
        $stmtTravado = $this->pdo->prepare($sqlTravado);
        $stmtTravado->execute();
        $jobTravado = $stmtTravado->fetch(\PDO::FETCH_ASSOC);

        if ($jobTravado) {
            // Se encontrar um job travado, marca como erro e retorna para ser reprocessado (ou tratado)
            $this->marcarComoErro($jobTravado['job_id'], "Job travado por mais de {$timeoutMinutes} minutos.");
            // O ideal seria retornar o job travado para que o controller possa decidir o que fazer
            // Mas para manter a lógica de "buscar o próximo", vamos buscar novamente
            return $this->buscarJobParaProcessar(); // Tenta buscar novamente após marcar o travado como erro
        }

        // Se não há jobs travados, verifica se já tem job processando (não travado)
        $sqlVerificacao = "SELECT COUNT(*) as total FROM batch_jobs WHERE status = 'processando'";
        $stmtVerificacao = $this->pdo->prepare($sqlVerificacao);
        $stmtVerificacao->execute();
        $jobsProcessando = $stmtVerificacao->fetch(\PDO::FETCH_ASSOC)['total'];
        
        if ($jobsProcessando > 0) {
            // Se há um job processando (e não está travado), não busca outro
            return null;
        }
        
        // Busca próximo job pendente
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
        $sql = "UPDATE batch_jobs SET status = 'processando', iniciado_em = NOW(), ultima_atualizacao_progresso = NOW() WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$jobId]);
    }

    /**
     * Marca job como concluído
     */
    public function marcarComoConcluido(string $jobId, array $resultado, array $progressoItens): bool
    {
        $sql = "UPDATE batch_jobs SET status = 'concluido', concluido_em = NOW(), resultado = ?, progresso_itens = ?, ultima_atualizacao_progresso = NOW() WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $resultadoJson = json_encode($resultado, JSON_UNESCAPED_UNICODE);
        $progressoItensJson = json_encode($progressoItens, JSON_UNESCAPED_UNICODE);
        return $stmt->execute([$resultadoJson, $progressoItensJson, $jobId]);
    }

    /**
     * Marca job como erro
     */
    public function marcarComoErro(string $jobId, string $mensagemErro, ?array $progressoItens = null): bool
    {
        $sql = "UPDATE batch_jobs SET status = 'erro', erro_mensagem = ?, progresso_itens = ?, ultima_atualizacao_progresso = NOW() WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $progressoItensJson = $progressoItens ? json_encode($progressoItens, JSON_UNESCAPED_UNICODE) : null;
        return $stmt->execute([$mensagemErro, $progressoItensJson, $jobId]);
    }

    /**
     * Atualiza o progresso detalhado de um job
     */
    public function atualizarProgressoItens(string $jobId, array $progressoItens): bool
    {
        $sql = "UPDATE batch_jobs SET progresso_itens = ?, ultima_atualizacao_progresso = NOW() WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $progressoItensJson = json_encode($progressoItens, JSON_UNESCAPED_UNICODE);
        return $stmt->execute([$progressoItensJson, $jobId]);
    }

    /**
     * Verifica se existe job em processamento (agora mais robusto com timeout)
     */
    public function temJobProcessando(): bool
    {
        // Considera jobs "processando" que não atingiram o timeout
        $timeoutMinutes = 10; 
        $sql = "SELECT COUNT(*) as total FROM batch_jobs WHERE status = 'processando' AND ultima_atualizacao_progresso >= (NOW() - INTERVAL {$timeoutMinutes} MINUTE)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC)['total'] > 0;
    }

    /**
     * Atualiza os contadores de itens processados, sucesso e erro de um job.
     */
    public function atualizarContadoresItens(string $jobId, int $sucesso = 0, int $erro = 0, int $processados = 0): bool
    {
        $sql = "UPDATE batch_jobs 
                SET items_sucesso = items_sucesso + ?, 
                    items_erro = items_erro + ?, 
                    items_processados = items_processados + ?,
                    ultima_atualizacao_progresso = NOW() 
                WHERE job_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$sucesso, $erro, $processados, $jobId]);
    }
}
