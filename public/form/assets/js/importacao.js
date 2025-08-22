document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Sistema de autocomplete v3.1 - POSICIONAMENTO DINÂMICO:', new Date().toISOString());

    let searchTimeout = null;
    
    function setupAutocomplete(inputId, listId) {
        const input = document.getElementById(inputId);
        const list = document.getElementById(listId);
        
        if (!input || !list) {
            console.error('Elementos não encontrados:', inputId, listId);
            return;
        }

        // Move a lista para o body para evitar problemas de clipping com 'overflow'
        document.body.appendChild(list);
        console.log('📋 Configurando autocomplete (body-append) para:', inputId);
        
        // Função para posicionar a lista de acordo com o input
        function positionList() {
            if (!list.classList.contains('active')) return;

            const inputRect = input.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            
            list.style.width = `${inputRect.width}px`;
            list.style.left = `${inputRect.left}px`;
            
            const spaceBelow = windowHeight - inputRect.bottom;
            const spaceAbove = inputRect.top;

            if (spaceBelow < 200 && spaceAbove > spaceBelow) {
                // Mostra acima
                list.style.top = 'auto';
                list.style.bottom = `${windowHeight - inputRect.top + 2}px`;
            } else {
                // Mostra abaixo
                list.style.bottom = 'auto';
                list.style.top = `${inputRect.bottom + 2}px`;
            }
        }
        
        // Função para buscar usuários dinamicamente
        function searchUsers(query) {
            if (query.length < 2) {
                list.classList.remove('active');
                list.innerHTML = '';
                console.log('🧹 Query muito curta:', query);
                return;
            }
            
            // Obtém parâmetro cliente da URL atual
            const urlParams = new URLSearchParams(window.location.search);
            const cliente = urlParams.get('cliente') || '';
            const clienteParam = cliente ? '&cliente=' + encodeURIComponent(cliente) : '';
            
            console.log('🔍 Buscando usuários para:', query);
            
            list.innerHTML = '<div style="padding: 12px; color: #666; text-align: center;"><em>🔄 Buscando...</em></div>';
            list.classList.add('active');
            positionList(); // Posiciona a lista assim que ela aparece
            
            fetch('/Apps/public/form/api/bitrix_users.php?q=' + encodeURIComponent(query) + clienteParam + '&cache_bust=' + Date.now())
                .then(res => {
                    console.log('📡 Resposta da API:', res.status, res.statusText);
                    if (!res.ok) {
                        throw new Error(`Erro HTTP: ${res.status} - ${res.statusText}`);
                    }
                    return res.json();
                })
                .then(data => {
                    console.log('📊 Usuários encontrados:', data.length);
                    console.log('👥 Lista de usuários:', data.slice(0, 10).map(u => u.name));
                    
                    if (!Array.isArray(data)) {
                        console.error('❌ API não retornou array:', typeof data, data);
                        throw new Error('API não retornou uma lista de usuários');
                    }
                    
                    displayUsers(data);
                })
                .catch(error => {
                    console.error('❌ Erro na busca:', error);
                    list.innerHTML = '<div style="padding: 12px; color: red; text-align: center;">❌ Erro: ' + error.message + '</div>';
                    list.classList.add('active');
                });
        }
        
        // Função para exibir usuários na lista
        function displayUsers(users) {
            list.innerHTML = '';
            
            if (users.length === 0) {
                const div = document.createElement('div');
                div.textContent = 'Nenhum usuário encontrado';
                div.style.color = '#666';
                div.style.padding = '8px 12px';
                div.style.fontStyle = 'italic';
                list.appendChild(div);
                list.classList.add('active');
                return;
            }
            
            console.log('📋 Exibindo', users.length, 'usuários');
            
            users.forEach(user => {
                const div = document.createElement('div');
                div.textContent = user.name;
                div.dataset.userid = user.id;
                div.style.padding = '8px 12px';
                div.style.cursor = 'pointer';
                div.style.borderBottom = '1px solid #eee';
                
                // Hover effect
                div.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f5f5f5';
                });
                div.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
                
                div.onclick = () => {
                    console.log('👤 Usuário selecionado:', user.name, '(ID:', user.id + ')');
                    input.value = user.name;
                    input.dataset.userid = user.id;
                    list.classList.remove('active');
                };
                list.appendChild(div);
            });
            
            list.classList.add('active');
            positionList(); // Reposiciona caso o conteúdo mude o tamanho da lista
        }
        
        // Event listener principal para input - COM DEBOUNCE
        input.addEventListener('input', function() {
            const query = input.value.trim();
            console.log('⌨️ Digitando:', query);
            
            // Limpa seleção anterior
            input.dataset.userid = '';
            
            // Cancela busca anterior
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Debounce - espera 500ms após parar de digitar
            searchTimeout = setTimeout(() => {
                searchUsers(query);
            }, 500);
        });
        
        // Event listeners para recalcular posição
        input.addEventListener('focus', function() {
            console.log('🎯 Campo focado:', inputId);
            const query = input.value.trim();
            if (query.length >= 2) {
                searchUsers(query); // Re-busca ao focar se já houver texto
            }
        });
        
        window.addEventListener('scroll', positionList, true);
        window.addEventListener('resize', positionList);
        
        // Fechar lista quando clica fora
        document.addEventListener('click', function(e) {
            if (!list.contains(e.target) && e.target !== input) {
                list.classList.remove('active');
                console.log('❌ Lista fechada (clique fora)');
            }
        });
    }
    
    // Inicializa os campos de autocomplete
    setupAutocomplete('responsavel', 'autocomplete-responsavel');
    setupAutocomplete('solicitante', 'autocomplete-solicitante');

    // Handler do formulário
    const form = document.getElementById('importacaoForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('📝 Enviando formulário...');
            
            const formData = new FormData(form);
            
            // Adiciona os IDs dos usuários selecionados
            const responsavelId = document.getElementById('responsavel').dataset.userid || '';
            const solicitanteId = document.getElementById('solicitante').dataset.userid || '';
            
            formData.append('responsavel_id', responsavelId);
            formData.append('solicitante_id', solicitanteId);
            
            console.log('👤 Responsável ID:', responsavelId);
            console.log('👤 Solicitante ID:', solicitanteId);
            
            // Mostra loading
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Enviando...';
            submitBtn.disabled = true;
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(res => {
                console.log('📡 Resposta do servidor:', res.status, res.statusText);
                return res.text();
            })
            .then(text => {
                console.log('📄 Resposta raw:', text);
                try {
                    const resp = JSON.parse(text);
                    console.log('📦 Resposta JSON:', resp);
                    
                    if (resp.sucesso && resp.next_url) {
                        console.log('✅ Redirecionando para:', resp.next_url);
                        window.location.href = resp.next_url;
                    } else {
                        console.log('❌ Erro na resposta:', resp);
                        const mensagem = document.getElementById('mensagem');
                        if (mensagem) {
                            mensagem.textContent = resp.mensagem || 'Erro desconhecido';
                            mensagem.style.color = 'red';
                        }
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                } catch (e) {
                    console.error('❌ Erro JSON:', e);
                    console.log('📄 Texto recebido:', text);
                    const mensagem = document.getElementById('mensagem');
                    if (mensagem) {
                        mensagem.textContent = 'Erro: Resposta inválida do servidor';
                        mensagem.style.color = 'red';
                    }
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('❌ Erro na requisição:', error);
                const mensagem = document.getElementById('mensagem');
                if (mensagem) {
                    mensagem.textContent = 'Erro ao enviar: ' + error.message;
                    mensagem.style.color = 'red';
                }
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    } else {
        console.error('❌ Formulário importacaoForm não encontrado');
    }
});
