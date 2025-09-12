<?php
namespace Controllers;

require_once __DIR__ . '/../Repositories/BatchJobDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../services/BatchJobProcessorService.php'; // Adicionado

use Repositories\BatchJobDAO;
use Helpers\BitrixDealHelper;
use Helpers\LogHelper;
use Services\BatchJobProcessorService; // Adicionado
use Exception;

class DealBatchController
{
    // Processa o próximo job pendente
    public static function processarProximoJob(): array
    {
        $dao = new BatchJobDAO();
        $processorService = new BatchJobProcessorService(); // Instancia o serviço
        
        $job = $dao->buscarJobParaProcessar(); // Usa o novo método que considera jobs travados
        
        if (!$job) {
            // Verifica se é porque tem job processando (não travado) ou se realmente não tem jobs
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
        
        $totalItensProcessados = 0;
        $totalItensComErro = 0;
        $progressoItens = json_decode($job['progresso_itens'] ?? '[]', true); // Carrega progresso existente
        $dadosEntrada = json_decode($job['dados_entrada'], true);
        $itensDoJob = $dadosEntrada['deals'] ?? $dadosEntrada['fields'] ?? []; // Itens a serem processados
        
        // Restaura o webhook salvo no job
        $webhookBitrix = $dadosEntrada['webhook'] ?? null;
        if (!empty($webhookBitrix)) {
            $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhookBitrix;
        }

        try {
            foreach ($itensDoJob as $index => $itemData) {
                // Verifica se o item já foi processado com sucesso
                $itemJaProcessado = false;
                foreach ($progressoItens as $pItem) {
                    // Assumindo que itemData tem um 'id' ou que o índice é suficiente para identificar
                    if (isset($itemData['id']) && isset($pItem['item_id']) && $pItem['item_id'] == $itemData['id'] && $pItem['status'] === 'sucesso') {
                        $itemJaProcessado = true;
                        break;
                    } elseif (!isset($itemData['id']) && $index == ($pItem['original_index'] ?? -1) && $pItem['status'] === 'sucesso') {
                        $itemJaProcessado = true;
                        break;
                    }
                }

                if ($itemJaProcessado) {
                    LogHelper::logDealBatchController("ITEM - Job: {$job['job_id']} | Item {$index} já processado com sucesso. Pulando.");
                    $totalItensProcessados++;
                    continue;
                }

                LogHelper::logDealBatchController("ITEM - Job: {$job['job_id']} | Processando Item {$index}");
                
                $itemResult = $processorService->processarItem(
                    $job['job_id'], 
                    $itemData, 
                    $job['tipo'], 
                    $dadosEntrada['spa'] ?? $dadosEntrada['entityId'], 
                    $dadosEntrada['category_id'] ?? $dadosEntrada['categoryId'],
                    $webhookBitrix
                );

                // Atualiza o progresso do item
                $itemResult['original_index'] = $index; // Adiciona o índice original para referência
                $progressoItens[] = $itemResult;
                $dao->atualizarProgressoItens($job['job_id'], $progressoItens);

                if ($itemResult['status'] === 'sucesso') {
                    $totalItensProcessados++;
                } else {
                    $totalItensComErro++;
                }
                
                // Pequena pausa para evitar sobrecarga, se necessário (já gerenciado pelo worker principal)
                // usleep(100000); // 0.1 segundos, ajustar conforme a necessidade e limites do Bitrix
            }
            
            $statusFinal = ($totalItensComErro === 0) ? 'concluido' : 'parcialmente_concluido';
            $dao->marcarComoConcluido($job['job_id'], ['total_processados' => $totalItensProcessados, 'total_erros' => $totalItensComErro], $progressoItens);
            
            // NOVO: Verificar se deve reprocessar automaticamente (lógica original)
            if (!empty($dadosEntrada['controller_origem']) && 
                $dadosEntrada['controller_origem'] === 'GerarOpportunidadeController' &&
                !empty($dadosEntrada['deal_origem_id']) &&
                isset($dadosEntrada['reprocessar_automaticamente']) && 
                $dadosEntrada['reprocessar_automaticamente'] === true) {
                
                $dealOrigemId = $dadosEntrada['deal_origem_id'];
                
                // Restaurar webhook para nova execução (já está na global)
                
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
            LogHelper::logDealBatchController("FIM - {$statusFinal} | Sucessos: {$totalItensProcessados} | Erros: {$totalItensComErro} | ID: {$job['job_id']}");
            
            return [
                'status' => $statusFinal,
                'job_id' => $job['job_id'],
                'total_processados' => $totalItensProcessados,
                'total_erros' => $totalItensComErro,
                'progresso_itens' => $progressoItens
            ];
            
        } catch (\Throwable $e) {
            // Em caso de erro fatal, marca o job como erro e salva o progresso atual
            $dao->marcarComoErro($job['job_id'], $e->getMessage(), $progressoItens);
            LogHelper::logDealBatchController("ERRO FATAL - Falha no processamento | ID: {$job['job_id']} | Erro: " . $e->getMessage());
            return [
                'status' => 'erro_fatal',
                'job_id' => $job['job_id'],
                'mensagem' => $e->getMessage()
            ];
        }
    }
}
