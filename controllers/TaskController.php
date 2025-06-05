<?php
require_once __DIR__ . '/../helpers/BitrixHelper.php';

class TaskController
{
    public function criar()
    {
        $webhook = $_GET['webhook'] ?? null;
        $titulo = $_GET['titulo'] ?? null;
        $descricao = $_GET['descricao'] ?? '';
        $responsavel = $_GET['responsavel'] ?? null;
        $prazoDias = (int) ($_GET['prazo'] ?? 0);

        if (!$webhook || !$titulo || !$responsavel) {
            http_response_code(400);
            echo json_encode(['erro' => 'Parâmetros obrigatórios ausentes.']);
            return;
        }

        $dataLimite = date('Y-m-d', strtotime("+$prazoDias weekdays"));

        $resposta = BitrixHelper::chamarApi('tasks.task.add', [
            'fields' => [
                'TITLE' => $titulo,
                'DESCRIPTION' => $descricao,
                'RESPONSIBLE_ID' => $responsavel,
                'DEADLINE' => $dataLimite
            ]
        ], ['webhook' => $webhook]);

        echo json_encode($resposta);
    }
}
