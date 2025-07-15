<?php
// viewer_logs.php - Coloque este arquivo na raiz do seu projeto ou em uma pasta admin/logs

// Verificação de autenticação com usuário e senha
session_start();
$usuario_correto = "KW24";
$senha_correta = "159Qwaszx753";

// Processar logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Verificar login ou processar tentativa de login
$loginError = false;
if (isset($_POST['usuario']) && isset($_POST['senha'])) {
    if (strtolower($_POST['usuario']) === strtolower($usuario_correto) && $_POST['senha'] === $senha_correta) {
        $_SESSION['logviewer_auth'] = true;
        $_SESSION['logviewer_user'] = $usuario_correto; // Sempre usar a versão correta do nome
    } else {
        $loginError = true;
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
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
                color: #333;
                overflow: hidden;
            }
            
            .login-container { 
                width: 360px;
                background: rgba(255, 255, 255, 0.75);
                padding: 40px 30px;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.2);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .alert {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                padding: 12px 24px;
                background: rgba(231, 76, 60, 0.9);
                color: white;
                border-radius: 6px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 1000;
                font-weight: 500;
                display: flex;
                align-items: center;
                animation: fadeIn 0.3s ease-out, fadeOut 0.5s ease-in 9.5s forwards;
            }
            
            .alert i {
                margin-right: 8px;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translate(-50%, -20px); }
                to { opacity: 1; transform: translate(-50%, 0); }
            }
            
            @keyframes fadeOut {
                from { opacity: 1; transform: translate(-50%, 0); }
                to { opacity: 0; transform: translate(-50%, -20px); }
            }
            
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .login-header img {
                max-width: 140px;
                margin-bottom: 20px;
            }
            
            h1 { 
                font-family: 'Rubik', sans-serif;
                margin-top: 0;
                margin-bottom: 30px;
                color: var(--primary-dark); 
                font-weight: 600;
                font-size: 1.6rem;
                text-align: center;
            }
            
            .input-group {
                position: relative;
                margin-bottom: 20px;
            }
            
            .input-icon {
                position: absolute;
                left: 12px;
                top: 12px;
                color: #777;
                width: 20px;
                text-align: center;
            }
            
            input[type="text"],
            input[type="password"] { 
                width: 100%; 
                padding: 12px 12px 12px 40px; 
                box-sizing: border-box; 
                border: 1px solid #ddd; 
                border-radius: 6px;
                font-family: 'Inter', sans-serif;
                font-size: 15px;
                background-color: rgba(255, 255, 255, 0.8);
            }
            
            button { 
                background: var(--primary-dark); 
                color: white; 
                padding: 12px 18px; 
                border: none; 
                border-radius: 6px; 
                cursor: pointer; 
                width: 100%;
                font-family: 'Inter', sans-serif;
                font-size: 16px;
                font-weight: 500;
                letter-spacing: 1px;
                text-transform: uppercase;
                transition: all 0.3s ease;
                margin-top: 10px;
            }
            
            button:hover { 
                background: var(--primary); 
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            
            .remember-me {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
                font-size: 14px;
                color: #555;
            }
            
            .remember-me input {
                margin-right: 8px;
            }
        </style>
    </head>
    <body class="<?= $sidebarState === 'collapsed' ? 'sidebar-collapsed' : '' ?>">
        <?php if ($loginError): ?>
        <div class="alert">
            <i class="fas fa-exclamation-circle"></i> Usuário ou senha inválidos
        </div>
        <?php endif; ?>
        <div class="login-container">
            <div class="login-header">
                <img src="https://gabriel.kw24.com.br/06_KW24_TAGLINE_%20POSITIVO.png" alt="KW24 Logo">
            </div>
            <h1>Log Viewer</h1>
            <form method="post">
                <div class="input-group">
                    <span class="input-icon">👤</span>
                    <input type="text" name="usuario" placeholder="Email ID" required>
                </div>
                <div class="input-group">
                    <span class="input-icon">🔒</span>
                    <input type="password" name="senha" placeholder="Password" required>
                </div>
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                <button type="submit">LOGIN</button>
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

// Verifica se há um estado da sidebar na URL
$sidebarState = $_GET['sidebar'] ?? '';

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
            overflow-x: hidden; /* Previne rolagem horizontal */
            position: relative;
        }
        
        .sidebar {
            width: 220px;
            background: var(--primary-dark);
            color: white;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: width 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
        }
        
        .sidebar.collapsed {
            width: 60px;
        }
        
        .sidebar-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        
        .toggle-btn {
            position: fixed;
            top: 15px;
            left: 200px;
            width: 36px;
            height: 36px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            z-index: 1000;
            transition: all 0.3s ease;
            font-size: 18px;
        }
        
        .sidebar.collapsed .toggle-btn {
            left: 40px;
        }
        
        .sidebar.collapsed .toggle-btn i {
            transform: rotate(180deg);
        }
        
        .toggle-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .logo-container {
            padding: 15px 10px;
            text-align: center;
            background: rgba(0,0,0,0.2);
            overflow: hidden;
            transition: height 0.3s ease, padding 0.3s ease, opacity 0.3s ease;
            margin-bottom: 10px;
        }
        
        .logo-container img {
            max-width: 140px;
            height: auto;
            transition: max-width 0.3s ease, opacity 0.3s ease;
        }
        
        .sidebar.collapsed .logo-container {
            height: 0;
            padding: 0;
            opacity: 0;
        }
        
        .sidebar.collapsed .logo-container img {
            max-width: 0;
            opacity: 0;
        }
        
        .sidebar-menu {
            padding: 20px 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar.collapsed .sidebar-menu {
            align-items: center;
            padding: 60px 0 20px 0; /* Aumentado o padding-top para descer os itens de menu */
        }
        
        .toggle-menu {
            position: absolute;
            top: 15px;
            right: 15px;
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.7);
            font-size: 16px;
            cursor: pointer;
            padding: 5px;
            z-index: 10;
            transition: transform 0.3s ease;
        }
        
        .sidebar.collapsed .toggle-menu {
            transform: rotate(180deg);
            right: 5px;
        }
        
        .user-panel {
            padding: 15px 20px;
            background: rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.9rem;
            overflow: hidden;
            transition: padding 0.3s ease, height 0.3s ease;
            margin-top: auto;
        }
        
        .sidebar.collapsed .user-panel {
            padding: 10px 5px;
            justify-content: center;
            height: auto;
        }
        
        .user-info {
            color: rgba(255,255,255,0.8);
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            transition: opacity 0.3s ease, width 0.3s ease, margin 0.3s ease;
            margin-right: 10px;
        }
        
        .sidebar.collapsed .user-info {
            width: 0;
            opacity: 0;
            margin-right: 0;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            border-radius: 4px;
            width: auto;
            padding: 5px 10px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .sidebar.collapsed .logout-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            margin: 10px auto;
            font-size: 18px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.15);
        }
        
        .logout-btn span {
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .logout-btn span {
            display: none;
        }
        
        .d-none {
            display: none;
        }
        
        .sidebar.collapsed .d-none.d-sidebar-collapsed-block {
            display: block;
        }
        
        .sidebar.collapsed .logout-btn i {
            display: inline-block;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            text-decoration: none;
            font-size: 15px;
            transition: all 0.3s ease;
            overflow: hidden;
            white-space: nowrap;
            position: relative;
            width: 100%;
        }
        
        .sidebar.collapsed .sidebar-menu a {
            justify-content: center;
            padding: 15px 5px;
            border-left: none;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu a.active {
            border-left: 4px solid var(--accent);
            font-weight: 500;
            padding-left: 16px;
        }
        
        .sidebar.collapsed .sidebar-menu a.active {
            border-left: none;
            border-right: 4px solid var(--accent);
            padding-left: 5px;
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 16px;
            transition: margin 0.3s ease, font-size 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-menu a i {
            margin-right: 0;
            font-size: 18px;
            width: 100%;
            text-align: center;
        }
        
        .sidebar.collapsed .sidebar-menu a span {
            opacity: 0;
            width: 0;
            height: 0;
            overflow: hidden;
        }
        
        .menu-tooltip {
            position: absolute;
            left: 70px;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
            white-space: nowrap;
            box-shadow: 0 3px 6px rgba(0,0,0,0.3);
            z-index: 1000;
        }
        
        .sidebar.collapsed .sidebar-menu a:hover .menu-tooltip {
            opacity: 1;
        }
        
        .menu-tooltip {
            position: absolute;
            left: 70px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 1000;
        }
        
        .sidebar.collapsed .sidebar-menu a:hover .menu-tooltip {
            opacity: 1;
        }
        
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin-left: 220px;
            transition: margin-left 0.3s ease;
            padding: 0;
            width: calc(100% - 220px);
        }
        
        .main-content.expanded {
            margin-left: 60px;
            width: calc(100% - 60px);
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
            padding: 16px 20px;
            margin-bottom: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--gray-border);
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .filter-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #777;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            padding: 10px 12px;
            border: 1px solid var(--gray-border);
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            width: 100%;
            background-color: white;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 30px;
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
            border: none;
        }
        
        table.logs-table th {
            background: var(--primary-dark);
            color: white;
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        table.logs-table th:first-child {
            border-top-left-radius: 4px;
        }
        
        table.logs-table th:last-child {
            border-top-right-radius: 4px;
        }
        
        table.logs-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray-border);
            font-size: 0.9rem;
            vertical-align: top;
        }
        
        table.logs-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        table.logs-table tr:hover {
            background: #f0f7fa;
        }
        
        table.logs-table .error-row {
            background: rgba(231, 76, 60, 0.05);
        }
        
        table.logs-table .error-row:hover {
            background: rgba(231, 76, 60, 0.1);
        }
        
        .file-tag {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            color: white;
            font-weight: 600;
            text-transform: lowercase;
        }
        
        .trace-id {
            color: var(--primary);
            font-size: 0.9rem;
            font-weight: 600;
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
                height: auto;
                min-height: 60px;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
            }
            
            .sidebar.collapsed {
                width: 100%;
                /* Não muda a largura no mobile */
            }
            
            .toggle-btn {
                display: none;
                /* Esconde o botão toggle no mobile */
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
            
            .sidebar-menu a span {
                display: none;
                /* Esconde o texto no mobile */
            }
            
            .menu-tooltip {
                display: none;
                /* Esconde os tooltips no mobile */
            }
            
            .user-panel {
                padding: 10px;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                margin-top: 60px; /* Espaço para o menu fixo no topo */
                /* Ignora a margem no mobile */
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
<body class="<?= $sidebarState === 'collapsed' ? 'sidebar-collapsed' : '' ?>">
    <!-- Menu Lateral -->
    <div class="sidebar <?= $sidebarState === 'collapsed' ? 'collapsed' : '' ?>">
        <button id="sidebarToggle" class="toggle-btn" title="Expandir/Recolher Menu">
            <i class="fas fa-angle-left"></i>
        </button>
        <div class="logo-container">
            <img src="https://gabriel.kw24.com.br/02_KW24_HORIZONTAL_NEGATIVO.png" alt="KW24 Logo">
        </div>
        <div class="sidebar-content">
            <div class="sidebar-menu">            <a href="#" data-mode="filter" class="sidebar-link <?= (!$downloadMode) ? 'active' : '' ?>" title="Filtro">
                <i class="fas fa-filter"></i> <span>Filtro</span>
                <div class="menu-tooltip">Filtro</div>
            </a>
            <a href="#" data-mode="download" class="sidebar-link <?= ($downloadMode) ? 'active' : '' ?>" title="Download">
                <i class="fas fa-download"></i> <span>Download</span>
                <div class="menu-tooltip">Download</div>
            </a>
            </div>
        </div>
        <div class="user-panel">
            <div class="user-info"><?= htmlspecialchars($_SESSION['logviewer_user'] ?? 'Usuário') ?></div>
            <form method="post" action="?logout=1">
                <button type="submit" class="logout-btn" title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Conteúdo Principal -->
    <div class="main-content <?= $sidebarState === 'collapsed' ? 'expanded' : '' ?>">
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
                        if ($date) echo ' para a data <strong>' . $date . '</strong>';
                        if ($traceId) echo ' com TRACE ID <strong>' . $traceId . '</strong>';
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
                                    <th width="10%">Trace</th>
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
    document.getElementById('date')?.addEventListener('change', function() {
        const trace = document.getElementById('trace').value;
        const sidebarState = localStorage.getItem('sidebarState') || '';
        window.location.href = `?mode=filter&date=${this.value}${trace ? '&trace=' + trace : ''}${sidebarState ? '&sidebar=' + sidebarState : ''}`;
    });
    
    document.getElementById('trace')?.addEventListener('change', function() {
        const date = document.getElementById('date').value;
        const sidebarState = localStorage.getItem('sidebarState') || '';
        window.location.href = `?mode=filter&trace=${this.value}${date ? '&date=' + date : ''}${sidebarState ? '&sidebar=' + sidebarState : ''}`;
    });
    
    // Manipular cliques nos links da barra lateral
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const mode = this.getAttribute('data-mode');
            const sidebarState = localStorage.getItem('sidebarState') || '';
            
            // Construir os parâmetros da URL dependendo do modo
            let params = `mode=${mode}`;
            
            if (mode === 'filter') {
                const date = document.getElementById('date')?.value || '';
                const trace = document.getElementById('trace')?.value || '';
                if (date) params += `&date=${date}`;
                if (trace) params += `&trace=${trace}`;
            }
            
            if (sidebarState) params += `&sidebar=${sidebarState}`;
            
            window.location.href = `?${params}`;
        });
    });
    
    // Função para controlar a barra lateral
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const mainContent = document.querySelector('.main-content');
        
        // Função para aplicar o estado correto da barra lateral
        function applySidebarState(state) {
            if (state === 'collapsed') {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                document.body.classList.add('sidebar-collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                document.body.classList.remove('sidebar-collapsed');
            }
        }
        
        // Verificar se há uma preferência na URL ou no localStorage
        const urlParams = new URLSearchParams(window.location.search);
        const urlSidebarState = urlParams.get('sidebar');
        const localSidebarState = localStorage.getItem('sidebarState');
        
        // Priorizar o estado da URL, depois usar o localStorage
        if (urlSidebarState) {
            applySidebarState(urlSidebarState);
            localStorage.setItem('sidebarState', urlSidebarState);
        } else if (localSidebarState) {
            applySidebarState(localSidebarState);
        }
        
        // Adicionar evento de clique ao botão de toggle
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            document.body.classList.toggle('sidebar-collapsed');
            
            // Salvar o estado atual no localStorage
            if (sidebar.classList.contains('collapsed')) {
                localStorage.setItem('sidebarState', 'collapsed');
            } else {
                localStorage.setItem('sidebarState', 'expanded');
            }
        });
    });
    </script>
</body>
</html>