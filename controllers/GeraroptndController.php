<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use Helpers\BitrixDealHelper;

class GeraroptndController
{
    public function executar()
    {
        // Aumenta tempo limite para processar múltiplas chamadas à API
        set_time_limit(300); // 5 minutos
        
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
        // Adiciona variável para oportunidades oferecidas
        $oferecidas = $ofer;
        $closerId = $dealId;

        // 5. Define campos que NÃO devem ser copiados
        $naoCopiar = [
            'ufCrm_1688060696', // Oportunidades Oferecidas
            'ufCrm_1728327366', // Oportunidades Convertidas
            'ufCrm_1670953245', // Negócios Vinculados à Negociação
            'stageId', // Fase do negócio
        ];

        // 6. Monta os campos base para copiar usando metadados (type, isMultiple)
        $camposBase = [];
        foreach ($item as $campo => $dados) {
            if (!in_array($campo, $naoCopiar)) {
                $valor = $dados['texto'] ?? $dados['valor'] ?? null;
                $tipo = $dados['type'] ?? null;
                $isMultiple = $dados['isMultiple'] ?? false;

                // Normalização automática baseada em metadados
                if ($isMultiple) {
                    // Sempre array, mesmo se valor único
                    if (is_array($valor)) {
                        $camposBase[$campo] = $valor;
                    } else if ($valor === null || $valor === '' || $valor === false) {
                        $camposBase[$campo] = [];
                    } else {
                        $camposBase[$campo] = [$valor];
                    }
                } else {
                    // Valor único
                    if (is_array($valor)) {
                        // Se vier array, pega primeiro
                        $camposBase[$campo] = count($valor) ? $valor[0] : '';
                    } else {
                        $camposBase[$campo] = $valor;
                    }
                }
            }
        }

        // 7. Lógica principal: diagnóstico ou concluído
        $resultadosCriacao = [];
        $logDiagnostico = [
            'dealId' => $dealId,
            'etapaAtualId' => $etapaAtualId,
            'etapaAtualNome' => $item['stageId']['texto'] ?? '',
            'empresas' => $empresas,
            'oportunidadesConvertidas' => $conv,
            'oportunidadesOferecidas' => $oferecidas,
            'vinculados' => $item['ufCrm_1670953245']['valor'] ?? null,
        ];
        
        $vinculados = $item['ufCrm_1670953245']['valor'] ?? null;
        $dealsParaCriar = []; // Array para criação em massa

        if ($etapaAtualId === 'C53:UC_1PAPS7') {
            // Solicitar Diagnóstico: criar cruzando empresas × oportunidades oferecidas
            $contador = 0;
            $maxTentativas = 100; // Aumentado já que não há mais sleep
            
            foreach ($empresas as $empresa) {
                foreach ($oferecidas as $oportunidade) {
                    $contador++;
                    if ($contador > $maxTentativas) {
                        break 2; // Sai dos dois loops
                    }
                    
                    // Prepara dados do novo negócio
                    $novoNegocio = [];
                    
                    // CompanyId
                    $companyIdMeta = $item['companyId'] ?? [];
                    $companyIdIsMultiple = $companyIdMeta['isMultiple'] ?? false;
                    if ($companyIdIsMultiple) {
                        $novoNegocio['companyId'] = is_array($empresa) ? $empresa : [$empresa];
                    } else {
                        $novoNegocio['companyId'] = is_array($empresa) ? (count($empresa) ? $empresa[0] : '') : $empresa;
                    }
                    
                    // Negocio Closer (ID do deal que solicitou)
                    $closerMeta = $item['ufcrm_1707331568'] ?? [];
                    $closerIsMultiple = $closerMeta['isMultiple'] ?? false;
                    if ($closerIsMultiple) {
                        $novoNegocio['ufcrm_1707331568'] = [$dealId];
                    } else {
                        $novoNegocio['ufcrm_1707331568'] = $dealId;
                    }
                    
                    // Define categoria baseada no tipo de processo
                    $tipoProcesso = trim($item['ufCrm_1650979003']['texto'] ?? '');
                    $categoryId = 17; // Padrão: Relatório Preliminar
                    
                    $dealsParaCriar[] = $novoNegocio;
                }
            }
            
            // Criação em massa usando o novo sistema
            if (!empty($dealsParaCriar)) {
                $resultadoCriacao = BitrixDealHelper::criarDeal(2, $categoryId, $dealsParaCriar);
                
                // Extrai IDs criados do resultado
                $idsCriados = [];
                if (!empty($resultadoCriacao['ids'])) {
                    $idsCriados = explode(', ', $resultadoCriacao['ids']);
                }
                
                // Atualiza o negócio original (closer) com os ids criados
                if (!empty($idsCriados)) {
                    $dadosVinculo = [
                        'ufCrm_1670953245' => $idsCriados
                    ];
                    BitrixDealHelper::editarDeal(2, $dealId, $dadosVinculo);
                }
                
                $resultadosCriacao = [
                    'tipo' => 'diagnostico',
                    'empresas' => count($empresas),
                    'oportunidades' => count($oferecidas),
                    'deals_enviados' => count($dealsParaCriar),
                    'deals_criados' => $resultadoCriacao['quantidade'] ?? 0,
                    'tempo_total' => $resultadoCriacao['tempo_total_segundos'] ?? 0,
                    'resultado_completo' => $resultadoCriacao
                ];
            }

        } else if ($etapaAtualId === 'C53:WON') {
            if (empty($vinculados)) {
                // Concluído sem diagnóstico: criar cruzando empresas × oportunidades convertidas
                foreach ($empresas as $empresa) {
                    foreach ($conv as $oportunidade) {
                        $novoNegocio = $camposBase;
                        
                        // Normalização companyId
                        $companyIdMeta = $item['companyId'] ?? [];
                        $companyIdIsMultiple = $companyIdMeta['isMultiple'] ?? false;
                        if ($companyIdIsMultiple) {
                            $novoNegocio['companyId'] = is_array($empresa) ? $empresa : [$empresa];
                        } else {
                            $novoNegocio['companyId'] = is_array($empresa) ? (count($empresa) ? $empresa[0] : '') : $empresa;
                        }
                        
                        // Normalização ufCrm_1646069163997 (Oportunidade)
                        $opMeta = $item['ufCrm_1646069163997'] ?? [];
                        $opIsMultiple = $opMeta['isMultiple'] ?? false;
                        if ($opIsMultiple) {
                            $novoNegocio['ufCrm_1646069163997'] = is_array($oportunidade) ? $oportunidade : [$oportunidade];
                        } else {
                            $novoNegocio['ufCrm_1646069163997'] = is_array($oportunidade) ? (count($oportunidade) ? $oportunidade[0] : '') : $oportunidade;
                        }
                        
                        $novoNegocio['ufcrm_1707331568'] = $dealId;
                        $dealsParaCriar[] = $novoNegocio;
                    }
                }
                
                // Criação em massa
                if (!empty($dealsParaCriar)) {
                    $resultadoCriacao = BitrixDealHelper::criarDeal(2, null, $dealsParaCriar);
                    
                    $resultadosCriacao = [
                        'tipo' => 'concluido_sem_diagnostico',
                        'empresas' => count($empresas),
                        'oportunidades' => count($conv),
                        'deals_enviados' => count($dealsParaCriar),
                        'deals_criados' => $resultadoCriacao['quantidade'] ?? 0,
                        'tempo_total' => $resultadoCriacao['tempo_total_segundos'] ?? 0,
                        'resultado_completo' => $resultadoCriacao
                    ];
                }
                
            } else {
                // Concluído após diagnóstico: consultar negócios vinculados e só criar o que falta
                $idsVinculados = is_array($vinculados) ? $vinculados : explode(',', $vinculados);
                $existentes = [];
                
                // Consulta todos os vinculados para verificar o que já existe
                foreach ($idsVinculados as $idVinc) {
                    $resVinc = BitrixDealHelper::consultarDeal(2, $idVinc, 'companyId,ufCrm_1646069163997');
                    $dadosVinc = $resVinc['result'] ?? [];
                    $empresaVinc = $dadosVinc['companyId']['texto'] ?? null;
                    $oportunidadeVinc = $dadosVinc['ufCrm_1646069163997']['texto'] ?? null;
                    if ($empresaVinc && $oportunidadeVinc) {
                        $existentes[$empresaVinc][$oportunidadeVinc] = true;
                    }
                }
                
                // Cria apenas os que não existem
                foreach ($empresas as $empresa) {
                    foreach ($conv as $oportunidade) {
                        if (empty($existentes[$empresa][$oportunidade])) {
                            $novoNegocio = $camposBase;
                            
                            // Normalização companyId
                            $companyIdMeta = $item['companyId'] ?? [];
                            $companyIdIsMultiple = $companyIdMeta['isMultiple'] ?? false;
                            if ($companyIdIsMultiple) {
                                $novoNegocio['companyId'] = is_array($empresa) ? $empresa : [$empresa];
                            } else {
                                $novoNegocio['companyId'] = is_array($empresa) ? (count($empresa) ? $empresa[0] : '') : $empresa;
                            }
                            
                            // Normalização ufCrm_1646069163997 (Oportunidade)
                            $opMeta = $item['ufCrm_1646069163997'] ?? [];
                            $opIsMultiple = $opMeta['isMultiple'] ?? false;
                            if ($opIsMultiple) {
                                $novoNegocio['ufCrm_1646069163997'] = is_array($oportunidade) ? $oportunidade : [$oportunidade];
                            } else {
                                $novoNegocio['ufCrm_1646069163997'] = is_array($oportunidade) ? (count($oportunidade) ? $oportunidade[0] : '') : $oportunidade;
                            }
                            
                            $novoNegocio['ufcrm_1707331568'] = $dealId;
                            $dealsParaCriar[] = $novoNegocio;
                        }
                    }
                }
                
                // Criação em massa dos que faltam
                if (!empty($dealsParaCriar)) {
                    $resultadoCriacao = BitrixDealHelper::criarDeal(2, null, $dealsParaCriar);
                    
                    $resultadosCriacao = [
                        'tipo' => 'concluido_apos_diagnostico',
                        'empresas' => count($empresas),
                        'oportunidades' => count($conv),
                        'deals_existentes' => count($idsVinculados),
                        'deals_enviados' => count($dealsParaCriar),
                        'deals_criados' => $resultadoCriacao['quantidade'] ?? 0,
                        'tempo_total' => $resultadoCriacao['tempo_total_segundos'] ?? 0,
                        'resultado_completo' => $resultadoCriacao
                    ];
                } else {
                    $resultadosCriacao = [
                        'tipo' => 'concluido_apos_diagnostico',
                        'mensagem' => 'Todos os deals já existem',
                        'deals_existentes' => count($idsVinculados)
                    ];
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'result' => $resultadosCriacao,
            'diagnostico' => $logDiagnostico
        ]);
    }
}
