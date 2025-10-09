<?php
namespace Controllers;

require_once __DIR__ . '/../services/clicksign/GerarAssinaturaService.php';
require_once __DIR__ . '/../services/clicksign/RetornoClickSignService.php';
require_once __DIR__ . '/../services/clicksign/DocumentoService.php';
require_once __DIR__ . '/../services/clicksign/PrazoService.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Services\ClickSign\GerarAssinaturaService;
use Services\ClickSign\RetornoClickSignService;
use Services\ClickSign\DocumentoService;
use Services\ClickSign\PrazoService;
use Helpers\LogHelper;

class ClickSignController
{
    // Método para gerar assinatura na ClickSign
    public static function GerarAssinatura()
    {
        header('Content-Type: application/json; charset=utf-8');
        $response = GerarAssinaturaService::gerarAssinatura($_GET);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return $response;
    }
 
    // Processa o webhook da ClickSign
    public static function retornoClickSign($requestData)
    {
        header('Content-Type: application/json; charset=utf-8');
        $rawBody = file_get_contents('php://input');
        $headerSignature = $_SERVER['HTTP_CONTENT_HMAC'] ?? null;
        $response = RetornoClickSignService::processarWebhook($requestData, $rawBody, $headerSignature);
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
                $response = DocumentoService::cancelarDocumento($params);
                break;

            case 'Atualizar Documento':
                $response = DocumentoService::atualizarDataDocumento($params);
                break;

            default:
                $response = ['success' => false, 'mensagem' => "Ação '$action' é inválida."];
                LogHelper::logClickSign($response['mensagem'], 'controller');
                break;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return $response;
    }

    // Atualizar data do documento a vencer (Job)
    public static function extendDeadlineForDueDocuments()
    {
        $response = PrazoService::processarAdiamentoDePrazos();
        return $response;
    }
}
