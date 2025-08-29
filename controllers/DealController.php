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
        
        $params = $_GET;

        $spa = $params['spa'] ?? null;
        $categoryId = $params['CATEGORY_ID'] ?? null;

        // Filtra campos customizados e campos padrão do CRM dinamicamente
        $fields = array_filter($params, function ($key) {
            // Aceita campos UF_CRM_ em qualquer formato (maiúsculo, minúsculo, camelCase)
            $keyUpper = strtoupper($key);
            $isUfCrm = strpos($keyUpper, 'UF_CRM_') === 0 || 
                       strpos($keyUpper, 'UFCRM_') === 0 || 
                       strpos($key, 'ufCrm_') === 0 ||
                       strpos($key, 'ufcrm_') === 0;
            
            // Aceita também campos padrão do CRM
            $isCrmField = in_array($key, ['companyId', 'contactId', 'stageId', 'sourceId', 'title']);
            
            return $isUfCrm || $isCrmField;
        }, ARRAY_FILTER_USE_KEY);

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
