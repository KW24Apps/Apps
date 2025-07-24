<?php
namespace Controllers;

class GeraroptndController
{
    public function executar()
    {
        // 1. Pega parâmetro do negócio (dealId)
        $dealId = $_GET['deal'] ?? $_GET['id'] ?? null;
        if (!$dealId) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Parâmetro deal/id é obrigatório']);
            return;
        }

        // 2. Define campos fixos a consultar no deal
        $camposBitrix = [
            'ufCrm_1689795579', // Data de Acompanhamento
            'ufCrm_1645475980', // Parceiro Comercial
            'ufCrm_1706634369', // Gerente Comercial
            'ufCrm_1693939517', // Gerente da Parceria
            'ufCrm_1726062999', // Participação Decisiva
            'ufCrm_1688150098', // Responsável Comercial (Closer)
            'ufCrm_1688170367', // Responsável SDR
            'ufCrm_1700511100', // Proposta
            'ufCrm_1700684166', // Proposta - Arquivo (Opcional)
            'ufCrm_1738604320', // Comentário Resumo
            'ufCrm_1685983679', // Resumo do Negócio (Não Editar)
            'ufCrm_1688175234', // Controle de Etapas Nimbus #
            'ufCrm_1739823491', // Resumo Relatório Preliminar (Diagnost)
            'sourceId',         // Fonte
            'ufCrm_1747227840', // Cliente
            'ufCrm_1689718588', // Todas Empresas do Negócio
            'ufCrm_1688060696', // Oportunidades Oferecidas
            'ufCrm_1728327366', // Oportunidades Convertidas
            'ufCrm_1646069163997', // Oportunidade
            'ufCrm_1670953245', // Negócios Vinculados à Negociação
            'ufCrm_1650979003', // Tipo de Processo Operacional
            'ufCrm_1692127712', // Arquivo NDA
            'ufCrm_1651061302', // Pasta Drive Negócio Online
            'ufCrm_1657749288', // Arquivos
            'assignedById',     // Responsável
            'ufCrm_1688177336', // ID Parceiro
            'ufCrm_1688173007', // Nome da Empresa
            'ufCrm_1688173081', // Pessoa de Contato
            'ufCrm_1688173501', // E-mail da Empresa
            'ufCrm_1688173527', // WhatsApp da Empresa
            'ufCrm_1688173663', // Observações
            'ufCrm_1658337118', // CNPJ/CPF
            'ufCrm_1679941333', // Regime de Tributação
            'ufCrm_1679941653', // Ramo de Atividade
            'ufCrm_1668479532', // Contador 01
            'ufCrm_1682225557', // Valor Honorários Variável
            'ufCrm_1687527019', // Honorários Fixos do Contrato (R$)
            'ufCrm_1679436470', // Status de validação CNPJ/CPF #
            'ufCrm_66994D65A0346', // Empresa Contratante
            'ufCrm_1731348911', // Oportunidades a criar
            'ufCrm_1695239018', // Segmento
            'ufCrm_1737406675', // Consultoria
            'ufCrm_1737406672', // Custo Extra de Compensação
            'ufCrm_1737406345', // Valor Custo Extra de Compensação
            'ufCrm_1687542931', // Percentual Fixo do Parceiro
            'ufCrm_1687543122', // Percentual Variável do Parceiro
        ];

        // 3. Consulta o deal no Bitrix
        require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
        $camposStr = implode(',', $camposBitrix);
        $resultado = \Helpers\BitrixDealHelper::consultarDeal(null, $dealId, $camposStr);

        // 4. Retorna os dados consultados (para teste)
        header('Content-Type: application/json');
        echo json_encode(['result' => $resultado]);
    }
}
