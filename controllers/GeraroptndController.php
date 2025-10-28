<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../enums/GeraroptndEnums.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../services/GerarOportunidades/OrganizarDadosService.php';
require_once __DIR__ . '/../services/GerarOportunidades/GerarOportunidadesService.php';

use Helpers\BitrixDealHelper;
use Helpers\LogHelper;
use Helpers\BitrixHelper;
use Enums\GeraroptndEnums;
use Exception;
use Services\GerarOportunidades\OrganizarDadosService;
use Services\GerarOportunidades\GerarOportunidadesService;

class GeraroptndController
{
    public function executar()
    {
        // Definir timeout e header
        set_time_limit(3600);
        header('Content-Type: application/json');

        $dealId = $_GET['deal'] ?? $_GET['id'] ?? null;
        // LogHelper::logGerarOportunidade("INFO: Processo iniciado para o Deal ID: " . ($dealId ?? 'N/A')); // Removido

        try {
            // ============================================
            // PARTE 1: COLETA E VALIDAÇÃO DE DADOS
            // ============================================
            if (!$dealId) {
                throw new Exception('Parâmetro deal/id é obrigatório');
            }

            // Buscar dados do deal principal
            // Esta lógica permanece aqui ou em um DAO/Repository específico para buscar o item bruto
            $camposBitrix = GeraroptndEnums::getAllFields();
            $camposStr = implode(',', $camposBitrix);
            $resultadoConsulta = BitrixDealHelper::consultarDeal(2, $dealId, $camposStr);
            $item = $resultadoConsulta['result'] ?? [];

            if (empty($item)) {
                throw new Exception("Deal com ID {$dealId} não encontrado ou sem dados.");
            }

            // ============================================
            // ORQUESTRAR SERVIÇOS
            // ============================================
            $organizarDadosService = new OrganizarDadosService($item);
            $gerarOportunidadesService = new GerarOportunidadesService($organizarDadosService);

            $resultadoProcesso = $gerarOportunidadesService->executarProcesso();

            // O resultadoProcesso já contém a estrutura final de retorno
            // e a lógica de deals já criados, criação e atualização.
            // Apenas precisamos retornar o JSON.
            echo json_encode($resultadoProcesso, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;

        } catch (Exception $e) {
            $mensagemErro = $e->getMessage();
            LogHelper::logGerarOportunidade("ERROR: Falha no processo para o Deal ID {$dealId}. Motivo: {$mensagemErro}");
            echo json_encode(['sucesso' => false, 'erro' => $mensagemErro], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }
    }
}
