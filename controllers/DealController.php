<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/PermissaoHelper.php';

class DealController
{
    public function criar()
    {
        $dados = $_GET;
        $cliente = $dados['cliente'] ?? null;
        $webhook = PermissaoHelper::obterWebhookPermitido($cliente, 'deal');

        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para criar negociação.']);
            return;
        }

        $dados['webhook'] = $webhook;
        $resultado = BitrixHelper::criarNegocio($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function consultar()
    {
        $filtros = $_GET;
        $cliente = $filtros['cliente'] ?? null;
        $webhook = PermissaoHelper::obterWebhookPermitido($cliente, 'deal');

        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para consultar negociação.']);
            return;
        }

        $filtros['webhook'] = $webhook;
        $resultado = BitrixHelper::consultarNegociacao($filtros);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function editar()
    {
        $dados = $_GET;
        $cliente = $dados['cliente'] ?? null;
        $webhook = PermissaoHelper::obterWebhookPermitido($cliente, 'deal');

        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para editar negociação.']);
            return;
        }

        $dados['webhook'] = $webhook;
        $resultado = BitrixHelper::editarNegociacao($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }
}
