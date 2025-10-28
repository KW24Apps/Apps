<?php
namespace Services\GerarOportunidades;

use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;
use Helpers\LogHelper;
use Exception;

class GerarOportunidadesService
{
    private $organizarDadosService;

    public function __construct(OrganizarDadosService $organizarDadosService)
    {
        $this->organizarDadosService = $organizarDadosService;
    }

    public function executarProcesso(): array
    {
        $dealId = $this->organizarDadosService->getDealId();
        $processType = $this->organizarDadosService->getProcessType();
        $empresas = $this->organizarDadosService->getEmpresas();
        $oportunidadesOferecidas = $this->organizarDadosService->getOportunidadesOferecidas();
        $oportunidadesConvertidas = $this->organizarDadosService->getOportunidadesConvertidas();
        $mapeamentoOportunidadesBitrix = $this->organizarDadosService->getMapeamentoOportunidadesBitrix();

        // Gerar JSON de depuração antes de obter combinações
        $this->gerarJsonDepuracaoOportunidades(
            $dealId,
            $oportunidadesOferecidas,
            $oportunidadesConvertidas,
            $mapeamentoOportunidadesBitrix
        );

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
                // AQUI: Usar o mapeamento VALUE para ID
                $opportunityIdBitrix = $mapeamentoOportunidadesBitrix[$nomeOportunidade] ?? null;

                if ($opportunityIdBitrix !== null) {
                    $combinacoesDesejadas[] = [
                        'companyId' => (string)$empresa,
                        'opportunityId' => (string)$opportunityIdBitrix, // Usar o ID mapeado
                        'opportunityName' => $nomeOportunidade
                    ];
                } else {
                    LogHelper::logGerarOportunidade("WARNING: Oportunidade '{$nomeOportunidade}' não encontrada no mapeamento para o campo ufCrm_1646069163997. Deal ID: {$dealId}");
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
        
        if (empty($combinacoesParaCriar)) {
            $mensagem = 'Todos os deals já foram criados';
            LogHelper::logGerarOportunidade("SUCCESS: {$mensagem} para o Deal ID: {$dealId}");
            return ['sucesso' => true, 'mensagem' => $mensagem];
        }

        $arrayFinalParaCriacao = $this->montarArrayCompletoParaCriacao($combinacoesParaCriar);

        $resultadoCriacao = $this->criarNovosDeals($arrayFinalParaCriacao);

        $resultadoUpdate = $this->atualizarDealOrigem($resultadoCriacao);
        if (isset($resultadoUpdate['status']) && $resultadoUpdate['status'] !== 'sucesso') {
            LogHelper::logGerarOportunidade("WARNING: Deals criados com sucesso, mas falha ao atualizar o Deal de origem {$dealId}. Mensagem: " . ($resultadoUpdate['mensagem'] ?? ''));
        }

        LogHelper::logGerarOportunidade("SUCCESS: Processo finalizado para o Deal ID {$dealId}. Criados {$resultadoCriacao['quantidade']} deals.");
        
        return [
            'sucesso' => true,
            'criacao_deals' => $resultadoCriacao,
            'update_deal_origem' => $resultadoUpdate,
            'contexto_original' => [
                'deal_origem' => $dealId,
                'etapa_atual' => $this->organizarDadosService->getDealItem()['stageId']['valor'] ?? '',
                'process_type' => $processType,
                'tipo_processo' => $this->organizarDadosService->getDealItem()['ufCrm_1650979003']['texto'] ?? 'Não definido',
                'combinacoes_solicitadas' => count($combinacoesParaCriar)
            ]
        ];
    }

    private function gerarJsonDepuracaoOportunidades(
        int $dealId,
        array $oportunidadesOferecidas,
        array $oportunidadesConvertidas,
        array $mapeamentoOportunidadesBitrix
    ): void {
        $dadosDepuracao = [];
        $oportunidadesSelecionadas = array_unique(array_merge($oportunidadesOferecidas, $oportunidadesConvertidas));

        foreach ($oportunidadesSelecionadas as $nomeOportunidade) {
            $idOportunidadeOferecida = null;
            $idOportunidadeConvertida = null;
            $idOportunidadeBitrix = $mapeamentoOportunidadesBitrix[$nomeOportunidade] ?? null;

            // Buscar ID do campo Oportunidades Oferecidas
            // A função getDealItem() retorna o item completo, que contém os metadados dos campos
            $dealItem = $this->organizarDadosService->getDealItem();
            $itemsOferecidas = $dealItem['ufCrm_1688060696']['items'] ?? [];
            foreach ($itemsOferecidas as $item) {
                if ($item['VALUE'] === $nomeOportunidade) {
                    $idOportunidadeOferecida = $item['ID'];
                    break;
                }
            }

            // Buscar ID do campo Oportunidades Convertidas
            $itemsConvertidas = $dealItem['ufCrm_1728327366']['items'] ?? [];
            foreach ($itemsConvertidas as $item) {
                if ($item['VALUE'] === $nomeOportunidade) {
                    $idOportunidadeConvertida = $item['ID'];
                    break;
                }
            }

            $dadosDepuracao[] = [
                'nome_amigavel' => $nomeOportunidade,
                'id_oportunidade_oferecida' => $idOportunidadeOferecida,
                'id_oportunidade_convertida' => $idOportunidadeConvertida,
                'id_oportunidade_bitrix_destino' => $idOportunidadeBitrix
            ];
        }

        $arquivoJson = __DIR__ . "/../../../logs/oportunidades_depuracao_deal_{$dealId}.json";
        file_put_contents($arquivoJson, json_encode($dadosDepuracao, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        LogHelper::logGerarOportunidade("INFO: JSON de depuracao de oportunidades gerado em: {$arquivoJson}");
    }

    public function obterCombinacoesParaCriar(): array
    {
        $dealId = $this->organizarDadosService->getDealId();
        $processType = $this->organizarDadosService->getProcessType();
        $empresas = $this->organizarDadosService->getEmpresas();
        $oportunidadesOferecidas = $this->organizarDadosService->getOportunidadesOferecidas();
        $oportunidadesConvertidas = $this->organizarDadosService->getOportunidadesConvertidas();
        $mapeamentoOportunidadesBitrix = $this->organizarDadosService->getMapeamentoOportunidadesBitrix();

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
                // AQUI: Usar o mapeamento VALUE para ID
                $opportunityIdBitrix = $mapeamentoOportunidadesBitrix[$nomeOportunidade] ?? null;

                if ($opportunityIdBitrix !== null) {
                    $combinacoesDesejadas[] = [
                        'companyId' => (string)$empresa,
                        'opportunityId' => (string)$opportunityIdBitrix, // Usar o ID mapeado
                        'opportunityName' => $nomeOportunidade
                    ];
                } else {
                    LogHelper::logGerarOportunidade("WARNING: Oportunidade '{$nomeOportunidade}' não encontrada no mapeamento para o campo ufCrm_1646069163997. Deal ID: {$dealId}");
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

    public function montarArrayCompletoParaCriacao(array $combinacoes): array
    {
        $dealId = $this->organizarDadosService->getDealId();
        $camposParaEspelhar = $this->organizarDadosService->getCamposParaEspelhar();
        $destinoInfo = $this->organizarDadosService->getDestinoInfo();

        $dealsCompletos = [];
        $entityTypeId = 2; // Para Deals

        foreach ($combinacoes as $combinacao) {
            $dealCompleto = [];
            
            foreach ($camposParaEspelhar as $campo => $valorCompleto) {
                $valorParaAdicionar = null;

                if (is_array($valorCompleto) && array_key_exists('valor', $valorCompleto)) {
                    $valorParaAdicionar = $valorCompleto['valor'];
                } else {
                    $valorParaAdicionar = $valorCompleto;
                }

                if ($valorParaAdicionar !== null && $valorParaAdicionar !== '' && !(is_array($valorParaAdicionar) && empty($valorParaAdicionar))) {
                    $dealCompleto[$campo] = $valorParaAdicionar;
                }
            }
            
            if (!empty($combinacao['companyId'])) {
                $dealCompleto['companyId'] = [(int)$combinacao['companyId']];
            }
            if (!empty($combinacao['opportunityId'])) {
                $dealCompleto['ufCrm_1646069163997'] = $combinacao['opportunityId']; // Alterado para enviar como string, não array
            }
            if (!empty($dealId)) {
                $dealCompleto['ufcrm_1707331568'] = [$dealId];
            }
            if (!empty($destinoInfo['stage_id'])) {
                $dealCompleto['stageId'] = $destinoInfo['stage_id'];
            }
            $dealCompleto['ufCrm_1755632512'] = 'criando DEALS';
            
            $dealCompleto['assignedById'] = 43;
            
            $dealsCompletos[] = $dealCompleto;
        }
        
        return [
            'entityId' => $entityTypeId,
            'categoryId' => $destinoInfo['category_id'],
            'fields' => $dealsCompletos
        ];
    }

    public function criarNovosDeals(array $arrayFinalParaCriacao): array
    {
        $resultadoCriacao = BitrixDealHelper::criarDeal(
            $arrayFinalParaCriacao['entityId'],
            $arrayFinalParaCriacao['categoryId'],
            $arrayFinalParaCriacao['fields'],
            5 // Tamanho do lote
        );

        if ($resultadoCriacao['status'] !== 'sucesso') {
            throw new Exception("Falha ao criar deals. Mensagem: " . ($resultadoCriacao['mensagem'] ?? 'Erro desconhecido'));
        }
        return $resultadoCriacao;
    }

    public function atualizarDealOrigem(array $resultadoCriacao): ?array
    {
        $dealId = $this->organizarDadosService->getDealId();
        $item = $this->organizarDadosService->getDealItem(); // Precisamos do item original para os vinculados

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

    public function getDealItem(): array
    {
        return $this->organizarDadosService->getDealItem();
    }
}
