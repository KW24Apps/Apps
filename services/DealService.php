<?php
namespace Services;

require_once __DIR__ . '/../helpers/BitrixHelper.php';
use Helpers\BitrixHelper;
use Helpers\LogHelper;

class DealService
{
    private $camposMetadata = [];
    private $mapeamentoTitulos = [];

    /**
     * Processa os campos de um deal para converter nomes amigáveis (ex: CNPJ/CPF, UFs) em seus respectivos campos técnicos e IDs.
     *
     * @param array $campos Os campos recebidos na requisição (da URL).
     * @param int $entityTypeId O ID da entidade no Bitrix (geralmente 2 para Deals).
     * @return array O payload final, enxuto e mapeado, pronto para ser enviado à API do Bitrix.
     */
    public function tratarCamposAmigaveis(array $campos, int $entityTypeId): array
    {
        if (empty($campos) || !$entityTypeId) {
            return [];
        }

        $this->carregarMetadata($entityTypeId);
        if (empty($this->camposMetadata[$entityTypeId])) {
            return $campos; // Retorna os campos originais se não houver metadados
        }

        $payloadFinal = [];

        foreach ($campos as $nomeCampoUrl => $valorCampo) {
            $nomeCampoTecnico = null;
            $definicaoCampo = null;

            // Normaliza o nome do campo da URL para o formato esperado pelo Bitrix (ufCrm_ID)
            $nomeCampoUrlNormalizado = $this->normalizarNomeCampo($nomeCampoUrl);

            // 1. Verifica se o nome do campo da URL (normalizado) já é um nome técnico
            if (isset($this->camposMetadata[$entityTypeId][$nomeCampoUrlNormalizado])) {
                $nomeCampoTecnico = $nomeCampoUrlNormalizado;
                $definicaoCampo = $this->camposMetadata[$entityTypeId][$nomeCampoUrlNormalizado];
            } 
            // 2. Se não for, busca pelo nome amigável (title) usando o nome original da URL
            elseif (isset($this->mapeamentoTitulos[$entityTypeId][strtolower($nomeCampoUrl)])) {
                $nomeCampoTecnico = $this->mapeamentoTitulos[$entityTypeId][strtolower($nomeCampoUrl)];
                $definicaoCampo = $this->camposMetadata[$entityTypeId][$nomeCampoTecnico];
            }

            // Se encontrou o campo correspondente no Bitrix
            if ($nomeCampoTecnico && $definicaoCampo) {
                $valorFinal = $this->converterValorSeNecessario($valorCampo, $definicaoCampo);
                $payloadFinal[$nomeCampoTecnico] = $valorFinal;
            } else {
                LogHelper::logBitrixHelpers("Campo da URL '$nomeCampoUrl' (normalizado: '$nomeCampoUrlNormalizado') nao foi encontrado no Bitrix e sera ignorado.", __CLASS__ . '::' . __FUNCTION__);
            }
        }

        return $payloadFinal;
    }

    /**
     * Normaliza o nome de um campo, convertendo padrões como UF_CRM_ID para ufCrm_ID.
     * Isso é crucial para que o DealService possa encontrar campos personalizados
     * que o Bitrix retorna em camelCase, mas que podem vir em uppercase na URL.
     *
     * @param string $campo O nome do campo a ser normalizado.
     * @return string O nome do campo normalizado.
     */
    private function normalizarNomeCampo(string $campo): string
    {
        $campoNormalizado = $campo;
        // Verifica se o campo começa com UF_CRM_ ou ufcrm_ (case-insensitive)
        if (preg_match('/^(UF_CRM_|ufcrm_)([0-9]+(?:_[0-9]+)?)$/i', $campo, $matches)) {
            // Converte para o padrão ufCrm_ID ou ufCrmID_ID
            $campoNormalizado = 'ufCrm_' . $matches[2];
        }
        return $campoNormalizado;
    }

    /**
     * Carrega os metadados da entidade do Bitrix e cria um mapa de títulos para nomes técnicos.
     */
    private function carregarMetadata(int $entityTypeId)
    {
        // Cache para evitar múltiplas chamadas na mesma requisição
        if (!empty($this->camposMetadata[$entityTypeId])) {
            return;
        }

        $metadata = BitrixHelper::consultarCamposCrm($entityTypeId);
        if (empty($metadata)) {
            LogHelper::logBitrixHelpers("Nao foi possivel obter metadados para a entidade $entityTypeId.", __CLASS__ . '::' . __FUNCTION__);
            return;
        }

        $this->camposMetadata[$entityTypeId] = $metadata;
        
        // Cria o mapa de Título => Nome Técnico
        $mapa = [];
        foreach ($metadata as $nomeTecnico => $definicao) {
            if (!empty($definicao['title'])) {
                // Chave do mapa em minúsculas para busca case-insensitive
                $mapa[strtolower($definicao['title'])] = $nomeTecnico;
            }
        }
        $this->mapeamentoTitulos[$entityTypeId] = $mapa;
    }

    /**
     * Converte o valor de um campo do tipo 'enumeration' de texto para ID.
     */
    private function converterValorSeNecessario($valor, array $definicaoCampo)
    {
        // Se o campo é do tipo lista e o valor recebido não é numérico
        if ($definicaoCampo['type'] === 'enumeration' && !is_numeric($valor) && !empty($valor)) {
            foreach ($definicaoCampo['items'] as $item) {
                // Comparação insensível a maiúsculas/minúsculas
                if (strcasecmp($item['VALUE'], $valor) == 0) {
                    LogHelper::logBitrixHelpers("Valor '{$valor}' convertido para ID '{$item['ID']}' para o campo '{$definicaoCampo['title']}'.", __CLASS__ . '::' . __FUNCTION__);
                    return $item['ID']; // Retorna o ID correspondente
                }
            }
            LogHelper::logBitrixHelpers("Valor '{$valor}' nao encontrado nas opcoes do campo '{$definicaoCampo['title']}'. O valor original sera mantido.", __CLASS__ . '::' . __FUNCTION__);
        }
        
        // Para todos os outros casos, retorna o valor original
        return $valor;
    }
}
