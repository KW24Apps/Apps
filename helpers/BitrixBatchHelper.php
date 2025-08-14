<?php
namespace Helpers;

require_once __DIR__ . '/BitrixHelper.php';
require_once __DIR__ . '/../dao/BatchJobDAO.php';

use Helpers\BitrixHelper;
use dao\BatchJobDAO;

class BitrixBatchHelper
{
    /**
     * Cria um job em batch para processamento assíncrono
     * 
     * @param string $tipo Tipo do job ('criar_deals', 'editar_deals', etc.)
     * @param array $dados Array com spa, category_id e dados para processar
     * @return string Job ID criado
     */
    public static function criarJob(string $tipo, array $dados): string
    {
        try {
            // Gera ID único para o job
            $jobId = 'job_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
            
            // Calcula total de itens baseado no tipo
            $totalItens = 0;
            if (isset($dados['deals'])) {
                $totalItens = count($dados['deals']);
            } elseif (isset($dados['companies'])) {
                $totalItens = count($dados['companies']);
            } elseif (isset($dados['tasks'])) {
                $totalItens = count($dados['tasks']);
            }

            // Usa DAO para salvar job
            $dao = new BatchJobDAO();
            $dao->criarJob($jobId, $tipo, $dados, $totalItens);

            // Log da criação
            $logCriacao = date('Y-m-d H:i:s') . " | BITRIX BATCH | Job criado: $jobId | Tipo: $tipo | Itens: $totalItens\n";
            file_put_contents(__DIR__ . '/../../logs/batch_jobs.log', $logCriacao, FILE_APPEND);

            return $jobId;

        } catch (\Exception $e) {
            $logErro = date('Y-m-d H:i:s') . " | BITRIX BATCH | ERRO ao criar job: " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/../../logs/batch_jobs.log', $logErro, FILE_APPEND);
            
            throw new \Exception("Erro ao criar job em batch: " . $e->getMessage());
        }
    }

    /**
     * Consulta o status de um job específico
     * 
     * @param string $jobId ID do job para consultar
     * @return array Status completo do job
     */
    public static function consultarStatus(string $jobId): array
    {
        try {
            // Usa DAO para buscar job
            $dao = new BatchJobDAO();
            $job = $dao->buscarJobPorId($jobId);

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
     * Processa jobs pendentes (usado pelo cron)
     * 
     * @return array Resultado do processamento
     */
    public static function processarJobsPendentes(): array
    {

        try {
            // LOG: início do método
            file_put_contents(__DIR__ . '/../../logs/batch_debug.log', date('Y-m-d H:i:s') . " | DEBUG | Entrou em processarJobsPendentes\n", FILE_APPEND);

            // Usa DAO para buscar job pendente
            $dao = new BatchJobDAO();
            file_put_contents(__DIR__ . '/../../logs/batch_debug.log', date('Y-m-d H:i:s') . " | DEBUG | Chamou buscarJobPendente\n", FILE_APPEND);
            $job = $dao->buscarJobPendente();

            if (!$job) {
                file_put_contents(__DIR__ . '/../../logs/batch_debug.log', date('Y-m-d H:i:s') . " | DEBUG | NENHUM JOB PENDENTE ENCONTRADO\n", FILE_APPEND);
                return [
                    'status' => 'sem_jobs',
                    'mensagem' => 'Nenhum job pendente encontrado',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

            // Marca job como processando
            $dao->marcarComoProcessando($job['job_id']);

            // Log inicial
            $logInicial = date('Y-m-d H:i:s') . " | BITRIX BATCH | Iniciando processamento: {$job['job_id']} | Tipo: {$job['tipo']}\n";
            file_put_contents(__DIR__ . '/../../logs/batch_processor.log', $logInicial, FILE_APPEND);

            // Delega processamento baseado no tipo
            $resultado = self::processarPorTipo($job, $dao);

            return $resultado;

        } catch (\Exception $e) {
            // Em caso de erro, marca job como erro
            if (isset($job['job_id']) && isset($dao)) {
                $dao->marcarComoErro($job['job_id'], $e->getMessage());
            }

            $logErro = date('Y-m-d H:i:s') . " | BITRIX BATCH | ERRO: " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/../../logs/batch_processor.log', $logErro, FILE_APPEND);

            return [
                'status' => 'erro',
                'mensagem' => 'Erro no processamento: ' . $e->getMessage(),
                'job_id' => isset($job['job_id']) ? $job['job_id'] : null
            ];
        }
    }

    /**
     * Processa job baseado no tipo
     */
    private static function processarPorTipo(array $job, BatchJobDAO $dao): array
    {
        $dados = json_decode($job['dados_entrada'], true);

        switch ($job['tipo']) {
            case 'criar_deals':
                return self::processarCriarDeals($job, $dados, $dao);
            
            case 'editar_deals':
                return self::processarEditarDeals($job, $dados, $dao);
            
            // Futuras extensões:
            // case 'criar_companies':
            //     return self::processarCriarCompanies($job, $dados, $dao);
            
            default:
                throw new \Exception("Tipo de job não suportado: {$job['tipo']}");
        }
    }

    /**
     * Processa criação de deals em batch
     */
    private static function processarCriarDeals(array $job, array $dados, BatchJobDAO $dao): array
    {
        $spa = $dados['spa'];
        $categoryId = $dados['category_id'];
        $deals = $dados['deals'];

        // Processa em chunks de 25
        $chunks = array_chunk($deals, 25);
        $todosIds = [];
        $totalSucessos = 0;
        $totalErros = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            $batchAtual = $chunkIndex + 1;
            
            // Log do progresso
            $logChunk = date('Y-m-d H:i:s') . " | BITRIX BATCH | Job {$job['job_id']} - Chunk $batchAtual/" . count($chunks) . "\n";
            file_put_contents(__DIR__ . '/../../logs/batch_processor.log', $logChunk, FILE_APPEND);

            // Processa chunk atual usando BitrixBatchHelper
            $resultadoChunk = self::processarChunkDeals($spa, $categoryId, $chunk);
            
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

            // Atualiza progresso no banco usando DAO
            $itemsProcessados = ($chunkIndex + 1) * 25;
            if ($itemsProcessados > count($deals)) {
                $itemsProcessados = count($deals);
            }

            $dao->atualizarProgresso($job['job_id'], $itemsProcessados, $totalSucessos, $totalErros);

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

        // Marca como concluído usando DAO
        $dao->marcarComoConcluido($job['job_id'], $resultadoFinal);

        $logFinal = date('Y-m-d H:i:s') . " | BITRIX BATCH | Job {$job['job_id']} CONCLUÍDO - {$totalSucessos} sucessos, {$totalErros} erros\n";
        file_put_contents(__DIR__ . '/../../logs/batch_processor.log', $logFinal, FILE_APPEND);

        return [
            'status' => 'processado',
            'job_id' => $job['job_id'],
            'resultado' => $resultadoFinal
        ];
    }

    /**
     * Processa edição de deals em batch (placeholder para futuro)
     */
    private static function processarEditarDeals(array $job, array $dados, BatchJobDAO $dao): array
    {
        // TODO: Implementar quando necessário
        throw new \Exception("Edição em batch ainda não implementada");
    }

    /**
     * Processa um chunk de deals usando a API do Bitrix
     */
    private static function processarChunkDeals($entityId, $categoryId, array $fields): array
    {
        // Monta comandos para batch do Bitrix
        $commands = [];
        
        foreach ($fields as $index => $fieldData) {
            $formattedFields = BitrixHelper::formatarCampos($fieldData);
            
            // CORREÇÃO: Só adiciona categoryId se for compatível com o entityId
            // Para evitar erro "Pipeline incorreto"
            if ($categoryId && $entityId != 1092) {
                $formattedFields['categoryId'] = $categoryId;
            }

            $commands["deal_$index"] = [
                'method' => 'crm.item.add',
                'params' => [
                    'entityTypeId' => $entityId,
                    'fields' => $formattedFields
                ]
            ];
        }

        // Executa batch no Bitrix
        $resultado = BitrixHelper::chamarApi('batch', ['cmd' => $commands], [
            'log' => true,
            'origem' => 'BitrixBatchHelper'
        ]);

        // Processa resultado
        $sucessos = 0;
        $ids = [];
        
        if (isset($resultado['result']['result'])) {
            foreach ($resultado['result']['result'] as $key => $itemResult) {
                if (isset($itemResult['item']['id'])) {
                    $sucessos++;
                    $ids[] = $itemResult['item']['id'];
                }
            }
        }

        return [
            'status' => 'sucesso',
            'quantidade' => $sucessos,
            'ids' => $ids,
            'total_chunk' => count($fields),
            'erros' => count($fields) - $sucessos
        ];
    }
}
