<?php
namespace Helpers;

require_once __DIR__ . '/LogHelper.php'; // Adicionado o require_once para LogHelper

use Helpers\LogHelper;

class ReceitaFederalHelper
{
    private const API_RECEITA_FEDERAL_URL = "https://minhareceita.org/";

    /**
     * Consulta a API minhareceita.org para obter dados de um CNPJ.
     *
     * @param string $cnpj O CNPJ a ser consultado (apenas números).
     * @return array Retorna um array associativo com os dados da Receita Federal ou um erro.
     */
    public static function consultarCnpj(string $cnpj): array
    {
        $url = self::API_RECEITA_FEDERAL_URL . $cnpj;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 segundos

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            LogHelper::logReceitaFederal("Erro na requisição cURL para CNPJ $cnpj: $error", __CLASS__ . '::' . __FUNCTION__);
            return ['erro' => "Erro na requisição cURL: $error"];
        }

        // Log da resposta bruta da API externa, independentemente do status HTTP
        LogHelper::logReceitaFederal("Resposta bruta da API minhareceita.org para CNPJ $cnpj (HTTP $httpCode): " . $response, __CLASS__ . '::' . __FUNCTION__);

        if ($httpCode !== 200) {
            LogHelper::logReceitaFederal("Erro HTTP $httpCode ao consultar a API da Receita Federal para CNPJ $cnpj. Resposta: $response", __CLASS__ . '::' . __FUNCTION__);
            return ['erro' => "Erro HTTP $httpCode ao consultar a API da Receita Federal. Resposta: " . $response];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            LogHelper::logReceitaFederal("Erro ao decodificar JSON da API da Receita Federal para CNPJ $cnpj: " . json_last_error_msg(), __CLASS__ . '::' . __FUNCTION__);
            return ['erro' => "Erro ao decodificar JSON da API da Receita Federal: " . json_last_error_msg()];
        }

        return $data;
    }
}
