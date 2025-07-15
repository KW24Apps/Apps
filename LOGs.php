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
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary-dark: #033140;
                --primary: #086B8D;
                --primary-light: #0DC2FF;
                --accent: #26FF93;
                --white: #F4FCFF;
                --dark: #061920;
                --gray-light: #f5f5f7;
            }
            
            body { 
                font-family: 'Inter', sans-serif; 
                margin: 0; 
                padding: 0; 
                background: var(--gray-light);
                color: #333;
            }
            
            .login-container { 
                max-width: 400px; 
                margin: 100px auto; 
                background: white; 
                padding: 30px; 
                border-radius: 8px; 
                box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            }
            
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .login-header img {
                max-width: 180px;
                margin-bottom: 20px;
            }
            
            h1 { 
                font-family: 'Rubik', sans-serif;
                margin-top: 0; 
                color: var(--primary-dark); 
                font-weight: 600;
            }
            
            input[type="password"] { 
                width: 100%; 
                padding: 12px; 
                margin: 15px 0; 
                box-sizing: border-box; 
                border: 1px solid #ddd; 
                border-radius: 6px;
                font-family: 'Inter', sans-serif;
                font-size: 15px;
            }
            
            button { 
                background: var(--primary); 
                color: white; 
                padding: 12px 18px; 
                border: none; 
                border-radius: 6px; 
                cursor: pointer; 
                width: 100%;
                font-family: 'Inter', sans-serif;
                font-size: 15px;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            
            button:hover { 
                background: var(--primary-light); 
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <img src="https://gabriel.kw24.com.br/6_LOGO%20KW24.png" alt="KW24 Logo">
                <h1>Log Viewer</h1>
            </div>
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

// Verifica se estamos no modo de download
$downloadMode = isset($_GET['mode']) && $_GET['mode'] === 'download';

// Para debugging, conta quantos arquivos foram processados
$fileCount = count($logFiles);

// Se estiver no modo de download, exibe a lista de arquivos para download
if ($downloadMode) {
    $fileList = [];
    foreach ($logFiles as $logFile) {
        $fileList[] = [
            'name' => basename($logFile),
            'size' => filesize($logFile),
            'modified' => date('Y-m-d H:i:s', filemtime($logFile)),
            'path' => $logFile
        ];
    }
    // Ordenar por data de modificação (mais recente primeiro)
    usort($fileList, function($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
    });
}

// Função para extrair a parte final do TRACE ID (após o último underscore)
function getShortTraceId($traceId) {
    if (strpos($traceId, '_') !== false) {
        $parts = explode('_', $traceId);
        return end($parts);
    }
    return $traceId;
}

// Função para formatar o tamanho do arquivo
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes > 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Função para formatar cada entrada de log na tabela
function formatLogTableRow($entry) {
    $sourceFile = basename($entry['sourceFile']);
    $shortTrace = getShortTraceId($entry['traceId']);
    $fileColor = getFileColor($sourceFile);
    
    // Extrair o conteúdo principal do log (removendo timestamp e trace)
    $content = $entry['content'];
    // Tenta remover a parte inicial padrão do log
    $content = preg_replace('/\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\]\s+\[[^\]]+\]/', '', $content);
    $content = trim($content);
    
    $rowClass = "";
    if (stripos($content, '[erro]') !== false || 
        stripos($content, 'error') !== false || 
        stripos($content, 'exceção') !== false ||
        stripos($content, 'exception') !== false) {
        $rowClass = "error-row";
    }
    
    return "
    <tr class=\"{$rowClass}\">
        <td><span class=\"file-tag\" style=\"background-color:{$fileColor}\">{$sourceFile}</span></td>
        <td>{$entry['date']} {$entry['time']}</td>
        <td><span class=\"trace-id\">{$shortTrace}</span></td>
        <td>{$content}</td>
    </tr>";
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Log Viewer - APIs KW24</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #033140;
            --primary: #086B8D;
            --primary-light: #0DC2FF;
            --accent: #26FF93;
            --white: #F4FCFF;
            --dark: #061920;
            --gray-light: #f5f5f7;
            --gray-border: #e0e0e0;
            --danger: #e74c3c;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            padding: 0; 
            background: var(--gray-light); 
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 220px;
            background: var(--primary-dark);
            color: white;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .logo-container {
            padding: 20px;
            text-align: center;
            background: rgba(0,0,0,0.2);
        }
        
        .logo-container img {
            max-width: 160px;
            height: auto;
        }
        
        .sidebar-menu {
            padding: 20px 0;
            flex-grow: 1;
        }
        
        .sidebar-menu a {
            display: block;
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            text-decoration: none;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu a.active {
            border-left: 4px solid var(--accent);
            font-weight: 500;
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .top-bar {
            background: white;
            border-bottom: 1px solid var(--gray-border);
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .page-title {
            font-family: 'Rubik', sans-serif;
            color: var(--primary-dark);
            font-size: 1.8rem;
            margin: 0;
        }
        
        .content-area {
            padding: 20px 30px;
            flex-grow: 1;
            overflow: auto;
        }
        
        .filters {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .filter-group label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stats {
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #666;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        select, input {
            padding: 10px;
            border: 1px solid var(--gray-border);
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            width: 100%;
        }
        
        button {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 15px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        button:hover {
            background: var(--primary-light);
        }
        
        .clear-button {
            background: var(--danger);
        }
        
        .clear-button:hover {
            background: #c0392b;
        }
        
        .logs-table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        table.logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table.logs-table th {
            background: var(--primary);
            color: white;
            text-align: left;
            padding: 12px 15px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        table.logs-table th:first-child {
            border-top-left-radius: 8px;
        }
        
        table.logs-table th:last-child {
            border-top-right-radius: 8px;
        }
        
        table.logs-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray-border);
            font-size: 0.9rem;
            vertical-align: top;
        }
        
        table.logs-table tr:hover {
            background: #f9f9f9;
        }
        
        table.logs-table .error-row {
            background: rgba(231, 76, 60, 0.05);
        }
        
        table.logs-table .error-row:hover {
            background: rgba(231, 76, 60, 0.1);
        }
        
        .file-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            color: white;
            font-weight: 500;
        }
        
        .trace-id {
            background: rgba(13, 194, 255, 0.1);
            color: var(--primary);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .empty {
            padding: 40px;
            text-align: center;
            color: #666;
            font-size: 1.1rem;
        }
        
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 0.8rem;
            color: #777;
            padding-bottom: 20px;
        }
        
        /* Download page styles */
        .file-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray-border);
            transition: all 0.2s;
        }
        
        .file-item:hover {
            background: #f9f9f9;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-name {
            flex-grow: 1;
            font-weight: 500;
            color: var(--primary-dark);
        }
        
        .file-meta {
            display: flex;
            gap: 15px;
            color: #777;
            font-size: 0.85rem;
        }
        
        .file-size, .file-date {
            min-width: 120px;
            text-align: right;
        }
        
        .download-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        
        .download-btn:hover {
            background: var(--primary-light);
        }
        
        /* Responsividade para telas menores */
        @media (max-width: 992px) {
            .sidebar {
                width: 180px;
            }
            
            .sidebar-menu a {
                padding: 10px 15px;
                font-size: 14px;
            }
            
            .top-bar {
                padding: 10px 20px;
            }
            
            .content-area {
                padding: 15px 20px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .logo-container {
                padding: 10px;
            }
            
            .logo-container img {
                max-width: 100px;
            }
            
            .sidebar-menu {
                display: flex;
                padding: 0;
            }
            
            .sidebar-menu a {
                padding: 15px 10px;
                font-size: 0.8rem;
                text-align: center;
                border-left: none;
                border-bottom: 4px solid transparent;
            }
            
            .sidebar-menu a.active {
                border-left: none;
                border-bottom: 4px solid var(--accent);
            }
            
            .sidebar-menu a i {
                margin-right: 0;
                margin-bottom: 5px;
                display: block;
                width: auto;
            }
            
            .filters {
                flex-direction: column;
            }
            
            table.logs-table {
                display: block;
                overflow-x: auto;
            }
            
            table.logs-table th:nth-child(3),
            table.logs-table td:nth-child(3) {
                min-width: 100px;
            }
            
            table.logs-table th:nth-child(4),
            table.logs-table td:nth-child(4) {
                min-width: 300px;
            }
        }
    </style>
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Menu Lateral -->
    <div class="sidebar">
        <div class="logo-container">
            <img src="https://gabriel.kw24.com.br/6_LOGO%20KW24.png" alt="KW24 Logo">
        </div>
        <div class="sidebar-menu">
            <a href="?mode=filter" class="<?= (!$downloadMode) ? 'active' : '' ?>">
                <i class="fas fa-filter"></i> Filtro
            </a>
            <a href="?mode=download" class="<?= ($downloadMode) ? 'active' : '' ?>">
                <i class="fas fa-download"></i> Download
            </a>
        </div>
    </div>
    
    <!-- Conteúdo Principal -->
    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title">Log Viewer</h1>
        </div>
        
        <div class="content-area">
            <?php if ($downloadMode): ?>
                <!-- Modo de Download -->
                <div class="stats">
                    <p><?= $fileCount ?> arquivo(s) disponível(is) para download</p>
                </div>
                
                <div class="logs-table-container">
                    <?php if (empty($fileList)): ?>
                        <div class="empty">
                            <p>Nenhum arquivo de log encontrado.</p>
                        </div>
                    <?php else: ?>
                        <ul class="file-list">
                            <?php foreach ($fileList as $file): ?>
                                <li class="file-item">
                                    <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                                    <div class="file-meta">
                                        <span class="file-size"><?= formatFileSize($file['size']) ?></span>
                                        <span class="file-date"><?= $file['modified'] ?></span>
                                        <a href="download.php?file=<?= urlencode($file['name']) ?>" class="download-btn">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Modo de Filtro -->
                <div class="filters">
                    <div class="filter-group">
                        <label for="date">Data:</label>
                        <select name="date" id="date">
                            <option value="">Todas as datas</option>
                            <?php foreach ($uniqueDates as $d): ?>
                                <option value="<?= $d ?>" <?= $d === $date ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="trace">TRACE ID:</label>
                        <select name="trace" id="trace">
                            <option value="">Todos os traces</option>
                            <?php foreach ($uniqueTraces as $t): ?>
                                <option value="<?= $t ?>" <?= $t === $traceId ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                
                <div class="logs-table-container">
                    <?php if (empty($allLogEntries)): ?>
                        <div class="empty">
                            <p>Nenhum registro de log encontrado com os filtros atuais.</p>
                        </div>
                    <?php else: ?>
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th width="15%">Origem</th>
                                    <th width="15%">Data</th>
                                    <th width="15%">Trace</th>
                                    <th>Log</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ($allLogEntries as $entry): 
                                    echo formatLogTableRow($entry);
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="footer">
                <p>Log Viewer v1.0 | APIs KW24 - <?= date('Y') ?></p>
            </div>
        </div>
    </div>
    
    <script>
    // Script para atualizar a página automaticamente quando mudam os filtros
    document.getElementById('date').addEventListener('change', function() {
        const trace = document.getElementById('trace').value;
        window.location.href = `?mode=filter&date=${this.value}${trace ? '&trace=' + trace : ''}`;
    });
    
    document.getElementById('trace').addEventListener('change', function() {
        const date = document.getElementById('date').value;
        window.location.href = `?mode=filter&trace=${this.value}${date ? '&date=' + date : ''}`;
    });
    </script>
</body>
</html>