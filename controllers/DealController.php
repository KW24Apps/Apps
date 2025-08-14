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
                $resultado = BitrixDealHelper::criarDeal($spa, $categoryId, $camposBase);
            } else {
                // Para testes: cria array com N deals
                $fieldsArray = [];
                $timestamp = date('Y-m-d H:i:s');
                
                for ($i = 1; $i <= $quantidade; $i++) {
                    $fieldsCopia = $camposBase;
                    
                    // Adiciona título único para cada deal
                    $fieldsCopia['title'] = "Teste Batch Deal #$i - $timestamp";
                    
                    // Se tem email, torna único
                    if (isset($fieldsCopia['UF_CRM_EMAIL'])) {
                        $email = $fieldsCopia['UF_CRM_EMAIL'];
                        $fieldsCopia['UF_CRM_EMAIL'] = str_replace('@', "+$i@", $email);
                    }
                    
                    $fieldsArray[] = $fieldsCopia;
                }
                
                // Log do teste
                $logTeste = date('Y-m-d H:i:s') . " | DEAL CONTROLLER | TESTE INICIADO: $quantidade deals\n";
                file_put_contents(__DIR__ . '/../../logs/deal_teste.log', $logTeste, FILE_APPEND);
                
                $resultado = BitrixDealHelper::criarDeal($spa, $categoryId, $fieldsArray);
                
                // Log do resultado
                $logResultado = date('Y-m-d H:i:s') . " | DEAL CONTROLLER | RESULTADO: " . json_encode($resultado, JSON_UNESCAPED_UNICODE) . "\n";
                file_put_contents(__DIR__ . '/../../logs/deal_teste.log', $logResultado, FILE_APPEND);
            }
        }

        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Consultar status de um job
     * GET /deal/status?job_id=job_20250814_143022_abc123
     */
    public function status()
    {
        $params = $_GET;
        $jobId = $params['job_id'] ?? null;

        if (!$jobId) {
            $resultado = [
                'erro' => 'Parâmetro job_id é obrigatório',
                'exemplo' => '/deal/status?job_id=job_20250814_143022_abc123'
            ];
        } else {
            $resultado = BitrixBatchHelper::consultarStatus($jobId);
        }

        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Processar jobs manualmente (para testes sem cron)
     * GET /deal/processar
     */
    public function processar()
    {
        $resultado = BitrixBatchHelper::processarJobsPendentes();

        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Listar jobs recentes para acompanhamento
     * GET /deal/jobs?status=pendente
     */
    public function jobs()
    {
        try {
            $params = $_GET;
            $status = $params['status'] ?? 'todos';
            $limit = (int)($params['limit'] ?? 10);

            require_once __DIR__ . '/../dao/BatchJobDAO.php';
            $dao = new \dao\BatchJobDAO();
            
            if ($status === 'todos') {
                // Busca jobs de todos os status
                $config = require __DIR__ . '/../config/config.php';
                $pdo = new \PDO(
                    "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                    $config['usuario'],
                    $config['senha'],
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
                
                $stmt = $pdo->prepare("SELECT * FROM batch_jobs ORDER BY criado_em DESC LIMIT ?");
                $stmt->execute([$limit]);
                $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                // Busca jobs por status específico
                $jobs = $dao->buscarJobsPorStatus($status, $limit);
            }

            // Conta jobs por status
            $contadores = [
                'pendente' => $dao->contarJobsPorStatus('pendente'),
                'processando' => $dao->contarJobsPorStatus('processando'),
                'concluido' => $dao->contarJobsPorStatus('concluido'),
                'erro' => $dao->contarJobsPorStatus('erro')
            ];

            $resultado = [
                'jobs' => $jobs,
                'contadores' => $contadores,
                'filtro_atual' => $status,
                'total_exibido' => count($jobs)
            ];

        } catch (\Exception $e) {
            $resultado = [
                'erro' => 'Erro ao listar jobs: ' . $e->getMessage()
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
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
