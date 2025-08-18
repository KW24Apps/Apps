document.addEventListener('DOMContentLoaded', function() {

    function setupAutocomplete(inputId, listId) {
        const input = document.getElementById(inputId);
        const list = document.getElementById(listId);
        let timeout = null;
        
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = input.value;
            // Permitir busca a partir do primeiro caractere
            if (query.length < 1) {
                list.classList.remove('active');
                list.innerHTML = '';
                return;
            }
            timeout = setTimeout(() => {
                // Obtém parâmetro cliente da URL atual
                const urlParams = new URLSearchParams(window.location.search);
                const cliente = urlParams.get('cliente') || '';
                const clienteParam = cliente ? '&cliente=' + encodeURIComponent(cliente) : '';
                
                // Busca usuários via API do sistema de rotas
                fetch('/Apps/importar/api/bitrix_users?q=' + encodeURIComponent(query) + clienteParam)
                    .then(res => {
                        // Log para debug
                        console.log('Status da resposta:', res.status, res.statusText);
                        
                        // Verifica se a resposta é válida
                        if (!res.ok) {
                            throw new Error(`Erro HTTP: ${res.status} - ${res.statusText}`);
                        }
                        return res.json();
                    })
                    .then(data => {
                        // Log da resposta para debug
                        console.log('Dados recebidos da API:', data);
                        
                        list.innerHTML = '';
                        
                        // PRIMEIRA VERIFICAÇÃO: Se é null ou undefined
                        if (!data) {
                            const div = document.createElement('div');
                            div.textContent = 'Erro: Resposta vazia da API';
                            div.style.color = 'red';
                            div.style.padding = '5px';
                            list.appendChild(div);
                            list.classList.add('active');
                            return;
                        }
                        
                        // SEGUNDA VERIFICAÇÃO: Se tem propriedades de erro (qualquer uma)
                        if (data.error || data.erro || data.detalhes || data.debug) {
                            const div = document.createElement('div');
                            const errorMsg = data.error || data.erro || data.detalhes || 'Erro desconhecido';
                            div.textContent = 'Erro: ' + errorMsg;
                            div.style.color = 'red';
                            div.style.padding = '5px';
                            list.appendChild(div);
                            list.classList.add('active');
                            console.error('Erro retornado pela API:', data);
                            return;
                        }
                        
                        // TERCEIRA VERIFICAÇÃO: Se NÃO é um array NUMÉRICO (lista de usuários)
                        // Arrays associativos do PHP são detectados como objetos no JavaScript
                        if (!Array.isArray(data) || (Array.isArray(data) && data.length > 0 && (data[0] === null || typeof data[0] === 'undefined'))) {
                            const div = document.createElement('div');
                            div.textContent = 'Erro: API retornou formato inválido (não é uma lista de usuários)';
                            div.style.color = 'red';
                            div.style.padding = '5px';
                            list.appendChild(div);
                            list.classList.add('active');
                            console.error('Resposta da API não é uma lista válida:', typeof data, data);
                            return;
                        }
                        
                        // VALIDAÇÃO ADICIONAL: Verifica se o primeiro elemento tem estrutura de usuário
                        if (data.length > 0 && (!data[0].hasOwnProperty('id') || !data[0].hasOwnProperty('name'))) {
                            const div = document.createElement('div');
                            div.textContent = 'Erro: Formato de usuário inválido na resposta da API';
                            div.style.color = 'red';
                            div.style.padding = '5px';
                            list.appendChild(div);
                            list.classList.add('active');
                            console.error('Primeiro usuário não tem id/name:', data[0]);
                            return;
                        }
                        
                        // QUARTA VERIFICAÇÃO: Se array está vazio
                        if (data.length === 0) {
                            const div = document.createElement('div');
                            div.textContent = 'Nenhum usuário encontrado';
                            div.style.color = '#666';
                            div.style.padding = '5px';
                            list.appendChild(div);
                            list.classList.add('active');
                            return;
                        }
                        
                        // QUINTA VERIFICAÇÃO: Processar apenas se chegou até aqui
                        console.log('Processando', data.length, 'usuários');
                        
                        // PROCESSAMENTO SEGURO DOS USUÁRIOS
                        try {
                            data.forEach((user, index) => {
                                // Valida cada usuário
                                if (!user || typeof user !== 'object') {
                                    console.warn('Usuário inválido no índice', index, ':', user);
                                    return; // pula este usuário
                                }
                                
                                if (!user.name || !user.id) {
                                    console.warn('Usuário sem nome/id no índice', index, ':', user);
                                    return; // pula este usuário
                                }
                                
                                const div = document.createElement('div');
                                div.textContent = user.name;
                                div.dataset.userid = user.id;
                                div.onclick = () => {
                                    input.value = user.name;
                                    input.dataset.userid = user.id;
                                    list.classList.remove('active');
                                    list.innerHTML = '';
                                };
                                list.appendChild(div);
                            });
                            
                            // Mostra a lista se há usuários
                            if (data.length > 0) {
                                list.classList.add('active');
                            } else {
                                list.classList.remove('active');
                            }
                            
                        } catch (foreachError) {
                            console.error('Erro no processamento dos usuários:', foreachError);
                            const div = document.createElement('div');
                            div.textContent = 'Erro no processamento dos dados';
                            div.style.color = 'red';
                            div.style.padding = '5px';
                            list.appendChild(div);
                            list.classList.add('active');
                        }
                    })
                    .catch(error => {
                        console.error('Erro na busca de usuários:', error);
                        list.innerHTML = '';
                        const div = document.createElement('div');
                        div.textContent = 'Erro de conexão: ' + error.message;
                        div.style.color = 'red';
                        div.style.padding = '5px';
                        div.style.fontSize = '12px';
                        list.appendChild(div);
                        list.classList.add('active');
                    });
            }, 300);
        });
        document.addEventListener('click', function(e) {
            if (!list.contains(e.target) && e.target !== input) {
                list.classList.remove('active');
            }
        });
    }
    
    setupAutocomplete('responsavel', 'autocomplete-responsavel');
    setupAutocomplete('solicitante', 'autocomplete-solicitante');

    document.getElementById('importacaoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        // Adiciona os IDs dos usuários selecionados
        data.append('responsavel_id', document.getElementById('responsavel').dataset.userid || '');
        data.append('solicitante_id', document.getElementById('solicitante').dataset.userid || '');
        
        fetch(form.action, {
            method: 'POST',
            body: data
        })
        .then(res => res.json())
        .then(resp => {
            if (resp.sucesso && resp.next_url) {
                window.location.href = resp.next_url;
            } else {
                document.getElementById('mensagem').textContent = resp.mensagem || 'Enviado com sucesso!';
                form.reset();
            }
        })
        .catch(() => {
            document.getElementById('mensagem').textContent = 'Erro ao enviar.';
        });
    });
});
