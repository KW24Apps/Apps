<?php

namespace Services;

require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../helpers/ReceitaFederalHelper.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\LogHelper;
use Helpers\ReceitaFederalHelper;
use Helpers\BitrixCompanyHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;

class ReceitaFederalService
{
    /**
     * Executa o fluxo completo de consulta e atualização.
     *
     * @param string $idEmpresa ID da empresa no Bitrix24.
     * @param string|null $idDeal ID do deal no Bitrix24 (opcional).
     * @param string|null $campoRetorno Nome do campo para salvar o retorno (opcional, prioriza o da URL).
     * @return array Resposta com status e mensagem.
     */
    public function processarAtualizacao(string $idEmpresa, ?string $idDeal = null, ?string $campoRetorno = null)
    {
        try {
            // 1. Obter configurações do cliente (já validadas no index.php)
            $acesso = $GLOBALS['ACESSO_AUTENTICADO'] ?? null;
            if (!$acesso) {
                return ['status' => 'erro', 'mensagem' => 'Acesso não autenticado ou configurações não encontradas.'];
            }

            $configExtra = $acesso['config_extra'] ?? null;
            if (is_string($configExtra)) {
                $configExtra = json_decode($configExtra, true);
            }
            
            $configReceita = $configExtra['receita_federal'] ?? null;

            if (!$configReceita) {
                return ['status' => 'erro', 'mensagem' => 'Configuração "receita_federal" não encontrada no JSON do cliente.'];
            }

            // 2. Buscar o CNPJ da empresa no Bitrix24
            $cnpjField = $configReceita['cnpj_field'] ?? null;
            if (!$cnpjField) {
                return ['status' => 'erro', 'mensagem' => 'Campo de CNPJ não configurado para este cliente.'];
            }

            $dadosEmpresa = BitrixCompanyHelper::consultarEmpresa([
                'empresa' => $idEmpresa,
                'campos' => [$cnpjField]
            ]);

            if (isset($dadosEmpresa['erro'])) {
                return ['status' => 'erro', 'mensagem' => 'Erro ao consultar empresa no Bitrix: ' . $dadosEmpresa['erro']];
            }

            $cnpj = $dadosEmpresa[$cnpjField] ?? null;
            if (!$cnpj) {
                return ['status' => 'erro', 'mensagem' => "CNPJ não encontrado no campo $cnpjField da empresa $idEmpresa."];
            }

            // Limpar CNPJ (apenas números)
            $cnpjLimpo = preg_replace('/\D/', '', $cnpj);

            // 3. Consultar Receita Federal
            LogHelper::logReceitaFederal("Iniciando consulta CNPJ $cnpjLimpo para empresa $idEmpresa", __CLASS__ . '::' . __FUNCTION__);
            $dadosReceita = ReceitaFederalHelper::consultarCnpj($cnpjLimpo);

            if (isset($dadosReceita['erro'])) {
                $this->registrarRetorno($idEmpresa, $idDeal, "Erro Receita: " . $dadosReceita['erro'], $configReceita, $campoRetorno);
                return ['status' => 'erro', 'mensagem' => 'Erro na consulta da Receita Federal: ' . $dadosReceita['erro']];
            }

            // 4. Mapear dados para o Bitrix24
            $mapeamento = $configReceita['company_mapping'] ?? [];
            $fieldsToUpdate = [];

            foreach ($mapeamento as $campoReceita => $campoBitrix) {
                if (isset($dadosReceita[$campoReceita])) {
                    $fieldsToUpdate[$campoBitrix] = $dadosReceita[$campoReceita];
                }
            }

            // 5. Atualizar Empresa no Bitrix24
            if (!empty($fieldsToUpdate)) {
                // Usamos BitrixHelper::chamarApi diretamente para permitir campos que não sejam UF_CRM_ (como TITLE)
                $payload = [
                    'id' => $idEmpresa,
                    'fields' => $fieldsToUpdate
                ];
                $updateResult = BitrixHelper::chamarApi('crm.company.update', $payload);
                
                if (isset($updateResult['error'])) {
                    LogHelper::logReceitaFederal("Erro ao atualizar empresa $idEmpresa: " . ($updateResult['error_description'] ?? $updateResult['error']), __CLASS__ . '::' . __FUNCTION__);
                }
            }

            // 6. Registrar Retorno/Status
            $statusMsg = "Dados atualizados com sucesso em " . date('d/m/Y H:i:s');
            $this->registrarRetorno($idEmpresa, $idDeal, $statusMsg, $configReceita, $campoRetorno);

            return [
                'status' => 'sucesso',
                'mensagem' => 'Processamento concluído.',
                'dados_receita' => $dadosReceita
            ];

        } catch (\Exception $e) {
            LogHelper::logReceitaFederal("Exceção no processamento: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
            return ['status' => 'erro', 'mensagem' => 'Erro interno: ' . $e->getMessage()];
        }
    }

    /**
     * Registra o status da operação no Deal ou na Empresa.
     */
    private function registrarRetorno($idEmpresa, $idDeal, $mensagem, $configReceita, $campoRetornoUrl = null)
    {
        // Prioridade do campo de retorno: 1. URL, 2. Config JSON
        $campoFinal = $campoRetornoUrl;
        
        if ($idDeal) {
            $campoFinal = $campoFinal ?: ($configReceita['return_field_deal'] ?? null);
            $entityTypeId = $configReceita['deal_entity_type_id'] ?? 2; // Default para Deal padrão (2)
            if ($campoFinal) {
                // Atualiza o Deal ou SPA
                BitrixDealHelper::editarDeal($entityTypeId, $idDeal, [$campoFinal => $mensagem]);
            }
        } else {
            $campoFinal = $campoFinal ?: ($configReceita['return_field_company'] ?? null);
            if ($campoFinal) {
                // Atualiza a Empresa
                $payload = [
                    'id' => $idEmpresa,
                    'fields' => [$campoFinal => $mensagem]
                ];
                BitrixHelper::chamarApi('crm.company.update', $payload);
            }
        }
    }
}
