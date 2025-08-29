<?php
namespace Controllers;

require_once __DIR__ . '/../services/ClickSignService.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Services\ClickSignService;
use Helpers\LogHelper;

class ClickSignController
{
    // Método para gerar assinatura na ClickSign
    public static function GerarAssinatura()
    {
        // Define headers para resposta JSON
        header('Content-Type: application/json; charset=utf-8');
        
        // Delega toda a lógica para o serviço
        $response = ClickSignService::gerarAssinatura($_GET);

        // Retorna a resposta do serviço em formato JSON
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return $response;
    }
 
    // Processa o webhook da ClickSign
    public static function retornoClickSign($requestData)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $rawBody = file_get_contents('php://input');
        $headerSignature = $_SERVER['HTTP_CONTENT_HMAC'] ?? null;

        $response = ClickSignService::processarWebhook($requestData, $rawBody, $headerSignature);

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return $response;
    }

    // Atualiza ou cancela um documento
    public static function atualizarDocumentoClickSign()
    {
        header('Content-Type: application/json; charset=utf-8');
        $params = $_GET;
        $action = $params['action'] ?? null;
        $response = [];

        switch ($action) {
            case 'Cancelar Documento':
                $response = ClickSignService::cancelarDocumento($params);
                break;

            case 'Atualizar Documento':
                $response = ClickSignService::atualizarDataDocumento($params);
                break;

            default:
                $response = ['success' => false, 'mensagem' => "Ação '$action' é inválida."];
                LogHelper::logClickSign($response['mensagem'], 'controller');
                break;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return $response;
    }
}
