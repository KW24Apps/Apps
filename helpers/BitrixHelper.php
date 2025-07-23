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
        file_put_contents(__DIR__ . '/../logs/01.log', date('c') . " | WEBHOOK:$webhookBase | ENDPOINT:$endpoint | PARAMS:" . json_encode($params) . "\n", FILE_APPEND);

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
    
    // Formata os campos conforme o padrão esperado pelo Bitrix (camelCase)
    public static function formatarCampos($dados)
    {
        $fields = [];

        foreach ($dados as $campo => $valor) {
            // Normaliza prefixos quebrados, aceita ufcrm_, uf_crm_, UF_CRM_...
            $campoNormalizado = strtoupper(str_replace(['ufcrm_', 'uf_crm_'], 'UF_CRM_', $campo));

            // Identifica se é SPA (tem _41_) ou DEAL (não tem _41_)
            if (strpos($campoNormalizado, 'UF_CRM_41_') === 0) {
                // SPA: mantem ufCrm41_...
                $chaveConvertida = 'ufCrm41_' . substr($campoNormalizado, 10);
                $fields[$chaveConvertida] = $valor;
            } elseif (strpos($campoNormalizado, 'UF_CRM_') === 0) {
                // DEAL: ufCrm_...
                $chaveConvertida = 'ufCrm_' . substr($campoNormalizado, 7);
                $fields[$chaveConvertida] = $valor;
            } else {
                $fields[$campo] = $valor;
            }
        }

        return $fields;
    }
  

}