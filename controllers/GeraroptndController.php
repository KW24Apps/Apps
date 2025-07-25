<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use Helpers\BitrixDealHelper;

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

        // 3. Consulta o deal no Bitrix (já retorna campos e valores amigáveis)
        $camposStr = implode(',', $camposBitrix);
        $resultado = BitrixDealHelper::consultarDeal(2, $dealId, $camposStr);
        $item = $resultado['result'] ?? [];

        // Validação de etapa/funil permitida por ID
        $etapasPermitidas = ['C53:UC_1PAPS7', 'C53:WON']; // Solicitar Diagnóstico e Concluído
        $etapaAtualId = $item['stageId']['valor'] ?? '';
        if (!in_array($etapaAtualId, $etapasPermitidas)) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Negócio está em etapa não permitida para criação de negócios.', 'etapaId' => $etapaAtualId, 'etapaNome' => $item['stageId']['texto'] ?? '']);
            return;
        }

        // 4. Extrai empresas e oportunidades oferecidas/convertidas (usando texto)
        $empresas = [];
        if (!empty($item['ufCrm_1689718588']['texto'])) {
            $empresas = is_array($item['ufCrm_1689718588']['texto']) ? $item['ufCrm_1689718588']['texto'] : explode(',', $item['ufCrm_1689718588']['texto']);
        }
        $ofer = [];
        if (!empty($item['ufCrm_1688060696']['texto'])) {
            $ofer = is_array($item['ufCrm_1688060696']['texto']) ? $item['ufCrm_1688060696']['texto'] : explode(',', $item['ufCrm_1688060696']['texto']);
        }
        $conv = [];
        if (!empty($item['ufCrm_1728327366']['texto'])) {
            $conv = is_array($item['ufCrm_1728327366']['texto']) ? $item['ufCrm_1728327366']['texto'] : explode(',', $item['ufCrm_1728327366']['texto']);
        }
        $closerId = $dealId;

        // 5. Define campos que NÃO devem ser copiados
        $naoCopiar = [
            'ufCrm_1688060696', // Oportunidades Oferecidas
            'ufCrm_1728327366', // Oportunidades Convertidas
            'ufCrm_1670953245', // Negócios Vinculados à Negociação
            'stageId', // Fase do negócio
        ];

        // 6. Monta os campos base para copiar
        $camposBase = [];
        foreach ($item as $campo => $dados) {
            if (!in_array($campo, $naoCopiar)) {
                $camposBase[$campo] = $dados['texto'] ?? $dados['valor'] ?? null;
            }
        }

        // 7. Lógica principal: diagnóstico ou concluído
        $resultadosCriacao = [];
        $vinculados = $item['ufCrm_1670953245']['valor'] ?? null;
        if (empty($vinculados)) {
            // Não passou pelo diagnóstico: criar cruzando empresas × oportunidades convertidas
            foreach ($empresas as $empresa) {
                foreach ($conv as $oportunidade) {
                    $novoNegocio = $camposBase;
                    $novoNegocio['companyId'] = is_array($empresa) ? $empresa : [$empresa];
                    $novoNegocio['ufCrm_1646069163997'] = is_array($oportunidade) ? $oportunidade : [$oportunidade];
                    $novoNegocio['ufcrm_1707331568'] = $closerId;
                    $res = BitrixDealHelper::criarDeal(2, null, $novoNegocio);
                    $resultadosCriacao[] = [
                        'empresa' => $empresa,
                        'oportunidade' => $oportunidade,
                        'resultado' => $res
                    ];
                }
            }
        } else {
            // Passou pelo diagnóstico: consultar negócios vinculados e só criar o que falta
            $idsVinculados = is_array($vinculados) ? $vinculados : explode(',', $vinculados);
            $existentes = [];
            foreach ($idsVinculados as $idVinc) {
                $resVinc = BitrixDealHelper::consultarDeal(2, $idVinc, 'companyId,ufCrm_1646069163997');
                $dadosVinc = $resVinc['result'] ?? [];
                $empresaVinc = $dadosVinc['companyId']['texto'] ?? null;
                $oportunidadeVinc = $dadosVinc['ufCrm_1646069163997']['texto'] ?? null;
                if ($empresaVinc && $oportunidadeVinc) {
                    $existentes[$empresaVinc][$oportunidadeVinc] = true;
                }
            }
            foreach ($empresas as $empresa) {
                foreach ($conv as $oportunidade) {
                    if (empty($existentes[$empresa][$oportunidade])) {
                        $novoNegocio = $camposBase;
                        $novoNegocio['companyId'] = is_array($empresa) ? $empresa : [$empresa];
                        $novoNegocio['ufCrm_1646069163997'] = is_array($oportunidade) ? $oportunidade : [$oportunidade];
                        $novoNegocio['ufcrm_1707331568'] = $closerId;
                        $res = BitrixDealHelper::criarDeal(2, null, $novoNegocio);
                        $resultadosCriacao[] = [
                            'empresa' => $empresa,
                            'oportunidade' => $oportunidade,
                            'resultado' => $res
                        ];
                    }
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['result' => $resultadosCriacao]);
    }
}
