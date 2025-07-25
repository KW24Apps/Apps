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
        // 1. Normaliza campos para array e remove espaços
        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        } else {
            $fields = array_map('trim', $fields);
        }

        if (!in_array('id', $fields)) {
            array_unshift($fields, 'id');
        }

        // 2. Consulta o negócio (deal)
        $params = [
            'entityTypeId' => $entityId,
            'id' => $dealId,
        ];
        $respostaApi = BitrixHelper::chamarApi('crm.item.get', $params, []);
        $dadosBrutos = $respostaApi['result']['item'] ?? [];

        // 3. Consulta os campos da SPA
        $camposSpa = BitrixHelper::consultarCamposSpa($entityId);

        // 4. Consulta as etapas do tipo
        $etapas = BitrixHelper::consultarEtapasPorTipo($entityId);

        // 5. Formata os campos para o padrão camelCase
        $camposFormatados = BitrixHelper::formatarCampos(array_fill_keys($fields, null));
        
        $valoresBrutos = [];
        foreach (array_keys($camposFormatados) as $campoConvertido) {
            $valoresBrutos[$campoConvertido] = $dadosBrutos[$campoConvertido] ?? null;
        }
        
        // Garantir que companyId seja incluído se existir nos dados brutos
        if (isset($dadosBrutos['companyId']) && !isset($valoresBrutos['companyId'])) {
            $valoresBrutos['companyId'] = $dadosBrutos['companyId'];
        }

        // 6. Mapeia valores enumerados
        $valoresConvertidos = BitrixHelper::mapearValoresEnumerados($valoresBrutos, $camposSpa);

        // 7. Mapeia o nome amigável da etapa, se existir campo de etapa
        $stageName = null;
        if (isset($valoresBrutos['stageId'])) {
            $stageName = BitrixHelper::mapearEtapaPorId($valoresBrutos['stageId'], $etapas);
        }

        // 8. Monta resposta amigável SEMPRE incluindo todos os campos solicitados
        $resultadoFinal = [];
        foreach ($fields as $campoOriginal) {
            // Converte o campo para o padrão Bitrix
            $campoConvertidoArr = BitrixHelper::formatarCampos([$campoOriginal => null]);
            $campoConvertido = array_key_first($campoConvertidoArr);
            $valorBruto = $valoresBrutos[$campoConvertido] ?? null;
            $valorConvertido = $valoresConvertidos[$campoConvertido] ?? $valorBruto;
            $spa = $camposSpa[$campoConvertido] ?? [];
            $nomeAmigavel = $spa['title'] ?? $campoOriginal;
            $texto = $valorConvertido;
            $type = $spa['type'] ?? null;
            $isMultiple = $spa['isMultiple'] ?? false;
            // Se for stageId, usa o nome da etapa como texto
            if ($campoConvertido === 'stageId') {
                $texto = $stageName ?? $valorBruto;
                $nomeAmigavel = 'Fase';
            }
            $resultadoFinal[$campoConvertido] = [
                'nome' => $nomeAmigavel,
                'valor' => $valorBruto,
                'texto' => $texto,
                'type' => $type,
                'isMultiple' => $isMultiple
            ];
        }
        // Sempre inclui o id bruto
        if (isset($valoresBrutos['id'])) {
            $resultadoFinal['id'] = $valoresBrutos['id'];
        }

        return ['result' => $resultadoFinal];
    }

}