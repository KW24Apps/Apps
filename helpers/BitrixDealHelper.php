<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

class BitrixDealHelper
{
    // Cria um negócio no Bitrix24 via API
    public static function criarDeal($entityId, $categoryId, $fields): array
    {
        // Formata os campos recebidos
        $formattedFields = BitrixHelper::formatarCampos($fields);

        if ($categoryId) {
            $formattedFields['categoryId'] = $categoryId;
        }

        $params = [
            'entityTypeId' => $entityId,
            'fields' => $formattedFields
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.add', $params, [
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
    public static function editarDeal($entityId, $dealId, array $fields): array
    {
        if (!$entityId || !$dealId || empty($fields)) {
            return [
                'success' => false,
                'error' => 'Parâmetros obrigatórios não informados.'
            ];
        }

        $fields = BitrixHelper::formatarCampos($fields);

        $params = [
            'entityTypeId' => $entityId,
            'id' => (int)$dealId,
            'fields' => $fields
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.update', $params, [
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
    public static function consultarDeal($entityId, $dealId, $fields)
    {

        // Normaliza campos para array e remove espaços
        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        } else {
            $fields = array_map('trim', $fields);
        }

        if (!in_array('id', $fields)) {
            array_unshift($fields, 'id');
        }
    
        $params = [
            'entityTypeId' => $entityId,
            'id' => $dealId,
        ];

        $respostaApi = BitrixHelper::chamarApi('crm.item.get', $params, []);
    
        $dadosBrutos = $respostaApi['result']['item'] ?? [];

        $camposFormatados = BitrixHelper::formatarCampos(array_fill_keys($fields, null));
        $resultadoFinal = [];

        foreach (array_keys($camposFormatados) as $campoConvertido) {
            $resultadoFinal[$campoConvertido] = $dadosBrutos[$campoConvertido] ?? null;
        }
        
        return ['result' => ['item' => $resultadoFinal]];
    }



}