<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verifica se cliente foi informado
$cliente = $_GET['cliente'] ?? $_POST['cliente'] ?? null;
if (!$cliente) {
    header('Content-Type: application/json');
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Parâmetro cliente é obrigatório'
    ]);
    exit;
}

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
    $spa = $formData['funil'] ?? null;

    if (empty($mapeamento) || !$spa) {
        throw new Exception('Dados de mapeamento ou SPA não encontrados na sessão');
    }

    // Busca o arquivo CSV mais recente
    $uploadDir = __DIR__ . '/uploads/';
    $files = glob($uploadDir . '*.csv');
    
    if (empty($files)) {
        throw new Exception('Arquivo CSV não encontrado');
    }

    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    $csvFile = $files[0];

    // Processa o CSV para criar os deals
    $deals = [];
    
    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        // Lê o cabeçalho
        $header = fgetcsv($handle, 1000, ',');
        
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $deal = [];
            
            // Para cada coluna do CSV
            for ($i = 0; $i < count($header); $i++) {
                $nomeColuna = trim($header[$i]);
                $valorCelula = isset($row[$i]) ? trim($row[$i]) : '';
                
                // Se existe mapeamento para esta coluna
                if (isset($mapeamento[$nomeColuna])) {
                    $codigoBitrix = $mapeamento[$nomeColuna];
                    $deal[$codigoBitrix] = $valorCelula;
                }
            }
            
            if (!empty($deal)) {
                $deals[] = $deal;
            }
        }
        fclose($handle);
    }

    if (empty($deals)) {
        throw new Exception('Nenhum deal válido encontrado no arquivo CSV');
    }

    // Prepara dados para o importar_job.php
    $jobData = [
        'entityId' => 2, // CRM Deal entity
        'categoryId' => (int)$spa,
        'deals' => $deals,
        'tipoJob' => 'criar_deals'
    ];

    // Chama o importar_job.php
    $url = 'http://localhost/Apps/public/form/api/importar_job.php?cliente=' . urlencode($cliente);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jobData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($jobData))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        
        // Redireciona para página de sucesso
        $redirectUrl = "/Apps/public/form/sucesso.php?cliente=" . urlencode($cliente) . 
                      "&job_id=" . ($result['job_id'] ?? 'unknown') . 
                      "&total=" . count($deals);
        
        header("Location: $redirectUrl");
        exit;
    } else {
        throw new Exception('Erro ao processar importação: ' . $response);
    }

} catch (Exception $e) {
    // Redireciona para página de erro
    $redirectUrl = "/Apps/public/form/erro.php?cliente=" . urlencode($cliente) . 
                  "&mensagem=" . urlencode($e->getMessage());
    
    header("Location: $redirectUrl");
    exit;
}
?>
