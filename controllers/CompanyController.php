<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';

use Helpers\BitrixHelper;
use Helpers\BitrixCompanyHelper;

class CompanyController 
{
    public function criar()
    {
        $dados = $_GET;
        $webhook = $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] ?? null;

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
        $webhook = $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] ?? null;

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
        $webhook = $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] ?? null;

        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para editar empresa.']);
            return;
        }   
   
        $dados['webhook'] = $webhook;
        $resultado = BitrixCompanyHelper::editarCamposEmpresa($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }
}
