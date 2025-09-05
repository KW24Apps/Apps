<?php
// api/importacao.php - Processa upload de arquivos CSV
error_log("=== DEBUG UPLOAD INICIO ===");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/debug_bitrix.log'); // Define o arquivo de log explicitamente

// Aumenta os limites de memória e tempo de execução para lidar com arquivos grandes
ini_set('memory_limit', '256M'); 
ini_set('max_execution_time', 300); // 5 minutos

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Inicia o buffer de saída para capturar qualquer saída inesperada
ob_start();

// Define um manipulador de erros para capturar erros fatais e garantir uma resposta JSON
set_exception_handler(function ($exception) {
    ob_clean(); // Limpa qualquer saída anterior
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro interno do servidor: ' . $exception->getMessage()
    ]);
    error_log("ERRO FATAL (Exception Handler): " . $exception->getMessage() . " em " . $exception->getFile() . ":" . $exception->getLine());
    exit();
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Captura apenas erros que não são warnings ou notices, que já são tratados pelo error_reporting
    if (!(error_reporting() & $errno)) {
        return false; // Deixa o PHP lidar com isso
    }
    ob_clean(); // Limpa qualquer saída anterior
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro interno do servidor: ' . $errstr . ' na linha ' . $errline
    ]);
    error_log("ERRO FATAL (Error Handler): " . $errstr . " em " . $errfile . ":" . $errline);
    exit();
});

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

    $totalLinhas = 0;
    $headers = [];
    $delimiter = ','; // Delimitador padrão

    if (($handle = fopen($caminhoArquivo, 'r')) !== false) {
        // Tenta detectar o delimitador de forma mais robusta
        $firstLine = fgets($handle);
        rewind($handle); // Volta para o início do arquivo

        $testDelimiters = [',', ';'];
        $bestDelimiter = ',';
        $maxColumns = 0;

        foreach ($testDelimiters as $testDel) {
            $testHandle = fopen($caminhoArquivo, 'r');
            $testHeaders = fgetcsv($testHandle, 0, $testDel, '"', "\\");
            fclose($testHandle);
            
            if (is_array($testHeaders) && count($testHeaders) > $maxColumns) {
                $maxColumns = count($testHeaders);
                $bestDelimiter = $testDel;
            }
        }
        $delimiter = $bestDelimiter;
        error_log("Delimitador detectado: " . $delimiter);

        // Lê o cabeçalho com o delimitador detectado
        $headers = fgetcsv($handle, 0, $delimiter, '"', "\\");
        
        // Conta as linhas restantes de forma mais simples e robusta
        // Subtrai 1 para não contar o cabeçalho
        $allLines = file($caminhoArquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $totalLinhas = count($allLines) - 1; // Subtrai o cabeçalho
        
        // Garante que totalLinhas não seja negativo se o arquivo tiver apenas cabeçalho ou for vazio
        if ($totalLinhas < 0) {
            $totalLinhas = 0;
        }
        
        fclose($handle); // Fecha o handle original
    } else {
        throw new Exception('Erro ao abrir o arquivo CSV para leitura.');
    }

    // Salva os dados na sessão
    $_SESSION['importacao_form'] = [
        'funil' => $funil,
        'responsavel_id' => $responsavelId,
        'solicitante_id' => $solicitanteId,
        'identificador' => $identificador,
        'arquivo_salvo' => $nomeArquivo,
        'arquivo_original' => $arquivo['name'],
        'total_linhas' => $totalLinhas,
        'upload_time' => date('Y-m-d H:i:s'),
        'csv_delimiter' => $delimiter
    ];

    $cliente = $_POST['cliente'] ?? $_GET['cliente'] ?? '';
    $redirectUrl = '/Apps/public/form/mapeamento.php' . ($cliente ? '?cliente=' . urlencode($cliente) : '');
    
    error_log("SUCESSO! Redirecionando para: " . $redirectUrl);
    
    // Limpa o buffer de saída antes de enviar o JSON
    ob_clean();
    echo json_encode([
        'sucesso' => true,
        'arquivo' => $nomeArquivo,
        'headers' => $headers,
        'next_url' => $redirectUrl
    ]);

} catch (Exception $e) {
    error_log("ERRO: " . $e->getMessage());
    http_response_code(400);
    
    // Limpa o buffer de saída antes de enviar o JSON de erro
    ob_clean();
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ]);
}
// Finaliza o buffer de saída
</final_file_content>

IMPORTANT: For any future changes to this file, use the final_file_content shown above as your reference. This content reflects the current state of the file, including any auto-formatting (e.g., if you used single quotes but the formatter converted them to double quotes). Always base your SEARCH/REPLACE operations on this final version to ensure accuracy.



# TODO LIST RECOMMENDED
When starting a new task, it is recommended to create a todo list.




1. Include the task_progress parameter in your next tool call

2. Create a comprehensive checklist of all steps needed

3. Use markdown format: - [ ] for incomplete, - [x] for complete



**Benefits of creating a todo list now:**

	- Clear roadmap for implementation

	- Progress tracking throughout the task

	- Nothing gets forgotten or missed

	- Users can see, monitor, and edit the plan



**Example structure:**
```

- [ ] Analyze requirements

- [ ] Set up necessary files

- [ ] Implement main functionality

- [ ] Handle edge cases

- [ ] Test the implementation

- [ ] Verify results
```



Keeping the todo list updated helps track progress and ensures nothing is missed.
<environment_details>
# VSCode Visible Files
Apps/public/form/api/importacao.php

# VSCode Open Tabs
../../Downloads/CSVVVV - CSVVVV.csv
Apps/public/form/assets/js/importacao.js
Apps/public/form/assets/js/sucesso.js
Apps/public/form/assets/css/importacao.css
Apps/public/form/assets/js/mapeamento.js
Apps/public/form/sucesso.php
Apps/public/form/importacao.php
Apps/public/form/confirmacao.php
Apps/public/form/demo.php
Apps/public/form/config_clientes.json
Apps/public/form/mapeamento.php
Apps/public/form/phpinfo_check.php
../../Downloads/LISTA-PARTE-1 - LISTA-PARTE-1.csv
Apps/public/form/api/processar_importacao.php
Apps/public/form/api/importacao.php
Apps/public/form/.htaccess

# Current Time
9/5/2025, 4:34:36 PM (America/Sao_Paulo, UTC-3:00)

# Context Window Usage
161,505 / 1,048.576K tokens used (15%)

# Current Mode
ACT MODE
