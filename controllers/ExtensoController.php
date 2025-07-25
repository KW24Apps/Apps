<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/UtilHelpers.php';

use Helpers\BitrixDealHelper;
use Helpers\UtilHelpers;

class ExtensoController
{
    public function executar()
    {
        header('Content-Type: application/json');
        $params = $_GET;

        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $dealId = $params['deal'] ?? $params['id'] ?? null;
        $campoValor = $params['valor'] ?? null;
        $campoRetorno = $params['retorno'] ?? null;

        // Validação padronizada
        $parametrosObrigatorios = ['spa', 'deal', 'valor', 'retorno'];
        foreach ($parametrosObrigatorios as $param) {
            if (empty($params[$param])) {
                http_response_code(400);
                echo json_encode(['erro' => "Parâmetro obrigatório ausente: $param"]);
                return;
            }
        }

        $resultado = BitrixDealHelper::consultarDeal($entityId, $dealId, $campoValor);
        $item = $resultado['result'] ?? null;

        // Padroniza acesso ao campo
        $campoBitrix = UtilHelpers::formatarCampos([$campoValor => null]);
        $campoBitrixKey = array_key_first($campoBitrix);

        // Log para depuração
        echo json_encode([
            'debug_resultado_consulta' => $resultado,
            'debug_formatar_campos' => $campoBitrix,
            'debug_campoBitrixKey' => $campoBitrixKey,
            'debug_item_keys' => $item ? array_keys($item) : null
        ]);
        return;
    }
}