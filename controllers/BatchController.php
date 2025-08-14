<?php
namespace Controllers;

require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use dao\AplicacaoAcessoDAO;
use Helpers\BitrixDealHelper;

class BatchController
{
    /**
     * Endpoint para consultar status de um job em batch
     * GET /batch/status?job_id=job_20250814_143022_abc123
     */
    public function status()
    {
        $params = $_GET;
        $jobId = $params['job_id'] ?? null;

        if (!$jobId) {
            $resultado = [
                'status' => 'erro',
                'mensagem' => 'Parâmetro job_id é obrigatório'
            ];
        } else {
            $resultado = $this->consultarStatusJob($jobId);
        }

        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Endpoint para processar jobs pendentes (usado pelo cron)
     * GET /batch/processar
     */
    public function processar()
    {
        $resultado = $this->processarJobsPendentes();

        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Consulta o status de um job específico
     */
    private function consultarStatusJob($jobId): array
    {
        try {
            // Busca job no banco
            $config = require __DIR__ . '/../config/config.php';
            $pdo = new \PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                $config['usuario'],
                $config['senha'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare("SELECT * FROM batch_jobs WHERE job_id = ?");
            $stmt->execute([$jobId]);
            $job = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$job) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Job não encontrado',
                    'job_id' => $jobId
                ];
            }

            // Calcula estatísticas
            $percentual = $job['total_items'] > 0 ? 
                round(($job['items_processados'] / $job['total_items']) * 100, 1) : 0;

            $tempoDecorrido = null;
            $estimativaRestante = null;
            
            if ($job['iniciado_em']) {
                $inicio = new \DateTime($job['iniciado_em']);
                $agora = new \DateTime();
                $tempoDecorrido = $agora->diff($inicio);
                
                if ($job['items_processados'] > 0 && $job['status'] === 'processando') {
                    $tempoMedioItem = $tempoDecorrido->s + ($tempoDecorrido->i * 60) + ($tempoDecorrido->h * 3600);
                    $tempoMedioItem = $tempoMedioItem / $job['items_processados'];
                    $itensRestantes = $job['total_items'] - $job['items_processados'];
                    $segundosRestantes = $itensRestantes * $tempoMedioItem;
                    $estimativaRestante = gmdate("H:i:s", $segundosRestantes);
                }
            }

            return [
                'job_id' => $job['job_id'],
                'tipo' => $job['tipo'],
                'status' => $job['status'],
                'progresso' => [
                    'total' => (int)$job['total_items'],
                    'processados' => (int)$job['items_processados'],
                    'sucesso' => (int)$job['items_sucesso'],
                    'erro' => (int)$job['items_erro'],
                    'percentual' => $percentual
                ],
                'tempo' => [
                    'criado_em' => $job['criado_em'],
                    'iniciado_em' => $job['iniciado_em'],
                    'concluido_em' => $job['concluido_em'],
                    'tempo_decorrido' => $tempoDecorrido ? $tempoDecorrido->format('%H:%I:%S') : null,
                    'estimativa_restante' => $estimativaRestante
                ],
                'resultado' => $job['resultado'] ? json_decode($job['resultado'], true) : null,
                'erro_mensagem' => $job['erro_mensagem']
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro ao consultar status: ' . $e->getMessage(),
                'job_id' => $jobId
            ];
        }
    }

    /**
     * Processa jobs pendentes (chamado pelo cron)
     */
    private function processarJobsPendentes(): array
    {
        try {
            // Conecta ao banco
            $config = require __DIR__ . '/../config/config.php';
            $pdo = new \PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                $config['usuario'],
                $config['senha'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // Busca job pendente mais antigo
            $stmt = $pdo->prepare("SELECT * FROM batch_jobs WHERE status = 'pendente' ORDER BY criado_em ASC LIMIT 1");
            $stmt->execute();
            $job = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$job) {
                return [
                    'status' => 'sem_jobs',
                    'mensagem' => 'Nenhum job pendente encontrado',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

            // Marca job como processando
            $stmt = $pdo->prepare("UPDATE batch_jobs SET status = 'processando', iniciado_em = NOW() WHERE job_id = ?");
            $stmt->execute([$job['job_id']]);

            // Recupera dados do job
            $dados = json_decode($job['dados_entrada'], true);
            $spa = $dados['spa'];
            $categoryId = $dados['category_id'];
            $deals = $dados['deals'];

            $logInicial = date('Y-m-d H:i:s') . " | BATCH PROCESSOR | Iniciando job {$job['job_id']} com " . count($deals) . " deals\n";
            file_put_contents(__DIR__ . '/../../logs/batch_processor.log', $logInicial, FILE_APPEND);

            // Processa em chunks de 25
            $chunks = array_chunk($deals, 25);
            $todosIds = [];
            $totalSucessos = 0;
            $totalErros = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                $batchAtual = $chunkIndex + 1;
                
                // Log do progresso
                $logChunk = date('Y-m-d H:i:s') . " | BATCH PROCESSOR | Job {$job['job_id']} - Processando chunk $batchAtual/" . count($chunks) . "\n";
                file_put_contents(__DIR__ . '/../../logs/batch_processor.log', $logChunk, FILE_APPEND);

                // Processa chunk atual
                $resultadoChunk = BitrixDealHelper::processarChunkSincrono($spa, $categoryId, $chunk);
                
                // Atualiza contadores
                $sucessosChunk = $resultadoChunk['quantidade'] ?? 0;
                $errosChunk = count($chunk) - $sucessosChunk;
                
                $totalSucessos += $sucessosChunk;
                $totalErros += $errosChunk;

                // Coleta IDs criados
                if (!empty($resultadoChunk['ids'])) {
                    if (is_array($resultadoChunk['ids'])) {
                        $todosIds = array_merge($todosIds, $resultadoChunk['ids']);
                    } else {
                        $todosIds[] = $resultadoChunk['ids'];
                    }
                }

                // Atualiza progresso no banco
                $itemsProcessados = ($chunkIndex + 1) * 25;
                if ($itemsProcessados > count($deals)) {
                    $itemsProcessados = count($deals);
                }

                $stmt = $pdo->prepare("UPDATE batch_jobs SET items_processados = ?, items_sucesso = ?, items_erro = ? WHERE job_id = ?");
                $stmt->execute([$itemsProcessados, $totalSucessos, $totalErros, $job['job_id']]);

                // Rate limiting: aguarda 1 segundo entre chunks
                if ($batchAtual < count($chunks)) {
                    sleep(1);
                }
            }

            // Monta resultado final
            $resultadoFinal = [
                'status' => 'concluido',
                'quantidade_total' => count($deals),
                'quantidade_sucesso' => $totalSucessos,
                'quantidade_erro' => $totalErros,
                'ids_criados' => $todosIds,
                'taxa_sucesso' => count($deals) > 0 ? round(($totalSucessos / count($deals)) * 100, 1) : 0
            ];

            // Marca como concluído
            $stmt = $pdo->prepare("UPDATE batch_jobs SET status = 'concluido', concluido_em = NOW(), resultado = ? WHERE job_id = ?");
            $stmt->execute([json_encode($resultadoFinal, JSON_UNESCAPED_UNICODE), $job['job_id']]);

            $logFinal = date('Y-m-d H:i:s') . " | BATCH PROCESSOR | Job {$job['job_id']} CONCLUÍDO - {$totalSucessos} sucessos, {$totalErros} erros\n";
            file_put_contents(__DIR__ . '/../../logs/batch_processor.log', $logFinal, FILE_APPEND);

            return [
                'status' => 'processado',
                'job_id' => $job['job_id'],
                'resultado' => $resultadoFinal
            ];

        } catch (\Exception $e) {
            // Em caso de erro, marca job como erro
            if (isset($job['job_id'])) {
                $stmt = $pdo->prepare("UPDATE batch_jobs SET status = 'erro', erro_mensagem = ? WHERE job_id = ?");
                $stmt->execute([$e->getMessage(), $job['job_id']]);
            }

            $logErro = date('Y-m-d H:i:s') . " | BATCH PROCESSOR | ERRO: " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/../../logs/batch_processor.log', $logErro, FILE_APPEND);

            return [
                'status' => 'erro',
                'mensagem' => 'Erro no processamento: ' . $e->getMessage(),
                'job_id' => isset($job['job_id']) ? $job['job_id'] : null
            ];
        }
    }
}
