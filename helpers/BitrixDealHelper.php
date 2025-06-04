<?php
require_once __DIR__ . '/../helpers/BitrixHelper.php';
class BitrixDealHelper

{
    // Cria um negócio no Bitrix24 via API
    public static function criarNegocio($dados)
    {
        //$dados = $_POST ?: $_GET;
        $spa = $dados['spa'] ?? null;
        $categoryId = $dados['CATEGORY_ID'] ?? null;
        $webhook = $dados['webhook'] ?? null;

        unset($dados['cliente'], $dados['spa'], $dados['CATEGORY_ID'], $dados['webhook']);

        $fields = BitrixHelper::formatarCampos($dados);
        if ($categoryId) {
            $fields['categoryId'] = $categoryId;
        }

        $params = [
            'entityTypeId' => $spa,
            'fields' => $fields
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.add', $params, [
            'webhook' => $webhook,
            'log' => true
        ]);

        if (isset($resultado['result']['item']['id'])) {
            return [
                'success' => true,
                'id' => $resultado['result']['item']['id']
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao criar negócio.'
        ];
    }

    // Edita um negócio existente no Bitrix24 via API
    public static function editarNegociacao($dados = [])
    {
        $spa = $dados['spa'] ?? null;
        $dealId = $dados['deal'] ?? null;
        $webhook = $dados['webhook'] ?? null;

        unset($dados['cliente'], $dados['spa'], $dados['deal'], $dados['webhook']);

        if (!$spa || !$dealId || empty($dados)) {
            return [
                'success' => false,
                'error' => 'Parâmetros obrigatórios não informados.'
            ];
        }

        $fields = BitrixHelper::formatarCampos($dados);

        $params = [
            'entityTypeId' => $spa,
            'id' => (int)$dealId,
            'fields' => $fields
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.update', $params, [
            'webhook' => $webhook,
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
    public static function consultarNegociacao($filtros)
    {
        $spa = $filtros['spa'] ?? 0;
        $dealId = $filtros['deal'] ?? null;
        $webhook = $filtros['webhook'] ?? null;

        if (!$dealId || !$webhook) {
            return ['erro' => 'ID do negócio ou webhook não informado.'];
        }

        $select = ['id'];

        if (!empty($filtros['campos'])) {
            $extras = explode(',', $filtros['campos']);
            foreach ($extras as $campo) {
                $campo = trim($campo);
                if (strpos($campo, 'UF_CRM_') === 0) {
                    $convertido = 'ufCrm' . substr($campo, 7);
                    if (!in_array($convertido, $select)) {
                        $select[] = $convertido;
                    }
                }
            }
        }

        $params = [
            'entityTypeId' => $spa,
            'id' => (int)$dealId,
            'select' => $select
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.get', $params, [
            'webhook' => $webhook,
            'log' => false
        ]);

        if (!isset($resultado['result']['item'])) {
            return $resultado;
        }

        $item = $resultado['result']['item'];

        if (!empty($filtros['campos'])) {
            $campos = explode(',', $filtros['campos']);
            $filtrado = ['id' => $item['id'] ?? null];

            foreach ($campos as $campo) {
                $campoConvertido = 'ufCrm' . substr($campo, 7);
                if (isset($item[$campoConvertido])) {
                    $filtrado[$campoConvertido] = $item[$campoConvertido];
                }
            }

            return ['result' => ['item' => $filtrado]];
        }

        return $resultado;
    }

}