<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;

class BitrixHelper
{
    // Envia requisição para API Bitrix com endpoint e parâmetros fornecidos
    public static function chamarApi($endpoint, $params, $opcoes = [])
    {
        $webhookBase = trim($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] ?? '');

        if (!$webhookBase) {
            LogHelper::logBitrixHelpers("Webhook não informado para chamada do endpoint: $endpoint", __CLASS__ . '::' . __FUNCTION__);
            return ['error' => 'Webhook não informado'];
        }

        $url = $webhookBase . '/' . $endpoint . '.json';
        $postData = http_build_query($params);
        
        $startTime = microtime(true);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $resposta = curl_exec($ch);
        $curlErro = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $endTime = microtime(true);
        $tempoExecucao = round(($endTime - $startTime) * 1000, 2);
        $respostaJson = json_decode($resposta, true);
         
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';

        // Prepara parâmetros para o log, ocultando dados sensíveis
        $paramsParaLog = $params;
        if (isset($paramsParaLog['webhook'])) {
            $paramsParaLog['webhook'] = '[WEBHOOK OCULTO]';
        }
        
        // Verificar se houve erro na resposta da API
        if (isset($respostaJson['error']) || isset($respostaJson['error_description']) || $httpCode >= 400 || !empty($curlErro)) {
            $logCompleto = "[$traceId] Endpoint: $endpoint | HTTP: $httpCode | Tempo: {$tempoExecucao}ms";
            
            // Adicionar erro cURL se houver
            if (!empty($curlErro)) {
                $logCompleto .= " | cURL Erro: $curlErro";
            }
            
            $logCompleto .= " | STATUS: ERRO";
            
            // Adicionar detalhes do erro da API
            if (!empty($respostaJson['error'])) {
                $logCompleto .= " | API Error Code: " . $respostaJson['error'];
            }
            
            if (!empty($respostaJson['error_description'])) {
                $logCompleto .= " | API Error Desc: " . $respostaJson['error_description'];
            }
            
            // Log da resposta RAW para debug completo de erros
            $logCompleto .= " | Raw Response: " . substr($resposta, 0, 1000) . (strlen($resposta) > 1000 ? '...[truncated]' : '');
            
            // Log dos parâmetros completos em caso de erro
            $logCompleto .= " | Full Params: " . json_encode($paramsParaLog, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            
            LogHelper::logBitrixHelpers($logCompleto, __CLASS__ . '::' . __FUNCTION__);
        }

        return $respostaJson;
    }

    // Consulta os campos de uma entidade CRM (SPA, Deals, etc.) no Bitrix24
    public static function consultarCamposCrm($entityTypeId)
    {
        // Usa endpoint genérico para todas as entidades (deals=2, contacts=3, companies=4, SPAs=outros números)
        $params = [
            'entityTypeId' => $entityTypeId,
        ];

        $respostaApi = BitrixHelper::chamarApi('crm.item.fields', $params);
        return $respostaApi['result']['fields'] ?? [];
    }
    
    // Formata os campos conforme o padrão esperado pelo Bitrix (camelCase)
    public static function formatarCampos($dados, $entityTypeId = null, $validarValores = false)
    {
        $metadata = [];
        if ($validarValores && $entityTypeId) {
            $metadata = self::consultarCamposCrm($entityTypeId);
        }

        $fields = [];
        $camposInvalidos = ['typeId'];

        foreach ($dados as $campo => $valor) {
            if (in_array($campo, $camposInvalidos)) {
                continue;
            }

            $campoFormatado = $campo;
            // Verifica se o campo já está no formato Bitrix (ufCrm_XXXX ou UF_CRM_XXXX)
            // Se sim, mantém o nome original para evitar formatação desnecessária.
            // A regex foi ajustada para ser mais abrangente e case-insensitive.
            // Verifica se o campo já está no formato camelCase (ufCrm_ ou ufCrmIDSPA_)
            // ou se é um nome amigável (não começa com UF_CRM_). Se sim, não altera.
            // Padrões para campos já formatados em camelCase:
            // ufCrm_12345 (Deal/Company/Contact)
            // ufCrm191_12345 (SPA)
            if (preg_match('/^ufCrm_(\d+)$/', $campo) || preg_match('/^ufCrm(\d+)_(\d+)$/', $campo)) {
                $campoFormatado = $campo;
            } 
            // Se o campo começa com UF_CRM_ (maiúsculo), aplica a conversão.
            else if (preg_match('/^UF_CRM_/i', $campo)) { // Case-insensitive para UF_CRM_
                $campoNormalizado = strtoupper($campo); // Normaliza para maiúsculas para as regexes específicas
                
                // Padrão para SPA: UF_CRM_idspa_codigo -> ufCrmidspa_codigo
                if (preg_match('/^UF_CRM_(\d+)_(\d+)$/', $campoNormalizado, $m)) {
                    $campoFormatado = 'ufCrm' . $m[1] . '_' . $m[2];
                } 
                // Padrão para Deal/Company/Contact: UF_CRM_codigo -> ufCrm_codigo
                elseif (preg_match('/^UF_CRM_(\d+)$/', $campoNormalizado, $m)) {
                    $campoFormatado = 'ufCrm_' . $m[1];
                } 
                // Se for UF_CRM_ mas não corresponder aos padrões conhecidos, mantém o original
                else {
                    $campoFormatado = $campo;
                }
            }
            // Se não for UF_CRM_ e não for ufCrm_ (camelCase), é um nome amigável.
            else {
                $campoFormatado = $campo;
            }
            if ($validarValores && !empty($metadata) && isset($metadata[$campoFormatado])) {
                $meta = $metadata[$campoFormatado];
                $tipo = $meta['type'] ?? 'string';
                $isMultiple = $meta['isMultiple'] ?? false;
                $fields[$campoFormatado] = self::formatarValorPorTipo($valor, $tipo, $isMultiple);
            } else {
                $fields[$campoFormatado] = $valor;
            }
        }

        return $fields;
    }
  
    // Mapeia valores enumerados de campos UF_CRM_* para seus textos correspondentes
    public static function mapearValoresEnumerados($dados, $fields)
    {
        foreach ($fields as $uf => $definicaoCampo) {
            if (!isset($dados[$uf])) {
                continue;
            }
            if (isset($definicaoCampo['type']) && $definicaoCampo['type'] === 'enumeration' && isset($definicaoCampo['items'])) {
                // Monta o mapa ID => VALUE para esse campo
                $mapa = [];
                foreach ($definicaoCampo['items'] as $item) {
                    $mapa[$item['ID']] = $item['VALUE'];
                }
                // Troca os valores numéricos por textos
                if (is_array($dados[$uf])) {
                    $dados[$uf] = array_map(function($v) use ($mapa) {
                        return $mapa[$v] ?? $v;
                    }, $dados[$uf]);
                } else {
                    $dados[$uf] = $mapa[$dados[$uf]] ?? $dados[$uf];
                }
            }
        }
        return $dados;
    }

    // Consulta as etapas de um tipo de entidade no Bitrix24 (usando crm.status.list para deals)
    public static function consultarEtapasPorTipo($entityTypeId)
    {
        $params = [
            'entityId' => $entityTypeId
        ];
        $resposta = BitrixHelper::chamarApi('crm.status.list', $params, []);
        return $resposta['result'] ?? [];
    }

    // Retorna o nome amigável da etapa a partir do ID e do array de etapas
    public static function mapearEtapaPorId($stageId, $stages)
    {
        foreach ($stages as $stage) {
            if (
                (isset($stage['ID']) && $stage['ID'] == $stageId) ||
                (isset($stage['STATUS_ID']) && $stage['STATUS_ID'] == $stageId) ||
                (isset($stage['statusId']) && $stage['statusId'] == $stageId) ||
                (isset($stage['id']) && $stage['id'] == $stageId)
            ) {
                return $stage['NAME'] ?? $stage['name'] ?? $stageId;
            }
        }
        return $stageId; // Se não encontrar, retorna o próprio ID
    }

    // Consulta as etapas de uma entidade CRM (usando o método mais novo crm.status.entity.items)
    public static function consultarEtapasCrmItem($entityId)
    {
        $params = [
            'entityId' => $entityId
        ];
        $resposta = BitrixHelper::chamarApi('crm.status.entity.items', $params, []);
        return $resposta['result']['items'] ?? $resposta['result'] ?? []; // A API pode retornar 'items' ou não
    }

    // Lista todos os itens de uma entidade CRM (genérico para Company, Deal, SPA, Contact) com paginação
    public static function listarItensCrm($entityTypeId, $filtros = [], $campos = ['*'], $limite = null)
    {
        $todosItens = [];
        $start = 0;
        $totalGeral = 0;
        $paginaAtual = 1;

        do {
            $params = [
                'entityTypeId' => $entityTypeId,
                'select' => $campos,
                'filter' => $filtros,
                'start' => $start
            ];

            $resultado = self::chamarApi('crm.item.list', $params, [
                'log' => true
            ]);

            if (!isset($resultado['result']['items'])) {
                return [
                    'success' => false,
                    'debug' => $resultado,
                    'error' => $resultado['error_description'] ?? 'Erro desconhecido ao listar itens CRM.'
                ];
            }

            $itensPagina = $resultado['result']['items'];
            $totalPagina = count($itensPagina);
            
            // Adiciona os itens desta página ao array total
            $todosItens = array_merge($todosItens, $itensPagina);
            $totalGeral += $totalPagina;

            // Verifica se há próxima página - o 'next' vem diretamente no resultado
            $temProximaPagina = isset($resultado['next']) && $resultado['next'] > 0;
            
            // Se há limite definido e já atingiu, para
            if ($limite && $totalGeral >= $limite) {
                $todosItens = array_slice($todosItens, 0, $limite);
                break;
            }

            // Prepara próxima página
            $start += 50; // Bitrix sempre retorna 50 por página
            $paginaAtual++;

        } while ($temProximaPagina && $totalPagina === 50); // Para quando não há mais páginas ou página incompleta

        return [
            'success' => true,
            'items' => $todosItens,
            'total' => count($todosItens),
            'paginas_processadas' => $paginaAtual
        ];
    }

    // Formatar Valor por Tipo
    private static function formatarValorPorTipo($valor, $tipo, $isMultiple)
    {
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
            case 'crm_entity':
                return $isMultiple ? (is_array($valor) ? $valor : [$valor]) : $valor;
                
            case 'string':
            case 'text':
            default:
                if ($isMultiple) {
                    return is_array($valor) ? $valor : [$valor];
                }
                return is_array($valor) ? implode(', ', $valor) : (string)$valor;
        }
    }

     /**
     * Adiciona um comentário na timeline de qualquer entidade do Bitrix24.
     *
     * @param string $entityType O tipo da entidade (ex: 'deal', 'company', 'dynamic_191').
     * @param int $entityId O ID da entidade.
     * @param string $comment O texto do comentário.
     * @param int|null $authorId O ID do autor do comentário (opcional).
     * @return array A resposta da API.
     */
     public static function adicionarComentarioTimeline(string $entityType, int $entityId, string $comment, ?int $authorId = null)
    {
        // Se authorId não for passado, tenta buscar da global
        if (!$authorId) {
            $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
            $configJson = $configExtra ? json_decode($configExtra, true) : [];
            
            if (!empty($configJson)) {
                $firstSpaKey = array_key_first($configJson);
                $authorId = $configJson[$firstSpaKey]['bitrix_user_id_comments'] ?? null;
            }
        }

        $params = [
            'fields' => [
                'ENTITY_ID' => $entityId,
                'ENTITY_TYPE' => $entityType,
                'COMMENT' => $comment
            ]
        ];

        if ($authorId) {
            $params['fields']['AUTHOR_ID'] = (int)$authorId;
        }

        $resultado = self::chamarApi('crm.timeline.comment.add', $params, ['log' => false]);

        // Log apenas em caso de erro
        if (!isset($resultado['result']) || empty($resultado['result'])) {
            LogHelper::logBitrixHelpers(
                "FALHA AO ADICIONAR COMENTÁRIO - EntityID: $entityId, EntityType: $entityType - Erro: " . json_encode($resultado, JSON_UNESCAPED_UNICODE),
                __CLASS__ . '::' . __FUNCTION__
            );
            return ['success' => false, 'error' => $resultado['error_description'] ?? 'Erro desconhecido'];
        }

        return ['success' => true, 'result' => $resultado['result']];
    }
}
