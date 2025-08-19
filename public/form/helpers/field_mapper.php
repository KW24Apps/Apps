<?php
// helpers/field_mapper.php - Mapeia campos UfCrm para nomes legíveis

class FieldMapper {
    
    /**
     * Mapeia códigos de campos UfCrm para nomes legíveis
     */
    public static function getFieldName($fieldCode) {
        $fieldMap = [
            // Campos comuns de empresa/pessoa
            'ufCrm_1658337137' => 'Razão Social',
            'ufCrm_1658337118' => 'CNPJ/CPF',
            'ufCrm_1688173081' => 'Cidade',
            'ufCrm_1692813629' => 'Data de Início',
            'ufCrm_1723670621' => 'Pessoa de Contato',
            'ufCrm_1695183281' => 'E-mail da Empresa',
            'ufCrm_1688173527' => 'WhatsApp da Empresa',
            'ufCrm_1696177458' => 'Nome Responsável',
            'ufCrm_1657026409' => 'CPF Responsável',
            'ufCrm_1740055091' => 'Código de Registro',
            'ufCrm_1740054935' => 'Tipo de Empresa',
            'ufCrm_1679941333' => 'Regime Tributário',
            'ufCrm_1693513443' => 'Contrato/Cláusula',
            
            // Campos genéricos mais comuns
            'ufCrm_1001' => 'Nome',
            'ufCrm_1002' => 'Sobrenome',
            'ufCrm_1003' => 'Telefone',
            'ufCrm_1004' => 'E-mail',
            'ufCrm_1005' => 'Empresa',
            'ufCrm_1006' => 'Cargo',
            'ufCrm_1007' => 'Endereço',
            'ufCrm_1008' => 'CEP',
            'ufCrm_1009' => 'Estado',
            'ufCrm_1010' => 'País',
            
            // Adicione mais campos conforme necessário
        ];
        
        // Se encontrar mapeamento, retorna nome amigável
        if (isset($fieldMap[$fieldCode])) {
            return $fieldMap[$fieldCode];
        }
        
        // Se não encontrar, retorna o código original formatado
        return self::formatFieldCode($fieldCode);
    }
    
    /**
     * Formata códigos de campo desconhecidos para exibição
     */
    private static function formatFieldCode($fieldCode) {
        // Se é um campo UfCrm, extrai apenas o número
        if (preg_match('/^ufCrm_(\d+)$/', $fieldCode, $matches)) {
            return "Campo Personalizado #{$matches[1]}";
        }
        
        // Se é um campo SPA
        if (preg_match('/^ufCrm(\d+)_(\d+)$/', $fieldCode, $matches)) {
            return "Campo SPA {$matches[1]} #{$matches[2]}";
        }
        
        // Caso padrão
        return $fieldCode;
    }
    
    /**
     * Obtém todos os campos mapeados
     */
    public static function getAllFields() {
        return [
            'ufCrm_1658337137' => 'Razão Social',
            'ufCrm_1658337118' => 'CNPJ/CPF',
            'ufCrm_1688173081' => 'Cidade',
            'ufCrm_1692813629' => 'Data de Início',
            'ufCrm_1723670621' => 'Pessoa de Contato',
            'ufCrm_1695183281' => 'E-mail da Empresa',
            'ufCrm_1688173527' => 'WhatsApp da Empresa',
            'ufCrm_1696177458' => 'Nome Responsável',
            'ufCrm_1657026409' => 'CPF Responsável',
            'ufCrm_1740055091' => 'Código de Registro',
            'ufCrm_1740054935' => 'Tipo de Empresa',
            'ufCrm_1679941333' => 'Regime Tributário',
            'ufCrm_1693513443' => 'Contrato/Cláusula',
        ];
    }
}
