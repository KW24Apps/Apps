<?php
// setup.php - Configuração inicial do sistema

// Verifica se o sistema já foi configurado
$configFile = __DIR__ . '/config.php';
$configSecureFile = __DIR__ . '/config_secure.php';
$configured = file_exists($configFile) && file_exists($configSecureFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$configured) {
    $webhook = $_POST['webhook'] ?? '';
    $funis = $_POST['funis'] ?? '';
    
    if ($webhook) {
        // Cria arquivo de configuração segura (webhook)
        $secureContent = "<?php\n";
        $secureContent .= "// config_secure.php - Configurações seguras (não deve ser commitado no git)\n\n";
        $secureContent .= "\$ambiente = getenv('APP_ENV') ?: 'local';\n\n";
        $secureContent .= "\$webhook_config = [];\n\n";
        $secureContent .= "if (\$ambiente === 'local') {\n";
        $secureContent .= "    // Configurações locais - webhook de desenvolvimento/teste\n";
        $secureContent .= "    \$webhook_config = [\n";
        $secureContent .= "        'bitrix_webhook' => 'https://seubitrix.bitrix24.com.br/rest/USER/WEBHOOK_LOCAL/',\n";
        $secureContent .= "        'ambiente' => 'local'\n";
        $secureContent .= "    ];\n";
        $secureContent .= "} else {\n";
        $secureContent .= "    // Configurações de produção - webhook real\n";
        $secureContent .= "    \$webhook_config = [\n";
        $secureContent .= "        'bitrix_webhook' => '" . addslashes($webhook) . "',\n";
        $secureContent .= "        'ambiente' => 'producao'\n";
        $secureContent .= "    ];\n";
        $secureContent .= "}\n\n";
        $secureContent .= "return \$webhook_config;";
        
        file_put_contents($configSecureFile, $secureContent);
        
        // Cria arquivo de configuração principal (sem webhook)
        $configContent = "<?php\n";
        $configContent .= "// config.php - Configurações do formulário de importação\n\n";
        $configContent .= "// Carrega configurações seguras (webhook)\n";
        $configContent .= "\$webhook_config = require_once __DIR__ . '/config_secure.php';\n\n";
        $configContent .= "// Configurações do Bitrix\n";
        $configContent .= "define('BITRIX_WEBHOOK', \$webhook_config['bitrix_webhook']);\n\n";
        $configContent .= "// Funis disponíveis\n";
        $configContent .= "\$FUNIS_DISPONIVEIS = [\n";
        $configContent .= "    '2' => 'Negócios',\n";
        $configContent .= "    '84' => 'Postagens e Avisos',\n";
        if ($funis) {
            $funisArray = explode("\n", $funis);
            foreach ($funisArray as $funil) {
                $funil = trim($funil);
                if ($funil && strpos($funil, '=>') !== false) {
                    $configContent .= "    " . $funil . ",\n";
                }
            }
        }
        $configContent .= "];\n\n";
        $configContent .= "// Configurações de upload\n";
        $configContent .= "define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB\n";
        $configContent .= "define('ALLOWED_EXTENSIONS', ['.csv', '.xlsx']);\n\n";
        $configContent .= "// Configurações de processamento\n";
        $configContent .= "define('BATCH_SIZE', 25); // Tamanho do lote para processamento\n\n";
        $configContent .= "return [\n";
        $configContent .= "    'bitrix_webhook' => BITRIX_WEBHOOK,\n";
        $configContent .= "    'funis' => \$FUNIS_DISPONIVEIS,\n";
        $configContent .= "    'upload' => [\n";
        $configContent .= "        'max_size' => UPLOAD_MAX_SIZE,\n";
        $configContent .= "        'extensions' => ALLOWED_EXTENSIONS\n";
        $configContent .= "    ],\n";
        $configContent .= "    'batch_size' => BATCH_SIZE\n";
        $configContent .= "];";
        
        file_put_contents($configFile, $configContent);
        
        // Cria diretórios necessários
        $dirs = ['uploads', 'logs'];
        foreach ($dirs as $dir) {
            if (!is_dir(__DIR__ . '/' . $dir)) {
                mkdir(__DIR__ . '/' . $dir, 0777, true);
            }
        }
        
        $configured = true;
        $message = "✅ Sistema configurado com sucesso!";
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Sistema de Importação KW24</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .setup-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        .setup-title {
            color: #3a4a5d;
            font-size: 2rem;
            margin-bottom: 30px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        button {
            background: #007bff;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
        }
        button:hover {
            background: #0056b3;
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .configured {
            text-align: center;
            color: #666;
        }
        .nav-link {
            display: inline-block;
            background: #28a745;
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            margin: 10px;
            font-weight: 600;
        }
        .nav-link:hover {
            background: #218838;
        }
        .help {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #17a2b8;
            margin-top: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1 class="setup-title">🚀 Setup Inicial</h1>
        
        <?php if (isset($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($configured): ?>
            <div class="configured">
                <h3>✅ Sistema Configurado!</h3>
                <p>O sistema está pronto para uso.</p>
                
                <a href="index.php" class="nav-link">🏠 Página Inicial</a>
                <a href="importacao.php" class="nav-link">📤 Iniciar Importação</a>
                <a href="demo.php" class="nav-link">🧪 Testes do Sistema</a>
                
                <div class="help">
                    <strong>📋 Próximos Passos:</strong><br>
                    1. Teste a conexão com Bitrix em "Testes do Sistema"<br>
                    2. Faça uma importação de teste com o arquivo exemplo.csv<br>
                    3. Verifique se os jobs estão sendo processados corretamente
                </div>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label for="webhook">🔗 Webhook do Bitrix24 *</label>
                    <input type="text" id="webhook" name="webhook" required 
                           placeholder="https://seudominio.bitrix24.com.br/rest/USER_ID/WEBHOOK_CODE/">
                </div>
                
                <div class="form-group">
                    <label for="funis">📋 Funis Adicionais (opcional)</label>
                    <textarea id="funis" name="funis" 
                              placeholder="'100' => 'Nome do Funil'&#10;'101' => 'Outro Funil'"></textarea>
                </div>
                
                <button type="submit">💾 Salvar Configuração</button>
            </form>
            
            <div class="help">
                <strong>🔧 Como obter o Webhook:</strong><br>
                1. Acesse seu Bitrix24<br>
                2. Vá em Aplicações > Webhooks<br>
                3. Crie um webhook de entrada<br>
                4. Copie a URL completa aqui
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
