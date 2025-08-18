<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;

class GeraroptndController
{
    public function executar()
    {
        // Aumenta tempo limite
        set_time_limit(300);
        
        // ============================================
        // PARTE 1: COLETA DE DADOS
        // ============================================
        
        // Passo 1: Obter dealId
        $dealId = $_GET['deal'] ?? $_GET['id'] ?? null;
        if (!$dealId) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Parâmetro deal/id é obrigatório']);
            return;
        }
        
        // Passo 1: Definir campos a consultar (aproveitando do copy)
        $camposBitrix = [
            'ufCrm_1645475980', // Parceiro Comercial
            'ufCrm_1706634369', // Gerente Comercial
            'ufCrm_1693939517', // Gerente da Parceria
            'ufCrm_1726062999', // Participação Decisiva
            'ufCrm_1688150098', // Responsável Comercial (Closer)
            'ufCrm_1688170367', // Responsável SDR
            'ufCrm_1700511100', // Proposta
            'ufCrm_1700684166', // Proposta - Arquivo (Opcional)
            'ufCrm_1685983679', // Resumo do Negócio (Não Editar)
            'ufCrm_1686151317', // Resumo do Negócio #
            'sourceId',         // Fonte
            'companyId',        // Cliente
            'ufCrm_1689718588', // Todas Empresas do Negócio
            'ufCrm_1688060696', // Oportunidades Oferecidas
            'ufCrm_1728327366', // Oportunidades Convertidas
            'ufCrm_1646069163997', // Oportunidade
            'ufCrm_1670953245', // Negócios Vinculados à Negociação
            'ufCrm_1650979003', // Tipo de Processo Operacional
            'ufCrm_1682225557', // Valor Honorários Variável
            'ufCrm_1687527019', // Honorários Fixos do Contrato (R$)
            'ufCrm_1737406675', // Consultoria
            'ufCrm_1737406672', // Custo Extra de Compensação
            'ufCrm_1737406345', // Valor Custo Extra de Compensação
            'ufCrm_1687542931', // Percentual Fixo do Parceiro
            'ufCrm_1687543122', // Percentual Variável do Parceiro
            'stageId', // Fase do negócio
            'ufcrm_1707331568', // Negocio Closer
        ];
        
        // Passo 1: Consultar deal usando BitrixDealHelper
        $camposStr = implode(',', $camposBitrix);
        $resultado = BitrixDealHelper::consultarDeal(2, $dealId, $camposStr);
        $item = $resultado['result'] ?? [];
        
        // Passo 2: Consultar metadados dos campos CRM
        $crmFields = BitrixHelper::consultarCamposCrm(2);
        
        // Passo 3: Construir mapa $oportunidades
        $oportunidades = [];
        if (isset($crmFields['result'])) {
            foreach ($crmFields['result'] as $campo => $info) {
                if (in_array($campo, ['ufCrm_1688060696', 'ufCrm_1728327366', 'ufCrm_1646069163997']) && isset($info['items'])) {
                    foreach ($info['items'] as $itemId => $itemData) {
                        $nomeAmigavel = $itemData['VALUE'] ?? '';
                        if (!empty($nomeAmigavel)) {
                            if (!isset($oportunidades[$nomeAmigavel])) {
                                $oportunidades[$nomeAmigavel] = [];
                            }
                            
                            // Mapear subcampos baseado no campo de origem
                            if ($campo == 'ufCrm_1688060696') { // Oferecidas
                                $oportunidades[$nomeAmigavel]['oferecida'] = ['id' => $itemId, 'texto' => $nomeAmigavel];
                            } elseif ($campo == 'ufCrm_1728327366') { // Convertidas
                                $oportunidades[$nomeAmigavel]['convertida'] = ['id' => $itemId, 'texto' => $nomeAmigavel];
                            } elseif ($campo == 'ufCrm_1646069163997') { // Oportunidade
                                $oportunidades[$nomeAmigavel]['oportunidade'] = ['id' => $itemId, 'texto' => $nomeAmigavel];
                            }
                        }
                    }
                }
            }
        }
        
        // Passo 4: Normalizar e extrair empresas, oferecidas, convertidas
        $empresas = [];
        if (!empty($item['ufCrm_1689718588']['texto'])) {
            $empresas = is_array($item['ufCrm_1689718588']['texto']) 
                ? $item['ufCrm_1689718588']['texto'] 
                : explode(',', $item['ufCrm_1689718588']['texto']);
        }
        
        $ofer = [];
        if (!empty($item['ufCrm_1688060696']['texto'])) {
            $ofer = is_array($item['ufCrm_1688060696']['texto']) 
                ? $item['ufCrm_1688060696']['texto'] 
                : explode(',', $item['ufCrm_1688060696']['texto']);
        }
        
        $conv = [];
        if (!empty($item['ufCrm_1728327366']['texto'])) {
            $conv = is_array($item['ufCrm_1728327366']['texto']) 
                ? $item['ufCrm_1728327366']['texto'] 
                : explode(',', $item['ufCrm_1728327366']['texto']);
        }
        
        // Passo 5: Definir campos que não serão copiados
        $naoCopiar = [
            'ufCrm_1688060696', // Oportunidades Oferecidas
            'ufCrm_1728327366', // Oportunidades Convertidas
            'ufCrm_1670953245', // Negócios Vinculados à Negociação
            'stageId', // Fase do negócio
        ];
        
        // ============================================
        // PARTE 2: DIAGNÓSTICO DE OPERAÇÃO
        // ============================================
        
        // Passo 1: Definir $processType
        $etapaAtualId = $item['stageId']['valor'] ?? '';
        $vinculados = $item['ufCrm_1670953245']['valor'] ?? null;
        
        // Normalizar $vinculados
        if ($vinculados) {
            if (is_array($vinculados)) {
                $vinculados = array_filter(array_map('trim', $vinculados));
            } else {
                $vinculados = array_filter(array_map('trim', explode(',', $vinculados)));
            }
        }
        
        $processType = 0;
        if ($etapaAtualId === 'C53:UC_1PAPS7') {
            $processType = 1; // solicitar diagnóstico
        } elseif ($etapaAtualId === 'C53:WON') {
            if (empty($vinculados)) {
                $processType = 2; // concluído sem diagnóstico
            } else {
                $processType = 3; // concluído com diagnóstico
            }
        }
        
        // Passo 2: Se processType == 3, buscar vinculados existentes
        $vinculadosList = [];
        if ($processType == 3) {
            $vinculadosList = BitrixHelper::listarItensCrm('deal', [
                'filter' => ['ufcrm_1707331568' => $dealId],
                'select' => ['companyId', 'ufCrm_1646069163997']
            ]);
        }
        
        // ============================================
        // DEBUG: ECHO DOS RESULTADOS
        // ============================================
        
        header('Content-Type: application/json');
        echo json_encode([
            'dealId' => $dealId,
            'processType' => $processType,
            'etapaAtualId' => $etapaAtualId,
            'empresas' => $empresas,
            'ofer' => $ofer,
            'conv' => $conv,
            'vinculados' => $vinculados,
            'oportunidades' => $oportunidades,
            'vinculadosList' => $vinculadosList,
            'item_completo' => $item,
            'debug_info' => [
                'total_empresas' => count($empresas),
                'total_oferecidas' => count($ofer),
                'total_convertidas' => count($conv),
                'total_oportunidades_mapa' => count($oportunidades),
                'campos_consultados' => count($camposBitrix)
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
