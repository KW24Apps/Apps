<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../services/DealService.php';
use Helpers\BitrixDealHelper;
use Services\DealService;

class DealController
    {
    public function criar()
    {
        // Definir timeout de 30 minutos para criação de deals
        set_time_limit(1800); // 30 minutos = 1800 segundos
        
        $params = array_merge($_GET, $_POST);

        $spa = $params['spa'] ?? null;
        $categoryId = $params['CATEGORY_ID'] ?? null;

        // Remove os parâmetros de controle para isolar apenas os campos do deal
        $fields = $params;
        unset($fields['cliente'], $fields['spa'], $fields['CATEGORY_ID']);

        // Trata campos com nomes amigáveis antes de criar o deal
        $dealService = new DealService();
        $entityTypeId = $spa ?? 2; // 2 é o ID padrão para Deals Clássicos
        $camposTratados = $dealService->tratarCamposAmigaveis($fields, $entityTypeId);

        // Comportamento normal - 1 deal
        $resultado = BitrixDealHelper::criarDeal($spa, $categoryId, $camposTratados);

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
        // Definir timeout de 30 minutos para edição de deals
        set_time_limit(1800); // 30 minutos = 1800 segundos
        
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
