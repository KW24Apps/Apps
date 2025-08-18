<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verifica se cliente foi informado
$cliente = $_GET['cliente'] ?? $_POST['cliente'] ?? null;
if (!$cliente) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Parâmetro cliente é obrigatório'
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    // Conecta diretamente ao banco para buscar webhook
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
        throw new Exception('Webhook não encontrado para o cliente: ' . $cliente);
    }

    // Define globalmente para uso nos helpers
    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;
    
    // Define constante para compatibilidade
    if (!defined('BITRIX_WEBHOOK')) {
        define('BITRIX_WEBHOOK', $webhook);
    }

    // Recupera dados da sessão
    $mapeamento = $_SESSION['mapeamento'] ?? [];
    $formData = $_SESSION['importacao_form'] ?? [];
    $spa = $formData['funil'] ?? 'undefined';

    if (empty($mapeamento)) {
        echo json_encode([
            'sucesso' => false, 
            'mensagem' => 'Mapeamento não encontrado na sessão'
        ]);
        exit;
    }

    // Busca o arquivo CSV mais recente
    $uploadDir = __DIR__ . '/uploads/';
    $files = glob($uploadDir . '*.csv');
    
    if (empty($files)) {
        echo json_encode([
            'sucesso' => false, 
            'mensagem' => 'Arquivo CSV não encontrado'
        ]);
        exit;
    }

    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    $csvFile = $files[0];
    $nomeArquivo = basename($csvFile);

    // Processa o CSV
    $dados = [];
    $dados_processamento = [];
    
    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        // Lê o cabeçalho
        $header = fgetcsv($handle, 1000, ',');
        $contador = 0;
        
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE && $contador < 5) {
            $linha_display = [];
            $linha_processamento = [];
            
            // Para cada coluna do CSV
            for ($i = 0; $i < count($header); $i++) {
                $nomeColuna = trim($header[$i]);
                $valorCelula = isset($row[$i]) ? trim($row[$i]) : '';
                
                // Para dados de exibição (mostra nome das colunas)
                $linha_display[$nomeColuna] = $valorCelula;
                
                // Para dados de processamento (usa códigos do Bitrix)
                if (isset($mapeamento[$nomeColuna])) {
                    $codigoBitrix = $mapeamento[$nomeColuna];
                    $linha_processamento[$codigoBitrix] = $valorCelula;
                }
            }
            
            $dados[] = $linha_display;
            $dados_processamento[] = $linha_processamento;
            $contador++;
        }
        fclose($handle);
    }

    // Conta total de linhas no arquivo
    $totalLinhas = 0;
    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        fgetcsv($handle); // Pula cabeçalho
        while (fgetcsv($handle) !== FALSE) {
            $totalLinhas++;
        }
        fclose($handle);
    }

    echo json_encode([
        'sucesso' => true,
        'dados' => $dados,
        'dados_processamento' => $dados_processamento,
        'total' => $totalLinhas,
        'arquivo' => $nomeArquivo,
        'spa' => $spa,
        'funil_id' => $spa,
        'mapeamento' => $mapeamento
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro: ' . $e->getMessage()
    ]);
}
?>
