<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
$q = $_GET['q'] ?? '';

require_once __DIR__ . '/../../../helpers/BitrixHelper.php';
use Helpers\BitrixHelper;

try {
    // Carrega configurações (que já define o webhook globalmente)
    $config = require_once __DIR__ . '/../config.php';
    
    // Verifica se o webhook foi configurado
    if (!isset($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) || 
        !$GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) {
        throw new Exception('Webhook do Bitrix não configurado. Configure no banco de dados para o cliente ou arquivo local.');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Configuração inválida',
        'detalhes' => $e->getMessage()
    ]);
    exit;
}

// Busca todos os usuários do Bitrix, paginando
$usuarios = [];
$start = 0;
do {
    $params = [
        'ACTIVE' => 'Y',
        'ORDER' => ['ID' => 'ASC'],
        'SELECT' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL'],
        'start' => $start
    ];
    $resposta = BitrixHelper::chamarApi('user.get', $params);
    if (isset($resposta['result']) && is_array($resposta['result'])) {
        foreach ($resposta['result'] as $user) {
            $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
            if ($q === '' || stripos($nome, $q) !== false) {
                $usuarios[] = [
                    'id' => $user['ID'],
                    'name' => $nome
                ];
            }
        }
        if (isset($resposta['next'])) {
            $start = $resposta['next'];
        } else {
            $start = null;
        }
    } else {
        // Retorna erro para debug
        echo json_encode(['error' => $resposta['error_description'] ?? 'Erro ao consultar usuários Bitrix', 'debug' => $resposta]);
        exit;
    }
} while ($start !== null);

echo json_encode($usuarios);
exit;
