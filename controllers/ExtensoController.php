<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';

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

        $valor = floatval($item['ufCrm' . substr($campoValor, 7)]);
        $extenso = $this->valorPorExtenso($valor);

        BitrixHelper::editarNegociacao($cliente, $spa, $dealId, [
            $campoRetorno => $extenso
        ]);

        echo json_encode(['extenso' => $extenso]);
    }

    private function valorPorExtenso($valor)
    {
        $fmt = new \NumberFormatter('pt_BR', \NumberFormatter::SPELLOUT);
        $inteiro = floor($valor);
        $centavos = round(($valor - $inteiro) * 100);

        $texto = $fmt->format($inteiro) . ' reais';
        if ($centavos > 0) {
            $texto .= ' e ' . $fmt->format($centavos) . ' centavos';
        }

        return $texto;
    }
}
