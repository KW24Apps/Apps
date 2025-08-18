<?php
// debug_api.php - API de debug para usuários Bitrix
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Log de debug
$logFile = __DIR__ . '/../logs/api_debug.log';
if (!is_dir(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0755, true);
}

function logDebug($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logDebug("=== INÍCIO DEBUG API ===");
logDebug("GET: " . json_encode($_GET));
logDebug("URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

$q = $_GET['q'] ?? '';
$cliente = $_GET['cliente'] ?? null;

logDebug("Parâmetros: q='$q', cliente='$cliente'");

try {
    logDebug("Carregando config...");
    
    // Se não tem cliente no GET, simula um para teste
    if (!$cliente) {
        $_GET['cliente'] = 'gnappC93jLq7RxKZVp28HswuAYMe1';
        $cliente = $_GET['cliente'];
        logDebug("Cliente não informado, usando padrão: $cliente");
    }
    
    $config = require_once __DIR__ . '/../config.php';
    logDebug("Config carregado com sucesso");
    
    // Verifica se o webhook foi configurado
    if (!isset($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) || 
        !$GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) {
        logDebug("Webhook não configurado em GLOBALS");
        throw new Exception('Webhook do Bitrix não configurado. Configure no banco de dados para o cliente ou arquivo local.');
    }
    
    $webhook = $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'];
    logDebug("Webhook encontrado: " . substr($webhook, 0, 50) . "...");
    
    require_once __DIR__ . '/../../../helpers/BitrixHelper.php';
    
    logDebug("BitrixHelper carregado");
    
    // Teste simples de conexão com Bitrix
    $params = [
        'ACTIVE' => 'Y',
        'ORDER' => ['ID' => 'ASC'],
        'SELECT' => ['ID', 'NAME', 'LAST_NAME'],
        'start' => 0,
        'FILTER' => ['ACTIVE' => 'Y']
    ];
    
    logDebug("Chamando Bitrix API user.get com params: " . json_encode($params));
    $resposta = \Helpers\BitrixHelper::chamarApi('user.get', $params);
    logDebug("Resposta Bitrix: " . substr(json_encode($resposta), 0, 200) . "...");
    
    if (isset($resposta['result']) && is_array($resposta['result'])) {
        $usuarios = [];
        foreach ($resposta['result'] as $user) {
            $nome = trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? ''));
            if ($q === '' || stripos($nome, $q) !== false) {
                $usuarios[] = [
                    'id' => $user['ID'],
                    'name' => $nome
                ];
            }
        }
        
        logDebug("Usuários encontrados: " . count($usuarios));
        echo json_encode($usuarios);
        
    } else {
        logDebug("Erro na resposta Bitrix: " . json_encode($resposta));
        echo json_encode([
            'error' => $resposta['error_description'] ?? 'Erro ao consultar usuários Bitrix', 
            'debug' => $resposta,
            'webhook_usado' => substr($webhook, 0, 50) . "...",
            'cliente' => $cliente
        ]);
    }
    
} catch (Exception $e) {
    $erro = $e->getMessage();
    logDebug("ERRO: $erro");
    
    http_response_code(500);
    echo json_encode([
        'erro' => 'Configuração inválida',
        'detalhes' => $erro,
        'cliente' => $cliente,
        'debug_info' => [
            'webhook_definido' => isset($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']),
            'webhook_valor' => $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] ?? 'NÃO DEFINIDO'
        ]
    ]);
}

logDebug("=== FIM DEBUG API ===");
?>
