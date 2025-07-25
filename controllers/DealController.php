<?php
namespace Controllers;

require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use dao\AplicacaoAcessoDAO;
use Helpers\BitrixDealHelper;

class DealController
    {
    public function criar()
    {
        $params = $_GET;

        $spa = $params['spa'] ?? null;
        $categoryId = $params['CATEGORY_ID'] ?? null;

        // Filtra todos os campos UF_CRM_* dinamicamente
        $fields = array_filter($params, function ($key) {
            return strpos($key, 'UF_CRM_') === 0;
        }, ARRAY_FILTER_USE_KEY);

        // Loga o payload enviado para criar negócio
        $resultado = BitrixDealHelper::criarDeal($spa, $categoryId, $fields);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function consultar()
    {
        $params = $_GET;
        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $dealId = $params['deal'] ?? $params['id'] ?? null;
        $fields = $params['campos'] ?? $params['fields'] ?? null;
      
        $resultado = BitrixDealHelper::consultarDeal($entityId, $dealId, $fields);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function editar()
    {
        $params = $_GET;
        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $dealId = $params['deal'] ?? $params['id'] ?? null;

        // Remove os campos fixos antes de repassar para os fields
        unset($params['cliente'], $params['spa'], $params['entityId'], $params['deal'], $params['id']);

        $resultado = BitrixDealHelper::editarDeal($entityId, $dealId, $params);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }
    }
