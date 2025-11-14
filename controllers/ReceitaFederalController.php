<?php
namespace Controllers;

use Helpers\LogHelper;
use Services\ReceitaFederalService;
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
        header('Content-Type: application/json');
        if ($result['status'] === 'sucesso') {
            // Log negativo: não logar sucesso, apenas erros.
            http_response_code(200);
            echo json_encode($result);
        } else {
            LogHelper::logReceitaFederal("Erro ao coletar dados iniciais para empresa ID '$idEmpresaBitrix': " . $result['mensagem'], __CLASS__ . '::' . __FUNCTION__);
            http_response_code(400); // Erro do cliente (parâmetro ausente, CNPJ não encontrado)
            echo json_encode($result);
        }
    }
}
