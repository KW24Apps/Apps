<?php
// api/importacao.php - Processa upload de arquivos CSV
error_log("=== DEBUG UPLOAD INICIO ===");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    error_log("Verificando cliente...");
    $cliente = $_GET['cliente'] ?? $_POST['cliente'] ?? null;
    if (!$cliente) {
        error_log("ERRO: Cliente não informado");
        throw new Exception('Parâmetro cliente é obrigatório');
    }
    error_log("Cliente OK: " . $cliente);

    error_log("Conectando ao banco...");
    $config = [
        'host' => 'localhost',
        'dbname' => 'kw24co49_api_kwconfig',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
        $config['usuario'],
        $config['senha']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Conexão banco OK");

    $sql = "
        SELECT ca.webhook_bitrix
        FROM clientes c
        JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id
        JOIN aplicacoes a ON ca.aplicacao_id = a.id
        WHERE c.chave_acesso = :chave
        AND a.slug = 'import'
        AND ca.ativo = 1
        AND ca.webhook_bitrix IS NOT NULL
        AND ca.webhook_bitrix != ''
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':chave', $cliente);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $webhook = $resultado['webhook_bitrix'] ?? null;

    if (!$webhook) {
        error_log("ERRO: Webhook não encontrado");
        throw new Exception('Webhook não encontrado para o cliente: ' . $cliente);
    }
    error_log("Webhook OK");

    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;
    if (!defined('BITRIX_WEBHOOK')) {
        define('BITRIX_WEBHOOK', $webhook);
    }

    error_log("Verificando arquivo...");
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        error_log("ERRO arquivo: " . ($_FILES['arquivo']['error'] ?? 'não enviado'));
        throw new Exception('Erro no upload do arquivo');
    }
    error_log("Arquivo OK");

    $arquivo = $_FILES['arquivo'];
    $funil = $_POST['funil'] ?? '';
    $responsavel = $_POST['responsavel'] ?? '';
    $solicitante = $_POST['solicitante'] ?? '';
    $identificador = $_POST['identificador'] ?? '';

    if (empty($funil)) throw new Exception('Funil é obrigatório');
    if (empty($identificador)) throw new Exception('Identificador é obrigatório');
    if (empty($responsavel)) throw new Exception('Responsável é obrigatório');
    if (empty($solicitante)) throw new Exception('Solicitante é obrigatório');

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, ['csv'])) {
        throw new Exception('Apenas arquivos CSV são aceitos');
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Erro ao criar diretório de upload');
        }
    }

    if (!is_writable($uploadDir)) {
        throw new Exception('Diretório de upload sem permissão de escrita');
    }

    $nomeArquivo = 'import_' . uniqid() . '.csv';
    $caminhoArquivo = $uploadDir . $nomeArquivo;

    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoArquivo)) {
        $uploadError = error_get_last();
        throw new Exception('Erro ao salvar arquivo: ' . ($uploadError['message'] ?? 'Motivo desconhecido'));
    }

    $_SESSION['importacao_form'] = [
        'funil' => $funil,
        'responsavel' => $responsavel,
        'solicitante' => $solicitante,
        'identificador' => $identificador,
        'arquivo' => $nomeArquivo,
        'upload_time' => date('Y-m-d H:i:s')
    ];

    $headers = [];
    if (($handle = fopen($caminhoArquivo, 'r')) !== false) {
        $headers = fgetcsv($handle, 0, ',', '"', "\\");
        fclose($handle);
    }

    $cliente = $_POST['cliente'] ?? $_GET['cliente'] ?? '';
    $redirectUrl = '/Apps/public/form/mapeamento.php' . ($cliente ? '?cliente=' . urlencode($cliente) : '');
    
    error_log("SUCESSO! Redirecionando para: " . $redirectUrl);
    echo json_encode([
        'sucesso' => true,
        'arquivo' => $nomeArquivo,
        'headers' => $headers,
        'next_url' => $redirectUrl
    ]);

} catch (Exception $e) {
    error_log("ERRO: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ]);
}
?>
