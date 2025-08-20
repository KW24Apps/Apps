<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../enums/GeraroptndEnums.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\BitrixDealHelper;
use Helpers\LogHelper;
use Helpers\BitrixHelper;
use Enums\GeraroptndEnums;
use Exception;

class GeraroptndController
{
    public function executar()
    {
        // Definir timeout e header
        set_time_limit(3600);
        header('Content-Type: application/json');

        $dealId = $this->obterDealId();
        LogHelper::logGerarOportunidade("INFO: Processo iniciado para o Deal ID: " . ($dealId ?? 'N/A'));

        try {
            // ============================================
            // PARTE 1: COLETA E VALIDAÇÃO DE DADOS
            // ============================================
            if (!$dealId) {
                throw new Exception('Parâmetro deal/id é obrigatório');
            }

            $item = $this->buscarDadosDealPrincipal($dealId);
            if (empty($item)) {
                throw new Exception("Deal com ID {$dealId} não encontrado ou sem dados.");
            }

            // ============================================
            // PARTE 2: EXTRAÇÃO E NORMALIZAÇÃO
            // ============================================
            $empresas = $this->extrairListaDeCampo($item, 'ufCrm_1689718588');
            $oportunidadesOferecidas = $this->extrairListaDeCampo($item, 'ufCrm_1688060696', true);
            $oportunidadesConvertidas = $this->extrairListaDeCampo($item, 'ufCrm_1728327366', true);

            $oportunidadesMapeadas = $this->mapearOportunidades($item, $oportunidadesOferecidas, $oportunidadesConvertidas);
            $camposParaEspelhar = $this->montarCamposParaEspelhar($item);

            // ============================================
            // PARTE 3: DIAGNÓSTICO E LÓGICA DE NEGÓCIO
            // ============================================
            $processType = $this->diagnosticarProcesso($item);
            $destinoInfo = $this->determinarDestinoDeals($processType, $item);

            $combinacoesParaCriar = $this->obterCombinacoesParaCriar($processType, $empresas, $oportunidadesOferecidas, $oportunidadesConvertidas, $oportunidadesMapeadas, $dealId);

            if (empty($combinacoesParaCriar)) {
                $mensagem = 'Todos os deals já foram criados';
                LogHelper::logGerarOportunidade("SUCCESS: {$mensagem} para o Deal ID: {$dealId}");
                echo json_encode(['sucesso' => true, 'mensagem' => $mensagem], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                return;
            }

            // ============================================
            // PARTE 4: CRIAÇÃO E ATUALIZAÇÃO
            // ============================================
            $arrayFinalParaCriacao = $this->montarArrayCompletoParaCriacao($combinacoesParaCriar, $camposParaEspelhar, $destinoInfo, $dealId);

            $resultadoCriacao = BitrixDealHelper::criarDeal(
                $arrayFinalParaCriacao['entityId'],
                $arrayFinalParaCriacao['categoryId'],
                $arrayFinalParaCriacao['fields'],
                5 // Tamanho do lote
            );

            if ($resultadoCriacao['status'] !== 'sucesso') {
                throw new Exception("Falha ao criar deals. Mensagem: " . ($resultadoCriacao['mensagem'] ?? 'Erro desconhecido'));
            }

            // ============================================
            // PARTE 5: ATUALIZAR NEGÓCIO ORIGINAL
            // ============================================
            $resultadoUpdate = $this->atualizarDealOrigem($dealId, $item, $resultadoCriacao);
            if (isset($resultadoUpdate['status']) && $resultadoUpdate['status'] !== 'sucesso') {
                // Loga como aviso, pois a criação dos deals foi bem-sucedida
                LogHelper::logGerarOportunidade("WARNING: Deals criados com sucesso, mas falha ao atualizar o Deal de origem {$dealId}. Mensagem: " . ($resultadoUpdate['mensagem'] ?? ''));
            }

            // ============================================
            // PARTE 6: RETORNO FINAL
            // ============================================
            LogHelper::logGerarOportunidade("SUCCESS: Processo finalizado para o Deal ID {$dealId}. Criados {$resultadoCriacao['quantidade']} deals.");
            $this->retornarRespostaFinal($resultadoCriacao, $resultadoUpdate, $dealId, $item, $processType, count($combinacoesParaCriar));

        } catch (Exception $e) {
            $mensagemErro = $e->getMessage();
            LogHelper::logGerarOportunidade("ERROR: Falha no processo para o Deal ID {$dealId}. Motivo: {$mensagemErro}");
            echo json_encode(['sucesso' => false, 'erro' => $mensagemErro], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    private function obterDealId()
    {
        return $_GET['deal'] ?? $_GET['id'] ?? null;
    }

    private function buscarDadosDealPrincipal(int $dealId)
    {
        $camposBitrix = GeraroptndEnums::getAllFields();
        $camposStr = implode(',', $camposBitrix);
        $resultado = BitrixDealHelper::consultarDeal(2, $dealId, $camposStr);
        return $resultado['result'] ?? [];
    }

    private function extrairListaDeCampo(array $item, string $campo, bool $filtrarNegativos = false): array
    {
        if (empty($item[$campo]['texto'])) {
            return [];
        }

        $texto = $item[$campo]['texto'];
        $valores = is_array($texto) ? $texto : explode(',', $texto);

        if ($filtrarNegativos) {
            return array_filter($valores, function ($valor) {
                return !in_array(strtoupper(trim($valor)), ['N', 'NAO', 'NÃO', 'NENHUMA', 'NONE', '']);
            });
        }

        return $valores;
    }

    private function mapearOportunidades(array $item, array $oportunidadesOferecidas, array $oportunidadesConvertidas): array
    {
        $oportunidades = [];
        $oportunidadesSelecionadas = array_unique(array_merge($oportunidadesOferecidas, $oportunidadesConvertidas));

        // Mapear oferecidas
        if (!empty($item['ufCrm_1688060696']['valor']) && !empty($item['ufCrm_1688060696']['texto'])) {
            $valores = is_array($item['ufCrm_1688060696']['valor']) ? $item['ufCrm_1688060696']['valor'] : [$item['ufCrm_1688060696']['valor']];
            $textos = is_array($item['ufCrm_1688060696']['texto']) ? $item['ufCrm_1688060696']['texto'] : [$item['ufCrm_1688060696']['texto']];
            
            for ($i = 0; $i < count($textos); $i++) {
                if (in_array($textos[$i], $oportunidadesSelecionadas)) {
                    $oportunidades[$textos[$i]]['oferecida'] = $valores[$i];
                    $oportunidades[$textos[$i]]['oportunidade'] = $valores[$i];
                }
            }
        }

        // Mapear convertidas
        if (!empty($item['ufCrm_1728327366']['valor']) && !empty($item['ufCrm_1728327366']['texto'])) {
            $valores = is_array($item['ufCrm_1728327366']['valor']) ? $item['ufCrm_1728327366']['valor'] : [$item['ufCrm_1728327366']['valor']];
            $textos = is_array($item['ufCrm_1728327366']['texto']) ? $item['ufCrm_1728327366']['texto'] : [$item['ufCrm_1728327366']['texto']];

            for ($i = 0; $i < count($textos); $i++) {
                if (in_array($textos[$i], $oportunidadesSelecionadas)) {
                    $oportunidades[$textos[$i]]['convertida'] = $valores[$i];
                    if (!isset($oportunidades[$textos[$i]]['oportunidade'])) {
                        $oportunidades[$textos[$i]]['oportunidade'] = $valores[$i];
                    }
                }
            }
        }
        return $oportunidades;
    }

    private function montarCamposParaEspelhar(array $item): array
    {
        $camposParaEspelhar = [];
        $camposExcluir = GeraroptndEnums::CAMPOS_EXCLUIR;
        
        foreach ($item as $campo => $valor) {
            if (!in_array($campo, $camposExcluir)) {
                $camposParaEspelhar[$campo] = $valor;
            }
        }
        return $camposParaEspelhar;
    }

    private function diagnosticarProcesso(array $item): int
    {
        $etapaAtualId = $item['stageId']['valor'] ?? '';
        $vinculados = $item['ufCrm_1670953245']['valor'] ?? null;

        if ($etapaAtualId === GeraroptndEnums::ETAPA_SOLICITAR_DIAGNOSTICO) {
            return 1; // solicitar diagnóstico
        }
        
        if ($etapaAtualId === GeraroptndEnums::ETAPA_CONCLUIDO) {
            return empty($vinculados) ? 2 : 3; // 2: concluído sem diagnóstico, 3: concluído com diagnóstico
        }

        return 0; // Tipo de processo não determinado
    }

    private function obterCombinacoesParaCriar($processType, $empresas, $oportunidadesOferecidas, $oportunidadesConvertidas, $oportunidadesMapeadas, $dealId)
    {
        $dealsExistentesResult = BitrixHelper::listarItensCrm(2, [
            'ufcrm_1707331568' => [$dealId]
        ], ['companyId', 'ufCrm_1646069163997']);
        
        $dealsExistentes = [];
        if ($dealsExistentesResult['success'] && !empty($dealsExistentesResult['items'])) {
            foreach ($dealsExistentesResult['items'] as $deal) {
                if (isset($deal['companyId'], $deal['ufCrm_1646069163997'])) {
                    $dealsExistentes[] = [
                        'companyId' => (string)$deal['companyId'],
                        'opportunityId' => (string)$deal['ufCrm_1646069163997']
                    ];
                }
            }
        }
        
        $oportunidadesParaUsar = ($processType == 1) ? $oportunidadesOferecidas : $oportunidadesConvertidas;
        
        $combinacoesDesejadas = [];
        foreach ($empresas as $empresa) {
            foreach ($oportunidadesParaUsar as $nomeOportunidade) {
                if (isset($oportunidadesMapeadas[$nomeOportunidade]['oportunidade'])) {
                    $combinacoesDesejadas[] = [
                        'companyId' => (string)$empresa,
                        'opportunityId' => (string)$oportunidadesMapeadas[$nomeOportunidade]['oportunidade'],
                        'opportunityName' => $nomeOportunidade
                    ];
                }
            }
        }
        
        $combinacoesParaCriar = [];
        foreach ($combinacoesDesejadas as $desejada) {
            $jaExiste = false;
            foreach ($dealsExistentes as $existente) {
                if ($existente['companyId'] === $desejada['companyId'] && $existente['opportunityId'] === $desejada['opportunityId']) {
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

    private function determinarDestinoDeals($processType, $item)
    {
        $tipoProcessoTexto = $item['ufCrm_1650979003']['texto'] ?? 'Não definido';
        $tipoNormalizado = strtolower(trim($tipoProcessoTexto));

        if ($processType == 1) { // Solicitando Diagnóstico
            $vaiParaRelatorio = in_array($tipoNormalizado, ['administrativo', 'administrativo (anexo v)', 'administrativo anexo 5', 'contencioso ativo']) || empty($tipoProcessoTexto) || $tipoProcessoTexto === 'Não definido';
            if ($vaiParaRelatorio) {
                return ['category_id' => GeraroptndEnums::CATEGORIA_RELATORIO_PRELIMINAR, 'stage_id' => GeraroptndEnums::STAGE_ID_TRIAGEM_RELATORIO];
            }
            return ['category_id' => GeraroptndEnums::CATEGORIA_CONTENCIOSO, 'stage_id' => GeraroptndEnums::STAGE_ID_TRIAGEM];
        }

        if ($processType == 2 || $processType == 3) { // Concluído
            if ($tipoNormalizado === 'administrativo') {
                return ['category_id' => GeraroptndEnums::CATEGORIA_OPERACIONAL, 'stage_id' => GeraroptndEnums::STAGE_ID_TRIAGEM_OPERACIONAL];
            }
            return ['category_id' => GeraroptndEnums::CATEGORIA_CONTENCIOSO, 'stage_id' => GeraroptndEnums::STAGE_ID_TRIAGEM];
        }

        return ['category_id' => null, 'stage_id' => null];
    }
    
    private function montarArrayCompletoParaCriacao($combinacoes, $camposParaEspelhar, $destinoInfo, $dealId)
    {
        $dealsCompletos = [];
        
        foreach ($combinacoes as $combinacao) {
            $dealCompleto = [];
            
            foreach ($camposParaEspelhar as $campo => $valorCompleto) {
                if (is_array($valorCompleto) && isset($valorCompleto['valor'])) {
                    $dealCompleto[$campo] = $valorCompleto['valor'];
                } else {
                    $dealCompleto[$campo] = $valorCompleto;
                }
            }
            
            $dealCompleto['companyId'] = (int)$combinacao['companyId'];
            $dealCompleto['ufCrm_1646069163997'] = $combinacao['opportunityId'];
            $dealCompleto['ufcrm_1707331568'] = $dealId;
            $dealCompleto['stageId'] = $destinoInfo['stage_id'];
            $dealCompleto['ufCrm_1755632512'] = 'criando DEALS';
            
            $dealsCompletos[] = $dealCompleto;
        }
        
        return [
            'entityId' => 2,
            'categoryId' => $destinoInfo['category_id'],
            'fields' => $dealsCompletos
        ];
    }

    private function atualizarDealOrigem(int $dealId, array $item, array $resultadoCriacao)
    {
        $novosIds = [];
        if (isset($resultadoCriacao['ids']) && !empty($resultadoCriacao['ids'])) {
            $novosIds = explode(', ', $resultadoCriacao['ids']);
        }

        if (empty($novosIds)) {
            return null;
        }

        $vinculadosExistentes = $item['ufCrm_1670953245']['valor'] ?? [];
        if (!is_array($vinculadosExistentes)) {
            $vinculadosExistentes = array_filter(array_map('trim', explode(',', $vinculadosExistentes)));
        }

        $vinculadosFinal = array_unique(array_merge($vinculadosExistentes, $novosIds));

        return BitrixDealHelper::editarDeal(
            2,
            $dealId,
            ['ufCrm_1670953245' => $vinculadosFinal]
        );
    }

    private function retornarRespostaFinal(array $resultadoCriacao, ?array $resultadoUpdate, int $dealId, array $item, int $processType, int $combinacoesSolicitadas)
    {
        $sucesso = ($resultadoCriacao['status'] === 'sucesso');
        
        echo json_encode([
            'sucesso' => $sucesso,
            'criacao_deals' => [
                'status' => $resultadoCriacao['status'],
                'quantidade_criada' => $resultadoCriacao['quantidade'],
                'ids_criados' => $resultadoCriacao['ids'],
                'mensagem' => $resultadoCriacao['mensagem'],
                'tempo_execucao_segundos' => $resultadoCriacao['tempo_total_segundos'] ?? 0
            ],
            'update_deal_origem' => [
                'atualizado' => !empty($resultadoUpdate),
                'status' => $resultadoUpdate['status'] ?? 'nao_executado',
                'mensagem' => $resultadoUpdate['mensagem'] ?? 'Nenhum deal novo para vincular.',
                'total_vinculados' => count($resultadoUpdate['ufCrm_1670953245'] ?? [])
            ],
            'contexto_original' => [
                'deal_origem' => $dealId,
                'etapa_atual' => $item['stageId']['valor'] ?? '',
                'process_type' => $processType,
                'tipo_processo' => $item['ufCrm_1650979003']['texto'] ?? 'Não definido',
                'combinacoes_solicitadas' => $combinacoesSolicitadas
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
