<?php
namespace routers;

use Helpers\LogHelper;

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// TEMPORARIAMENTE DESABILITADO PARA DEBUG
// LogHelper::registrarEntradaGlobal($uri, $method);

// Remove os prefixos 'Apps/' e 'importar' da URI para facilitar o roteamento interno  
$uriLimpa = ltrim($uri, '/');
$uriLimpa = preg_replace('/^Apps\//', '', $uriLimpa);
$uriSemPrefixo = preg_replace('/^importar\/?/', '', $uriLimpa);

// Rotas da API
if (strpos($uriSemPrefixo, 'api/') === 0) {
    $apiRoute = str_replace('api/', '', $uriSemPrefixo);
    
    switch ($apiRoute) {
        case 'importacao':
            if ($method === 'POST') {
                require_once __DIR__ . '/../public/form/api/importacao.php';
            } else {
                http_response_code(405);
                echo json_encode(['erro' => 'Método não permitido']);
            }
            break;
            
        case 'bitrix_users':
        case 'bitrix_users.php':
            if ($method === 'GET') {
                require_once __DIR__ . '/../public/form/api/bitrix_users.php';
            } else {
                http_response_code(405);
                echo json_encode(['erro' => 'Método não permitido']);
            }
            break;
            
        case 'salvar_mapeamento':
            if ($method === 'POST') {
                require_once __DIR__ . '/../public/form/api/salvar_mapeamento.php';
            } else {
                http_response_code(405);
                echo json_encode(['erro' => 'Método não permitido']);
            }
            break;
            
        case 'importar_job':
            if ($method === 'POST') {
                require_once __DIR__ . '/../public/form/api/importar_job.php';
            } else {
                http_response_code(405);
                echo json_encode(['erro' => 'Método não permitido']);
            }
            break;
            
        case 'confirmacao_import':
            if ($method === 'POST') {
                require_once __DIR__ . '/../public/form/api/confirmacao_import.php';
            } else {
                http_response_code(405);
                echo json_encode(['erro' => 'Método não permitido']);
            }
            break;
            
        case 'status_job':
            if ($method === 'GET') {
                require_once __DIR__ . '/../public/form/api/status_job.php';
            } else {
                http_response_code(405);
                echo json_encode(['erro' => 'Método não permitido']);
            }
            break;
            
        // Rotas de compatibilidade com sistema antigo FastRoute
        case 'importar_async':
        case 'importar_batch':
            if ($method === 'POST') {
                require_once __DIR__ . '/../public/form/api/importar_job.php';
            } else {
                http_response_code(405);
                echo json_encode(['erro' => 'Método não permitido']);
            }
            break;
            
        case 'status_importacao':
        case 'status_batch':
            if ($method === 'GET') {
                require_once __DIR__ . '/../public/form/api/status_job.php';
            } else {
                http_response_code(405);
                echo json_encode(['erro' => 'Método não permitido']);
            }
            break;
            
        default:
            // TEMPORARIAMENTE DESABILITADO PARA DEBUG
            // LogHelper::registrarRotaNaoEncontrada("importar/api/$apiRoute", $method, __FILE__);
            http_response_code(404);
            echo json_encode(['erro' => 'Endpoint da API não encontrado', 'route' => $apiRoute]);
    }
} 
// Rotas das páginas principais
elseif ($uriSemPrefixo === '' || $uriSemPrefixo === 'index' || $uriSemPrefixo === 'index.php') {
    // Página inicial do sistema de importação
    require_once __DIR__ . '/../public/form/index.php';
} 
elseif ($uriSemPrefixo === 'importacao' || $uriSemPrefixo === 'importacao.php') {
    // Página de upload de arquivos
    require_once __DIR__ . '/../public/form/importacao.php';
} 
elseif ($uriSemPrefixo === 'mapeamento' || $uriSemPrefixo === 'mapeamento.php') {
    // Página de mapeamento de campos
    require_once __DIR__ . '/../public/form/mapeamento.php';
} 
elseif ($uriSemPrefixo === 'setup' || $uriSemPrefixo === 'setup.php') {
    // Página de configuração inicial
    require_once __DIR__ . '/../public/form/setup.php';
} 
elseif ($uriSemPrefixo === 'demo' || $uriSemPrefixo === 'demo.php') {
    // Página de demonstração/teste
    require_once __DIR__ . '/../public/form/demo.php';
} 
else {
    // TEMPORARIAMENTE DESABILITADO PARA DEBUG
    // LogHelper::registrarRotaNaoEncontrada("importar/$uriSemPrefixo", $method, __FILE__);
    http_response_code(404);
    echo json_encode(['erro' => 'Página não encontrada no sistema de importação', 'uri' => $uriSemPrefixo]);
}
