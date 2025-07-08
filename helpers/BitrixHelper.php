<?php

class BitrixHelper


{
    // Envia requisição para API Bitrix com endpoint e parâmetros fornecidos
    public static function chamarApi($endpoint, $params, $opcoes = [])
    {
        $webhookBase = $opcoes['webhook'] ?? '';
        if (!$webhookBase) {
            return ['error' => 'Webhook não informado'];
        }

        $logAtivo = $opcoes['log'] ?? false;
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

        if ($logAtivo) {
            $mensagem = "==== CHAMADA API ====\n";
            $mensagem .= "Endpoint: $endpoint\nURL: $url\nDados: $postData\nHTTP: $httpCode\nErro: $curlErro\nResposta: $resposta\n";
            $mensagem .= "Campos enviados (params): " . print_r($params, true) . "\n";
            LogHelper::logBitrixHelpers($mensagem, "BitrixHelper::chamarApi");
        }
        return $respostaJson;
    }
    
    // Formata os campos conforme o padrão esperado pelo Bitrix (camelCase)
    public static function formatarCampos($dados)
    {
        $fields = [];

        foreach ($dados as $campo => $valor) {
            if (strpos($campo, 'UF_CRM_') === 0) {
                $chaveConvertida = 'ufCrm' . substr($campo, 7);
                $fields[$chaveConvertida] = $valor;
            } else {
                $fields[$campo] = $valor;
            }
        }

        return $fields;
    }

}