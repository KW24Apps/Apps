<?php
namespace Services\GerarOportunidades;

use Helpers\BitrixHelper;
use Helpers\LogHelper;
use Enums\GeraroptndEnums;
use Exception;

class OrganizarDadosService
{
    private $dealItem;
    private $dealId;
    private $oportunidadesMapeadasBitrix; // Cache para o mapeamento de oportunidades

    public function __construct(array $dealItem)
    {
        $this->dealItem = $dealItem;
        $this->dealId = $this->obterDealIdInterno();
    }

    private function obterDealIdInterno(): ?int
    {
        return $this->dealItem['id']['valor'] ?? null;
    }

    public function getDealId(): ?int
    {
        return $this->dealId;
    }

    public function getEmpresas(): array
    {
        return $this->extrairListaDeCampo($this->dealItem, 'ufCrm_1689718588');
    }

    public function getOportunidadesOferecidas(): array
    {
        return $this->extrairListaDeCampo($this->dealItem, 'ufCrm_1688060696', true);
    }

    public function getOportunidadesConvertidas(): array
    {
        return $this->extrairListaDeCampo($this->dealItem, 'ufCrm_1728327366', true);
    }

    public function getCamposParaEspelhar(): array
    {
        $camposParaEspelhar = [];
        $camposExcluir = GeraroptndEnums::CAMPOS_EXCLUIR;
        
        foreach ($this->dealItem as $campo => $valor) {
            if (!in_array($campo, $camposExcluir)) {
                $camposParaEspelhar[$campo] = $valor;
            }
        }
        return $camposParaEspelhar;
    }

    public function getProcessType(): int
    {
        $etapaAtualId = $this->dealItem['stageId']['valor'] ?? '';
        $vinculados = $this->dealItem['ufCrm_1670953245']['valor'] ?? null;

        if ($etapaAtualId === GeraroptndEnums::ETAPA_SOLICITAR_DIAGNOSTICO) {
            return 1; // solicitar diagnóstico
        }
        
        if ($etapaAtualId === GeraroptndEnums::ETAPA_CONCLUIDO) {
            return empty($vinculados) ? 2 : 3; // 2: concluído sem diagnóstico, 3: concluído com diagnóstico
        }

        return 0; // Tipo de processo não determinado
    }
    
    public function getDestinoInfo(): array
    {
        // Prioridade 1: Verificar campo "Consultoria"
        $consultoriaValor = $this->dealItem['ufCrm_1737406675']['valor'] ?? null;
        if ($consultoriaValor === GeraroptndEnums::UFCRM_CONSULTORIA_SIM_ID) {
            return ['category_id' => GeraroptndEnums::CATEGORIA_CONSULTORIA, 'stage_id' => GeraroptndEnums::STAGE_ID_TRIAGEM_CONSULTORIA];
        }

        $tipoProcessoTexto = $this->dealItem['ufCrm_1650979003']['texto'] ?? 'Não definido';
        $tipoNormalizado = strtolower(trim($tipoProcessoTexto));

        if ($this->getProcessType() == 1) { // Solicitando Diagnóstico
            $vaiParaRelatorio = in_array($tipoNormalizado, ['administrativo', 'administrativo (anexo v)', 'administrativo anexo 5', 'contencioso ativo']) || empty($tipoProcessoTexto) || $tipoProcessoTexto === 'Não definido';
            if ($vaiParaRelatorio) {
                return ['category_id' => GeraroptndEnums::CATEGORIA_RELATORIO_PRELIMINAR, 'stage_id' => GeraroptndEnums::FASE_TRIAGEM_RELATORIO];
            }
            return ['category_id' => GeraroptndEnums::CATEGORIA_CONTENCIOSO, 'stage_id' => GeraroptndEnums::FASE_TRIAGEM];
        }

        if ($this->getProcessType() == 2 || $this->getProcessType() == 3) { // Concluído
            if ($tipoNormalizado === 'administrativo') {
                return ['category_id' => GeraroptndEnums::CATEGORIA_OPERACIONAL, 'stage_id' => GeraroptndEnums::FASE_TRIAGEM_OPERACIONAL];
            }
            return ['category_id' => GeraroptndEnums::CATEGORIA_CONTENCIOSO, 'stage_id' => GeraroptndEnums::FASE_TRIAGEM];
        }

        return ['category_id' => null, 'stage_id' => null];
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

    public function getMapeamentoOportunidadesBitrix(): array
    {
        if ($this->oportunidadesMapeadasBitrix === null) {
            $entityTypeId = 2; // Deals
            $campoOportunidadeId = 'ufCrm_1646069163997'; // ID do campo "Oportunidade"

            $camposCrm = BitrixHelper::consultarCamposCrm($entityTypeId);
            $mapeamento = [];

            if (isset($camposCrm[$campoOportunidadeId]['items'])) {
                foreach ($camposCrm[$campoOportunidadeId]['items'] as $item) {
                    $mapeamento[$item['VALUE']] = $item['ID'];
                }
            }
            $this->oportunidadesMapeadasBitrix = $mapeamento;
        }
        return $this->oportunidadesMapeadasBitrix;
    }

    public function getDealItem(): array
    {
        return $this->dealItem;
    }
}
