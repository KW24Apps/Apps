<?php
set_time_limit(120);
date_default_timezone_set('America/Sao_Paulo');

// Carrega as dependências do Core e helpers Joao é mentiroso123
require_once __DIR__ . '/helpers/LogHelper.php';
require_once __DIR__ . '/Core/ValidacaoAcesso.php';
require_once __DIR__ . '/Core/Router.php';
require_once __DIR__ . '/Repositories/AplicacaoAcessoDAO.php'; // Dependência da validação


use Core\Router;
use Core\ValidacaoAcesso;
use Helpers\LogHelper;

// --- INICIALIZAÇÃO GLOBAL ---
LogHelper::gerarTraceId();

// Log de erros e exceções globais
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    LogHelper::registrarErroGlobal("[$errno] $errstr em $errfile na linha $errline", 'INDEX', 'ErroGlobal');
});
set_exception_handler(function ($exception) {
    LogHelper::registrarErroGlobal("Exceção não capturada: " . $exception->getMessage(), 'INDEX', 'ErroGlobal');
});

// --- PROCESSAMENTO DA REQUISIÇÃO ---
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$cliente = $_GET['cliente'] ?? null;

// --- BYPASS PARA DOCUMENTAÇÃO ---
if (strpos($uri, '/documentacao') === 0) {
    // Calcula o caminho real no disco removendo a barra inicial se necessário
    $relativePath = ltrim($uri, '/');
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    
    // Serve arquivo estático se existir (css, js, md)
    if (is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js'  => 'application/javascript',
            'md'  => 'text/markdown',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml'
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($filePath);
        exit;
    } 
    // Se for o diretório base ou rota, serve o index.html
    elseif (is_dir($filePath) || $uri === '/documentacao' || $uri === '/documentacao/') {
        readfile(__DIR__ . '/documentacao/index.html');
        exit;
    }
}

// Log de entrada da requisição
LogHelper::registrarEntradaGlobal($uri, $method);

// --- VALIDAÇÃO DE ACESSO (CONDICIONAL) ---
if ($cliente) {
    // Extrai o slug da URI. Ex: /company/criar -> company
    $parts = explode('/', trim($uri, '/'));
    $slug = $parts[0] ?? null;

    // Chama o handle de validação. Se falhar, a execução é interrompida dentro do método.
    if (!ValidacaoAcesso::handle($cliente, $slug)) {
        return; // Encerra a execução se a validação falhar
    }
}

// --- ROTEAMENTO ---
// Se a execução chegou até aqui, a validação não era necessária ou foi bem-sucedida.
$router = new Router();
$router->dispatch($uri, $method);
