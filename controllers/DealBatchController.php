<?php
namespace Controllers;

require_once __DIR__ . '/../dao/BatchJobDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use dao\BatchJobDAO;
use Helpers\BitrixDealHelper;

class DealBatchController
{
    // Processa o próximo job pendente
    public static function processarProximoJob(): array
    {
        $dao = new BatchJobDAO();
        $job = $dao->buscarJobPendente();
        if (!$job) {
            return [
                'status' => 'sem_jobs',
                'mensagem' => 'Nenhum job pendente encontrado',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        $dao->marcarComoProcessando($job['job_id']);
        try {
            $dados = json_decode($job['dados_entrada'], true);
            $tipo = $job['tipo'];
            $resultado = null;
            if ($tipo === 'criar_deals') {
                $resultado = BitrixDealHelper::criarDeal($dados['spa'], $dados['category_id'], $dados['deals']);
            } elseif ($tipo === 'editar_deals') {
                $resultado = BitrixDealHelper::editarDeal($dados['spa'], $dados['deal_ids'], $dados['deals']);
            } else {
                throw new \Exception('Tipo de job não suportado: ' . $tipo);
            }
            $dao->marcarComoConcluido($job['job_id'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
            return [
                'status' => 'processado',
                'job_id' => $job['job_id'],
                'resultado' => $resultado
            ];
        } catch (\Throwable $e) {
            $dao->marcarComoErro($job['job_id'], $e->getMessage());
            return [
                'status' => 'erro',
                'job_id' => $job['job_id'],
                'mensagem' => $e->getMessage()
            ];
        }
    }
}
