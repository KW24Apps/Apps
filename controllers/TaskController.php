<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixTaskHelper.php';

use dao\AplicacaoAcessoDAO;

class TaskController
{
    public function criar()
    {
        $dados = $_GET;
        $cliente = $dados['cliente'] ?? null;
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'task');
        $webhook = $acesso['webhook_bitrix'] ?? null;

        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para criar tarefa.']);
            return;
        }

        $dados['webhook'] = $webhook;
        $resultado = BitrixTaskHelper::criarTarefaAutomatica($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }
}
