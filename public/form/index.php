<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Importação - KW24</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        h1 {
            color: #3a4a5d;
            margin-bottom: 30px;
            font-size: 2rem;
            font-weight: 600;
        }
        .description {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .start-btn {
            display: inline-block;
            background: #007bff;
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: background 0.2s;
        }
        .start-btn:hover {
            background: #0056b3;
        }
        .features {
            margin-top: 30px;
            text-align: left;
        }
        .feature {
            display: flex;
            align-items: center;
            margin: 10px 0;
            color: #555;
        }
        .feature::before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sistema de Importação</h1>
        <div class="description">
            Importe dados de planilhas CSV/Excel diretamente para o Bitrix24 com processamento em segundo plano.
        </div>
        
        <a href="importacao.php<?php echo isset($_GET['cliente']) ? '?cliente=' . urlencode($_GET['cliente']) : ''; ?>" class="start-btn">Iniciar Importação</a>
        
        <div class="features">
            <div class="feature">Sistema de jobs assíncrono</div>
            <div class="feature">Mapeamento automático de campos</div>
            <div class="feature">Processamento em lote (batch)</div>
            <div class="feature">Integração com BitrixDealHelper atualizado</div>
            <div class="feature">Interface moderna e responsiva</div>
        </div>
    </div>
</body>
</html>
