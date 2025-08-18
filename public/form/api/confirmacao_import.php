<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conecta ao sistema principal
require_once __DIR__ . '/../../../index.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    // Verifica se webhook está configurado
    if (!isset($GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) || 
        !$GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix']) {
        throw new Exception('Webhook do Bitrix não configurado');
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
