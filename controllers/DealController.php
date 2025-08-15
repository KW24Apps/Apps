<?php
namespace Controllers;

require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixBatchHelper.php';

use dao\AplicacaoAcessoDAO;
use Helpers\BitrixDealHelper;
use Helpers\BitrixBatchHelper;

class DealController
{
    /**
     * Criar deals - funciona para 1 deal ou milhares
     * GET /deal/criar?spa=123&CATEGORY_ID=456&quantidade=500&UF_CRM_EMAIL=teste@teste.com
     */
    public function criar()
    {
        $params = $_GET;

    $spa = $params['spa'] ?? null;
    $categoryId = $params['CATEGORY_ID'] ?? null;
    $quantidade = (int)($params['quantidade'] ?? 1); // Parâmetro para teste
    $tipoJob = $params['tipo_job'] ?? 'criar_deals';


        if (!$spa || !$categoryId) {
            $resultado = [
                'erro' => 'Parâmetros spa e CATEGORY_ID são obrigatórios'
            ];
        } else {
            // Filtra campos UF_CRM_* dinamicamente
            $camposBase = array_filter($params, function ($key) {
                return strpos($key, 'UF_CRM_') === 0;
            }, ARRAY_FILTER_USE_KEY);

            // Se quantidade = 1, usa campos como vieram
            if ($quantidade == 1) {
                $resultado = BitrixDealHelper::criarJobParaFila($spa, $categoryId, [$camposBase], 'criar_deals');
            } else {
                // Para testes: cria array com N deals
                $fieldsArray = [];
                $timestamp = date('Y-m-d H:i:s');
                for ($i = 1; $i <= $quantidade; $i++) {
                    $fieldsCopia = $camposBase;
                    $fieldsCopia['title'] = "Teste Batch Deal #$i - $timestamp";
                    if (isset($fieldsCopia['UF_CRM_EMAIL'])) {
                        $email = $fieldsCopia['UF_CRM_EMAIL'];
                        $fieldsCopia['UF_CRM_EMAIL'] = str_replace('@', "+$i@", $email);
                    }
                    $fieldsArray[] = $fieldsCopia;
                }
                $resultado = BitrixDealHelper::criarJobParaFila($spa, $categoryId, $fieldsArray, 'criar_deals');
            }
        }

        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    // Função para consultar Deals
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
