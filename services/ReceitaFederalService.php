<?php
namespace Services;

use Helpers\BitrixCompanyHelper;
use Helpers\BitrixDealHelper;
use Helpers\LogHelper;

class ReceitaFederalService
{
    private const API_RECEITA_FEDERAL_URL = "https://minhareceita.org/";

    public function processarConsultaReceita(array $webhookData): array
    {
        $cnpj = preg_replace('/[^0-9]/', '', $webhookData['cnpj']); // Limpa o CNPJ

        // 1. Consultar a API da Receita Federal
        $receitaData = $this->consultarApiReceitaFederal($cnpj);
        if (isset($receitaData['erro'])) {
            return ['status' => 'erro', 'mensagem' => $receitaData['erro']];
        }

        // 2. Mapear dados da Receita Federal para campos do Bitrix24
        $bitrixFields = $this->mapearDadosParaBitrix($receitaData, $webhookData);

        // 3. Atualizar Bitrix24
        $campoRetorno = strtolower($webhookData['campo_retorno']);
        $updateResult = $this->atualizarBitrixEntity($campoRetorno, $bitrixFields, $webhookData);

        if ($updateResult['status'] === 'sucesso' || (isset($updateResult['result']) && $updateResult['result'] === true)) {
            return ['status' => 'sucesso', 'mensagem' => "Dados da Receita Federal atualizados no Bitrix24.", 'resultado_bitrix' => $updateResult];
        } else {
            return ['status' => 'erro', 'mensagem' => "Falha ao atualizar dados no Bitrix24.", 'detalhes' => $updateResult];
        }
    }

    private function consultarApiReceitaFederal(string $cnpj): array
    {
        $url = self::API_RECEITA_FEDERAL_URL . $cnpj;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 segundos

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['erro' => "Erro na requisição cURL: $error"];
        }

        if ($httpCode !== 200) {
            return ['erro' => "Erro HTTP $httpCode ao consultar a API da Receita Federal. Resposta: $response"];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['erro' => "Erro ao decodificar JSON da API da Receita Federal: " . json_last_error_msg()];
        }

        return $data;
    }

    private function mapearDadosParaBitrix(array $dadosReceita, array $webhookData): array
    {
        $bitrixFields = [];

        // Mapeamento de Campos de Nível Superior
        $bitrixFields['TITLE'] = $dadosReceita['razao_social'] ?? null;
        $bitrixFields['UF_CRM_1643894689490'] = $dadosReceita['nome_fantasia'] ?? null; // Nome Fantasia
        $bitrixFields['UF_CRM_1641693445101'] = $dadosReceita['cnpj'] ?? null; // CNPJ/CPF
        $bitrixFields['UF_CRM_1651170686'] = $dadosReceita['uf'] ?? null; // Estado (UF)
        $bitrixFields['UF_CRM_1657666567'] = $dadosReceita['cep'] ?? null; // CEP 01
        $bitrixFields['UF_CRM_1696352922'] = $dadosReceita['email'] ?? null; // E-mail
        $bitrixFields['UF_CRM_1657666676'] = $dadosReceita['bairro'] ?? null; // Bairro 02
        $bitrixFields['UF_CRM_1704323923'] = $dadosReceita['numero'] ?? null; // Numero (Endereço)
        $bitrixFields['UF_CRM_ADDRESS_CITY'] = $dadosReceita['municipio'] ?? null; // Município*
        $bitrixFields['UF_CRM_1669387590'] = $dadosReceita['codigo_municipio'] ?? null; // Código Municipal
        $bitrixFields['UF_CRM_1657666583'] = $dadosReceita['logradouro'] ?? null; // Logradouro
        $bitrixFields['UF_CRM_1657666659'] = $dadosReceita['complemento'] ?? null; // Complemento
        $bitrixFields['UF_CRM_1645141153'] = $dadosReceita['capital_social'] ?? null; // Capital Social (Mapeado para Faturamento Anual)
        $bitrixFields['UF_CRM_1670261459'] = $dadosReceita['ddd_telefone_1'] ?? null; // Telefone
        $bitrixFields['UF_CRM_1645141733685'] = $dadosReceita['cnae_fiscal_descricao'] ?? null; // CNAE Principal
        $bitrixFields['UF_CRM_1696339884'] = $dadosReceita['natureza_juridica'] ?? null; // Natureza Jurídica

        // Campos Aninhados e Consolidados
        // qsa (Quadro de Sócios e Administradores)
        $sociosInfo = [];
        if (!empty($dadosReceita['qsa']) && is_array($dadosReceita['qsa'])) {
            foreach ($dadosReceita['qsa'] as $socio) {
                $nome = $socio['nome_socio'] ?? 'N/A';
                $dataEntrada = $socio['data_entrada_sociedade'] ?? 'N/A';
                $qualificacao = $socio['qualificacao_socio'] ?? 'N/A';
                $sociosInfo[] = "Nome: $nome, Entrada: $dataEntrada, Qualificação: $qualificacao";
            }
        }
        $bitrixFields['UF_CRM_RF_SOCIOS_INFO'] = implode("; ", $sociosInfo);

        // cnaes_secundarios (CNAEs Secundários)
        $cnaesSecundariosInfo = [];
        if (!empty($dadosReceita['cnaes_secundarios']) && is_array($dadosReceita['cnaes_secundarios'])) {
            foreach ($dadosReceita['cnaes_secundarios'] as $cnae) {
                $codigo = $cnae['codigo'] ?? 'N/A';
                $descricao = $cnae['descricao'] ?? 'N/A';
                $cnaesSecundariosInfo[] = "Código: $codigo, Descrição: $descricao";
            }
        }
        $bitrixFields['UF_CRM_1710268217'] = implode("; ", $cnaesSecundariosInfo); // CNAE Secundário #

        // regime_tributario (Regime Tributário)
        $regimeTributarioInfo = [];
        if (!empty($dadosReceita['regime_tributario']) && is_array($dadosReceita['regime_tributario'])) {
            foreach ($dadosReceita['regime_tributario'] as $regime) {
                $forma = $regime['forma_de_tributacao'] ?? 'N/A';
                $regimeTributarioInfo[] = "Forma de Tributação: $forma";
            }
        }
        $bitrixFields['UF_CRM_1645140434'] = implode("; ", $regimeTributarioInfo); // Regime de Tributação

        // Endereço Completo (Campo Nativo do Bitrix24)
        // Padrão brasileiro: "Logradouro, Número - Complemento, Bairro, Município - UF, CEP"
        $logradouro = $dadosReceita['logradouro'] ?? '';
        $numero = $dadosReceita['numero'] ?? '';
        $complemento = $dadosReceita['complemento'] ?? '';
        $bairro = $dadosReceita['bairro'] ?? '';
        $municipio = $dadosReceita['municipio'] ?? '';
        $uf = $dadosReceita['uf'] ?? '';
        $cep = $dadosReceita['cep'] ?? '';

        $enderecoCompleto = [];
        if ($logradouro) $enderecoCompleto[] = $logradouro;
        if ($numero) $enderecoCompleto[] = $numero;
        if ($complemento) $enderecoCompleto[] = $complemento;
        if ($bairro) $enderecoCompleto[] = $bairro;
        if ($municipio) $enderecoCompleto[] = $municipio;
        if ($uf) $enderecoCompleto[] = $uf;
        if ($cep) $enderecoCompleto[] = $cep;

        $bitrixFields['ADDRESS'] = implode(", ", $enderecoCompleto);

        return $bitrixFields;
    }

    private function atualizarBitrixEntity(string $campoRetorno, array $bitrixFields, array $webhookData): array
    {
        $updateResult = ['status' => 'erro', 'mensagem' => 'Nenhuma atualização realizada.'];

        switch ($campoRetorno) {
            case 'empresa':
                $idEmpresaBitrix = $webhookData['id_empresa_bitrix'] ?? null;
                if (!$idEmpresaBitrix) {
                    LogHelper::logReceitaFederal("Erro: 'id_empresa_bitrix' ausente para atualização de empresa.", __CLASS__ . '::' . __FUNCTION__);
                    return ['status' => 'erro', 'mensagem' => "Parâmetro 'id_empresa_bitrix' ausente."];
                }
                $updateResult = BitrixCompanyHelper::editarCamposEmpresa([
                    'id' => $idEmpresaBitrix,
                    'fields' => $bitrixFields
                ]);
                break;
            case 'spa':
            case 'deal':
                $entityTypeId = $webhookData['entity_type_id'] ?? null; // Para SPA, ex: 123
                $idSpaDeal = $webhookData['id_spa'] ?? $webhookData['id_deal'] ?? null;
                if (!$entityTypeId || !$idSpaDeal) {
                    LogHelper::logReceitaFederal("Erro: 'entity_type_id' ou 'id_spa'/'id_deal' ausente para atualização de SPA/Deal.", __CLASS__ . '::' . __FUNCTION__);
                    return ['status' => 'erro', 'mensagem' => "Parâmetros 'entity_type_id' ou 'id_spa'/'id_deal' ausentes."];
                }
                $updateResult = BitrixDealHelper::editarDeal(
                    $entityTypeId,
                    $idSpaDeal,
                    $bitrixFields
                );
                break;
            default:
                LogHelper::logReceitaFederal("Erro: 'campo_retorno' inválido: $campoRetorno.", __CLASS__ . '::' . __FUNCTION__);
                $updateResult = ['status' => 'erro', 'mensagem' => "Valor de 'campo_retorno' inválido."];
                break;
        }
        return $updateResult;
    }
}
