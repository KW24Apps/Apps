<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';

use dao\AplicacaoAcessoDAO;

class CompanyController
{
    public function criar()
    {
        $dados = $_GET;
        $cliente = $dados['cliente'] ?? null;
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'company');
        $webhook = $acesso['webhook_bitrix'] ?? null;

        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para criar empresa.']);
            return;
        }

        $dados['webhook'] = $webhook;
        $resultado = BitrixCompanyHelper::criarEmpresa($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function consultar()
    {
        $filtros = $_GET;
        $cliente = $filtros['cliente'] ?? null;
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'company');
        $webhook = $acesso['webhook_bitrix'] ?? null;

        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para consultar empresa.']);
            return;
        }

        $filtros['webhook'] = $webhook;
        $resultado = BitrixCompanyHelper::consultarEmpresa($filtros);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function editar()
    {
        $dados = $_GET;
        $cliente = $dados['cliente'] ?? null;
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'company');
        $webhook = $acesso['webhook_bitrix'] ?? null;

        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para editar empresa.']);
            return;
        }
        
        file_put_contents(__DIR__ . '/../logs/company_editar.log', json_encode([
            'cliente' => $cliente,
            'webhook' => $webhook,
            'GET' => $_GET,
            'dados' => $dados
        ]) . PHP_EOL, FILE_APPEND);
        

        $dados['webhook'] = $webhook;
        $resultado = BitrixCompanyHelper::editarCamposEmpresa($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }
} 
