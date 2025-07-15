<?php
require_once __DIR__ . '/../helpers/FormataHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

class ExtensoController
{
    public function executar()
    {
        header('Content-Type: application/json');
        $params = $_GET;

        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $dealId = $params['deal'] ?? $params['id'] ?? null;
        $campoValor = $params['valor'] ?? null;
        $campoRetorno = $params['retorno'] ?? null;

        // Validação padronizada
        $parametrosObrigatorios = ['spa', 'deal', 'valor', 'retorno'];
        foreach ($parametrosObrigatorios as $param) {
            if (empty($params[$param])) {
                http_response_code(400);
                echo json_encode(['erro' => "Parâmetro obrigatório ausente: $param"]);
                return;
            }
        }

        $resultado = BitrixDealHelper::consultarDeal($entityId, $dealId, $campoValor);
        $item = $resultado['result']['item'] ?? null;

        // Padroniza acesso ao campo
        $campoBitrix = BitrixHelper::formatarCampos([$campoValor => null]);
        $campoBitrixKey = array_key_first($campoBitrix);

        if (!$item || !isset($item[$campoBitrixKey])) {
            http_response_code(404);
            echo json_encode(['erro' => 'Valor não encontrado no negócio.']);
            return;
        }

        $valor = FormataHelper::normalizarValor($item[$campoBitrixKey]);
        $extenso = FormataHelper::valorPorExtenso($valor);

        BitrixDealHelper::editarDeal($entityId, $dealId, [$campoRetorno => $extenso]);

        echo json_encode(['extenso' => $extenso]);
    }
}