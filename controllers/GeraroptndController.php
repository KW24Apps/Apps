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

        // LOG: Debug das arrays para verificar se há loops infinitos
        $logDebug = date('Y-m-d H:i:s') . ' | DEBUG: empresas=' . count($empresas) . ', oferecidas=' . count($oferecidas) . ', conv=' . count($conv) . "\n";
        file_put_contents(__DIR__ . '/../logs/01.log', $logDebug, FILE_APPEND);

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
            'tentativas' => []
        ];
        $vinculados = $item['ufCrm_1670953245']['valor'] ?? null;
        if ($etapaAtualId === 'C53:UC_1PAPS7') {
            // Solicitar Diagnóstico: criar cruzando empresas × oportunidades oferecidas
            $idsCriados = [];
            $contador = 0;
            $maxTentativas = 20; // Limite máximo para evitar loops infinitos
            
            foreach ($empresas as $empresa) {
                foreach ($oferecidas as $oportunidade) {
                    $contador++;
                    if ($contador > $maxTentativas) {
                        $logPayload = date('Y-m-d H:i:s') . ' | LIMITE ATINGIDO: Máximo de ' . $maxTentativas . ' tentativas atingido.' . "\n";
                        file_put_contents(__DIR__ . '/../logs/01.log', $logPayload, FILE_APPEND);
                        break 2; // Sai dos dois loops
                    }
                    
                    // Criar negócio apenas com o campo empresa (companyId) e funil/categoria 17
                    $novoNegocio = [];
                    // Garantir que nunca exista 'categoryId' (caixa baixa) no array
                    unset($novoNegocio['categoryId']);
                    $companyIdMeta = $item['companyId'] ?? [];
                    $companyIdIsMultiple = $companyIdMeta['isMultiple'] ?? false;
                    if ($companyIdIsMultiple) {
                        $novoNegocio['companyId'] = is_array($empresa) ? $empresa : [$empresa];
                    } else {
                        $novoNegocio['companyId'] = is_array($empresa) ? (count($empresa) ? $empresa[0] : '') : $empresa;
                    }
                    // Define CATEGORY_ID conforme regras de etapa e tipo de processo
                    $tipoProcesso = trim($item['ufCrm_1650979003']['texto'] ?? '');
                    if ($etapaAtualId === 'C53:UC_1PAPS7') {
                        if (
                            $tipoProcesso === '' ||
                            strcasecmp($tipoProcesso, 'Administrativo') === 0 ||
                            strcasecmp($tipoProcesso, 'Anexo V') === 0 ||
                            strcasecmp($tipoProcesso, 'Contencioso Ativo') === 0
                        ) {
                            $novoNegocio['CATEGORY_ID'] = 17; // Relatório Preliminar
                        } else {
                            $novoNegocio['CATEGORY_ID'] = 17; // Contencioso
                        }
                    } else if ($etapaAtualId === 'C53:WON') {
                        if (strcasecmp($tipoProcesso, 'Administrativo') === 0) {
                            $novoNegocio['CATEGORY_ID'] = 17; // Operacional
                        } else {
                            $novoNegocio['CATEGORY_ID'] = 17; // Contencioso
                        }
                    }
                    // Extrai categoryId como parâmetro separado, igual ao DealController que funciona
                    $categoryId = $novoNegocio['CATEGORY_ID'];
                    unset($novoNegocio['CATEGORY_ID']);
                    // LOG: grava o payload enviado para criar negócio
                    $logPayload = date('Y-m-d H:i:s') . ' | PAYLOAD CRIAR: ' . json_encode($novoNegocio, JSON_UNESCAPED_UNICODE) . ' | CATEGORY_ID: ' . $categoryId . "\n";
                    file_put_contents(__DIR__ . '/../logs/01.log', $logPayload, FILE_APPEND);
                    $res = BitrixDealHelper::criarDeal(2, $categoryId, $novoNegocio);
                    
                    // Delay de 2 segundos entre chamadas para evitar rate limiting do Bitrix
                    sleep(2);
                    // Se criado com sucesso, salva o id
                    if (!empty($res['success']) && !empty($res['id'])) {
                        $idsCriados[] = $res['id'];
                    }
                    $tentativa = [
                        'empresa' => $empresa,
                        'oportunidade' => $oportunidade,
                        'camposEnviados' => $novoNegocio,
                        'resultado' => $res
                    ];
                    $logDiagnostico['tentativas'][] = $tentativa;
                    $resultadosCriacao[] = [
                        'empresa' => $empresa,
                        'oportunidade' => $oportunidade,
                        'resultado' => $res
                    ];
                }
            }
            // Atualiza o negócio original (closer) com os ids criados em ufCrm_1670953245
            if (!empty($idsCriados)) {
                $dadosVinculo = [
                    'ufCrm_1670953245' => $idsCriados
                ];
                BitrixDealHelper::editarDeal(2, $dealId, $dadosVinculo);
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
                        // Normalização ufCrm_1646069163997 (sempre array se múltiplo)
                        $opMeta = $item['ufCrm_1646069163997'] ?? [];
                        $opIsMultiple = $opMeta['isMultiple'] ?? false;
                        if ($opIsMultiple) {
                            if (is_array($oportunidade)) {
                                $novoNegocio['ufCrm_1646069163997'] = $oportunidade;
                            } else {
                                $novoNegocio['ufCrm_1646069163997'] = [$oportunidade];
                            }
                        } else {
                            if (is_array($oportunidade)) {
                                $novoNegocio['ufCrm_1646069163997'] = count($oportunidade) ? $oportunidade[0] : '';
                            } else {
                                $novoNegocio['ufCrm_1646069163997'] = $oportunidade;
                            }
                        }
                        $novoNegocio['ufcrm_1707331568'] = $closerId;
                        $res = BitrixDealHelper::criarDeal(2, null, $novoNegocio);
                        $tentativa = [
                            'empresa' => $empresa,
                            'oportunidade' => $oportunidade,
                            'camposEnviados' => $novoNegocio,
                            'resultado' => $res
                        ];
                        $logDiagnostico['tentativas'][] = $tentativa;
                        $resultadosCriacao[] = [
                            'empresa' => $empresa,
                            'oportunidade' => $oportunidade,
                            'resultado' => $res
                        ];
                    }
                }
            } else {
                // Concluído após diagnóstico: consultar negócios vinculados e só criar o que falta
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
                        $tentativa = [
                            'empresa' => $empresa,
                            'oportunidade' => $oportunidade,
                            'jaExistente' => !empty($existentes[$empresa][$oportunidade]),
                            'camposEnviados' => null,
                            'resultado' => null
                        ];
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
                            // Normalização ufCrm_1646069163997 (sempre array se múltiplo)
                            $opMeta = $item['ufCrm_1646069163997'] ?? [];
                            $opIsMultiple = $opMeta['isMultiple'] ?? false;
                            if ($opIsMultiple) {
                                if (is_array($oportunidade)) {
                                    $novoNegocio['ufCrm_1646069163997'] = $oportunidade;
                                } else {
                                    $novoNegocio['ufCrm_1646069163997'] = [$oportunidade];
                                }
                            } else {
                                if (is_array($oportunidade)) {
                                    $novoNegocio['ufCrm_1646069163997'] = count($oportunidade) ? $oportunidade[0] : '';
                                } else {
                                    $novoNegocio['ufCrm_1646069163997'] = $oportunidade;
                                }
                            }
                            $novoNegocio['ufcrm_1707331568'] = $closerId;
                            $res = BitrixDealHelper::criarDeal(2, null, $novoNegocio);
                            $tentativa['camposEnviados'] = $novoNegocio;
                            $tentativa['resultado'] = $res;
                            $resultadosCriacao[] = [
                                'empresa' => $empresa,
                                'oportunidade' => $oportunidade,
                                'resultado' => $res
                            ];
                        }
                        $logDiagnostico['tentativas'][] = $tentativa;
                    }
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
