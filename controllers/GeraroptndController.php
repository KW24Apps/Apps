<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../enums/GeraroptndEnums.php';

use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;
use Enums\GeraroptndEnums;

class GeraroptndController
{
    public function executar()
    {
        // Definir timeout de 30 minutos para criação de múltiplos deals
        set_time_limit(1800); // 30 minutos = 1800 segundos
        
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
        
        // Passo 1: Definir campos a consultar usando Enums
        $camposBitrix = GeraroptndEnums::getAllFields();
        
        // Passo 1: Consultar deal usando BitrixDealHelper
        $camposStr = implode(',', $camposBitrix);
        $resultado = BitrixDealHelper::consultarDeal(2, $dealId, $camposStr);
        $item = $resultado['result'] ?? [];
        
        // Passo 2: Consultar metadados dos campos CRM
        $crmFields = BitrixHelper::consultarCamposCrm(2);
        
        // Debug completo da resposta
        $debugCrmFieldsCompleto = [
            'resposta_completa' => $crmFields,
            'tem_result' => isset($crmFields['result']),
            'keys_principais' => is_array($crmFields) ? array_keys($crmFields) : 'não é array',
        ];
        
        // Passo 3: Normalizar e extrair empresas, oferecidas, convertidas
        $empresas = [];
        if (!empty($item['ufCrm_1689718588']['texto'])) {
            $empresas = is_array($item['ufCrm_1689718588']['texto']) 
                ? $item['ufCrm_1689718588']['texto'] 
                : explode(',', $item['ufCrm_1689718588']['texto']);
        }
        
        $ofer = [];
        if (!empty($item['ufCrm_1688060696']['texto'])) {
            $textoOfer = $item['ufCrm_1688060696']['texto'];
            if (is_array($textoOfer)) {
                $ofer = array_filter($textoOfer, function($valor) {
                    return !in_array(strtoupper(trim($valor)), ['N', 'NAO', 'NÃO', 'NENHUMA', 'NONE', '']);
                });
            } else {
                $valores = explode(',', $textoOfer);
                $ofer = array_filter($valores, function($valor) {
                    return !in_array(strtoupper(trim($valor)), ['N', 'NAO', 'NÃO', 'NENHUMA', 'NONE', '']);
                });
            }
        }
        
        $conv = [];
        if (!empty($item['ufCrm_1728327366']['texto'])) {
            $textoConv = $item['ufCrm_1728327366']['texto'];
            if (is_array($textoConv)) {
                $conv = array_filter($textoConv, function($valor) {
                    return !in_array(strtoupper(trim($valor)), ['N', 'NAO', 'NÃO', 'NENHUMA', 'NONE', '']);
                });
            } else {
                $valores = explode(',', $textoConv);
                $conv = array_filter($valores, function($valor) {
                    return !in_array(strtoupper(trim($valor)), ['N', 'NAO', 'NÃO', 'NENHUMA', 'NONE', '']);
                });
            }
        }

        // Passo 4: Construir mapa $oportunidades apenas das selecionadas
        $oportunidades = [];
        $debugCrmFields = null; // Debug temporário
        
        // A API do Bitrix retorna os campos diretamente, não em result.fields
        if (!empty($crmFields) && (isset($crmFields['ufCrm_1646069163997']) || 
            isset($crmFields['ufCrm_1688060696']) || isset($crmFields['ufCrm_1728327366']))) {
            
            // Primeiro, coletar todas as oportunidades selecionadas (oferecidas + convertidas)
            $oportunidadesSelecionadas = array_merge($ofer, $conv);
            $oportunidadesSelecionadas = array_unique($oportunidadesSelecionadas);
            
            $debugCrmFields = [
                'oportunidades_selecionadas' => $oportunidadesSelecionadas,
                'campos_disponiveis' => ['uf_1646069163997' => isset($crmFields['ufCrm_1646069163997']), 
                                        'uf_1688060696' => isset($crmFields['ufCrm_1688060696']),
                                        'uf_1728327366' => isset($crmFields['ufCrm_1728327366'])]
            ];
            
            // Mapear campos de oportunidade usando Enum
            $camposOportunidades = GeraroptndEnums::CAMPOS_OPORTUNIDADES;
            
            foreach ($camposOportunidades as $campo => $tipo) {
                if (isset($crmFields[$campo]['items'])) {
                    $debugCrmFields[$campo] = [
                        'tem_items' => true,
                        'items_count' => count($crmFields[$campo]['items'])
                    ];
                    
                    foreach ($crmFields[$campo]['items'] as $itemData) {
                        $nomeAmigavel = $itemData['VALUE'] ?? '';
                        $itemId = $itemData['ID'] ?? '';
                        
                        if (!empty($nomeAmigavel) && in_array($nomeAmigavel, $oportunidadesSelecionadas)) {
                            if (!isset($oportunidades[$nomeAmigavel])) {
                                $oportunidades[$nomeAmigavel] = [];
                            }
                            
                            // Armazenar apenas o ID (o nome já é a chave do array)
                            $oportunidades[$nomeAmigavel][$tipo] = $itemId;
                        }
                    }
                } else {
                    $debugCrmFields[$campo] = ['tem_items' => false, 'items_count' => 0];
                }
            }
        } else {
            $debugCrmFields = ['erro' => 'Campos de oportunidade não encontrados na resposta da API'];
        }
        
        // Passo 5: Montar campos base para espelhar usando Enum
        $camposParaEspelhar = [];
        $camposExcluir = GeraroptndEnums::CAMPOS_EXCLUIR;
        
        foreach ($item as $campo => $valor) {
            if (!in_array($campo, $camposExcluir)) {
                $camposParaEspelhar[$campo] = $valor;
            }
        }
        
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
        if ($etapaAtualId === GeraroptndEnums::ETAPA_SOLICITAR_DIAGNOSTICO) {
            $processType = 1; // solicitar diagnóstico
        } elseif ($etapaAtualId === GeraroptndEnums::ETAPA_CONCLUIDO) {
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
        // PARTE 3: DETERMINAÇÃO DE PIPELINE E ETAPA (Passo 1)
        // ============================================
        
        // Obter tipo de processo operacional para a lógica
        $tipoProcessoOperacional = $item['ufCrm_1650979003']['valor'] ?? null;
        $tipoProcessoTexto = $item['ufCrm_1650979003']['texto'] ?? 'Não definido';
        
        // Determinar pipeline e etapa de destino - passar o texto diretamente
        $destinoInfo = $this->determinarDestinoDeals($processType, $tipoProcessoTexto, $empresas, $ofer, $conv);
        
        // ============================================
        // PARTE 3: OBTER COMBINAÇÕES PARA CRIAR
        // ============================================
        
        // Buscar combinações que precisam ser criadas
        $combinacoesParaCriar = $this->obterCombinacoesParaCriar($processType, $empresas, $ofer, $conv, $oportunidades, $dealId);
        
        // Se não há nada para criar
        if (empty($combinacoesParaCriar)) {
            header('Content-Type: application/json');
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Todos os deals já foram criados',
                'deals_para_criar' => [
                    'quantidade' => 0,
                    'combinacoes' => []
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // ============================================
        // PARTE 4: MONTAR ARRAY COMPLETO PARA CRIAÇÃO
        // ============================================
        
        $arrayFinalParaCriacao = $this->montarArrayCompletoPoraCriacao($combinacoesParaCriar, $camposParaEspelhar, $destinoInfo, $dealId);

        // ============================================
        // PARTE 4: CRIAR OS DEALS VIA JOB ASSÍNCRONO
        // ============================================
        
        // Ao invés de criar diretamente, enviar para fila de Jobs
        $resultadoCriacao = BitrixDealHelper::criarJobParaFila(
            $arrayFinalParaCriacao['entityId'],
            $arrayFinalParaCriacao['categoryId'], 
            $arrayFinalParaCriacao['fields'],
            'gerar_oportunidades' // Tipo do job
        );
        
        // ============================================
        // PARTE 4: RETORNO IMEDIATO (SEM ATUALIZAR VINCULADOS)
        // ============================================
        
        // Para jobs assíncronos, não podemos atualizar os vinculados imediatamente
        // Isso será feito pelo processador de jobs quando os deals forem criados
        
        // ============================================
        // RETORNO FINAL - JOB CRIADO
        // ============================================
        
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => true,
            'resultado_criacao' => $resultadoCriacao,
            'deals_para_processar' => [
                'quantidade' => count($arrayFinalParaCriacao['fields']),
                'pipeline_destino' => $destinoInfo['pipeline_name'],
                'etapa_destino' => $destinoInfo['stage_name']
            ],
            'job_info' => [
                'job_id' => $resultadoCriacao['job_id'] ?? null,
                'status' => $resultadoCriacao['status'] ?? 'erro',
                'consultar_status' => $resultadoCriacao['consultar_status'] ?? null
            ],
            'resumo' => [
                'deals_solicitados' => count($combinacoesParaCriar),
                'modo_execucao' => 'assincrono_via_job',
                'tempo_criacao_job' => 'imediato'
            ],
            'momento_atual' => [
                'etapa' => $etapaAtualId,
                'processType' => $processType,
                'tipo_processo' => $tipoProcessoTexto
            ],
            'debug_tentativa_criacao' => [
                'array_enviado_para_job' => $arrayFinalParaCriacao,
                'combinacoes_identificadas' => $combinacoesParaCriar,
                'campos_por_deal' => array_map(function($deal) {
                    return [
                        'campos_enviados' => array_keys($deal),
                        'valores' => $deal
                    ];
                }, $arrayFinalParaCriacao['fields'])
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Obtém as combinações empresa+oportunidade que precisam ser criadas
     * Faz busca ativa no Bitrix para comparar com o que já existe
     */
    private function obterCombinacoesParaCriar($processType, $empresas, $ofer, $conv, $oportunidades, $dealId)
    {
        // Buscar deals existentes que referenciam este closer
        $dealsExistentesResult = BitrixHelper::listarItensCrm(2, [
            'ufcrm_1707331568' => $dealId
        ], ['companyId', 'ufCrm_1646069163997']);
        
        $dealsExistentes = [];
        if ($dealsExistentesResult['success'] && !empty($dealsExistentesResult['items'])) {
            foreach ($dealsExistentesResult['items'] as $deal) {
                $companyId = $deal['companyId'] ?? null;
                $opportunityId = $deal['ufCrm_1646069163997'] ?? null;
                
                if ($companyId && $opportunityId) {
                    $dealsExistentes[] = [
                        'companyId' => $companyId,
                        'opportunityId' => $opportunityId
                    ];
                }
            }
        }
        
        // Determinar quais oportunidades usar baseado no processType
        $oportunidadesParaUsar = [];
        if ($processType == 1) {
            $oportunidadesParaUsar = $ofer; // oferecidas
        } else {
            $oportunidadesParaUsar = $conv; // convertidas
        }
        
        // Montar todas as combinações desejadas
        $combinacoesDesejadas = [];
        foreach ($empresas as $empresa) {
            foreach ($oportunidadesParaUsar as $nomeOportunidade) {
                // Verificar se temos o mapeamento da oportunidade
                if (!isset($oportunidades[$nomeOportunidade]['oportunidade'])) {
                    continue; // Pula se não tem o ID mapeado
                }
                
                $combinacoesDesejadas[] = [
                    'companyId' => $empresa,
                    'opportunityId' => $oportunidades[$nomeOportunidade]['oportunidade'],
                    'opportunityName' => $nomeOportunidade
                ];
            }
        }
        
        // Filtrar apenas as que NÃO existem ainda
        $combinacoesParaCriar = [];
        foreach ($combinacoesDesejadas as $desejada) {
            $jaExiste = false;
            
            foreach ($dealsExistentes as $existente) {
                if ($existente['companyId'] == $desejada['companyId'] && 
                    $existente['opportunityId'] == $desejada['opportunityId']) {
                    $jaExiste = true;
                    break;
                }
            }
            
            if (!$jaExiste) {
                $combinacoesParaCriar[] = $desejada;
            }
        }
        
        return $combinacoesParaCriar;
    }

    /**
     * Determina pipeline e etapa de destino baseado no processType e tipo de processo
     */
    private function determinarDestinoDeals($processType, $tipoProcessoTexto, $empresas, $ofer, $conv)
    {
        $categoryId = null;
        $stageId = null;
        $pipelineName = '';
        $stageName = '';
        $oportunidadesOrigem = '';
        $totalEstimado = 0;
        
        // Normalizar tipo de processo para comparação
        $tipoNormalizado = strtolower(trim($tipoProcessoTexto ?? ''));
        
        switch ($processType) {
            case 1: // Solicitando Diagnóstico
                $oportunidadesOrigem = 'oferecidas';
                $totalEstimado = count($empresas) * count($ofer);
                
                // Para solicitar diagnóstico: Administrativo, Administrativo Anexo V, Contencioso Ativo ou VAZIO vão para Relatório Preliminar
                $vaiParaRelatorio = in_array($tipoNormalizado, [
                    'administrativo', 
                    'administrativo (anexo v)',
                    'administrativo anexo 5', 
                    'contencioso ativo'
                ]) || empty($tipoProcessoTexto) || $tipoProcessoTexto === 'Não definido';
                
                if ($vaiParaRelatorio) {
                    $categoryId = GeraroptndEnums::CATEGORIA_RELATORIO_PRELIMINAR;
                    $stageId = GeraroptndEnums::STAGE_ID_TRIAGEM_RELATORIO; // Usar ID real: 493
                    $pipelineName = 'Relatório Preliminar';
                    $stageName = 'Coleta de Documentos (Parceiro)';
                } else {
                    $categoryId = GeraroptndEnums::CATEGORIA_CONTENCIOSO;
                    $stageId = GeraroptndEnums::STAGE_ID_TRIAGEM; // Usar ID real: 1067
                    $pipelineName = 'Contencioso';
                    $stageName = 'Triagem';
                }
                break;
                
            case 2: // Concluído sem Diagnóstico
            case 3: // Concluído com Diagnóstico
                $oportunidadesOrigem = $processType == 2 ? 'convertidas' : 'convertidas (apenas faltantes)';
                $totalEstimado = count($empresas) * count($conv);
                
                // Para concluído: APENAS "Administrativo" puro vai para Operacional, todos os outros para Contencioso
                $vaiParaOperacional = ($tipoNormalizado === 'administrativo');
                
                if ($vaiParaOperacional) {
                    $categoryId = GeraroptndEnums::CATEGORIA_OPERACIONAL;
                    $stageId = GeraroptndEnums::STAGE_ID_TRIAGEM_OPERACIONAL; // Usar ID real: 477
                    $pipelineName = 'Operacional';
                    $stageName = 'Triagem (CheckList Operação)';
                } else {
                    $categoryId = GeraroptndEnums::CATEGORIA_CONTENCIOSO;
                    $stageId = GeraroptndEnums::STAGE_ID_TRIAGEM; // Usar ID real: 1067
                    $pipelineName = 'Contencioso';
                    $stageName = 'Triagem';
                }
                break;
                
            default:
                return [
                    'erro' => 'ProcessType inválido: ' . $processType,
                    'total_deals_estimado' => 0
                ];
        }
        
        return [
            'category_id' => $categoryId,
            'stage_id' => $stageId,
            'pipeline_name' => $pipelineName,
            'stage_name' => $stageName,
            'oportunidades_origem' => $oportunidadesOrigem,
            'decisao_baseada_em' => [
                'processType' => $processType,
                'tipo_processo_texto' => $tipoProcessoTexto,
                'tipo_normalizado' => $tipoNormalizado,
                'regra_aplicada' => $processType == 1 ? 'solicitar_diagnostico' : 'concluido',
                'criterio_decisao' => $processType == 1 
                    ? 'Administrativo/Anexo V/Contencioso Ativo/Vazio → Relatório Preliminar | Outros → Contencioso'
                    : 'Apenas Administrativo → Operacional | Outros → Contencioso'
            ],
            'total_deals_estimado' => $totalEstimado,
            'calculo_estimativa' => [
                'empresas' => count($empresas),
                'oportunidades_origem' => $processType == 1 ? count($ofer) : count($conv),
                'formula' => 'empresas × oportunidades'
            ]
        ];
    }
    
    /**
     * Monta o array completo com todos os campos para cada deal a ser criado
     */
    private function montarArrayCompletoPoraCriacao($combinacoes, $camposParaEspelhar, $destinoInfo, $dealId)
    {
        $dealsCompletos = [];
        
        foreach ($combinacoes as $combinacao) {
            // GENÉRICO: Começar com TODOS os campos para espelhar
            $dealCompleto = [];
            
            // 1. Adicionar todos os campos que devem ser espelhados (APENAS valores simples)
            foreach ($camposParaEspelhar as $campo => $valorCompleto) {
                // CORREÇÃO: Extrair corretamente o valor baseado na estrutura
                if (is_array($valorCompleto) && isset($valorCompleto['valor'])) {
                    // Se tem estrutura {nome, valor, texto, type}, pegar apenas o valor
                    $valor = $valorCompleto['valor'];
                    
                    // Se o valor ainda é um array, verificar se é múltiplo
                    if (is_array($valor) && count($valor) === 1) {
                        $dealCompleto[$campo] = $valor[0]; // Converter array de 1 elemento para valor único
                    } elseif (is_array($valor)) {
                        $dealCompleto[$campo] = $valor; // Manter array múltiplo
                    } else {
                        $dealCompleto[$campo] = $valor; // Valor simples
                    }
                } else {
                    // Valor direto sem estrutura
                    $dealCompleto[$campo] = $valorCompleto;
                }
            }
            
            // 2. Sobrescrever/adicionar campos específicos desta combinação
            $dealCompleto['companyId'] = (int)$combinacao['companyId'];           // Empresa específica como integer
            $dealCompleto['ufCrm_1646069163997'] = $combinacao['opportunityId'];  // Oportunidade específica
            $dealCompleto['ufcrm_1707331568'] = $dealId;                          // Negócio Closer (será validado automaticamente)
            $dealCompleto['stageId'] = $destinoInfo['stage_id'];                  // Etapa de destino
            
            $dealsCompletos[] = $dealCompleto;
        }
        
        return [
            'entityId' => 2, // Deals sempre são entity 2
            'categoryId' => $destinoInfo['category_id'],
            'fields' => $dealsCompletos
        ];
    }
}

