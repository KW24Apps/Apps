<?php
namespace Controllers;

require_once __DIR__ . '/../dao/BatchJobDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use dao\BatchJobDAO;
use Helpers\BitrixDealHelper;
use Helpers\LogHelper;

class DealBatchController
{
    // Processa o próximo job pendente
    public static function processarProximoJob(): array
    {
        $dao = new BatchJobDAO();
        $job = $dao->buscarJobPendente();
        
        if (!$job) {
            // Verifica se é porque tem job processando ou se realmente não tem jobs
            if ($dao->temJobProcessando()) {
                return [
                    'status' => 'job_em_andamento',
                    'mensagem' => 'Existe job em processamento - aguardando conclusão',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } else {
                return [
                    'status' => 'sem_jobs',
                    'mensagem' => 'Nenhum job pendente encontrado',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
        }

        // Job encontrado - inicia processamento
        LogHelper::logDealBatchController("INICIO - Job iniciado | ID: {$job['job_id']} | Tipo: {$job['tipo']}");
        
        $dao->marcarComoProcessando($job['job_id']);
        try {
            $dados = json_decode($job['dados_entrada'], true);
            $tipo = $job['tipo'];
            $resultado = null;
            
            // Restaura o webhook salvo no job
            if (!empty($dados['webhook'])) {
                $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $dados['webhook'];
            }
            
            // Padroniza variáveis
            $spa = $dados['spa'] ?? $dados['entityId'] ?? null;
            $categoryId = $dados['category_id'] ?? $dados['categoryId'] ?? null;
            $fields = $dados['deals'] ?? $dados['fields'] ?? [];
            
            if ($tipo === 'criar_deals' || $tipo === 'gerar_oportunidades') {
                $resultado = BitrixDealHelper::criarDeal($spa, $categoryId, $fields);
            } elseif ($tipo === 'editar_deals') {
                $dealIds = $dados['deal_ids'] ?? $dados['dealIds'] ?? null;
                $resultado = BitrixDealHelper::editarDeal($spa, $dealIds, $fields);
            } else {
                throw new \Exception('Tipo de job não suportado: ' . $tipo);
            }
            
            $dao->marcarComoConcluido($job['job_id'], $resultado);
            
            // Log de sucesso
            $quantidade = $resultado['quantidade'] ?? 0;
            $tempo = $resultado['tempo_total_minutos'] ?? 0;
            $status = $resultado['status'] === 'sucesso' ? 'SUCESSO' : 'PARCIAL_SUCESSO';
            LogHelper::logDealBatchController("FIM - {$status} | Deals: {$quantidade} | Tempo: {$tempo}m | ID: {$job['job_id']}");
            
            return [
                'status' => 'processado',
                'job_id' => $job['job_id'],
                'resultado' => $resultado
            ];
            
        } catch (\Throwable $e) {
            $dao->marcarComoErro($job['job_id'], $e->getMessage());
            LogHelper::logDealBatchController("ERRO - Falha no processamento | ID: {$job['job_id']} | Erro: " . $e->getMessage());
            return [
                'status' => 'erro',
                'job_id' => $job['job_id'],
                'mensagem' => $e->getMessage()
            ];
        }
    }
}
