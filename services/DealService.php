<?php
namespace Services;

require_once __DIR__ . '/../helpers/BitrixHelper.php';
use Helpers\BitrixHelper;
use Helpers\LogHelper;

class DealService
{
    private $camposMetadata = [];

    /**
     * Processa os campos de um deal para converter nomes amigáveis (ex: UFs) em IDs.
     *
     * @param array $campos Os campos recebidos na requisição.
     * @param int $entityTypeId O ID da entidade no Bitrix (geralmente 2 para Deals).
     * @return array Os campos com os valores devidamente tratados.
     */
    public function tratarCamposAmigaveis(array $campos, int $entityTypeId): array
    {
        if (empty($campos) || !$entityTypeId) {
            return $campos;
        }

        // Cache simples para os metadados dos campos na mesma instância
        if (empty($this->camposMetadata[$entityTypeId])) {
            $this->camposMetadata[$entityTypeId] = BitrixHelper::consultarCamposCrm($entityTypeId);
        }

        $metadata = $this->camposMetadata[$entityTypeId];
        if (empty($metadata)) {
            LogHelper::logBitrixHelpers("Nao foi possivel obter metadados para a entidade $entityTypeId.", __CLASS__ . '::' . __FUNCTION__);
            return $campos; // Retorna os campos originais se não encontrar metadados
        }

        $camposTratados = $campos;

        foreach ($campos as $nomeCampo => $valorCampo) {
            // Ignora valores que já são numéricos (provavelmente IDs) ou vazios
            if (is_numeric($valorCampo) || empty($valorCampo) || is_array($valorCampo)) {
                continue;
            }

            // Procura a definição do campo nos metadados
            if (isset($metadata[$nomeCampo]) && $metadata[$nomeCampo]['type'] === 'enumeration') {
                $definicaoCampo = $metadata[$nomeCampo];

                // Procura o ID correspondente ao valor amigável
                $idEncontrado = null;
                foreach ($definicaoCampo['items'] as $item) {
                    // Comparação insensível a maiúsculas/minúsculas e acentos
                    if (strcasecmp($item['VALUE'], $valorCampo) == 0) {
                        $idEncontrado = $item['ID'];
                        break;
                    }
                }

                if ($idEncontrado !== null) {
                    $camposTratados[$nomeCampo] = $idEncontrado;
                    LogHelper::logBitrixHelpers("Campo '$nomeCampo': Valor '$valorCampo' convertido para ID '$idEncontrado'.", __CLASS__ . '::' . __FUNCTION__);
                } else {
                    LogHelper::logBitrixHelpers("Campo '$nomeCampo': Valor amigavel '$valorCampo' nao encontrado nos metadados. O valor original sera mantido.", __CLASS__ . '::' . __FUNCTION__);
                }
            }
        }

        return $camposTratados;
    }
}
