<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixBatchHelper.php';

use Helpers\BitrixHelper;
use Helpers\BitrixBatchHelper;

class BitrixDealHelper
{
    // Cria um ou vários negócios no Bitrix24 via API (método genérico com batch automático)
    public static function criarDeal($entityId, $categoryId, $fields): array
    {
        // Se $fields não é array de arrays, transforma em array único
        if (!isset($fields[0]) || !is_array($fields[0])) {
            $fields = [$fields];
        }

        $totalDeals = count($fields);

        // REGRA DOS 50: Volumes grandes precisam de processamento assíncrono
        if ($totalDeals > 50) {
            // Cria job assíncrono usando BitrixBatchHelper
            $dados = [
                'spa' => $entityId,
                'category_id' => $categoryId,
                'deals' => $fields
            ];
            
            $jobId = BitrixBatchHelper::criarJob('criar_deals', $dados);
            
            return [
                'status' => 'aceito_para_processamento',
                'job_id' => $jobId,
                'total_solicitado' => $totalDeals,
                'limite_sincrono' => 50,
                'mensagem' => "Volume de $totalDeals deals enviado para processamento assíncrono.",
                'consultar_status' => "Use BitrixBatchHelper::consultarStatus('$jobId') para acompanhar o progresso."
            ];
        }

        // Se é apenas 1 deal, usa método individual para manter compatibilidade de resposta
        if (count($fields) === 1) {
            $formattedFields = BitrixHelper::formatarCampos($fields[0]);

            if ($categoryId) {
                $formattedFields['categoryId'] = $categoryId;
            }

            $params = [
                'entityTypeId' => $entityId,
                'fields' => $formattedFields
            ];

            $resultado = BitrixHelper::chamarApi('crm.item.add', $params, [
                'log' => true
            ]);

            if (isset($resultado['result']['item']['id'])) {
                return [
                    'status' => 'sucesso',
                    'quantidade' => 1,
                    'ids' => $resultado['result']['item']['id'],
                    'mensagem' => '1 deal criado com sucesso'
                ];
            }

            return [
                'status' => 'erro',
                'quantidade' => 0,
                'ids' => '',
                'mensagem' => $resultado['error_description'] ?? 'Erro desconhecido ao criar negócio.'
            ];
        }

        // MÚLTIPLOS DEALS - Divisão automática em chunks de 25 + rate limiting
        $chunks = array_chunk($fields, 25);
        $todosIds = [];
        $totalSucessos = 0;
        $totalErros = 0;
        $debugInfo = [];

        // LOG INICIAL
        $logInicial = date('Y-m-d H:i:s') . " | INICIANDO CRIAÇÃO BATCH | TOTAL DEALS: " . count($fields) . " | CHUNKS: " . count($chunks) . "\n";
        file_put_contents(__DIR__ . '/../../logs/debug_batch.log', $logInicial, FILE_APPEND);

        foreach ($chunks as $chunkIndex => $chunk) {
            // Log do progresso
            $batchAtual = $chunkIndex + 1;
            $totalBatches = count($chunks);
            
            // Monta batch commands para este chunk
            $batchCommands = [];
            
            foreach ($chunk as $index => $dealFields) {
                $formattedFields = BitrixHelper::formatarCampos($dealFields);
                
                if ($categoryId) {
                    $formattedFields['categoryId'] = $categoryId;
                }
                
                $params = [
                    'entityTypeId' => $entityId,
                    'fields' => $formattedFields
                ];
                
                $batchCommands["deal$index"] = 'crm.item.add?' . http_build_query($params);
            }
            
            // Executa o batch para este chunk
            $resultado = BitrixHelper::chamarApi('batch', ['cmd' => $batchCommands], [
                'log' => true
            ]);

            // Processa resultado do chunk
            $sucessosChunk = 0;
            $errosChunk = 0;
            $idsChunk = [];
            
            if (isset($resultado['result']['result'])) {
                foreach ($resultado['result']['result'] as $key => $res) {
                    // Verifica múltiplas formas de sucesso
                    if (isset($res['item']['id'])) {
                        // Formato padrão de sucesso
                        $sucessosChunk++;
                        $idsChunk[] = $res['item']['id'];
                        $todosIds[] = $res['item']['id'];
                    } elseif (isset($res['result']['item']['id'])) {
                        // Formato alternativo de sucesso
                        $sucessosChunk++;
                        $idsChunk[] = $res['result']['item']['id'];
                        $todosIds[] = $res['result']['item']['id'];
                    } elseif (isset($res['result']) && is_numeric($res['result'])) {
                        // Sucesso retornando apenas ID numérico
                        $sucessosChunk++;
                        $idsChunk[] = $res['result'];
                        $todosIds[] = $res['result'];
                    } else {
                        $errosChunk++;
                    }
                }
            } elseif (isset($resultado['result']) && is_array($resultado['result'])) {
                // Formato de resposta diferente - tenta interpretar
                $errosChunk = 0; // Reset para recontagem
                foreach ($chunk as $index => $item) {
                    // Se chegou aqui, assume que pode ter sido criado
                    // Mas não conseguimos extrair o ID
                    $errosChunk++;
                }
            } else {
                $errosChunk = count($chunk); // Se não há result válido, todos falharam
            }

            $totalSucessos += $sucessosChunk;
            $totalErros += $errosChunk;
            
            // LOG POR BATCH
            $logBatch = date('Y-m-d H:i:s') . " | BATCH $batchAtual FINALIZADO | SUCESSOS: $sucessosChunk | ERROS: $errosChunk | IDS: " . implode(',', $idsChunk) . "\n";
            file_put_contents(__DIR__ . '/../../logs/debug_batch.log', $logBatch, FILE_APPEND);
            
            // Debug info DETALHADO para este chunk
            $debugInfo["batch_$batchAtual"] = [
                'chunk_size' => count($chunk),
                'sucessos' => $sucessosChunk,
                'erros' => $errosChunk,
                'ids' => $idsChunk,
                'raw_response' => $resultado, // RESPOSTA COMPLETA para debug
                'batch_index' => $batchAtual
            ];

            // Rate limiting: aguarda 1 segundo entre batches (exceto no último)
            if ($batchAtual < $totalBatches) {
                sleep(1);
            }
        }

        // Retorna resultado consolidado de todos os batches - FORMATO LIMPO
        $idsString = implode(', ', $todosIds);
        
        return [
            'status' => $totalSucessos > 0 ? 'sucesso' : 'erro',
            'quantidade' => $totalSucessos,
            'ids' => $idsString,
            'mensagem' => $totalSucessos > 0 
                ? "$totalSucessos deals criados com sucesso" . ($totalErros > 0 ? " ($totalErros falharam)" : "")
                : "Falha ao criar deals: $totalErros erros"
        ];
    }

    // Edita um ou vários negócios existentes no Bitrix24 via API (método genérico com batch automático)
    public static function editarDeal($entityId, $dealId, array $fields): array
    {
        // Detecta se é edição individual ou múltipla
        $isMultiple = is_array($dealId);
        
        if ($isMultiple) {
            // MÚLTIPLAS EDIÇÕES - Validações
            if (empty($dealId) || empty($fields)) {
                return [
                    'status' => 'erro',
                    'quantidade' => 0,
                    'ids' => '',
                    'mensagem' => 'Arrays de IDs e fields não podem estar vazios para edição em batch.'
                ];
            }

            $totalDeals = count($dealId);

            // REGRA DOS 50: Volumes grandes precisam de processamento assíncrono
            if ($totalDeals > 50) {
                // Cria job assíncrono usando BitrixBatchHelper
                $dados = [
                    'spa' => $entityId,
                    'deal_ids' => $dealId,
                    'fields_array' => $fields
                ];
                
                $jobId = BitrixBatchHelper::criarJob('editar_deals', $dados);
                
                return [
                    'status' => 'aceito_para_processamento',
                    'job_id' => $jobId,
                    'total_solicitado' => $totalDeals,
                    'limite_sincrono' => 50,
                    'mensagem' => "Volume de $totalDeals edições enviado para processamento assíncrono.",
                    'consultar_status' => "Use BitrixBatchHelper::consultarStatus('$jobId') para acompanhar o progresso."
                ];
            }

            if (count($dealId) !== count($fields)) {
                return [
                    'status' => 'erro',
                    'quantidade' => 0,
                    'ids' => '',
                    'mensagem' => 'Número de IDs deve ser igual ao número de arrays de fields.'
                ];
            }

            // Prepara dados para processamento em chunks
            $editData = [];
            foreach ($dealId as $index => $id) {
                $editData[] = [
                    'id' => $id,
                    'fields' => $fields[$index]
                ];
            }
        } else {
            // EDIÇÃO INDIVIDUAL - Converte para formato múltiplo para processamento uniforme
            if (!$entityId || !$dealId || empty($fields)) {
                return [
                    'status' => 'erro',
                    'quantidade' => 0,
                    'ids' => '',
                    'mensagem' => 'Parâmetros obrigatórios não informados.'
                ];
            }

            $editData = [[
                'id' => $dealId,
                'fields' => $fields
            ]];
        }

        // Se é apenas 1 edição, usa método individual para manter compatibilidade
        if (count($editData) === 1) {
            $fieldsFormatados = BitrixHelper::formatarCampos($editData[0]['fields']);

            $params = [
                'entityTypeId' => $entityId,
                'id' => (int)$editData[0]['id'],
                'fields' => $fieldsFormatados
            ];

            $resultado = BitrixHelper::chamarApi('crm.item.update', $params, [
                'log' => true
            ]);

            if (isset($resultado['result'])) {
                return [
                    'status' => 'sucesso',
                    'quantidade' => 1,
                    'ids' => $editData[0]['id'],
                    'mensagem' => '1 deal editado com sucesso'
                ];
            }

            return [
                'status' => 'erro',
                'quantidade' => 0,
                'ids' => '',
                'mensagem' => $resultado['error_description'] ?? 'Erro desconhecido ao editar negócio.'
            ];
        }

        // MÚLTIPLAS EDIÇÕES - Divisão automática em chunks de 50 + rate limiting
        $chunks = array_chunk($editData, 50);
        $todosIds = [];
        $totalSucessos = 0;
        $totalErros = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            // Log do progresso
            $batchAtual = $chunkIndex + 1;
            $totalBatches = count($chunks);
            
            // Monta batch commands para este chunk
            $batchCommands = [];
            
            foreach ($chunk as $index => $editItem) {
                $fieldsFormatados = BitrixHelper::formatarCampos($editItem['fields']);
                
                $params = [
                    'entityTypeId' => $entityId,
                    'id' => (int)$editItem['id'],
                    'fields' => $fieldsFormatados
                ];
                
                $batchCommands["edit$index"] = 'crm.item.update?' . http_build_query($params);
            }
            
            // Executa o batch para este chunk
            $resultado = BitrixHelper::chamarApi('batch', ['cmd' => $batchCommands], [
                'log' => true
            ]);

            // Processa resultado do chunk
            $sucessosChunk = 0;
            $errosChunk = 0;
            $idsChunk = [];
            
            if (isset($resultado['result']['result'])) {
                foreach ($resultado['result']['result'] as $key => $res) {
                    $chunkItemIndex = (int)str_replace('edit', '', $key);
                    $dealId = $chunk[$chunkItemIndex]['id'];
                    
                    if (isset($res['item']) || (isset($res['result']) && $res['result'] === true)) {
                        $sucessosChunk++;
                        $idsChunk[] = $dealId;
                        $todosIds[] = $dealId;
                    } else {
                        $errosChunk++;
                    }
                }
            } else {
                $errosChunk = count($chunk); // Se não há result, todos falharam
            }

            $totalSucessos += $sucessosChunk;
            $totalErros += $errosChunk;

            // Rate limiting: aguarda 1 segundo entre batches (exceto no último)
            if ($batchAtual < $totalBatches) {
                sleep(1);
            }
        }

        // Retorna resultado consolidado de todos os batches - FORMATO LIMPO
        $idsString = implode(', ', $todosIds);
        
        return [
            'status' => $totalSucessos > 0 ? 'sucesso' : 'erro',
            'quantidade' => $totalSucessos,
            'ids' => $idsString,
            'mensagem' => $totalSucessos > 0 
                ? "$totalSucessos deals editados com sucesso" . ($totalErros > 0 ? " ($totalErros falharam)" : "")
                : "Falha ao editar deals: $totalErros erros"
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

        if (!in_array('id', $fields)) {
            array_unshift($fields, 'id');
        }

        // 2. Consulta o negócio (deal)
        $params = [
            'entityTypeId' => $entityId,
            'id' => $dealId,
        ];
        $respostaApi = BitrixHelper::chamarApi('crm.item.get', $params, []);
        $dadosBrutos = $respostaApi['result']['item'] ?? [];

        // 3. Consulta os campos da SPA
        $camposSpa = BitrixHelper::consultarCamposCrm($entityId);

        // 4. Consulta as etapas do tipo
        $etapas = BitrixHelper::consultarEtapasPorTipo($entityId);

        // 5. Formata os campos para o padrão camelCase
        $camposFormatados = BitrixHelper::formatarCampos(array_fill_keys($fields, null));
        
        $valoresBrutos = [];
        foreach (array_keys($camposFormatados) as $campoConvertido) {
            $valoresBrutos[$campoConvertido] = $dadosBrutos[$campoConvertido] ?? null;
        }
        
        // Garantir que companyId seja incluído se existir nos dados brutos
        if (isset($dadosBrutos['companyId']) && !isset($valoresBrutos['companyId'])) {
            $valoresBrutos['companyId'] = $dadosBrutos['companyId'];
        }

        // 6. Mapeia valores enumerados
        $valoresConvertidos = BitrixHelper::mapearValoresEnumerados($valoresBrutos, $camposSpa);

        // 7. Mapeia o nome amigável da etapa, se existir campo de etapa
        $stageName = null;
        if (isset($valoresBrutos['stageId'])) {
            $stageName = BitrixHelper::mapearEtapaPorId($valoresBrutos['stageId'], $etapas);
        }

        // 8. Monta resposta amigável SEMPRE incluindo todos os campos solicitados
        $resultadoFinal = [];
        foreach ($fields as $campoOriginal) {
            // Converte o campo para o padrão Bitrix
            $campoConvertidoArr = BitrixHelper::formatarCampos([$campoOriginal => null]);
            $campoConvertido = array_key_first($campoConvertidoArr);
            $valorBruto = $valoresBrutos[$campoConvertido] ?? null;
            $valorConvertido = $valoresConvertidos[$campoConvertido] ?? $valorBruto;
            $spa = $camposSpa[$campoConvertido] ?? [];
            $nomeAmigavel = $spa['title'] ?? $campoOriginal;
            $texto = $valorConvertido;
            $type = $spa['type'] ?? null;
            $isMultiple = $spa['isMultiple'] ?? false;
            // Se for stageId, usa o nome da etapa como texto
            if ($campoConvertido === 'stageId') {
                $texto = $stageName ?? $valorBruto;
                $nomeAmigavel = 'Fase';
            }
            $resultadoFinal[$campoConvertido] = [
                'nome' => $nomeAmigavel,
                'valor' => $valorBruto,
                'texto' => $texto,
                'type' => $type,
                'isMultiple' => $isMultiple
            ];
        }
        // Sempre inclui o id bruto
        if (isset($valoresBrutos['id'])) {
            $resultadoFinal['id'] = $valoresBrutos['id'];
        }

        return ['result' => $resultadoFinal];
    }

}