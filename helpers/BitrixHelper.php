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

        $respostaJson = json_decode($resposta, true);
         
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $resumo = "[$traceId] Endpoint: $endpoint | HTTP: $httpCode | Erro: $curlErro";
        
        if (!empty($respostaJson['error_description'])) {
            $resumo .= " | Descrição: " . $respostaJson['error_description'];
        }

        LogHelper::logBitrixHelpers($resumo, __CLASS__ . '::' . __FUNCTION__);

        return $respostaJson;
    }

    // Consulta os campos de uma entidade CRM (SPA, Deals, etc.) no Bitrix24
    public static function consultarCamposSpa($entityTypeId)
    {
        // Para deals, usa endpoint específico
        if ($entityTypeId === 'crm.deal.fields') {
            $respostaApi = BitrixHelper::chamarApi('crm.deal.fields', []);
            return $respostaApi['result'] ?? [];
        }
        
        // Para SPAs, usa endpoint genérico
        $params = [
            'entityTypeId' => $entityTypeId,
        ];

        $respostaApi = BitrixHelper::chamarApi('crm.item.fields', $params);
        return $respostaApi['result']['fields'] ?? [];
    }
    
    // Formata os campos conforme o padrão esperado pelo Bitrix (camelCase)
    public static function formatarCampos($dados)
    {
        $fields = [];

        foreach ($dados as $campo => $valor) {
            // Se já está no padrão camelCase (ufCrm_ ou ufCrmXX_), não altera
            if (preg_match('/^ufCrm(\d+_)?\d+$/', $campo)) {
                $fields[$campo] = $valor;
                continue;
            }

            // Normaliza prefixos quebrados, aceita ufcrm_, uf_crm_, UF_CRM_...
            $campoNormalizado = strtoupper(str_replace(['ufcrm_', 'uf_crm_'], 'UF_CRM_', $campo));

            // SPA: UF_CRM_XX_YYYYYYY (XX = qualquer número de SPA, YYYYYYY = campo)
            if (preg_match('/^UF_CRM_(\d+)_([0-9]+)$/', $campoNormalizado, $m)) {
                $chaveConvertida = 'ufCrm' . $m[1] . '_' . $m[2];
                $fields[$chaveConvertida] = $valor;
            }
            // DEAL: UF_CRM_YYYYYYY
            elseif (preg_match('/^UF_CRM_([0-9]+)$/', $campoNormalizado, $m)) {
                $chaveConvertida = 'ufCrm_' . $m[1];
                $fields[$chaveConvertida] = $valor;
            }
            // Se não bate nenhum padrão, mantém como veio
            else {
                $fields[$campo] = $valor;
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

            // Log de progresso
            LogHelper::logSincronizacaoBitrix("Página {$paginaAtual} processada - {$totalPagina} itens encontrados. Total acumulado: {$totalGeral}");

        } while ($temProximaPagina && $totalPagina === 50); // Para quando não há mais páginas ou página incompleta

        return [
            'success' => true,
            'items' => $todosItens,
            'total' => count($todosItens),
            'paginas_processadas' => $paginaAtual
        ];
    }

}