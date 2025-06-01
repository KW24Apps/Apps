<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/FormataHelper.php';

class ExtensoController
{
    public function executar($params)
    {
        $cliente = $params['cliente'] ?? null;
        $spa = $params['spa'] ?? null;
        $dealId = $params['deal'] ?? null;

        $camposBitrix = array_filter($params, fn($v) => strpos($v, 'UF_CRM_') === 0);
        $camposSelecionados = array_values($camposBitrix);

        $campoValor = $camposSelecionados[0] ?? null;
        $campoRetorno = $camposSelecionados[1] ?? null;

        if (!$cliente || !$spa || !$dealId || !$campoValor || !$campoRetorno) {
            http_response_code(400);
            echo json_encode(['erro' => 'Parâmetros obrigatórios ausentes ou inválidos.']);
            return;
        }

        $dados = BitrixHelper::consultarNegociacao([
            'cliente' => $cliente,
            'spa' => $spa,
            'deal' => $dealId,
            'campos' => implode(',', $camposSelecionados)
        ]);

        $item = $dados['result']['item'] ?? null;
        if (!$item || !isset($item['ufCrm' . substr($campoValor, 7)])) {
            http_response_code(404);
            echo json_encode(['erro' => 'Valor não encontrado no negócio.']);
            return;
        }

        $valor = FormataHelper::normalizarValor($item['ufCrm' . substr($campoValor, 7)]);
        $extenso = FormataHelper::valorPorExtenso($valor);

        BitrixHelper::editarNegociacao([
            'cliente' => $cliente,
            'spa' => $spa,
            'deal' => $dealId,
            $campoRetorno => $extenso
        ]);

        echo json_encode(['extenso' => $extenso]);
    }
}
