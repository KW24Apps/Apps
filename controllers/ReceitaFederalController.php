<?php
namespace Controllers;

use Helpers\LogHelper;

class ReceitaFederalController
{
    public function executar()
    {
        // Recebe dados da URL (query string)
        $data = $_GET;

        // Pega e valida o ID da empresa
        $idEmpresaBitrix = $data['id'] ?? null;
        if (empty($idEmpresaBitrix)) {
            LogHelper::logReceitaFederal("Erro: Parâmetro 'id' (ID da empresa) ausente na URL.", __CLASS__ . '::' . __FUNCTION__);
            http_response_code(400);
            echo json_encode(['status' => 'erro', 'mensagem' => "Parâmetro 'id' (ID da empresa) ausente."]);
            return;
        }

        // 3. Instancia o serviço e chama a função para consultar dados iniciais
        $service = new ReceitaFederalService();
        $result = $service->consultarDadosIniciais($idEmpresaBitrix);

        // 4. Retorna o resultado da consulta de dados iniciais
        if ($result['status'] === 'sucesso') {
            LogHelper::logReceitaFederal("Dados iniciais coletados com sucesso para empresa ID '$idEmpresaBitrix'.", __CLASS__ . '::' . __FUNCTION__);
            http_response_code(200);
            echo json_encode($result);
        } else {
            LogHelper::logReceitaFederal("Erro ao coletar dados iniciais para empresa ID '$idEmpresaBitrix': " . $result['mensagem'], __CLASS__ . '::' . __FUNCTION__);
            http_response_code(500); // Ou 404 se o CNPJ não for encontrado, conforme o erro do serviço
            echo json_encode($result);
        }
    }
}
