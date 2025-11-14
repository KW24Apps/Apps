<?php
namespace Controllers;

use Helpers\LogHelper;
use Services\ReceitaFederalService; // Será criado em breve

class ReceitaFederalController
{
    public function executar()
    {
        // 1. Receber e validar os dados do webhook
        // Para requisições POST, os dados podem vir no corpo da requisição (json) ou via $_POST
        // O usuário mencionou que a URL é `apis.kw24.com.br/receita?cliente=...`, o que sugere $_GET
        // No entanto, a rota foi definida como POST. Vamos assumir que os dados virão via $_POST ou json_decode(file_get_contents('php://input'))
        // Para simplificar, vamos usar $_REQUEST que abrange $_GET e $_POST, mas o ideal seria ser mais específico.
        $data = $_REQUEST; 

        LogHelper::logReceitaFederal("Webhook recebido (Controller): " . json_encode($data, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);

        // Validação básica dos parâmetros
        $requiredParams = ['chave_acesso', 'cnpj', 'campo_retorno'];
        foreach ($requiredParams as $param) {
            if (empty($data[$param])) {
                LogHelper::logReceitaFederal("Erro: Parâmetro '$param' ausente no webhook.", __CLASS__ . '::' . __FUNCTION__);
                http_response_code(400);
                echo json_encode(['status' => 'erro', 'mensagem' => "Parâmetro '$param' ausente."]);
                return;
            }
        }

        // Instancia o serviço e processa a consulta
        $service = new ReceitaFederalService();
        $result = $service->processarConsultaReceita($data);

        // Retorna o resultado
        if ($result['status'] === 'sucesso') {
            LogHelper::logReceitaFederal("Processamento concluído com sucesso: " . json_encode($result, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);
            http_response_code(200);
            echo json_encode($result);
        } else {
            LogHelper::logReceitaFederal("Erro no processamento: " . json_encode($result, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__);
            http_response_code(500);
            echo json_encode($result);
        }
    }
}
