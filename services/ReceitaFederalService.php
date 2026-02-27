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
     * @param string|null $entityTypeId ID do tipo de entidade (opcional).
     * @return array Resposta com status e mensagem.
     */
    public function processarAtualizacao(string $idEmpresa, ?string $idDeal = null, ?string $entityTypeId = null)
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

            // 2. Buscar o CNPJ da empresa no Bitrix24 (usando o campo configurado no nível raiz do JSON)
            $cnpjField = $configReceita['cnpj'] ?? $configReceita['company_mapping']['cnpj'] ?? null;

            if (!$cnpjField) {
                return ['status' => 'erro', 'mensagem' => 'Campo de CNPJ não configurado no JSON ("cnpj" ou mapeamento).'];
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
            $dadosReceita = ReceitaFederalHelper::consultarCnpj($cnpjLimpo);

            if (isset($dadosReceita['erro'])) {
                $this->registrarRetorno($idEmpresa, $idDeal, $entityTypeId, "Erro Receita: " . $dadosReceita['erro'], $configReceita);
                return ['status' => 'erro', 'mensagem' => 'Erro na consulta da Receita Federal: ' . $dadosReceita['erro']];
            }

            // 4. Mapear dados para o Bitrix24 com Lógicas Especiais
            $mapeamento = $configReceita['company_mapping'] ?? [];
            $fieldsToUpdate = [];

            // A. CNAE Concatenado (Código - Descrição)
            $cnaeUf = $mapeamento['cnae_fiscal'] ?? null;
            if ($cnaeUf && isset($dadosReceita['cnae_fiscal']) && isset($dadosReceita['cnae_fiscal_descricao'])) {
                $fieldsToUpdate[$cnaeUf] = $dadosReceita['cnae_fiscal'] . " - " . $dadosReceita['cnae_fiscal_descricao'];
            }

            // B. Logradouro Concatenado (Tipo + Nome)
            $logradouroUf = $mapeamento['logradouro'] ?? null;
            if ($logradouroUf && isset($dadosReceita['descricao_tipo_de_logradouro']) && isset($dadosReceita['logradouro'])) {
                $fieldsToUpdate[$logradouroUf] = trim($dadosReceita['descricao_tipo_de_logradouro'] . " " . $dadosReceita['logradouro']);
            }

            // C. CNAEs Secundários (Campos Múltiplos)
            $cnaesSecUf = $mapeamento['cnaes_secundarios'] ?? null;
            if ($cnaesSecUf && isset($dadosReceita['cnaes_secundarios']) && is_array($dadosReceita['cnaes_secundarios'])) {
                $cnaesFormatados = [];
                foreach ($dadosReceita['cnaes_secundarios'] as $cnae) {
                    if (isset($cnae['codigo']) && isset($cnae['descricao'])) {
                        $cnaesFormatados[] = $cnae['codigo'] . " - " . $cnae['descricao'];
                    }
                }
                if (!empty($cnaesFormatados)) {
                    $fieldsToUpdate[$cnaesSecUf] = $cnaesFormatados;
                }
            }

            // D. Demais campos mapeados (evitando sobrescrever o que já foi concatenado)
            foreach ($mapeamento as $campoReceita => $campoBitrix) {
                if (in_array($campoReceita, ['cnae_fiscal', 'cnae_fiscal_descricao', 'logradouro', 'descricao_tipo_de_logradouro', 'cnaes_secundarios'])) {
                    continue;
                }
                if (isset($dadosReceita[$campoReceita]) && !empty($dadosReceita[$campoReceita])) {
                    $fieldsToUpdate[$campoBitrix] = $dadosReceita[$campoReceita];
                }
            }

            // D. Lógica de Regime de Tributação
            $regimeField = $configReceita['regime_tributacao_field'] ?? null;
            if ($regimeField) {
                $this->tratarRegimeTributacao($idEmpresa, $regimeField, $dadosReceita, $fieldsToUpdate);
            }

            // E. Lógica de Endereço Completo (Padrão Google)
            $addressField = $configReceita['address_field'] ?? null;
            if ($addressField) {
                $logradouroFormatado = trim(($dadosReceita['descricao_tipo_de_logradouro'] ?? '') . " " . ($dadosReceita['logradouro'] ?? ''));
                $numero = $dadosReceita['numero'] ?? '';
                $complemento = $dadosReceita['complemento'] ?? '';
                $bairro = $dadosReceita['bairro'] ?? '';
                $municipio = $dadosReceita['municipio'] ?? '';
                $uf = $dadosReceita['uf'] ?? '';
                $cep = $dadosReceita['cep'] ?? '';

                $enderecoCompleto = $logradouroFormatado;
                if ($numero) $enderecoCompleto .= ", " . $numero;
                if ($complemento) $enderecoCompleto .= " - " . $complemento;
                if ($bairro) $enderecoCompleto .= ", " . $bairro;
                if ($municipio) $enderecoCompleto .= ", " . $municipio;
                if ($uf) $enderecoCompleto .= " - " . $uf;
                if ($cep) $enderecoCompleto .= ", " . $cep;

                $fieldsToUpdate[$addressField] = trim($enderecoCompleto);
            }

            // 5. Atualizar Empresa no Bitrix24
            if (!empty($fieldsToUpdate)) {
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
            $this->registrarRetorno($idEmpresa, $idDeal, $entityTypeId, $statusMsg, $configReceita);

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
     * Trata a lógica específica de Regime de Tributação para campos de lista.
     */
    private function tratarRegimeTributacao($idEmpresa, $regimeField, $dadosReceita, &$fieldsToUpdate)
    {
        // 1. Consultar metadados do campo de lista no Bitrix
        $camposBitrix = BitrixCompanyHelper::consultarCamposCompany();
        $configCampo = $camposBitrix[$regimeField] ?? null;

        if (!$configCampo || ($configCampo['type'] ?? '') !== 'enumeration') {
            return;
        }

        // Encontrar ID da opção "Simples Nacional"
        $simplesNacionalId = null;
        foreach ($configCampo['items'] ?? [] as $item) {
            if (mb_stripos($item['VALUE'], 'Simples Nacional') !== false) {
                $simplesNacionalId = $item['ID'];
                break;
            }
        }

        if (!$simplesNacionalId) return;

        // 2. Consultar valor ATUAL na empresa
        $dadosAtuais = BitrixCompanyHelper::consultarEmpresa([
            'empresa' => $idEmpresa,
            'campos' => [$regimeField]
        ]);
        $valorAtual = $dadosAtuais[$regimeField] ?? null;

        $optaSimples = $dadosReceita['opcao_pelo_simples'] ?? false;

        if ($optaSimples) {
            // Seta "Simples Nacional"
            $fieldsToUpdate[$regimeField] = $simplesNacionalId;
        } elseif ($valorAtual == $simplesNacionalId) {
            // Deixou de ser Simples e estava marcado como Simples -> Limpa o campo
            // No Bitrix, para limpar um campo de lista (enumeration) via REST, costuma-se usar aspas vazias
            $fieldsToUpdate[$regimeField] = "";
        }
    }

    /**
     * Registra o status da operação no Deal ou na Empresa.
     */
    private function registrarRetorno($idEmpresa, $idDeal, $entityTypeId, $mensagem, $configReceita)
    {
        // Prioridade: Negócio/SPA (se idDeal e entityTypeId estiverem presentes)
        if ($idDeal && $entityTypeId) {
            $returnFieldDeal = $configReceita['return_field_deal'] ?? null;
            if ($returnFieldDeal) {
                $payloadFormatado = BitrixHelper::formatarCampos([$returnFieldDeal => $mensagem], $entityTypeId, true);
                $resultadoEdit = BitrixDealHelper::editarDeal($entityTypeId, $idDeal, $payloadFormatado);
                
                if ($resultadoEdit['status'] === 'erro') {
                    LogHelper::logReceitaFederal("Erro ao registrar retorno no Deal $idDeal: " . ($resultadoEdit['mensagem'] ?? 'Erro desconhecido'), __CLASS__ . '::' . __FUNCTION__);
                }
            }
        } else {
            // Fallback: Empresa
            $returnFieldCompany = $configReceita['return_field_company'] ?? null;
            if ($returnFieldCompany) {
                $fieldsFormatados = BitrixHelper::formatarCampos([$returnFieldCompany => $mensagem], 4, true); // 4 = Company
                $resultadoUpdate = BitrixHelper::chamarApi('crm.company.update', ['id' => $idEmpresa, 'fields' => $fieldsFormatados]);
                
                if (isset($resultadoUpdate['error'])) {
                    LogHelper::logReceitaFederal("Erro ao registrar retorno na Empresa $idEmpresa: " . ($resultadoUpdate['error_description'] ?? $resultadoUpdate['error']), __CLASS__ . '::' . __FUNCTION__);
                }
            }
        }
    }
}
