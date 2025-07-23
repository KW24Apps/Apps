<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;

class SchedulerController
{
    public function executar()
    {
        // 1. Pega parâmetros básicos
        $spa = $_GET['spa'] ?? null;
        $dealId = $_GET['deal'] ?? $_GET['id'] ?? null;

        if (!$spa || !$dealId) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Parâmetros spa e deal/id são obrigatórios']);
            return;
        }

        // 2. Busca campos da config_extra (via acesso autenticado)
        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        if (!$configExtra) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Configuração extra não encontrada']);
            return;
        }

        $configJson = json_decode($configExtra, true);
        $spaKey = 'SPA_' . $spa;

        if (!isset($configJson[$spaKey]['campos'])) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'SPA não encontrada no config_extra']);
            return;
        }

        // 3. Monta lista dos campos UF_CRM_* do grupo da SPA
        $campos = $configJson[$spaKey]['campos'];
        $ufCampos = array_column($campos, 'uf');
        // Formata os campos no padrão do retorno da API
        $ufCamposFormatados = BitrixHelper::formatarCampos(array_fill_keys($ufCampos, null));
        // Só as chaves
        $listaCampos = array_keys($ufCamposFormatados);

        // 4. Consulta o deal
        $resultado = BitrixDealHelper::consultarDeal($spa, $dealId, implode(',', $ufCampos));

        // 5. Consulta os fields da SPA (pega definição dos campos, inclusive os de lista)
        $fields = BitrixHelper::consultarCamposSpa($spa);

        // 6. Mapeia os valores dos campos lista de ID para texto
        $itemRetornado = $resultado['result']['item'] ?? [];
        $itemConvertido = BitrixHelper::mapearValoresEnumerados($itemRetornado, $fields);

        // 7. Monta retorno final (você pode trocar para nome amigável aqui se quiser)
        header('Content-Type: application/json');
        echo json_encode(['result' => ['item' => $itemConvertido]]);
        }

    }