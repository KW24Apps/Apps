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

            // Log do nome original da URL
            LogHelper::logDeal("DEBUG MAPPING: Processando nomeCampoUrl: '$nomeCampoUrl'", __CLASS__ . '::' . __FUNCTION__);

            $nomeCampoUrlNormalizadoUF = null;
            // Verifica se o campo da URL começa com UF_CRM_ ou ufcrm_
            if (preg_match('/^(UF_CRM_|ufcrm_)([0-9]+(?:_[0-9]+)?)$/i', $nomeCampoUrl)) {
                $nomeCampoUrlNormalizadoUF = $this->normalizarNomeCampo($nomeCampoUrl, $entityTypeId);
                LogHelper::logDeal("DEBUG MAPPING: nomeCampoUrlNormalizadoUF (UF): '$nomeCampoUrlNormalizadoUF'", __CLASS__ . '::' . __FUNCTION__);
            }

            // 1. Verifica se o nome do campo da URL (normalizado como UF) já é um nome técnico
            if ($nomeCampoUrlNormalizadoUF && isset($this->camposMetadata[$entityTypeId][$nomeCampoUrlNormalizadoUF])) {
                $nomeCampoTecnico = $nomeCampoUrlNormalizadoUF;
                $definicaoCampo = $this->camposMetadata[$entityTypeId][$nomeCampoUrlNormalizadoUF];
                LogHelper::logDeal("DEBUG MAPPING: Encontrado por nome tecnico UF: '$nomeCampoTecnico'", __CLASS__ . '::' . __FUNCTION__);
            } 
            // Normaliza o nome do campo da URL para comparação com title e upperName
            $nomeCampoUrlNormalizadoParaComparacao = $this->normalizarNomeParaComparacao($nomeCampoUrl);
            LogHelper::logDeal("DEBUG MAPPING: nomeCampoUrlNormalizadoParaComparacao: '$nomeCampoUrlNormalizadoParaComparacao'", __CLASS__ . '::' . __FUNCTION__);

            // 1. Verifica se o nome do campo da URL (normalizado como UF) já é um nome técnico
            if ($nomeCampoUrlNormalizadoUF && isset($this->camposMetadata[$entityTypeId][$nomeCampoUrlNormalizadoUF])) {
                $nomeCampoTecnico = $nomeCampoUrlNormalizadoUF;
                $definicaoCampo = $this->camposMetadata[$entityTypeId][$nomeCampoUrlNormalizadoUF];
                LogHelper::logDeal("DEBUG MAPPING: Encontrado por nome tecnico UF: '$nomeCampoTecnico'", __CLASS__ . '::' . __FUNCTION__);
            } 
            // 2. Se não for um UF_CRM_ ou não encontrado, tenta buscar pelo nome amigável (title) ou upperName
            else {
                foreach ($this->camposMetadata[$entityTypeId] as $bitrixFieldName => $metadata) {
                    // Tenta comparar com o 'title' (nome amigável)
                    if (isset($metadata['title'])) {
                        $bitrixTitleNormalizado = $this->normalizarNomeParaComparacao($metadata['title']);
                        LogHelper::logDeal("DEBUG MAPPING: Comparando URL normalizada '$nomeCampoUrlNormalizadoParaComparacao' com Bitrix title normalizado: '$bitrixTitleNormalizado' (campo Bitrix: '$bitrixFieldName')", __CLASS__ . '::' . __FUNCTION__);
                        if ($bitrixTitleNormalizado === $nomeCampoUrlNormalizadoParaComparacao) {
                            $nomeCampoTecnico = $bitrixFieldName;
                            $definicaoCampo = $metadata;
                            LogHelper::logDeal("DEBUG MAPPING: Encontrado por title: '$nomeCampoTecnico'", __CLASS__ . '::' . __FUNCTION__);
                            break;
                        }
                    }
                    // Se não encontrou pelo title, tenta comparar com o 'upperName'
                    if (!$nomeCampoTecnico && isset($metadata['upperName'])) {
                        $bitrixUpperNameNormalizado = $this->normalizarNomeParaComparacao($metadata['upperName']);
                        LogHelper::logDeal("DEBUG MAPPING: Comparando URL normalizada '$nomeCampoUrlNormalizadoParaComparacao' com Bitrix upperName normalizado: '$bitrixUpperNameNormalizado' (campo Bitrix: '$bitrixFieldName')", __CLASS__ . '::' . __FUNCTION__);
                        if ($bitrixUpperNameNormalizado === $nomeCampoUrlNormalizadoParaComparacao) {
                            $nomeCampoTecnico = $bitrixFieldName;
                            $definicaoCampo = $metadata;
                            LogHelper::logDeal("DEBUG MAPPING: Encontrado por upperName: '$nomeCampoTecnico'", __CLASS__ . '::' . __FUNCTION__);
                            break;
                        }
                    }
                }
            }

            // Se encontrou o campo correspondente no Bitrix
            if ($nomeCampoTecnico && $definicaoCampo) {
                $valorFinal = $this->converterValorSeNecessario($valorCampo, $definicaoCampo);
                $payloadFinal[$nomeCampoTecnico] = $valorFinal;
                LogHelper::logDeal("DEBUG MAPPING: Campo '$nomeCampoUrl' mapeado para '$nomeCampoTecnico' com valor '$valorFinal'", __CLASS__ . '::' . __FUNCTION__);
            } else {
                LogHelper::logDeal("Campo da URL '$nomeCampoUrl' (normalizado UF: '$nomeCampoUrlNormalizadoUF', nome URL normalizado para comparacao: '$nomeCampoUrlNormalizadoParaComparacao') nao foi encontrado no Bitrix e sera ignorado.", __CLASS__ . '::' . __FUNCTION__);
            }
        }

        LogHelper::logDeal("Payload Final apos tratarCamposAmigaveis: " . json_encode($payloadFinal, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);
        return $payloadFinal;
    }

    /**
     * Normaliza o nome de um campo, convertendo padrões como UF_CRM_ID para ufCrmID.
     * Isso é crucial para que o DealService possa encontrar campos personalizados
     * que o Bitrix retorna em camelCase, mas que podem vir em uppercase na URL.
     *
     * @param string $campo O nome do campo a ser normalizado.
     * @param int $entityTypeId O ID da entidade (para diferenciar SPA de entidades nativas).
     * @return string O nome do campo normalizado.
     */
    private function normalizarNomeCampo(string $campo, int $entityTypeId): string
    {
        $campoNormalizado = $campo;
        // Verifica se o campo começa com UF_CRM_ ou ufcrm_ (case-insensitive)
        if (preg_match('/^(UF_CRM_|ufcrm_)([0-9]+(?:_[0-9]+)?)$/i', $campo, $matches)) {
            $idParte = $matches[2]; // Ex: 41_1737477724 ou 1737477724

            // Entidades nativas (Deal=2, Contact=3, Company=4) usam UF_CRM_ID
            if (in_array($entityTypeId, [2, 3, 4])) {
                // Formato esperado: ufCrm_ID (ex: ufCrm_1737477724)
                $campoNormalizado = 'ufCrm_' . str_replace('_', '', $idParte);
            } else {
                // SPAs usam UF_CRM_41_ID (ex: ufCrm41_1737477724)
                // Mantém o underscore se houver um prefixo numérico (ex: 41_)
                $campoNormalizado = 'ufCrm' . $idParte;
            }
        }
        return $campoNormalizado;
    }

    /**
     * Normaliza um nome de campo para comparação, removendo espaços, caracteres especiais e convertendo para minúsculas.
     *
     * @param string $nome O nome do campo a ser normalizado.
     * @return string O nome do campo normalizado para comparação.
     */
    private function normalizarNomeParaComparacao(string $nome): string
    {
        $nome = strtolower($nome);
        $nome = preg_replace('/[^a-z0-9]/', '', $nome); // Remove tudo que não for letra ou número
        return $nome;
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
            LogHelper::logDeal("Nao foi possivel obter metadados para a entidade $entityTypeId.", __CLASS__ . '::' . __FUNCTION__);
            return;
        }

        $this->camposMetadata[$entityTypeId] = $metadata;
        
        // O mapeamento de títulos não é mais necessário aqui, pois a busca é feita diretamente nos metadados
        // $mapa = [];
        // foreach ($metadata as $nomeTecnico => $definicao) {
        //     if (!empty($definicao['title'])) {
        //         $mapa[strtolower($definicao['title'])] = $nomeTecnico;
        //     }
        // }
        // $this->mapeamentoTitulos[$entityTypeId] = $mapa;
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
                    LogHelper::logDeal("Valor '{$valor}' convertido para ID '{$item['ID']}' para o campo '{$definicaoCampo['title']}'.", __CLASS__ . '::' . __FUNCTION__);
                    return $item['ID']; // Retorna o ID correspondente
                }
            }
            LogHelper::logDeal("Valor '{$valor}' nao encontrado nas opcoes do campo '{$definicaoCampo['title']}'. O valor original sera mantido.", __CLASS__ . '::' . __FUNCTION__);
        }

        // Se o campo é múltiplo e o valor recebido não é um array, converte para array
        if (isset($definicaoCampo['isMultiple']) && $definicaoCampo['isMultiple'] && !is_array($valor)) {
            LogHelper::logDeal("Campo '{$definicaoCampo['title']}' e multiplo. Valor '{$valor}' convertido para array.", __CLASS__ . '::' . __FUNCTION__);
            return [$valor];
        }
        
        // Para todos os outros casos, retorna o valor original
        return $valor;
    }
}
