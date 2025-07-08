<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';


use dao\AplicacaoAcessoDAO;

class DealController
{
    public function criar()
    {
        $dados = $_GET;
        $cliente = $dados['cliente'] ?? null;
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'deal');
        $webhook = $acesso['webhook_bitrix'] ?? null;
        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para criar negociação.']);
            return;
        }

        $dados['webhook'] = $webhook;
        $resultado = BitrixDealHelper::criarNegocio($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function consultar()
    {
        $params = $_GET;
        $cliente = $params['cliente'] ?? null;
        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $id = $params['deal'] ?? $params['id'] ?? null;
        $fields = $params['campos'] ?? $params['fields'] ?? null;

        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'deal');
        $webhook = $acesso['webhook_bitrix'] ?? null;
        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para consultar negociação.']);
            return;
        }

        $resultado = BitrixDealHelper::consultarDeal($entityId, $id, $fields, $webhook);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function editar()
    {
        $dados = $_GET;
        $cliente = $dados['cliente'] ?? null;
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'deal');
        $webhook = $acesso['webhook_bitrix'] ?? null;
        
        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para editar negociação.']);
            return;
        }

        $dados['webhook'] = $webhook;
        $resultado = BitrixDealHelper::editarNegociacao($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }
}
