<?php
// download.php - Script para download seguro de arquivos de log
session_start();

// Verificar autenticação
if (!isset($_SESSION['logviewer_auth']) || $_SESSION['logviewer_auth'] !== true) {
    header('Location: LOGs.php');
    exit;
}

// Verificar se o parâmetro de arquivo foi fornecido
if (!isset($_GET['file'])) {
    header('HTTP/1.0 400 Bad Request');
    echo 'Arquivo não especificado';
    exit;
}

// Sanitizar o nome do arquivo (permitir apenas caracteres alfanuméricos, ponto e underscore)
$fileName = basename($_GET['file']);
if (!preg_match('/^[a-zA-Z0-9._]+$/', $fileName)) {
    header('HTTP/1.0 400 Bad Request');
    echo 'Nome de arquivo inválido';
    exit;
}

// Caminho completo para o arquivo
$filePath = __DIR__ . '/logs/' . $fileName;

// Verificar se o arquivo existe e está dentro da pasta logs
if (!file_exists($filePath) || !is_file($filePath) || dirname(realpath($filePath)) !== realpath(__DIR__ . '/logs')) {
    header('HTTP/1.0 404 Not Found');
    echo 'Arquivo não encontrado';
    exit;
}

// Configurar cabeçalhos para download
header('Content-Description: File Transfer');
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Enviar o arquivo para o navegador
readfile($filePath);
exit;
