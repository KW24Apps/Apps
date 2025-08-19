<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/BatchJobDAO.php';

use Helpers\BitrixHelper;
use dao\BatchJobDAO;

class BitrixDealHelper
{
    // Cria um ou vários negócios no Bitrix24 via API (sempre em batch, sem agendamento)
    public static function criarDeal($entityId, $categoryId, $fields): array
    {
        error_log("=== DEBUG CRIAR DEAL ===");
        error_log("EntityId: " . $entityId);
        error_log("CategoryId: " . $categoryId);
        error_log("Total fields recebidos: " . count($fields));
        error_log("Primeiro field: " . print_r($fields[0] ?? 'nenhum', true));
        
        // Sempre trata $fields como array de arrays
        if (!isset($fields[0]) || !is_array($fields[0])) {
            $fields = [$fields];
        }

        // REABILITADO: Consultar metadados dos campos antes de criar
        $camposMetadata = BitrixHelper::consultarCamposCrm($entityId);
        
        // REABILITADO: Validar e formatar campos baseado nos metadados
        foreach ($fields as &$dealFields) {
            $dealFields = self::validarEFormatarCampos($dealFields, $camposMetadata);
        }

        // Sempre executa em batch, mesmo para 1 deal
        $chunks = array_chunk($fields, 25);
        $todosIds = [];
        $totalSucessos = 0;
        $totalErros = 0;

        $startTime = microtime(true);

        foreach ($chunks as $chunkIndex => $chunk) {
            error_log("Processando chunk " . ($chunkIndex + 1) . " com " . count($chunk) . " deals");
            
            $batchCommands = [];
            foreach ($chunk as $index => $dealFields) {
                error_log("DEBUG: Deal $index campos originais: " . print_r($dealFields, true));
                
                $formattedFields = BitrixHelper::formatarCampos($dealFields);
                
                // Garantir que categoryId seja sempre adicionado
                if ($categoryId) {
                    $formattedFields['categoryId'] = $categoryId;
                }
                
                // CORREÇÃO: Garantir que stageId seja preservado corretamente
                if (isset($dealFields['stageId']) && !empty($dealFields['stageId'])) {
                    $formattedFields['stageId'] = $dealFields['stageId'];
                }
                
                $params = [
                    'entityTypeId' => $entityId,
                    'fields' => $formattedFields
                ];
                $batchCommands["deal$index"] = 'crm.item.add?' . http_build_query($params);
                
                error_log("DEBUG: Deal $index comando: " . $batchCommands["deal$index"]);
            }
            
            error_log("Batch commands preparados: " . count($batchCommands));
            
            $resultado = BitrixHelper::chamarApi('batch', ['cmd' => $batchCommands], [
                'log' => true
            ]);
            
            // LOG ADICIONAL para debug
            LogHelper::logBitrixHelpers("BATCH DEBUG - Chunk processado com " . count($chunk) . " deals. Resposta: " . json_encode($resultado, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);
            
            $sucessosChunk = 0;
            $idsChunk = [];
            $errosChunk = [];
            
            if (isset($resultado['result']['result'])) {
                foreach ($resultado['result']['result'] as $key => $res) {
                    if (isset($res['item']['id'])) {
                        $sucessosChunk++;
                        $idsChunk[] = $res['item']['id'];
                        $todosIds[] = $res['item']['id'];
                    } elseif (isset($res['result']['item']['id'])) {
                        $sucessosChunk++;
                        $idsChunk[] = $res['result']['item']['id'];
                        $todosIds[] = $res['result']['item']['id'];
                    } elseif (isset($res['result']) && is_numeric($res['result'])) {
                        $sucessosChunk++;
                        $idsChunk[] = $res['result'];
                        $todosIds[] = $res['result'];
                    } else {
                        // LOG do erro específico
                        LogHelper::logBitrixHelpers("DEAL FALHOU - Key: $key - Erro: " . json_encode($res, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);
                    }
                }
            } else {
                error_log("ERRO: Resposta da API não tem estrutura esperada");
                error_log("Resposta completa: " . print_r($resultado, true));
            }
            
            if (!empty($errosChunk)) {
                error_log("ERROS encontrados no chunk " . ($chunkIndex + 1) . ": " . print_r($errosChunk, true));
            }
            
            $totalSucessos += $sucessosChunk;
            $totalErros += count($chunk) - $sucessosChunk;
            
            // LOG de resumo do chunk
            LogHelper::logBitrixHelpers("CHUNK RESUMO - Sucessos: $sucessosChunk | Erros: " . (count($chunk) - $sucessosChunk) . " | IDs: " . implode(',', $idsChunk), __CLASS__ . '::' . __FUNCTION__);
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
    public static function editarDeal($entityId, $dealId, array $fields): array
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
        $chunks = array_chunk($editData, 25);
        $todosIds = [];
        $totalSucessos = 0;
        $totalErros = 0;

        $startTime = microtime(true);

        foreach ($chunks as $chunk) {
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

    /**
     * Valida e formata campos baseado nos metadados do Bitrix
     */
    private static function validarEFormatarCampos($fields, $metadata)
    {
        $fieldsFormatados = [];
        
        foreach ($fields as $campo => $valor) {
            // Primeiro, formatar o nome do campo
            $campoFormatado = array_key_first(BitrixHelper::formatarCampos([$campo => null]));
            
            // Verificar se existe metadados para este campo
            if (!isset($metadata[$campoFormatado])) {
                // Campo não existe nos metadados, manter como está
                $fieldsFormatados[$campoFormatado] = $valor;
                continue;
            }
            
            $meta = $metadata[$campoFormatado];
            $tipo = $meta['type'] ?? 'string';
            $isMultiple = $meta['isMultiple'] ?? false;
            
            // Formatar valor baseado no tipo
            $valorFormatado = self::formatarValorPorTipo($valor, $tipo, $isMultiple);
            
            $fieldsFormatados[$campoFormatado] = $valorFormatado;
        }
        
        return $fieldsFormatados;
    }
    
    /**
     * Formata valor baseado no tipo do campo
     */
    private static function formatarValorPorTipo($valor, $tipo, $isMultiple)
    {
        // Se valor é nulo ou vazio, retorna como está
        if ($valor === null || $valor === '') {
            return $valor;
        }
        
        switch ($tipo) {
            case 'integer':
            case 'double':
                return $isMultiple ? (is_array($valor) ? array_map('intval', $valor) : [(int)$valor]) : (int)$valor;
                
            case 'boolean':
                return $isMultiple ? (is_array($valor) ? array_map('boolval', $valor) : [(bool)$valor]) : (bool)$valor;
                
            case 'enumeration':
                // Para campos de seleção, manter como está (IDs)
                return $isMultiple ? (is_array($valor) ? $valor : [$valor]) : $valor;
                
            case 'crm_entity':
                // Para campos de entidades (company, contact, deal)
                return $isMultiple ? (is_array($valor) ? array_map('intval', $valor) : [(int)$valor]) : (int)$valor;
                
            case 'string':
            case 'text':
            default:
                return $isMultiple ? (is_array($valor) ? $valor : [$valor]) : (string)$valor;
        }
    }

}