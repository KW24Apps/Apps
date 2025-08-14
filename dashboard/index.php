<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Jobs Bitrix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status { padding: 10px; border-radius: 4px; margin: 10px 0; }
        .ativo { background: #d4edda; color: #155724; }
        .inativo { background: #f8d7da; color: #721c24; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .stat { text-align: center; padding: 20px; background: white; border-radius: 8px; }
        .stat h3 { margin: 0; font-size: 2em; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Dashboard Jobs Bitrix</h1>
        
        <div class="card">
            <h2>Status do CRON</h2>
            <div id="cron-status" class="status">Verificando...</div>
        </div>

        <div class="stats">
            <div class="stat">
                <h3 id="pendente">-</h3>
                <p>Pendente</p>
            </div>
            <div class="stat">
                <h3 id="processando">-</h3>
                <p>Processando</p>
            </div>
            <div class="stat">
                <h3 id="concluido">-</h3>
                <p>Concluído</p>
            </div>
            <div class="stat">
                <h3 id="erro">-</h3>
                <p>Erro</p>
            </div>
        </div>

        <div class="card">
            <h2>Jobs Recentes</h2>
            <div id="jobs-list">Carregando...</div>
        </div>

        <div class="card">
            <button onclick="atualizar()">🔄 Atualizar</button>
            <span style="margin-left: 20px;">Última atualização: <span id="ultima-atualizacao">-</span></span>
        </div>
    </div>

    <script>
        function atualizar() {
            fetch('/dashboard/api.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Atualizar contadores
                        document.getElementById('pendente').textContent = data.contadores.pendente || 0;
                        document.getElementById('processando').textContent = data.contadores.processando || 0;
                        document.getElementById('concluido').textContent = data.contadores.concluido || 0;
                        document.getElementById('erro').textContent = data.contadores.erro || 0;

                        // Status CRON
                        const cronEl = document.getElementById('cron-status');
                        if (data.cron.ativo) {
                            cronEl.className = 'status ativo';
                            cronEl.textContent = '✅ CRON ATIVO - Última execução: ' + (data.cron.ultima_execucao || 'N/A');
                        } else {
                            cronEl.className = 'status inativo';
                            cronEl.textContent = '❌ CRON INATIVO - ' + (data.cron.minutos_sem_execucao ? data.cron.minutos_sem_execucao + ' min sem execução' : 'Sem histórico');
                        }

                        // Jobs
                        const jobsList = document.getElementById('jobs-list');
                        if (data.jobs && data.jobs.length > 0) {
                            jobsList.innerHTML = data.jobs.map(job => 
                                '<div style="border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px;">' +
                                '<strong>' + job.job_id + '</strong> - Status: ' + job.status + '<br>' +
                                'Total: ' + job.total_solicitado + ' | Processados: ' + job.deals_processados + ' | Sucessos: ' + job.deals_sucesso + '<br>' +
                                'Criado: ' + job.created_at +
                                '</div>'
                            ).join('');
                        } else {
                            jobsList.innerHTML = '<p>Nenhum job encontrado</p>';
                        }

                        document.getElementById('ultima-atualizacao').textContent = new Date().toLocaleTimeString();
                    } else {
                        alert('Erro: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    alert('Erro de conexão: ' + error.message);
                });
        }

        // Atualizar ao carregar
        atualizar();
        
        // Auto-refresh a cada 10 segundos
        setInterval(atualizar, 10000);
    </script>
</body>
</html>
