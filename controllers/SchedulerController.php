<?php
namespace controllers;

class SchedulerController
{
    // Método principal para lidar com a requisição
    public function executar()
    {
        file_put_contents(__DIR__ . '/test_jurandir.log', date('c') . " [DEBUG] Antes de chamar detectarAplicacaoPorUri", FILE_APPEND);
        // 1. Pega parâmetros da URL
        $spa = $_GET['spa'] ?? null;
        $dealId = $_GET['deal'] ?? $_GET['id'] ?? null;
        if (!$spa || !$dealId) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Parâmetros spa e deal/id são obrigatórios']);
            return;
        }

        // 2. Pega config_extra do acesso autenticado
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

        // 3. Monta lista de UF_CRM_* para consulta (usa BitrixHelper::formatarCampos para compatibilidade total)
        require_once __DIR__ . '/../helpers/BitrixHelper.php';
        $campos = $configJson[$spaKey]['campos'];
        $ufCamposOriginais = array_column($campos, 'uf');
        $ufCamposFormatados = BitrixHelper::formatarCampos(array_fill_keys($ufCamposOriginais, null));
        // Mapeia campo original => campo formatado
        $mapCampos = [];
        $ufCampos = [];
        foreach ($ufCamposOriginais as $campo) {
            $normalizado = BitrixHelper::formatarCampos([$campo => null]);
            $chaveBitrix = array_key_first($normalizado);
            $mapCampos[$campo] = $chaveBitrix;
            $ufCampos[] = $chaveBitrix;
        }

        // 4. Consulta o Deal usando o helper já existente
        require_once __DIR__ . '/../controllers/DealController.php';
        $dealController = new \Controllers\DealController();
        // Adapta para o método consultar esperar campos como string separada por vírgula
        $_GET['campos'] = implode(',', $ufCampos);
        $_GET['spa'] = $spa;
        $_GET['deal'] = $dealId;
        ob_start();
        $dealController->consultar();
        $result = ob_get_clean();

        // 5. Filtra e exibe apenas os campos do config_extra, com nomes corretos (compatível com Bitrix)
        $data = json_decode($result, true);
        $item = $data['result']['item'] ?? [];
        $retorno = [];
        foreach ($mapCampos as $campoOriginal => $campoBitrix) {
            $retorno[$campoOriginal] = $item[$campoBitrix] ?? null;
        }
        $retorno['id'] = $item['id'] ?? null;
        header('Content-Type: application/json');
        echo json_encode(['result' => ['item' => $retorno]]);
    }
}
