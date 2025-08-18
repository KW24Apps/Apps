<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Inclui o BitrixDealHelper
require_once __DIR__ . '/../../../helpers/BitrixDealHelper.php';
use Helpers\BitrixDealHelper;

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

    error_log("DEBUG: Session mapeamento: " . print_r($mapeamento, true));
    error_log("DEBUG: Session formData: " . print_r($formData, true));
    error_log("DEBUG: SPA: " . $spa);

    if (empty($mapeamento) || !$spa) {
        error_log("ERRO: Mapeamento vazio: " . (empty($mapeamento) ? 'SIM' : 'NÃO'));
        error_log("ERRO: SPA vazio: " . ($spa ? 'NÃO' : 'SIM'));
        throw new Exception('Dados de mapeamento ou SPA não encontrados na sessão');
    }

    // Busca o arquivo CSV mais recente
    $uploadDir = __DIR__ . '/../uploads/';
    error_log("DEBUG: Procurando arquivos CSV em: " . $uploadDir);
    
    if (!is_dir($uploadDir)) {
        error_log("ERRO: Diretório de upload não existe: " . $uploadDir);
        throw new Exception('Diretório de uploads não encontrado');
    }
    
    $files = glob($uploadDir . '*.csv');
    error_log("DEBUG: Arquivos CSV encontrados: " . print_r($files, true));
    
    if (empty($files)) {
        error_log("ERRO: Nenhum arquivo CSV encontrado em: " . $uploadDir);
        // Lista todos os arquivos para debug
        $allFiles = glob($uploadDir . '*');
        error_log("DEBUG: Todos os arquivos no diretório: " . print_r($allFiles, true));
        throw new Exception('Arquivo CSV não encontrado');
    }

    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    $csvFile = $files[0];
    
    // Se temos o nome do arquivo na sessão, tenta usar ele primeiro
    $nomeArquivoSessao = $formData['arquivo'] ?? null;
    if ($nomeArquivoSessao) {
        $arquivoSessao = $uploadDir . $nomeArquivoSessao;
        error_log("DEBUG: Tentando usar arquivo da sessão: " . $arquivoSessao);
        if (file_exists($arquivoSessao)) {
            $csvFile = $arquivoSessao;
            error_log("DEBUG: Usando arquivo da sessão: " . $csvFile);
        } else {
            error_log("WARNING: Arquivo da sessão não existe, usando mais recente: " . $csvFile);
        }
    }
    
    error_log("DEBUG: Arquivo CSV selecionado: " . $csvFile);

    // Processa o CSV para criar os deals
    $deals = [];
    
    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        // Lê o cabeçalho
        $header = fgetcsv($handle, 1000, ',');
        error_log("DEBUG: Cabeçalho do CSV: " . print_r($header, true));
        
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
                    
                    // Log apenas para o primeiro deal
                    if (count($deals) === 0) {
                        error_log("DEBUG: Mapeamento - Coluna '$nomeColuna' -> Campo '$codigoBitrix' = '$valorCelula'");
                    }
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

    error_log("DEBUG: Total de deals preparados: " . count($deals));
    error_log("DEBUG: Primeiro deal: " . print_r($deals[0] ?? 'nenhum', true));
    error_log("DEBUG: Último deal: " . print_r($deals[count($deals)-1] ?? 'nenhum', true));

    // Divide os deals em chunks de até 50 itens cada
    $maxDealsPerJob = 50;
    $chunks = array_chunk($deals, $maxDealsPerJob);
    $totalChunks = count($chunks);
    
    error_log("DEBUG: Dividindo " . count($deals) . " deals em " . $totalChunks . " jobs de até " . $maxDealsPerJob . " itens");

    $jobIds = [];
    $totalDealsProcessados = 0;

    // Processa diretamente cada chunk
    foreach ($chunks as $chunkIndex => $chunk) {
        $chunkNumber = $chunkIndex + 1;
        error_log("DEBUG: Processando chunk $chunkNumber/" . $totalChunks . " com " . count($chunk) . " deals");

        // Prepara dados para o job atual
        $jobData = [
            'entityId' => 2, // CRM Deal entity
            'categoryId' => (int)$spa,
            'deals' => $chunk,
            'tipoJob' => 'criar_deals'
        ];

        error_log("DEBUG: JobData chunk $chunkNumber preparado com " . count($jobData['deals']) . " deals");

        // Usa a função do BitrixDealHelper para criar job na fila
        $resultado = BitrixDealHelper::criarJobParaFila(2, (int)$spa, $chunk, 'criar_deals');
        
        if ($resultado['status'] === 'job_criado') {
            $jobIds[] = $resultado['job_id'];
            $totalDealsProcessados += count($chunk);
            error_log("DEBUG: Job $chunkNumber criado com sucesso - ID: " . $resultado['job_id']);
        } else {
            error_log("ERRO: Falha ao criar job $chunkNumber: " . ($resultado['mensagem'] ?? 'Erro desconhecido'));
            throw new Exception("Erro ao criar job $chunkNumber: " . ($resultado['mensagem'] ?? 'Erro desconhecido'));
        }
    }

    // Redireciona para página de sucesso com informações dos múltiplos jobs
    $redirectUrl = "/Apps/public/form/sucesso.php?cliente=" . urlencode($cliente) . 
                  "&jobs=" . urlencode(implode(',', $jobIds)) . 
                  "&total=" . $totalDealsProcessados . 
                  "&chunks=" . $totalChunks;
    
    header("Location: $redirectUrl");
    exit;

} catch (Exception $e) {
    // Redireciona para página de erro
    $redirectUrl = "/Apps/public/form/erro.php?cliente=" . urlencode($cliente) . 
                  "&mensagem=" . urlencode($e->getMessage());
    
    header("Location: $redirectUrl");
    exit;
}
?>
