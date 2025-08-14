<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

class BitrixDealHelper
{
    // Cria um ou vários negócios no Bitrix24 via API (método genérico com batch automático)
    public static function criarDeal($entityId, $categoryId, $fields): array
    {
        // Se $fields não é array de arrays, transforma em array único
        if (!isset($fields[0]) || !is_array($fields[0])) {
            $fields = [$fields];
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

        // MÚLTIPLOS DEALS - Divisão automática em chunks de 50 + rate limiting
        $chunks = array_chunk($fields, 50);
        $todosIds = [];
        $totalSucessos = 0;
        $totalErros = 0;
        $debugInfo = [];

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
                    if (isset($res['item']['id'])) {
                        $sucessosChunk++;
                        $idsChunk[] = $res['item']['id'];
                        $todosIds[] = $res['item']['id'];
                    } else {
                        $errosChunk++;
                    }
                }
            } else {
                $errosChunk = count($chunk); // Se não há result, todos falharam
            }

            $totalSucessos += $sucessosChunk;
            $totalErros += $errosChunk;
            
            // Debug info para este chunk
            $debugInfo["batch_$batchAtual"] = [
                'chunk_size' => count($chunk),
                'sucessos' => $sucessosChunk,
                'erros' => $errosChunk,
                'ids' => $idsChunk,
                'api_response' => $resultado
            ];

            // Rate limiting: aguarda 2 segundos entre batches (exceto no último)
            if ($batchAtual < $totalBatches) {
                sleep(2);
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

    // Edita um ou vários negócios existentes no Bitrix24 via API (método genérico com batch)
    public static function editarDeal($entityId, $dealId, array $fields): array
    {
        // Se $dealId é array = múltiplas edições, $fields deve ser array de arrays
        if (is_array($dealId)) {
            if (empty($dealId) || empty($fields)) {
                return [
                    'success' => false,
                    'error' => 'Arrays de IDs e fields não podem estar vazios para edição em batch.'
                ];
            }

            if (count($dealId) !== count($fields)) {
                return [
                    'success' => false,
                    'error' => 'Número de IDs deve ser igual ao número de arrays de fields.'
                ];
            }

            // Batch de edições
            $batchCommands = [];
            
            foreach ($dealId as $index => $id) {
                $fieldsFormatados = BitrixHelper::formatarCampos($fields[$index]);
                
                $params = [
                    'entityTypeId' => $entityId,
                    'id' => (int)$id,
                    'fields' => $fieldsFormatados
                ];
                
                $batchCommands["edit$index"] = 'crm.item.update?' . http_build_query($params);
            }
            
            $resultado = BitrixHelper::chamarApi('batch', ['cmd' => $batchCommands], [
                'log' => true
            ]);

            // Retorna resultado do batch com estatísticas
            $sucessos = 0;
            $erros = 0;
            
            if (isset($resultado['result']['result'])) {
                foreach ($resultado['result']['result'] as $key => $res) {
                    if (isset($res['item']) || (isset($res['result']) && $res['result'] === true)) {
                        $sucessos++;
                    } else {
                        $erros++;
                    }
                }
            }

            return [
                'success' => $sucessos > 0,
                'total_enviados' => count($dealId),
                'sucessos' => $sucessos,
                'erros' => $erros,
                'debug' => $resultado
            ];
        }

        // Edição individual (comportamento original)
        if (!$entityId || !$dealId || empty($fields)) {
            return [
                'success' => false,
                'error' => 'Parâmetros obrigatórios não informados.'
            ];
        }

        $fields = BitrixHelper::formatarCampos($fields);

        $params = [
            'entityTypeId' => $entityId,
            'id' => (int)$dealId,
            'fields' => $fields
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.update', $params, [
            'log' => true
        ]);

        if (isset($resultado['result'])) {
            return [
                'success' => true,
                'id' => $dealId
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao editar negócio.'
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