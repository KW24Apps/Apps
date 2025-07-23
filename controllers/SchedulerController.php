<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use Helpers\BitrixDealHelper;

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

        // 4. Chama direto o helper, igual o controller de consulta

        file_put_contents(__DIR__ . '/../logs/01.log', date('c') . " | SPA:$spa | DEAL_ID:$dealId | CAMPOS:" . implode(',', $ufCampos) . "\n", FILE_APPEND);

        $resultado = BitrixDealHelper::consultarDeal($spa, $dealId, $ufCampos);

        // 5. Imprime o resultado (igual DealController)
        header('Content-Type: application/json');
        echo json_encode($resultado);
    }
}
