<?php
namespace Enums;

class GeraroptndEnums
{
    // ============================================
    // CAMPOS PARA ESPELHAR (COPIAR PARA NOVOS DEALS)
    // ============================================
    const CAMPOS_ESPELHAR = [
        'ufCrm_1645475980', // Parceiro Comercial
        'ufCrm_1706634369', // Gerente Comercial
        'ufCrm_1693939517', // Gerente da Parceria
        'ufCrm_1726062999', // Participação Decisiva
        'ufCrm_1688150098', // Responsável Comercial (Closer)
        'ufCrm_1688170367', // Responsável SDR
        'ufCrm_1685983679', // Resumo do Negócio (Não Editar)
        'ufCrm_1686151317', // Resumo do Negócio #
        'sourceId',         // Fonte
        'ufCrm_1650979003', // Tipo de Processo Operacional
        'ufCrm_1682225557', // Valor Honorários Variável
        'ufCrm_1687527019', // Honorários Fixos do Contrato (R$)
        'ufCrm_1737406675', // Consultoria
        'ufCrm_1737406672', // Custo Extra de Compensação
        'ufCrm_1737406345', // Valor Custo Extra de Compensação
        'ufCrm_1687542931', // Percentual Fixo do Parceiro
        'ufCrm_1687543122', // Percentual Variável do Parceiro
    ];

    // ============================================
    // CAMPOS PARA DIAGNÓSTICO E CONTROLE (APENAS PARA LEITURA)
    // ============================================
    const CAMPOS_DIAGNOSTICO = [
        'ufCrm_1670953245', // Negócios Vinculados à Negociação (NÃO copiar)
        'ufCrm_1689718588', // Todas Empresas do Negócio (NÃO copiar)
        'ufCrm_1688060696', // Oportunidades Oferecidas (NÃO copiar)
        'ufCrm_1728327366', // Oportunidades Convertidas (NÃO copiar)
        'stageId',          // Fase do negócio (NÃO copiar - definido individualmente)
    ];

    // ============================================
    // CAMPOS PARA GERAÇÃO (PREENCHIDOS INDIVIDUALMENTE)
    // ============================================
    const CAMPOS_GERACAO = [
        'ufCrm_1646069163997', // Oportunidade (definido individualmente)
        'companyId',           // Cliente (definido individualmente)
        'ufcrm_1707331568',    // Negócio Closer (definido individualmente)
    ];

    // ============================================
    // CAMPOS EXCLUÍDOS (NÃO COPIAR)
    // ============================================
    const CAMPOS_EXCLUIR = [
        'id',                     // ID do deal original (NÃO copiar)
        'ufCrm_1670953245',       // Negócios Vinculados à Negociação (NÃO copiar)
        'ufCrm_1689718588',       // Todas Empresas do Negócio (NÃO copiar)
        'ufCrm_1688060696',       // Oportunidades Oferecidas (NÃO copiar)
        'ufCrm_1728327366',       // Oportunidades Convertidas (NÃO copiar)
        'stageId',                // Fase do negócio (definido individualmente conforme tipo de processo)
        'ufCrm_1646069163997',    // Oportunidade (definido individualmente)
        'companyId',              // Cliente (definido individualmente)
        'ufcrm_1707331568',       // Negócio Closer (definido individualmente)
    ];

    // ============================================
    // CAMPOS DE OPORTUNIDADES (PARA MAPEAMENTO)
    // ============================================
    const CAMPOS_OPORTUNIDADES = [
        'ufCrm_1688060696' => 'oferecida',   // Oportunidades Oferecidas  
        'ufCrm_1728327366' => 'convertida',  // Oportunidades Convertidas
        'ufCrm_1646069163997' => 'oportunidade' // Oportunidade (para usar na criação)
    ];

    // ============================================
    // ETAPAS PARA DIAGNÓSTICO DE PROCESSTYPE
    // ============================================
    const ETAPA_SOLICITAR_DIAGNOSTICO = 'C53:UC_1PAPS7';
    const ETAPA_CONCLUIDO = 'C53:WON';

    // ============================================
    // CATEGORIAS/PIPELINES DE DESTINO
    // ============================================
    const CATEGORIA_CONTENCIOSO = 55;
    const CATEGORIA_RELATORIO_PRELIMINAR = 17; 
    const CATEGORIA_OPERACIONAL = 15;
    const CATEGORIA_CONSULTORIA = 77;

    // ============================================
    // FASES/ETAPAS DE DESTINO
    // ============================================
    const FASE_TRIAGEM = 'C55:NEW';              // Contencioso (ID: 1067)
    const FASE_TRIAGEM_RELATORIO = 'C17:PREPARATION';    // Relatório Preliminar (ID: 495)
    const FASE_TRIAGEM_OPERACIONAL = 'C15:NEW';  // Operacional (ID: 477)
    
    // IDs de controle (já corretos)
    const STAGE_ID_SOLICITAR_DIAGNOSTICO = '1257'; // C53:UC_1PAPS7 - Solicitar Diagnóstico  
    const STAGE_ID_CONCLUIDO = '1061';             // C53:WON - Concluído

    const STAGE_ID_TRIAGEM_CONSULTORIA = 'C77:NEW'; //Fase Consultoria

    // ============================================
    // IDs DE VALORES DE CAMPOS
    // ============================================
    const UFCRM_CONSULTORIA_SIM_ID = '21182'; // ID para o valor "Sim" do campo ufCrm_1737406675

    // ============================================
    // TIPOS ADMINISTRATIVOS (PARA LÓGICA DE DECISÃO)
    // ============================================
    
    // Para solicitar diagnóstico - vão para Relatório Preliminar
    const TIPOS_RELATORIO_PRELIMINAR = [
        'administrativo',
        'administrativo (anexo v)',
        'administrativo anexo 5', 
        'contencioso ativo',
        null, // vazio
        '' // string vazia
    ];
    
    // Para concluído - apenas administrativo puro vai para Operacional
    const TIPOS_OPERACIONAL = [
        'administrativo'
    ];

    // ============================================
    // CAMPOS COMPLETOS (TODOS JUNTOS)
    // ============================================
    public static function getAllFields()
    {
        return array_merge(
            self::CAMPOS_ESPELHAR,
            self::CAMPOS_DIAGNOSTICO,
            self::CAMPOS_GERACAO
        );
    }
}
