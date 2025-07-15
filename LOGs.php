<?php
// viewer_logs.php - Coloque este arquivo na raiz do seu projeto ou em uma pasta admin/logs

// Verificação de autenticação - IMPORTANTE: implemente sua própria lógica de autenticação!
// Esta é apenas uma proteção básica com senha codificada
session_start();
$senha = "159753132"; // Substitua por uma senha forte

// Verificar login ou processar tentativa de login
if (isset($_POST['senha'])) {
    if ($_POST['senha'] === $senha) {
        $_SESSION['logviewer_auth'] = true;
    }
}

// Se não estiver autenticado, mostrar tela de login
if (!isset($_SESSION['logviewer_auth']) || $_SESSION['logviewer_auth'] !== true) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Log Viewer - Login</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
            .login-container { max-width: 400px; margin: 100px auto; background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { margin-top: 0; color: #333; }
            input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
            button { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #45a049; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>Log Viewer</h1>
            <form method="post">
                <input type="password" name="senha" placeholder="Digite a senha" required>
                <button type="submit">Acessar</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Autenticado - código do visualizador de logs
$logDir = __DIR__ . '/logs/';
$logFiles = glob($logDir . '*.log'); // Isso vai capturar todos os arquivos .log, inclusive os novos
$date = $_GET['date'] ?? date('Y-m-d');
$traceId = $_GET['trace'] ?? '';
$allLogEntries = [];

// Funções auxiliares
function parseLogLine($line, $sourceFile) {
    // Extrai timestamp, trace ID e conteúdo
    if (preg_match('/\[([\d-]+)\s([\d:]+)\]\s+\[(.*?)\]/', $line, $matches)) {
        return [
            'date' => $matches[1],
            'time' => $matches[2],
            'timestamp' => strtotime($matches[1] . ' ' . $matches[2]),
            'traceId' => $matches[3],
            'content' => $line,
            'sourceFile' => $sourceFile // Armazena o arquivo fonte
        ];
    }
    return null;
}

function formatLogLine($entry) {
    $line = htmlspecialchars($entry['content']);
    $sourceFile = basename($entry['sourceFile']);
    $fileColor = getFileColor($sourceFile);
    
    // Tag com nome do arquivo
    $fileTag = "<span class=\"file-tag\" style=\"background-color:{$fileColor}\">{$sourceFile}</span>";
    
    // Destacar o trace ID
    $traceId = htmlspecialchars($entry['traceId']);
    $line = str_replace(
        "[$traceId]", 
        "<span class=\"trace-id\">[{$traceId}]</span>", 
        $line
    );
    
    // Destacar erros em vermelho
    $lineClass = "log-line";
    if (stripos($line, '[erro]') !== false || 
        stripos($line, 'error') !== false || 
        stripos($line, 'exceção') !== false ||
        stripos($line, 'exception') !== false) {
        $lineClass .= " error";
    }
    
    return "<div class=\"{$lineClass}\">{$fileTag} {$line}</div>";
}

// Gera cores consistentes para cada arquivo de log
function getFileColor($filename) {
    $colors = [
        '#3498db', '#2ecc71', '#e74c3c', '#9b59b6', '#f39c12', 
        '#1abc9c', '#d35400', '#34495e', '#16a085', '#27ae60',
        '#8e44ad', '#f1c40f', '#e67e22', '#c0392b', '#7f8c8d'
    ];
    
    // Usar o nome do arquivo para gerar um índice consistente
    $hash = crc32($filename);
    $index = abs($hash) % count($colors);
    
    return $colors[$index];
}

// Processa todos os arquivos de log
foreach ($logFiles as $logFile) {
    $content = file_get_contents($logFile);
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $parsed = parseLogLine($line, $logFile);
        if ($parsed) {
            // Filtrar por data
            if ($date && $parsed['date'] !== $date) {
                continue;
            }
            
            // Filtrar por trace ID
            if ($traceId && $parsed['traceId'] !== $traceId) {
                continue;
            }
            
            // Adicionar entrada à lista
            $allLogEntries[] = $parsed;
        }
    }
}

// Ordenar entradas por timestamp
usort($allLogEntries, function($a, $b) {
    // Primeiro compara por timestamp
    $timestampCompare = $a['timestamp'] <=> $b['timestamp'];
    if ($timestampCompare !== 0) {
        return $timestampCompare;
    }
    
    // Se o timestamp for igual, ordena pelo nome do arquivo
    return strcmp($a['sourceFile'], $b['sourceFile']);
});

// Obter lista de traces únicos para o filtro dropdown
$uniqueTraces = [];
$uniqueDates = [];

foreach ($logFiles as $logFile) {
    $content = file_get_contents($logFile);
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        $parsed = parseLogLine($line, $logFile);
        if ($parsed) {
            if (!isset($uniqueDates[$parsed['date']])) {
                $uniqueDates[$parsed['date']] = true;
            }
            
            if (!isset($uniqueTraces[$parsed['traceId']])) {
                $uniqueTraces[$parsed['traceId']] = true;
            }
        }
    }
}

// Ordenar datas e traces
$uniqueDates = array_keys($uniqueDates);
rsort($uniqueDates); // Mais recentes primeiro
$uniqueTraces = array_keys($uniqueTraces);
sort($uniqueTraces);

// Para debugging, conta quantos arquivos foram processados
$fileCount = count($logFiles);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Log Viewer - APIs KW24</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 0.5em; }
        h2 { font-size: 1.2em; margin: 1em 0; }
        .filters { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .filters form { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        select, input, button { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        button { background: #4CAF50; color: white; border: none; cursor: pointer; padding: 8px 15px; }
        button:hover { background: #45a049; }
        .clear-button { background: #F44336; }
        .clear-button:hover { background: #D32F2F; }
        .log-container { background: white; padding: 15px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .log-line { font-family: monospace; white-space: pre-wrap; padding: 6px 10px; border-bottom: 1px solid #f0f0f0; }
        .log-line:hover { background: #f9f9f9; }
        .error { color: #D32F2F; font-weight: 500; }
        .file-tag { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; margin-right: 5px; color: white; font-weight: bold; }
        .trace-id { font-weight: bold; background: #FFF59D; padding: 0 2px; }
        .footer { margin-top: 20px; text-align: center; font-size: 0.8em; color: #666; }
        .stats { margin-bottom: 15px; font-size: 0.9em; color: #666; }
        .empty { padding: 20px; text-align: center; color: #666; }
        
        /* Responsividade para telas menores */
        @media (max-width: 768px) {
            .filters form { flex-direction: column; align-items: stretch; }
            .filters form > * { margin-bottom: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Log Viewer - APIs KW24</h1>
        
        <div class="filters">
            <form method="get">
                <label for="date">Data:</label>
                <select name="date" id="date">
                    <option value="">Todas as datas</option>
                    <?php foreach ($uniqueDates as $d): ?>
                        <option value="<?= $d ?>" <?= $d === $date ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label for="trace">TRACE ID:</label>
                <select name="trace" id="trace">
                    <option value="">Todos os traces</option>
                    <?php foreach ($uniqueTraces as $t): ?>
                        <option value="<?= $t ?>" <?= $t === $traceId ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit">Filtrar</button>
                <button type="button" class="clear-button" onclick="window.location.href='?'">Limpar Filtros</button>
            </form>
        </div>
        
        <div class="stats">
            <p>
                <?= $fileCount ?> arquivo(s) de log processado(s). 
                <?php 
                echo count($allLogEntries) . ' registros encontrados';
                if ($date) echo ' para a data ' . $date;
                if ($traceId) echo ' com TRACE ID ' . $traceId;
                ?>
            </p>
        </div>
        
        <div class="log-container">
            <?php if (empty($allLogEntries)): ?>
                <div class="empty">
                    <p>Nenhum registro de log encontrado com os filtros atuais.</p>
                </div>
            <?php else: ?>
                <div id="log-entries">
                    <?php 
                    $lastDate = null;
                    foreach ($allLogEntries as $entry): 
                        // Adiciona separador de data quando muda o dia
                        if ($lastDate !== $entry['date']) {
                            echo '<h2>' . $entry['date'] . '</h2>';
                            $lastDate = $entry['date'];
                        }
                        echo formatLogLine($entry);
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Log Viewer v1.0 | APIs KW24 - <?= date('Y') ?></p>
        </div>
    </div>
    
    <script>
    // Script para atualizar a página automaticamente quando mudam os filtros
    document.getElementById('date').addEventListener('change', function() {
        this.form.submit();
    });
    
    document.getElementById('trace').addEventListener('change', function() {
        this.form.submit();
    });
    </script>
</body>
</html>