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
        $filtros = $_GET;
        $cliente = $filtros['cliente'] ?? null;
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'deal');
        $webhook = $acesso['webhook_bitrix'] ?? null;
        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para consultar negociação.']);
            return;
        }

        $filtros['webhook'] = $webhook;
        $resultado = BitrixDealHelper::consultarNegociacao($filtros);

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
