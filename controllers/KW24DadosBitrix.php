<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixTaskHelper.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../dao/KW24DadosBitrixDAO.php';

use Helpers\BitrixHelper;
use Helpers\BitrixTaskHelper;
use Helpers\BitrixCompanyHelper;
use Helpers\LogHelper;
use dao\KW24DadosBitrixDAO;

class KW24DadosBitrix 
{
    private $config;

    public function __construct() 
    {
        $this->config = require __DIR__ . '/../config/config_KW24DadosBitrix.php';
    }

    // FUNÇÃO PRINCIPAL - Executa sincronização completa dos dados Bitrix24
    public function executar() 
    {
        // Gera o TRACE_ID uma única vez (igual ao index.php)
        LogHelper::gerarTraceId();
        
        LogHelper::logSincronizacaoBitrix($this->config['sync']['messages']['sync_start'], __CLASS__ . '::' . __FUNCTION__);
        
        try {
            // Configurar webhook global para BitrixHelper
            $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $this->config['sync']['webhook_bitrix'];
            
            // PASSO 1: Consultar Dados
            LogHelper::logSincronizacaoBitrix($this->config['sync']['messages']['step_1_start'], __CLASS__ . '::' . __FUNCTION__);
            $dadosConsultados = $this->consultardados();
            if (!$dadosConsultados['sucesso']) {
                throw new \Exception('Falha na consulta de dados: ' . $dadosConsultados['erro']);
            }
            LogHelper::logSincronizacaoBitrix($this->config['sync']['messages']['step_1_success'], __CLASS__ . '::' . __FUNCTION__);
            
            // PASSO 2: Tabela Dicionário (criar/atualizar primeiro)
            LogHelper::logSincronizacaoBitrix($this->config['sync']['messages']['step_2_start'], __CLASS__ . '::' . __FUNCTION__);
            $resultadoDicionario = $this->tabeladinamica($dadosConsultados['dados']);
            if (!$resultadoDicionario['sucesso']) {
                throw new \Exception('Falha na tabela dicionário: ' . $resultadoDicionario['erro']);
            }
            LogHelper::logSincronizacaoBitrix(sprintf($this->config['sync']['messages']['step_2_success'], $resultadoDicionario['total_campos']), __CLASS__ . '::' . __FUNCTION__);
            
            // PASSO 3: Criar tabelas padrão (apenas se não existirem)
            LogHelper::logSincronizacaoBitrix('PASSO 3: Verificando e criando tabelas padrão', __CLASS__ . '::' . __FUNCTION__);
            
            // Verificar se tabelas já existem
            $dao = new KW24DadosBitrixDAO();
            $tabelasExistem = $dao->tabelaExiste('kw24_cards') && 
                             $dao->tabelaExiste('kw24_clientes') && 
                             $dao->tabelaExiste('kw24_tarefas');
            
            if (!$tabelasExistem) {
                $resultadoTabelas = $this->criarTabelasPadrao();
                if (!$resultadoTabelas['sucesso']) {
                    throw new \Exception('Falha na criação das tabelas: ' . $resultadoTabelas['erro']);
                }
                LogHelper::logSincronizacaoBitrix('Tabelas padrão criadas com sucesso', __CLASS__ . '::' . __FUNCTION__);
            } else {
                LogHelper::logSincronizacaoBitrix('Tabelas já existem - pulando criação', __CLASS__ . '::' . __FUNCTION__);
            }
            
            LogHelper::logSincronizacaoBitrix($this->config['sync']['messages']['sync_success'], __CLASS__ . '::' . __FUNCTION__);
            return [
                'sucesso' => true, 
                'mensagem' => 'Sincronização completa realizada',
                'detalhes' => [
                    'dicionario' => $resultadoDicionario
                ]
            ];
            
        } catch (\Exception $e) {
            LogHelper::logSincronizacaoBitrix('ERRO na sincronização: ' . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    public function consultardados() 
    {
        try {
            // PASSO 1: Consultar Dados do Bitrix24 usando configurações centralizadas
            $cardsKW24 = BitrixHelper::listarItensCrm($this->config['sync']['entity_ids']['cards']);
            $camposSpaKW24 = BitrixHelper::consultarCamposSpa($this->config['sync']['entity_ids']['cards']);
            $etapasSpaKW24 = BitrixHelper::consultarEtapasPorTipo($this->config['sync']['entity_ids']['cards']);
            $camposTask = BitrixTaskHelper::consultarCamposTask();
            $todasTarefas = BitrixTaskHelper::listarTarefas(['GROUP_ID' => $this->config['sync']['entity_ids']['tasks_group']], ['*']);
            
            // Validar dados principais
            if (!$cardsKW24['success']) {
                throw new \Exception('Falha ao consultar cards da SPA: ' . ($cardsKW24['error'] ?? 'Erro desconhecido'));
            }
            
            // Extrair IDs únicos de empresas
            $idsEmpresasUnicas = [];
            $empresasVinculadas = [];
            
            if (!empty($cardsKW24['items'])) {
                foreach ($cardsKW24['items'] as $card) {
                    if (!empty($card['companyId'])) {
                        $idsEmpresasUnicas[] = $card['companyId'];
                    }
                }
                $idsEmpresasUnicas = array_unique($idsEmpresasUnicas);
                $empresasVinculadas = BitrixCompanyHelper::consultarEmpresas(['origem' => $idsEmpresasUnicas]);
            }
            
            $cardsColaboradores = BitrixHelper::listarItensCrm(
                $this->config['sync']['entity_ids']['colaboradores'], 
                ['categoryId' => $this->config['sync']['entity_ids']['colaboradores_category']]
            );
            $camposSpaColaboradores = BitrixHelper::consultarCamposSpa($this->config['sync']['entity_ids']['colaboradores']);
            $camposCompanies = BitrixHelper::consultarCamposSpa($this->config['sync']['entity_ids']['companies']);
            
            return [
                'sucesso' => true,
                'dados' => [
                    'cardsKW24' => $cardsKW24,
                    'camposSpaKW24' => $camposSpaKW24,
                    'etapasSpaKW24' => $etapasSpaKW24,
                    'camposTask' => $camposTask,
                    'todasTarefas' => $todasTarefas,
                    'idsEmpresasUnicas' => $idsEmpresasUnicas,
                    'empresasVinculadas' => $empresasVinculadas,
                    'cardsColaboradores' => $cardsColaboradores,
                    'camposSpaColaboradores' => $camposSpaColaboradores,
                    'camposCompanies' => $camposCompanies
                ]
            ];
            
        } catch (\Exception $e) {
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    public function tabeladinamica($dados) 
    {
        try {
            $dao = new KW24DadosBitrixDAO();
            
            LogHelper::logSincronizacaoBitrix('INÍCIO - Sincronizando tabela dicionário', __CLASS__ . '::' . __FUNCTION__);
            
            // Definir entidades a processar
            $entidades = [
                [
                    'tipo' => $this->config['sync']['entity_types']['cards'],
                    'dados' => $dados['camposSpaKW24'] ?? [],
                    'estrutura' => $this->config['sync']['api_structures']['simple']
                ],
                [
                    'tipo' => $this->config['sync']['entity_types']['tasks'], 
                    'dados' => $dados['camposTask'] ?? [],
                    'estrutura' => $this->config['sync']['api_structures']['api_result']
                ],
                [
                    'tipo' => $this->config['sync']['entity_types']['companies'],
                    'dados' => $dados['camposCompanies'] ?? [],
                    'estrutura' => $this->config['sync']['api_structures']['api_result_or_simple']
                ]
            ];
            
            $entidadesProcessadas = [];
            
            // Processar cada entidade
            foreach ($entidades as $entidade) {
                $campos = [];
                $dadosProcessados = [];
                
                // Detectar estrutura e extrair campos
                switch ($entidade['estrutura']) {
                    case 'simples':
                        $dadosProcessados = $entidade['dados'];
                        break;
                        
                    case 'api_result':
                        if (!empty($entidade['dados']['result']['fields'])) {
                            $dadosProcessados = $entidade['dados']['result']['fields'];
                        } elseif (!empty($entidade['dados']['fields'])) {
                            $dadosProcessados = $entidade['dados']['fields'];
                        }
                        break;
                        
                    case 'api_result_ou_simples':
                        if (!empty($entidade['dados']['result']['fields'])) {
                            $dadosProcessados = $entidade['dados']['result']['fields'];
                        } elseif (is_array($entidade['dados']) && !isset($entidade['dados']['result'])) {
                            $dadosProcessados = $entidade['dados'];
                        }
                        break;
                }
                
                // Processar campos extraídos
                foreach ($dadosProcessados as $ufField => $dadosCampo) {
                    // Extrair nome amigável
                    $nomeAmigavel = (is_array($dadosCampo) && isset($dadosCampo['title'])) 
                        ? $dadosCampo['title'] 
                        : $ufField;
                    
                    // Extrair tipo do campo
                    $tipoCampo = (is_array($dadosCampo) && isset($dadosCampo['type'])) 
                        ? $dadosCampo['type'] 
                        : null;
                    
                    $campos[] = [
                        'uf_field' => $ufField,
                        'friendly_name' => $nomeAmigavel,
                        'entity_type' => $entidade['tipo'],
                        'field_type' => $tipoCampo
                    ];
                }
                
                $entidadesProcessadas[] = [
                    'tipo' => $entidade['tipo'],
                    'campos' => $campos,
                    'count' => count($campos)
                ];
            }
            
            $totalCampos = array_sum(array_column($entidadesProcessadas, 'count'));
            
            // Verificar se tabela existe e sincronizar
            $tabelaExiste = $dao->tabelaDicionarioExiste();
            
            if (!$tabelaExiste) {
                $dao->criarTabelaDicionario();
                LogHelper::logSincronizacaoBitrix('Criando tabela dicionário com ' . $totalCampos . ' campos', __CLASS__ . '::' . __FUNCTION__);
            } else {
                LogHelper::logSincronizacaoBitrix('Atualizando tabela dicionário existente', __CLASS__ . '::' . __FUNCTION__);
                
                // Sincronizar campos existentes (adicionar novos, remover obsoletos)
                $camposAtuaisBanco = $dao->consultarDicionario();
                
                $ufsExistentes = [];
                foreach ($camposAtuaisBanco as $campo) {
                    $ufsExistentes[] = $campo['uf_field'] . '|' . $campo['entity_type'];
                }
                
                $todosOsCamposBitrix = [];
                foreach ($entidadesProcessadas as $entidade) {
                    foreach ($entidade['campos'] as $campo) {
                        $todosOsCamposBitrix[] = $campo['uf_field'] . '|' . $campo['entity_type'];
                    }
                }
                
                // Remover campos obsoletos
                $chavesObsoletas = array_diff($ufsExistentes, $todosOsCamposBitrix);
                if (!empty($chavesObsoletas)) {
                    $dao->removerCamposObsoletos($chavesObsoletas);
                    LogHelper::logSincronizacaoBitrix('Removidos ' . count($chavesObsoletas) . ' campos obsoletos', __CLASS__ . '::' . __FUNCTION__);
                }
                
                // Filtrar apenas campos novos
                foreach ($entidadesProcessadas as &$entidade) {
                    $entidade['campos'] = array_filter($entidade['campos'], function($campo) use ($ufsExistentes) {
                        $chave = $campo['uf_field'] . '|' . $campo['entity_type'];
                        return !in_array($chave, $ufsExistentes);
                    });
                }
            }
            
            // Inserir novos campos
            $camposParaInserir = [];
            foreach ($entidadesProcessadas as $entidade) {
                $camposParaInserir = array_merge($camposParaInserir, $entidade['campos']);
            }
            
            if (!empty($camposParaInserir)) {
                $dao->inserirCamposDicionario($camposParaInserir);
            }
            
            LogHelper::logSincronizacaoBitrix('SUCESSO - Total de campos processados: ' . $totalCampos, __CLASS__ . '::' . __FUNCTION__);
            
            return [
                'sucesso' => true,
                'tabela_existia' => $tabelaExiste,
                'total_campos' => $totalCampos,
                'detalhes' => array_column($entidadesProcessadas, 'count', 'tipo')
            ];
            
        } catch (\Exception $e) {
            LogHelper::logSincronizacaoBitrix('ERRO na tabela dicionário: ' . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }
 
    // Cria as tabelas padrão se não existirem
    public function criarTabelasPadrao() 
    {
        try {
            $dao = new KW24DadosBitrixDAO();
            
            LogHelper::logSincronizacaoBitrix('INÍCIO - Criando tabelas padrão', __CLASS__ . '::' . __FUNCTION__);
            
            // Definir mapeamento entity_type → nome da tabela
            $mapeamentoTabelas = [
                'cards' => 'kw24_cards',
                'companies' => 'kw24_clientes', 
                'tasks' => 'kw24_tarefas'
            ];
            
            $tabelasCriadas = [];
            
            foreach ($mapeamentoTabelas as $entityType => $nomeTabela) {
                // Consultar campos do dicionário para esta entidade
                $campos = $dao->consultarCamposPorEntidade($entityType);
                
                if (empty($campos)) {
                    LogHelper::logSincronizacaoBitrix("Nenhum campo encontrado para entity_type: $entityType", __CLASS__ . '::' . __FUNCTION__);
                    continue;
                }
                
                // Criar tabela usando friendly_name como colunas
                $resultado = $dao->criarTabelaComColunas($nomeTabela, $campos);
                
                if ($resultado['sucesso']) {
                    $tabelasCriadas[] = [
                        'tabela' => $nomeTabela,
                        'entity_type' => $entityType,
                        'colunas' => count($campos)
                    ];
                    LogHelper::logSincronizacaoBitrix("Tabela $nomeTabela criada com " . count($campos) . " colunas", __CLASS__ . '::' . __FUNCTION__);
                } else {
                    throw new \Exception("Falha ao criar tabela $nomeTabela: " . $resultado['erro']);
                }
            }
            
            LogHelper::logSincronizacaoBitrix('SUCESSO - ' . count($tabelasCriadas) . ' tabelas criadas', __CLASS__ . '::' . __FUNCTION__);
            
            return [
                'sucesso' => true,
                'tabelas_criadas' => $tabelasCriadas,
                'total' => count($tabelasCriadas)
            ];
            
        } catch (\Exception $e) {
            LogHelper::logSincronizacaoBitrix('ERRO na criação das tabelas: ' . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    public function montararray($dados) 
    {
        try {
            $cardsKW24 = $dados['cardsKW24'];
            $camposSpaKW24 = $dados['camposSpaKW24'];
            $etapasSpaKW24 = $dados['etapasSpaKW24'];
            
            $dealsProcessados = [];
            
            // Processar cada card/deal individualmente
            foreach ($cardsKW24['items'] as $card) {
                // 1. Mapear valores enumerados (igual ao BitrixDealHelper)
                $cardComEnums = BitrixHelper::mapearValoresEnumerados($card, $camposSpaKW24);
                
                // 2. Mapear nome da etapa, se existir stageId
                $stageName = null;
                if (isset($card['stageId'])) {
                    $stageName = BitrixHelper::mapearEtapaPorId($card['stageId'], $etapasSpaKW24);
                }
                
                // 3. Montar estrutura final para este card (igual ao Postman)
                $cardEstruturado = [];
                
                // Sempre incluir ID
                $cardEstruturado['id'] = $card['id'] ?? null;
                
                // Processar todos os campos do card
                foreach ($card as $campo => $valorBruto) {
                    if ($campo === 'id') continue; // Já incluído acima
                    
                    $valorConvertido = $cardComEnums[$campo] ?? $valorBruto;
                    $spa = $camposSpaKW24[$campo] ?? [];
                    $nomeAmigavel = $spa['title'] ?? $campo;
                    $texto = $valorConvertido;
                    $type = $spa['type'] ?? null;
                    $isMultiple = $spa['isMultiple'] ?? false;
                    
                    // Se for stageId, usa o nome da etapa como texto
                    if ($campo === 'stageId') {
                        $texto = $stageName ?? $valorBruto;
                        $nomeAmigavel = 'Fase';
                    }
                    
                    $cardEstruturado[$campo] = [
                        'nome' => $nomeAmigavel,
                        'valor' => $valorBruto,
                        'texto' => $texto,
                        'type' => $type,
                        'isMultiple' => $isMultiple
                    ];
                }
                
                $dealsProcessados[] = $cardEstruturado;
            }
            
            return [
                'sucesso' => true,
                'dealsProcessados' => $dealsProcessados,
                'totalProcessados' => count($dealsProcessados)
            ];
            
        } catch (\Exception $e) {
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    public function Tabelakw24() 
    {


    }
    public function tabelacliente() 
    {


    }   
    public function tabelatarefas() 
    {


    }         
}
