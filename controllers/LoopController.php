<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;
use DateTime;

class LoopController
{
    public function executar()
    {
        $taskId = $_GET['task_id'] ?? null;
        $cardId = $_GET['card_id'] ?? null;
        $etapaAtual = $_GET['etapa_atual'] ?? null;
        $etapaDestino = $_GET['etapa_destino_id'] ?? null;
        $diasAteNova = (int) ($_GET['dias_ate_proxima'] ?? 0);
        $responsavelId = $_GET['responsavel_id'] ?? null;
        $nomeTarefa = $_GET['nome_tarefa'] ?? '';
        $descricao = $_GET['descricao'] ?? '';
        $prazoDias = (int) ($_GET['prazo_em_dias'] ?? 0);
        $webhook = $_GET['webhook'] ?? null;
        $dealId = $_GET['deal'] ?? null;

        if (!$taskId || !$cardId || !$etapaAtual || !$etapaDestino || !$webhook || !$dealId) {
            http_response_code(400);
            echo json_encode(['erro' => 'Parâmetros obrigatórios ausentes.']);
            return;
        }

        if ($etapaAtual === 'CONCLUIDO') {
            echo json_encode(['status' => 'Card finalizado, nada a fazer.']);
            return;
        }

        // Atualizar tarefa existente antes do sleep
        $dataLimite = date('Y-m-d', strtotime("+$prazoDias weekdays"));

        $atualizacao = BitrixHelper::chamarApi('tasks.task.update', [
            'taskId' => $taskId,
            'fields' => [
                'TITLE' => $nomeTarefa,
                'DESCRIPTION' => $descricao,
                'RESPONSIBLE_ID' => $responsavelId,
                'DEADLINE' => $dataLimite
            ]
        ], ['webhook' => $webhook]);

        // Espera antes de mover o card
        $segundos = $this->calcularSleep($diasAteNova);
        sleep($segundos);

        BitrixHelper::chamarApi('crm.item.update', [
            'entityTypeId' => 2,
            'id' => $cardId,
            'fields' => ['stageId' => $etapaDestino]
        ], ['webhook' => $webhook]);

        echo json_encode([
            'tarefa_atualizada' => $taskId,
            'movido_para' => $etapaDestino,
            'resultado' => $atualizacao
        ]);
    }

    private function calcularSleep($dias)
    {
        $diasSegundos = 0;
        $hoje = new DateTime();

        while ($dias > 0) {
            $hoje->modify('+1 day');
            if ($hoje->format('N') < 6) {
                $dias--;
                $diasSegundos += 86400;
            }
        }

        return $diasSegundos;
    }
}
