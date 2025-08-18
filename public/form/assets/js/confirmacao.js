// confirmação.js - Adaptado para usar sistema de jobs
// Exibe modal de confirmação após o mapeamento

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mapeamentoForm');
    console.log('[DEBUG] confirmacao.js carregado');
    if (!form) {
        console.warn('[DEBUG] Formulário #mapeamentoForm não encontrado');
        return;
    }

    // Cria modal
    const modal = document.createElement('div');
    modal.id = 'confirmModal';
    modal.style.display = 'none';
    modal.innerHTML = `
        <div class="modal-bg"></div>
        <div class="modal-content">
            <h2>Confirmação da Importação</h2>
            <div id="modal-info"></div>
            <div id="modal-cards" style="max-height:200px;overflow:auto;"></div>
            <button id="confirmarImportacao">Confirmar</button>
            <button id="cancelarImportacao" style="margin-left:10px;">Cancelar</button>
        </div>
    `;
    document.body.appendChild(modal);

    // Estilo básico do modal
    const style = document.createElement('style');
    style.innerHTML = `
        #confirmModal { position:fixed; top:0; left:0; width:100vw; height:100vh; z-index:1000; display:flex; align-items:center; justify-content:center; }
        #confirmModal .modal-bg { position:absolute; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); }
        #confirmModal .modal-content { position:relative; background:#fff; border-radius:8px; padding:24px; min-width:340px; max-width:90vw; box-shadow:0 2px 16px rgba(0,0,0,0.2); z-index:2; }
        #modal-cards { margin:16px 0; }
        .linha-card { background:#f5f5f5; border-radius:4px; margin-bottom:8px; padding:8px 12px; }
    `;
    document.head.appendChild(style);

    // Modal de loading
    const loadingModal = document.createElement('div');
    loadingModal.id = 'loadingModal';
    loadingModal.style.display = 'none';
    loadingModal.innerHTML = `
        <div class="modal-bg"></div>
        <div class="modal-content" style="min-width:340px;max-width:90vw;">
            <h2>Processando importação...</h2>
            <div id="loading-bar-container" style="width:100%;background:#eee;border-radius:6px;height:18px;margin:18px 0 10px 0;">
                <div id="loading-bar" style="height:18px;width:0%;background:#2196f3;border-radius:6px;transition:width 0.3s;"></div>
            </div>
            <div id="loading-status" style="margin-bottom:10px;font-size:1.05em;color:#333;">Criando job na fila...</div>
            <div id="loading-ids" style="max-height:120px;overflow:auto;font-size:0.98em;background:#f8f8f8;padding:8px 10px;border-radius:5px;"></div>
        </div>
    `;
    document.body.appendChild(loadingModal);

    // Ao submeter o mapeamento, mostra o modal
    form.addEventListener('submit', function(e) {
        console.log('[DEBUG] Submit do form interceptado');
        e.preventDefault();
        
        // Salva o mapeamento dos campos na sessão antes de mostrar o modal
        const formData = new FormData(form);
        console.log('[DEBUG] Enviando fetch para api/salvar_mapeamento.php');
        
        fetch('/Apps/public/form/api/salvar_mapeamento.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            console.log('[DEBUG] Resposta recebida de api/salvar_mapeamento.php', res);
            return res.json();
        })
        .then(resp => {
            console.log('[DEBUG] JSON de api/salvar_mapeamento.php', resp);
            if (resp.sucesso) {
                // Busca dados do arquivo e SPA normalmente
                console.log('[DEBUG] Mapeamento salvo com sucesso, buscando api/confirmacao_import.php');
                fetch('/Apps/public/form/api/confirmacao_import.php')
                    .then(res => {
                        console.log('[DEBUG] Resposta recebida de api/confirmacao_import.php', res);
                        if (!res.ok) {
                            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                        }
                        return res.text(); // Primeiro pega como texto para debug
                    })
                    .then(text => {
                        console.log('[DEBUG] Texto bruto de api/confirmacao_import.php:', text);
                        try {
                            const data = JSON.parse(text);
                            console.log('[DEBUG] JSON de api/confirmacao_import.php', data);
                            return data;
                        } catch (e) {
                            console.error('[DEBUG] Erro ao parsear JSON:', e);
                            throw new Error('Resposta não é um JSON válido: ' + text.substring(0, 100));
                        }
                    })
                    .then(data => {
                        // Monta info
                        let info = `<b>SPA escolhida:</b> ${data.spa}<br>`;
                        info += `<b>Nome do arquivo:</b> ${data.arquivo}<br>`;
                        info += `<b>Linhas encontradas:</b> ${data.total}<br>`;
                        info += `<b>Processamento:</b> Via sistema de jobs (assíncrono)`;
                        
                        document.getElementById('modal-info').innerHTML = info;
                        
                        // Exibe cards com os campos de cada linha do CSV
                        let cards = '';
                        if (data.dados && data.dados.length > 0) {
                            let maxCards = 3;
                            data.dados.slice(0, maxCards).forEach((linha, idx) => {
                                cards += `<div class='linha-card'><b>Linha ${idx+1}</b><br>`;
                                Object.entries(linha).forEach(([campo, valor]) => {
                                    cards += `<span style='font-size:0.95em;color:#333;'><b>${campo}:</b> ${valor}</span><br>`;
                                });
                                cards += `</div>`;
                            });
                            if (data.dados.length > maxCards) {
                                cards += `<div style='text-align:center;color:#888;font-size:0.98em;'>...e mais ${data.dados.length-maxCards} linhas</div>`;
                            }
                        }
                        document.getElementById('modal-cards').innerHTML = cards;
                        
                        // Link para o funil
                        if (data.funil_id) {
                            let link = `https://gnapp.bitrix24.com.br/crm/deal/kanban/category/${data.funil_id}/`;
                            document.getElementById('modal-info').innerHTML += `<br><a href='${link}' target='_blank' style='color:#007bff;font-weight:600;'>Abrir Funil no Bitrix</a>`;
                        }
                        
                        console.log('[DEBUG] Exibindo modal');
                        modal.style.display = 'flex';
                    })
                    .catch(err => {
                        console.error('[DEBUG] Erro ao fazer fetch de api/confirmacao_import.php', err);
                    });
            } else {
                alert('Erro ao salvar mapeamento: ' + (resp.mensagem || ''));
            }
        })
        .catch(err => {
            console.error('[DEBUG] Erro ao fazer fetch de api/salvar_mapeamento.php', err);
        });
    });

    // Botão cancelar fecha modal
    document.getElementById('cancelarImportacao').onclick = function() {
        modal.style.display = 'none';
    };

    // Botão confirmar usa sistema de jobs
    document.getElementById('confirmarImportacao').onclick = function() {
        modal.style.display = 'none';
        loadingModal.style.display = 'flex';
        
        document.getElementById('loading-bar').style.width = '0%';
        document.getElementById('loading-status').textContent = 'Criando job na fila...';
        document.getElementById('loading-ids').innerHTML = '';

        // Busca dados do modal (já carregados)
        fetch('/Apps/public/form/api/confirmacao_import.php')
            .then(res => res.json())
            .then(data => {
                // Usa o novo sistema de jobs
                fetch('/Apps/public/form/api/importar_job.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        entityId: data.spa,
                        categoryId: data.funil_id,
                        deals: data.dados_processamento, // Usa dados com códigos Bitrix
                        tipoJob: 'criar_deals'
                    })
                })
                .then(res => res.json())
                .then(resp => {
                    if (!resp.sucesso) {
                        loadingModal.style.display = 'none';
                        alert('Erro ao criar job: ' + (resp.mensagem || ''));
                        return;
                    }

                    // Job criado com sucesso
                    document.getElementById('loading-status').textContent = resp.mensagem || 'Job criado com sucesso!';
                    document.getElementById('loading-bar').style.width = '100%';
                    document.getElementById('loading-ids').innerHTML = `
                        <div style="color:#2196f3;">Job ID: ${resp.job_id}</div>
                        <div>Total de deals: ${resp.total_deals}</div>
                        <div style="margin-top:10px;">${resp.consultar_status || ''}</div>
                    `;

                    setTimeout(() => {
                        loadingModal.style.display = 'none';
                        alert('Job criado com sucesso! Os deals serão processados em segundo plano.');
                        window.location.href = 'importacao.php';
                    }, 2000);
                })
                .catch(err => {
                    console.error('Erro ao criar job:', err);
                    loadingModal.style.display = 'none';
                    alert('Erro ao criar job na fila.');
                });
            });
    };
});
