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
        return $this->extrairListaDeCampo($this->dealItem, 'ufCrm_1765464627');
    }

    public function getOportunidadesConvertidas(): array
    {
        return $this->extrairListaDeCampo($this->dealItem, 'ufCrm_1772039407');
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
        $tipoProcessoTexto = $this->dealItem['ufCrm_1650979003']['texto'] ?? 'Não definido';
        $tipoNormalizado = strtolower(trim($tipoProcessoTexto));
        $processType = $this->getProcessType();

        // 1. Prioridade Absoluta: Solicitar Diagnóstico (sempre vai para Diagnóstico)
        if ($processType == 1) { // 1 = ETAPA_SOLICITAR_DIAGNOSTICO
            $vaiParaRelatorio = in_array($tipoNormalizado, ['administrativo', 'administrativo (anexo v)', 'administrativo anexo 5', 'contencioso ativo']) || empty($tipoProcessoTexto) || $tipoProcessoTexto === 'Não definido';
            if ($vaiParaRelatorio) {
                return ['category_id' => GeraroptndEnums::CATEGORIA_RELATORIO_PRELIMINAR, 'stage_id' => GeraroptndEnums::FASE_TRIAGEM_RELATORIO];
            }
            return ['category_id' => GeraroptndEnums::CATEGORIA_CONTENCIOSO, 'stage_id' => GeraroptndEnums::FASE_TRIAGEM];
        }

        // 2. Se for "Concluído" (2 ou 3)
        if ($processType == 2 || $processType == 3) {
            // Verifica o campo Consultoria apenas neste momento
            $consultoriaValor = $this->dealItem['ufCrm_1737406675']['valor'] ?? null;
            if ($consultoriaValor === GeraroptndEnums::UFCRM_CONSULTORIA_SIM_ID) {
                return ['category_id' => GeraroptndEnums::CATEGORIA_CONSULTORIA, 'stage_id' => GeraroptndEnums::STAGE_ID_TRIAGEM_CONSULTORIA];
            }

            // Se não for consultoria, segue a lógica normal de concluído
            if ($tipoNormalizado === 'administrativo') {
                return ['category_id' => GeraroptndEnums::CATEGORIA_OPERACIONAL, 'stage_id' => GeraroptndEnums::FASE_TRIAGEM_OPERACIONAL];
            }
            return ['category_id' => GeraroptndEnums::CATEGORIA_CONTENCIOSO, 'stage_id' => GeraroptndEnums::FASE_TRIAGEM];
        }

        return ['category_id' => null, 'stage_id' => null];
    }

    private function extrairListaDeCampo(array $item, string $campo): array
    {
        $valor = $item[$campo]['valor'] ?? [];
        
        if (empty($valor)) {
            return [];
        }

        $valores = is_array($valor) ? $valor : [$valor];

        // Se o valor vier no formato Bitrix CRM Bond (ex: "D_123" ou "C_456")
        // No caso de cards do novo funil (Deals), o prefixo é "D_"
        return array_map(function ($v) {
            if (is_string($v) && strpos($v, '_') !== false) {
                $partes = explode('_', $v);
                return end($partes);
            }
            return (string)$v;
        }, $valores);
    }

    public function getDealItem(): array
    {
        return $this->dealItem;
    }
}
