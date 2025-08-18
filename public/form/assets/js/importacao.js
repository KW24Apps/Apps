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
                        // Verifica se a resposta é válida
                        if (!res.ok) {
                            throw new Error(`Erro HTTP: ${res.status} - ${res.statusText}`);
                        }
                        return res.json();
                    })
                    .then(users => {
                        list.innerHTML = '';
                        
                        // Verifica se há erro na resposta
                        if (users.error || users.erro) {
                            const div = document.createElement('div');
                            div.textContent = 'Erro: ' + (users.error || users.erro || users.detalhes || 'Erro desconhecido');
                            div.style.color = 'red';
                            div.style.padding = '5px';
                            list.appendChild(div);
                            list.classList.add('active');
                            return;
                        }
                        
                        // Verifica se users é um array válido
                        if (!Array.isArray(users)) {
                            const div = document.createElement('div');
                            div.textContent = 'Erro: Resposta inválida da API (não é um array)';
                            div.style.color = 'red';
                            div.style.padding = '5px';
                            list.appendChild(div);
                            list.classList.add('active');
                            console.error('Resposta da API não é um array:', users);
                            return;
                        }
                        
                        // Se não há usuários encontrados
                        if (users.length === 0) {
                            const div = document.createElement('div');
                            div.textContent = 'Nenhum usuário encontrado';
                            div.style.color = '#666';
                            div.style.padding = '5px';
                            list.appendChild(div);
                            list.classList.add('active');
                            return;
                        }
                        
                        // Processa usuários encontrados
                        users.forEach(user => {
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
                        if (users.length > 0) {
                            list.classList.add('active');
                        } else {
                            list.classList.remove('active');
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
