<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Inclui o DAO para salvar os jobs diretamente
require_once __DIR__ . '/../../../dao/BatchJobDAO.php';
use dao\BatchJobDAO;

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

    // Recupera dados da sessão
    $mapeamento = $_SESSION['mapeamento'] ?? [];
    $formData = $_SESSION['importacao_form'] ?? [];
    $funilSelecionado = $formData['funil'] ?? null;

    if (empty($mapeamento) || !$funilSelecionado) {
        throw new Exception('Dados de mapeamento ou funil não encontrados na sessão');
    }

    // Busca o arquivo CSV
    $uploadDir = __DIR__ . '/../uploads/';
    $nomeArquivoSessao = $formData['arquivo_salvo'] ?? null;
    if (!$nomeArquivoSessao || !file_exists($uploadDir . $nomeArquivoSessao)) {
        throw new Exception('Arquivo CSV não encontrado no servidor');
    }
    $csvFile = $uploadDir . $nomeArquivoSessao;

    // Processa o CSV para criar os deals
    $deals = [];
    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            $deal = [];
            foreach ($header as $i => $nomeColuna) {
                $nomeColuna = trim($nomeColuna);
                if (isset($mapeamento[$nomeColuna])) {
                    $codigoBitrix = $mapeamento[$nomeColuna];
                    $deal[$codigoBitrix] = $row[$i] ?? '';
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

    // Divide os deals em chunks de até 2000 itens cada
    $maxDealsPerJob = 2000;
    $chunks = array_chunk($deals, $maxDealsPerJob);
    
    $jobIds = [];
    $totalDealsProcessados = 0;
    $dao = new BatchJobDAO();

    // Extrai os IDs corretos do funil selecionado
    $partesFunil = explode('_', $funilSelecionado);
    $entityTypeId = $partesFunil[0] ?? null;
    $categoryId = $partesFunil[1] ?? null;

    if (!$entityTypeId || !$categoryId) {
        throw new Exception("ID do funil inválido na sessão: '$funilSelecionado'");
    }

    // Processa cada chunk, criando um job para cada um
    foreach ($chunks as $chunk) {
        $jobId = uniqid('job_', true);
        $tipoJob = 'criar_deals';
        
        // Monta o payload do job com os dados corretos
        $dadosJob = [
            'spa' => $entityTypeId,
            'category_id' => $categoryId,
            'deals' => $chunk,
            'webhook' => $webhook
        ];
        
        $totalItensChunk = count($chunk);

        // Salva o job diretamente no banco de dados
        $ok = $dao->criarJob($jobId, $tipoJob, $dadosJob, $totalItensChunk);
        
        if ($ok) {
            $jobIds[] = $jobId;
            $totalDealsProcessados += $totalItensChunk;
        } else {
            throw new Exception("Falha ao inserir o job $jobId no banco de dados.");
        }
    }

    // Redireciona para página de sucesso
    $redirectUrl = "/Apps/public/form/sucesso.php?cliente=" . urlencode($cliente) . 
                  "&jobs=" . urlencode(implode(',', $jobIds)) . 
                  "&total=" . $totalDealsProcessados;
    
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
