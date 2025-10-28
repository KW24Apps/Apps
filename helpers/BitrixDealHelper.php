<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../Repositories/BatchJobDAO.php';
require_once __DIR__ . '/../services/DealService.php'; // Adicionado para incluir o arquivo da classe

use Helpers\BitrixHelper;
use Repositories\BatchJobDAO;
use Services\DealService; // Adicionado para permitir o uso direto da classe

class BitrixDealHelper
{
    // Cria um ou vários negócios no Bitrix24 via API (sempre em batch, sem agendamento)
    public static function criarDeal($entityId, $categoryId, $fields, int $tamanhoLote = 15): array
    {
        // Sempre trata $fields como array de arrays
        if (!isset($fields[0]) || !is_array($fields[0])) {
            $fields = [$fields];
        }

        // Sempre executa em batch, mesmo para 1 deal
        $chunks = array_chunk($fields, $tamanhoLote);
        $todosIds = [];
        $totalSucessos = 0;
        $totalErros = 0;

        $startTime = microtime(true);

        foreach ($chunks as $chunkIndex => $chunk) {
            $batchCommands = [];
            foreach ($chunk as $index => $dealFields) {
                
                // Formata nomes e valida/formata valores dos campos
                $formattedFields = BitrixHelper::formatarCampos($dealFields, $entityId, true);
                
                // Garantir que categoryId seja sempre adicionado
                if ($categoryId) {
                    $formattedFields['categoryId'] = $categoryId;
                }
                
                // CORREÇÃO: Garantir que stageId seja preservado corretamente
                if (isset($dealFields['stageId']) && !empty($dealFields['stageId'])) {
                    unset($formattedFields['STAGE_ID']); // Garante a remoção de chaves com formato antigo
                    
                    $stageIdFornecido = $dealFields['stageId'];
                    
                    // Verifica se o stageId é numérico. Se for, busca o STATUS_ID correspondente.
                    // Se não for numérico, assume que já é o STATUS_ID correto.
                    if (is_numeric($stageIdFornecido)) {
                        // Constrói o ID da entidade de estágio dinamicamente, conforme a nova documentação
                        $stageEntityId = "DYNAMIC_{$entityId}_STAGE";
                        if (!empty($categoryId)) {
                            $stageEntityId .= "_{$categoryId}";
                        }
                        
                        // Usa a nova função para consultar as etapas com o método correto
                        $etapasDisponiveis = BitrixHelper::consultarEtapasCrmItem($stageEntityId);
                        $statusIdCorreto = null;
                        
                        foreach ($etapasDisponiveis as $etapa) {
                            // O ID numérico da etapa está no campo 'ID'
                            if (isset($etapa['ID']) && $etapa['ID'] == $stageIdFornecido) {
                                $statusIdCorreto = $etapa['STATUS_ID'];
                                break;
                            }
                        }
                        
                        // Usa o STATUS_ID encontrado; se não encontrar, mantém o numérico como fallback (pode falhar)
                        $formattedFields['stageId'] = $statusIdCorreto ?? $stageIdFornecido;
                        
                    } else {
                        // Se não for numérico, usa o valor diretamente, assumindo que é o STATUS_ID
                        $formattedFields['stageId'] = $stageIdFornecido;
                    }
                }
                
                // Adicionar log do payload antes de montar a chamada da API
                LogHelper::logDeal("DEBUG: Payload para crm.item.add (BitrixDealHelper::criarDeal) - entityTypeId: " . $entityId . ", fields: " . json_encode($formattedFields, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);

                $params = [
                    'entityTypeId' => $entityId,
                    'fields' => $formattedFields
                ];
                
                $batchCommands["deal$index"] = 'crm.item.add?' . http_build_query($params);
            }
            
            $resultado = BitrixHelper::chamarApi('batch', ['cmd' => $batchCommands], [
                'log' => false // Mantido como false, pois o log detalhado será feito aqui
            ]);
            
            // Log da resposta COMPLETA da API do Bitrix para o batch
            LogHelper::logDeal("DEBUG: Resposta COMPLETA da API Bitrix para batch (criarDeal): " . json_encode($resultado, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);

            $sucessosChunk = 0;
            $idsChunk = [];
            $errosChunk = [];
            
            if (isset($resultado['result']['result'])) {
                foreach ($resultado['result']['result'] as $key => $res) {
                    $itemId = null;
                    // Tenta extrair o ID do item de várias estruturas possíveis
                    if (isset($res['item']['id'])) { // crm.item.add para SPA (nova API)
                        $itemId = $res['item']['id'];
                    } elseif (isset($res['result']['item']['id'])) { // Outra variação para crm.item.add
                        $itemId = $res['result']['item']['id'];
                    } elseif (isset($res['result']) && is_numeric($res['result'])) { // crm.deal.add (legado) ou outras APIs que retornam ID direto
                        $itemId = $res['result'];
                    } elseif (isset($res['result']) && is_array($res['result']) && isset($res['result']['ID'])) { // crm.deal.add (legado) com ID dentro de array
                        $itemId = $res['result']['ID'];
                    }

                    if ($itemId) {
                        $sucessosChunk++;
                        $idsChunk[] = $itemId;
                        $todosIds[] = $itemId;
                    } else {
                        // Se não encontrou o ID, registra como erro
                        LogHelper::logDeal("DEAL FALHOU - Key: $key - Erro: " . json_encode($res, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);
                    }
                }
            } else {
                // Se a estrutura principal 'result.result' não existe, é um erro geral do batch
                LogHelper::logDeal("ERRO GERAL BATCH: Resposta da API não tem estrutura esperada ou erro geral. Resposta completa: " . json_encode($resultado, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);
            }
            
            $totalSucessos += $sucessosChunk;
            $totalErros += count($chunk) - $sucessosChunk; // Calcula erros com base no que não foi sucesso
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalTimeSeconds = round($totalTime, 2);
        $totalTimeMinutes = round($totalTime / 60, 2);
        $mediaPorDeal = $totalSucessos > 0 ? round($totalTime / $totalSucessos, 2) : 0;

        $idsString = implode(', ', $todosIds);
        return [
            'status' => $totalSucessos > 0 ? 'sucesso' : 'erro',
            'quantidade' => $totalSucessos,
            'ids' => $idsString,
            'mensagem' => $totalSucessos > 0 
                ? "$totalSucessos deals criados com sucesso" . ($totalErros > 0 ? " ($totalErros falharam)" : "")
                : "Falha ao criar deals: $totalErros erros",
            'tempo_total_segundos' => $totalTimeSeconds,
            'tempo_total_minutos' => $totalTimeMinutes,
            'media_tempo_por_deal_segundos' => $mediaPorDeal
        ];
    }

    // Edita um ou vários negócios existentes no Bitrix24 via API (sempre em batch, sem agendamento)
    public static function editarDeal($entityId, $dealId, array $fields, int $tamanhoLote = 15): array
    {
        // Sempre trata $dealId e $fields como arrays múltiplos
        $editData = [];
        if (is_array($dealId)) {
            foreach ($dealId as $index => $id) {
                $editData[] = [
                    'id' => $id,
                    'fields' => $fields[$index] ?? []
                ];
            }
        } else {
            $editData[] = [
                'id' => $dealId,
                'fields' => $fields
            ];
        }

        // Sempre executa em batch, mesmo para 1 edição
        $chunks = array_chunk($editData, $tamanhoLote);
        $todosIds = [];
        $totalSucessos = 0;
        $totalErros = 0;

        $startTime = microtime(true);

        foreach ($chunks as $chunk) {
            $batchCommands = [];
            foreach ($chunk as $index => $editItem) {
                // Formata nomes e valida/formata valores dos campos
                $fieldsFormatados = BitrixHelper::formatarCampos($editItem['fields'], $entityId, true);
                $params = [
                    'entityTypeId' => $entityId,
                    'id' => (int)$editItem['id'],
                    'fields' => $fieldsFormatados
                ];
                $batchCommands["edit$index"] = 'crm.item.update?' . http_build_query($params);
            }
            $resultado = BitrixHelper::chamarApi('batch', ['cmd' => $batchCommands], [
                'log' => true
            ]);
            $sucessosChunk = 0;
            $idsChunk = [];
            if (isset($resultado['result']['result'])) {
                foreach ($resultado['result']['result'] as $key => $res) {
                    $chunkItemIndex = (int)str_replace('edit', '', $key);
                    $dealId = $chunk[$chunkItemIndex]['id'];
                    if (isset($res['item']) || (isset($res['result']) && $res['result'] === true)) {
                        $sucessosChunk++;
                        $idsChunk[] = $dealId;
                        $todosIds[] = $dealId;
                    }
                }
            }
            $totalSucessos += $sucessosChunk;
            $totalErros += count($chunk) - $sucessosChunk;
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalTimeSeconds = round($totalTime, 2);
        $totalTimeMinutes = round($totalTime / 60, 2);
        $mediaPorDeal = $totalSucessos > 0 ? round($totalTime / $totalSucessos, 2) : 0;

        $idsString = implode(', ', $todosIds);
        return [
            'status' => $totalSucessos > 0 ? 'sucesso' : 'erro',
            'quantidade' => $totalSucessos,
            'ids' => $idsString,
            'mensagem' => $totalSucessos > 0 
                ? "$totalSucessos deals editados com sucesso" . ($totalErros > 0 ? " ($totalErros falharam)" : "")
                : "Falha ao editar deals: $totalErros erros",
            'tempo_total_segundos' => $totalTimeSeconds,
            'tempo_total_minutos' => $totalTimeMinutes,
            'media_tempo_por_deal_segundos' => $mediaPorDeal
        ];
    }

    // Consulta uma Negócio específico no Bitrix24 via ID
    public static function consultarDeal($entityId, $dealId, $fields)
    {
        // 1. Normaliza campos para array e remove espaços
        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        } else {
            $fields = array_map('trim', $fields);
        }

        // Instancia DealService para tratar nomes amigáveis
        $dealService = null;
        try {
            $dealService = new DealService(); // Alterado para usar a declaração 'use'
        } catch (\Throwable $e) {
            LogHelper::logDeal("ERROR: Falha ao instanciar DealService em consultarDeal: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString(), __CLASS__ . '::' . __FUNCTION__);
            throw $e; // Re-lança a exceção para ser capturada pelo try-catch principal
        }
        
        // Mapeia os campos solicitados (amigáveis ou UF_CRM_) para seus nomes técnicos Bitrix
        // A função tratarCamposAmigaveis espera um array associativo, então criamos um
        // onde cada campo solicitado tem um valor dummy (null) para que o serviço possa mapear apenas o nome.
        $camposParaMapear = [];
        foreach ($fields as $campo) {
            $camposParaMapear[$campo] = null;
        }

        $camposMapeados = $dealService->tratarCamposAmigaveis($camposParaMapear, $entityId);
        
        // Extrai apenas os nomes técnicos dos campos que foram mapeados com sucesso
        $camposTecnicosSolicitados = array_keys($camposMapeados);

        // Garante que 'id' esteja sempre presente nos campos solicitados
        if (!in_array('id', $camposTecnicosSolicitados)) {
            array_unshift($camposTecnicosSolicitados, 'id');
        }

        // 2. Consulta o negócio (deal)
        $params = [
            'entityTypeId' => $entityId,
            'id' => $dealId,
        ];

        $respostaApi = BitrixHelper::chamarApi('crm.item.get', $params, []);
        $dadosBrutosOriginais = $respostaApi['result']['item'] ?? [];

        // Normaliza as chaves dos dados brutos recebidos da API
        $dadosBrutosNormalizados = BitrixHelper::formatarCampos($dadosBrutosOriginais);

        // 3. Consulta os campos da SPA (metadados)
        $camposSpa = BitrixHelper::consultarCamposCrm($entityId);

        // 4. Consulta as etapas do tipo
        $etapas = BitrixHelper::consultarEtapasPorTipo($entityId);

        // 5. Monta resposta amigável SEMPRE incluindo todos os campos solicitados
        $resultadoFinal = [];
        foreach ($camposTecnicosSolicitados as $campoTecnico) {

            // Obtém o valor bruto do array normalizado
            $valorBruto = $dadosBrutosNormalizados[$campoTecnico] ?? null;
            
            $spa = $camposSpa[$campoTecnico] ?? [];
            $nomeAmigavel = $spa['title'] ?? $campoTecnico; // Usa o title do metadado se disponível
            $type = $spa['type'] ?? null;
            $isMultiple = $spa['isMultiple'] ?? false;

            $textoFinal = $valorBruto; // Valor padrão para texto é o valor bruto

            // Mapeamento para campos de enumeração (incluindo UF_CRM_ e sourceId se for enumeração)
            if (isset($spa['type']) && $spa['type'] === 'enumeration' && isset($spa['items'])) {
                $mapa = [];
                foreach ($spa['items'] as $item) {
                    $mapa[$item['ID']] = $item['VALUE'];
                    // Alguns campos de source podem ter STATUS_ID como chave
                    if (isset($item['STATUS_ID'])) {
                        $mapa[$item['STATUS_ID']] = $item['VALUE'];
                    }
                }
                if (is_array($valorBruto)) {
                    $textoFinal = array_map(function($v) use ($mapa) {
                        return $mapa[$v] ?? $v;
                    }, $valorBruto);
                } else {
                    $textoFinal = $mapa[$valorBruto] ?? $valorBruto;
                }
            }

            // Tratamento especial para stageId
            if ($campoTecnico === 'stageId') {
                $stageName = BitrixHelper::mapearEtapaPorId($valorBruto, $etapas);
                $textoFinal = $stageName ?? $valorBruto;
                $nomeAmigavel = 'Fase';
                $type = 'crm_status';
            }
            
            // Tratamento especial para companyId
            if ($campoTecnico === 'companyId' && $valorBruto !== null) {
                $nomeAmigavel = 'Empresa';
                $type = 'crm_company';
                $textoFinal = $valorBruto; // Mantém o ID da empresa como texto
            }


            $resultadoFinal[$campoTecnico] = [
                'nome' => $nomeAmigavel,
                'valor' => $valorBruto,
                'texto' => $textoFinal,
                'type' => $type,
                'isMultiple' => $isMultiple
            ];
        }

        return ['result' => $resultadoFinal];
    }

    // Cria um job para a fila de processamento
    public static function criarJobParaFila($entityId, $categoryId, array $deals, $tipoJob): array
    {
        try {
            // Prepara dados para o job, incluindo o webhook
            $webhook = $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] ?? '';
            $dados = [
                'spa' => $entityId,
                'category_id' => $categoryId,
                'deals' => $deals,
                'webhook' => $webhook
            ];
            $totalItens = count($deals);
            $jobId = uniqid('job_', true);
            $dao = new BatchJobDAO();
            $ok = $dao->criarJob($jobId, $tipoJob, $dados, $totalItens);
            if (!$ok) {
                throw new \Exception('Falha ao inserir job no banco.');
            }
            return [
                'status' => 'job_criado',
                'job_id' => $jobId,
                'total_deals' => $totalItens,
                'tipo_job' => $tipoJob,
                'mensagem' => $totalItens . ' deals enviados para processamento assíncrono.',
                'consultar_status' => "Use /deal/status?job_id=$jobId para acompanhar o progresso."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro ao criar job: ' . $e->getMessage(),
                'total_deals' => count($deals)
            ];
        }
    }

    // Adiciona um comentário na timeline de um negócio (deal)
    public static function adicionarComentarioDeal($entityId, $dealId, $comment, $authorId = null)
    {
        // Se authorId não for passado, tenta buscar da global
        if (!$authorId) {
            $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
            $configJson = $configExtra ? json_decode($configExtra, true) : [];
            
            // A chave pode variar, então tentamos a mais comum primeiro
            // A lógica assume que a estrutura é ['SPA_XXXX' => ['bitrix_user_id_comments' => Y]]
            if (!empty($configJson)) {
                $firstSpaKey = array_key_first($configJson);
                $authorId = $configJson[$firstSpaKey]['bitrix_user_id_comments'] ?? null;
            }
        }

        $fields = [
            'COMMENT' => $comment
        ];

        if ($authorId) {
            $fields['AUTHOR_ID'] = (int)$authorId;
        }

        $params = [
            'fields' => [
                'ENTITY_ID' => (int)$dealId,
                'ENTITY_TYPE' => 'dynamic_' . $entityId,
                'COMMENT' => $comment
            ]
        ];

        if ($authorId) {
            $params['fields']['AUTHOR_ID'] = (int)$authorId;
        }

        $resultado = BitrixHelper::chamarApi('crm.timeline.comment.add', $params, ['log' => false]);

        // Log apenas em caso de erro
        if (!isset($resultado['result']) || empty($resultado['result'])) {
            LogHelper::logBitrixHelpers(
                "FALHA AO ADICIONAR COMENTÁRIO - DealID: $dealId - Erro: " . json_encode($resultado, JSON_UNESCAPED_UNICODE),
                __CLASS__ . '::' . __FUNCTION__
            );
            return ['success' => false, 'error' => $resultado['error_description'] ?? 'Erro desconhecido'];
        }

        return ['success' => true, 'result' => $resultado['result']];
    }
}
