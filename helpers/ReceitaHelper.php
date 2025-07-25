<?php
namespace Helpers;

class ReceitaHelper
{
    // Consulta dados de CNPJ na ReceitaWS
    public static function consultarCNPJ($cnpj)
    {
        $cnpj = preg_replace('/\D/', '', $cnpj); // Remove tudo que não for número
        if (strlen($cnpj) !== 14) {
            return ['erro' => 'CNPJ inválido'];
        }
        $url = "https://receitaws.com.br/v1/cnpj/{$cnpj}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $resposta = curl_exec($ch);
        $erro = curl_error($ch);
        curl_close($ch);
        if ($erro) {
            return ['erro' => 'Erro na consulta ReceitaWS: ' . $erro];
        }
        $dados = json_decode($resposta, true);
        if (isset($dados['status']) && $dados['status'] === 'ERROR') {
            return ['erro' => $dados['message'] ?? 'Erro desconhecido na ReceitaWS'];
        }
        return $dados;
    }
}
