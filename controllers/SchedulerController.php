<?php
namespace controllers;

class SchedulerController
{
    // Método principal para lidar com a requisição
    public function executar()
    {
        file_put_contents(__DIR__ . '/test_jurandir.log', date('c') . " [DEBUG] Antes de chamar detectarAplicacaoPorUri | uri={$uri}\n", FILE_APPEND);
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

        // 3. Monta lista de UF_CRM_* para consulta
        $campos = $configJson[$spaKey]['campos'];
        $ufCampos = array_column($campos, 'uf');

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

        // 5. Exibe o resultado da consulta para teste
        header('Content-Type: application/json');
        echo $result;
    }
}
