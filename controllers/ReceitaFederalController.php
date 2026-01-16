<?php

namespace Controllers;

require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../services/ReceitaFederalService.php';

use Helpers\LogHelper;
use Services\ReceitaFederalService;

class ReceitaFederalController
{
    public function executar()
    {
        // Recebe dados da URL (query string)
        $data = $_GET;

        // 1. Validar ID da empresa (obrigatório)
        $idEmpresa = $data['id'] ?? null;
        if (empty($idEmpresa)) {
            LogHelper::logReceitaFederal("Erro: Parâmetro 'id' (ID da empresa) ausente na URL.", __CLASS__ . '::' . __FUNCTION__);
            http_response_code(400);
            echo json_encode(['status' => 'erro', 'mensagem' => "Parâmetro 'id' (ID da empresa) ausente."]);
            return;
        }

        // 2. Obter parâmetros opcionais
        $idDeal = $data['deal'] ?? null;
        $campoRetorno = $data['retorno'] ?? null;

        // 3. Instanciar o serviço e processar a atualização
        $service = new ReceitaFederalService();
        $result = $service->processarAtualizacao($idEmpresa, $idDeal, $campoRetorno);

        // 4. Retornar o resultado
        header('Content-Type: application/json');
        if ($result['status'] === 'sucesso') {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        
        echo json_encode($result);
    }
}
