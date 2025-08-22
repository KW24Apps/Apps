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

            // Define o tamanho do lote com base na entidade (SPA)
            $tamanhoLote = ($spa == 2) ? 10 : 25;
            
            if ($tipo === 'criar_deals') {
                $resultado = BitrixDealHelper::criarDeal($spa, $categoryId, $fields, $tamanhoLote);
            } elseif ($tipo === 'editar_deals') {
                $dealIds = $dados['deal_ids'] ?? $dados['dealIds'] ?? null;
                $resultado = BitrixDealHelper::editarDeal($spa, $dealIds, $fields, $tamanhoLote);
            } else {
                throw new \Exception('Tipo de job não suportado: ' . $tipo);
            }
            
            $dao->marcarComoConcluido($job['job_id'], $resultado);
            
            // NOVO: Verificar se deve reprocessar automaticamente
            if (!empty($dados['controller_origem']) && 
                $dados['controller_origem'] === 'GerarOpportunidadeController' &&
                !empty($dados['deal_origem_id']) &&
                isset($dados['reprocessar_automaticamente']) && 
                $dados['reprocessar_automaticamente'] === true) {
                
                // Executar o GerarOpportunidadeController novamente para verificar se ainda falta algo
                $dealOrigemId = $dados['deal_origem_id'];
                $webhook = $dados['webhook'] ?? '';
                
                // Restaurar webhook para nova execução
                if ($webhook) {
                    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;
                }
                
                LogHelper::logDealBatchController("REPROCESSAMENTO - Executando GerarOpportunidadeController novamente | Deal: $dealOrigemId");
                
                // Simular GET para o controller
                $_GET['deal'] = $dealOrigemId;
                
                try {
                    $controller = new \Controllers\GeraroptndController();
                    ob_start(); // Capturar output para não interferir
                    $controller->executar();
                    ob_end_clean(); // Descartar output
                    
                    LogHelper::logDealBatchController("REPROCESSAMENTO - Concluído com sucesso | Deal: $dealOrigemId");
                } catch (\Exception $e) {
                    LogHelper::logDealBatchController("REPROCESSAMENTO - Falhou | Deal: $dealOrigemId | Erro: " . $e->getMessage());
                }
            }
            
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
