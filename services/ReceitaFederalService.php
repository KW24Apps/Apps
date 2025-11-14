<?php
namespace Services;

use Helpers\BitrixCompanyHelper;
use Helpers\LogHelper;
use Helpers\BitrixHelper; // Adicionado para consultar campos

class ReceitaFederalService
{
    public function consultarDadosIniciais(string $idEmpresaBitrix): array
    {
        // 1. Consultar a empresa no Bitrix24 para obter o CNPJ
        $companyDetails = BitrixCompanyHelper::consultarEmpresa(['empresa' => $idEmpresaBitrix]);

        if (isset($companyDetails['erro'])) {
            LogHelper::logReceitaFederal("Erro ao consultar empresa ID $idEmpresaBitrix no Bitrix24: " . $companyDetails['erro'], __CLASS__ . '::' . __FUNCTION__);
            return ['status' => 'erro', 'mensagem' => "Erro ao consultar empresa ID $idEmpresaBitrix no Bitrix24."];
        }
        
        $cnpj = $companyDetails['UF_CRM_1641693445101'] ?? null; // UF_CRM_1641693445101 é o campo CNPJ/CPF

        if (empty($cnpj)) {
            LogHelper::logReceitaFederal("Erro: CNPJ não encontrado para a empresa ID $idEmpresaBitrix no Bitrix24.", __CLASS__ . '::' . __FUNCTION__);
            return ['status' => 'erro', 'mensagem' => "CNPJ não encontrado para a empresa ID $idEmpresaBitrix no Bitrix24."];
        }

        // 2. Consultar *todos* os campos da empresa no Bitrix24 usando BitrixHelper::consultarCamposCrm()
        // A entidade para Company é 3 (CRM_COMPANY_ENTITY_TYPE_ID)
        $companyFieldsMetadata = BitrixHelper::consultarCamposCrm(3); // 3 é o entityTypeId para Company

        if (isset($companyFieldsMetadata['erro'])) {
            LogHelper::logReceitaFederal("Erro ao consultar metadados dos campos da empresa no Bitrix24: " . $companyFieldsMetadata['erro'], __CLASS__ . '::' . __FUNCTION__);
            return ['status' => 'erro', 'mensagem' => "Erro ao consultar metadados dos campos da empresa no Bitrix24."];
        }

        // Filtrar e formatar os campos que nos interessam, especialmente os de lista
        $camposMapeados = [];
        // Lista de campos a serem mapeados, baseada no receita_federal_bitrix_integration.rd
        $camposInteresse = [
            'TITLE',
            'UF_CRM_1643894689490', // Nome Fantasia
            'UF_CRM_1641693445101', // CNPJ/CPF
            'UF_CRM_1651170686',    // UF (Lista)
            'UF_CRM_1657666567',    // CEP
            'UF_CRM_1696352922',    // Email
            'UF_CRM_1657666676',    // Bairro
            'UF_CRM_1704323923',    // Número
            'UF_CRM_ADDRESS_CITY',  // Município
            'UF_CRM_1669387590',    // Código Municipal
            'UF_CRM_1657666583',    // Logradouro
            'UF_CRM_1657666659',    // Complemento
            'UF_CRM_1645141153',    // Capital Social
            'UF_CRM_1670261459',    // Telefone
            'UF_CRM_1645141733685', // CNAE Principal
            'UF_CRM_1710268217',    // CNAE Secundário (Lista)
            'UF_CRM_1645140434',    // Regime de Tributação (Lista)
            'UF_CRM_1696339884',    // Natureza Jurídica
            'ADDRESS'               // Endereço Completo
        ];

        foreach ($companyFieldsMetadata as $fieldId => $fieldInfo) {
            if (in_array($fieldId, $camposInteresse)) {
                $camposMapeados[$fieldId] = [
                    'nome_amigavel' => $fieldInfo['title'] ?? $fieldId,
                    'tipo' => $fieldInfo['type'] ?? 'desconhecido',
                    'is_multiple' => $fieldInfo['isMultiple'] ?? false,
                    'lista_items' => []
                ];

                // Se for um campo de lista (enumeration), extrair os IDs e valores
                if (isset($fieldInfo['items']) && is_array($fieldInfo['items'])) {
                    foreach ($fieldInfo['items'] as $item) {
                        $camposMapeados[$fieldId]['lista_items'][$item['ID']] = $item['VALUE'];
                    }
                }
            }
        }

        // Retorna os dados coletados
        return [
            'status' => 'sucesso',
            'mensagem' => "Dados iniciais coletados com sucesso.",
            'id_empresa_bitrix' => $idEmpresaBitrix,
            'cnpj_encontrado' => $cnpj,
            'campos_bitrix_metadata' => $camposMapeados
        ];
    }
}
