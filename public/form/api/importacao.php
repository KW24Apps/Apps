<?php
// api/importacao.php - Processa upload de arquivos CSV
error_log("=== DEBUG UPLOAD INICIO ===");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Aumenta os limites de memória e tempo de execução para lidar com arquivos grandes
ini_set('memory_limit', '256M'); 
ini_set('max_execution_time', 300); // 5 minutos

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
        $uploadError = $_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMessage = 'Erro no upload do arquivo: ';
        switch ($uploadError) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMessage .= 'O arquivo excede o limite de tamanho definido no php.ini (upload_max_filesize).';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage .= 'O arquivo excede o limite de tamanho definido no formulário HTML.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage .= 'O upload do arquivo foi feito apenas parcialmente.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage .= 'Nenhum arquivo foi enviado.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage .= 'Faltando uma pasta temporária.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage .= 'Falha ao escrever o arquivo em disco.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage .= 'Uma extensão do PHP interrompeu o upload do arquivo.';
                break;
            default:
                $errorMessage .= 'Motivo desconhecido (código: ' . $uploadError . ').';
                break;
        }
        error_log("ERRO arquivo: " . $errorMessage);
        throw new Exception($errorMessage);
    }
    error_log("Arquivo OK");

    $arquivo = $_FILES['arquivo'];
    $funil = $_POST['funil'] ?? '';
    $responsavelId = $_POST['responsavel_id'] ?? ''; // Captura o ID do responsável
    $solicitanteId = $_POST['solicitante_id'] ?? ''; // Captura o ID do solicitante
    $identificador = $_POST['identificador'] ?? '';

    if (empty($funil)) throw new Exception('Funil é obrigatório');
    if (empty($identificador)) throw new Exception('Identificador é obrigatório');
    if (empty($responsavelId)) throw new Exception('Responsável é obrigatório'); // Valida o ID
    if (empty($solicitanteId)) throw new Exception('Solicitante é obrigatório'); // Valida o ID

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

    // Detecta o delimitador do CSV para contar as linhas
    $delimiter = ','; // Padrão
    if (($handle = fopen($caminhoArquivo, 'r')) !== false) {
        $firstLine = fgets($handle);
        rewind($handle);
        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');
        if ($semicolonCount > $commaCount) {
            $delimiter = ';';
        }
        fclose($handle);
    }
    // Salva o delimitador na sessão para uso posterior
    $_SESSION['importacao_form']['csv_delimiter'] = $delimiter;

    // Conta as linhas do arquivo para exibir na confirmação
    $totalLinhas = 0;
    if (($handle = fopen($caminhoArquivo, 'r')) !== false) {
        // Pula o cabeçalho
        fgetcsv($handle, 0, $delimiter); 
        while (fgetcsv($handle, 0, $delimiter) !== false) {
            $totalLinhas++;
        }
        fclose($handle);
    }

    $_SESSION['importacao_form'] = [
        'funil' => $funil,
        'responsavel_id' => $responsavelId, // Salva o ID do responsável
        'solicitante_id' => $solicitanteId, // Salva o ID do solicitante
        'identificador' => $identificador,
        'arquivo_salvo' => $nomeArquivo, // Nome do arquivo no servidor
        'arquivo_original' => $arquivo['name'], // Nome original do arquivo
        'total_linhas' => $totalLinhas, // Total de registros
        'upload_time' => date('Y-m-d H:i:s'),
        'csv_delimiter' => $delimiter // Garante que o delimitador esteja na sessão
    ];

    // Reabre o arquivo para pegar os cabeçalhos para o mapeamento
    $headers = [];
    if (($handle = fopen($caminhoArquivo, 'r')) !== false) {
        $headers = fgetcsv($handle, 0, $delimiter, '"', "\\"); // Usa o delimitador detectado
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
