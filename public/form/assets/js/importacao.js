document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Sistema de autocomplete carregado');

    // Cache global de usuários para evitar requisições desnecessárias
    let usersCache = null;
    let cachePromise = null;
    
    function setupAutocomplete(inputId, listId) {
        const input = document.getElementById(inputId);
        const list = document.getElementById(listId);
        
        if (!input || !list) {
            console.error('Elementos não encontrados:', inputId, listId);
            return;
        }

        console.log('📋 Configurando autocomplete para:', inputId);
        
        // Função para verificar se deve mostrar a lista acima
        function checkPosition() {
            const inputRect = input.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            const spaceBelow = windowHeight - inputRect.bottom;
            const spaceAbove = inputRect.top;
            
            // Se há menos espaço abaixo que acima E menos de 200px abaixo
            if (spaceBelow < 200 && spaceAbove > spaceBelow) {
                list.classList.add('show-above');
            } else {
                list.classList.remove('show-above');
            }
        }
        
        // Função para carregar todos os usuários uma única vez
        function loadAllUsers() {
            if (usersCache) {
                console.log('✅ Usando cache existente com', usersCache.length, 'usuários');
                return Promise.resolve(usersCache);
            }
            
            if (cachePromise) {
                console.log('⏳ Aguardando cache sendo carregado...');
                return cachePromise;
            }
            
            // Obtém parâmetro cliente da URL atual
            const urlParams = new URLSearchParams(window.location.search);
            const cliente = urlParams.get('cliente') || '';
            const clienteParam = cliente ? '&cliente=' + encodeURIComponent(cliente) : '';
            
            console.log('🔄 Carregando todos os usuários do Bitrix...');
            
            cachePromise = fetch('/Apps/public/form/api/bitrix_users.php?q=' + clienteParam + '&cache_bust=' + Date.now())
                .then(res => {
                    console.log('📡 Resposta da API:', res.status, res.statusText);
                    if (!res.ok) {
                        throw new Error(`Erro HTTP: ${res.status} - ${res.statusText}`);
                    }
                    return res.json();
                })
                .then(data => {
                    console.log('📊 Dados recebidos:', data);
                    
                    if (!Array.isArray(data)) {
                        console.error('❌ API não retornou array:', typeof data, data);
                        throw new Error('API não retornou uma lista de usuários');
                    }
                    
                    if (data.length === 0) {
                        console.warn('⚠️ API retornou lista vazia');
                        usersCache = [];
                        return usersCache;
                    }
                    
                    // Remove duplicatas e ordena
                    const uniqueUsers = new Map();
                    data.forEach((user, index) => {
                        if (!user || !user.name || !user.id) {
                            console.warn('⚠️ Usuário inválido no índice', index, ':', user);
                            return;
                        }
                        
                        const nameKey = user.name.toLowerCase().trim();
                        if (!uniqueUsers.has(nameKey)) {
                            uniqueUsers.set(nameKey, user);
                        } else {
                            console.log('🔄 Removendo duplicata:', user.name);
                        }
                    });
                    
                    usersCache = Array.from(uniqueUsers.values()).sort((a, b) => 
                        a.name.localeCompare(b.name, 'pt-BR', { sensitivity: 'base' })
                    );
                    
                    console.log('✅ Cache carregado com sucesso!', usersCache.length, 'usuários únicos');
                    return usersCache;
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar usuários:', error);
                    cachePromise = null; // Reset para tentar novamente
                    throw error;
                });
            
            return cachePromise;
        }
        
        // Função para filtrar usuários localmente
        function filterUsers(query) {
            if (!usersCache || usersCache.length === 0) {
                console.log('📋 Cache vazio para filtrar');
                return [];
            }
            
            const queryLower = query.toLowerCase().trim();
            const filtered = usersCache.filter(user => 
                user.name.toLowerCase().includes(queryLower)
            );
            
            console.log(`🔍 Filtrados ${filtered.length} usuários para "${query}"`);
            return filtered.slice(0, 50); // Limita a 50 para performance na UI
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
            console.log('📋 Exibindo', users.length, 'usuários na lista');
        }
        
        // Event listener principal para input
        input.addEventListener('input', function() {
            const query = input.value.trim();
            console.log('⌨️ Digitando:', query);
            
            // Limpa seleção anterior
            input.dataset.userid = '';
            
            // Permitir busca a partir do primeiro caractere
            if (query.length < 1) {
                list.classList.remove('active');
                list.innerHTML = '';
                console.log('🧹 Lista limpa (query muito curta)');
                return;
            }
            
            // Verifica posicionamento antes de mostrar
            checkPosition();
            
            // Se o cache existe, filtra localmente (instantâneo)
            if (usersCache) {
                const filteredUsers = filterUsers(query);
                displayUsers(filteredUsers);
                return;
            }
            
            // Se não tem cache, carrega uma vez e depois filtra
            console.log('🔄 Cache não existe, carregando...');
            list.innerHTML = '<div style="padding: 12px; color: #666; text-align: center;"><em>🔄 Carregando usuários...</em></div>';
            list.classList.add('active');
            
            loadAllUsers()
                .then(() => {
                    console.log('✅ Cache carregado, filtrando...');
                    const filteredUsers = filterUsers(query);
                    displayUsers(filteredUsers);
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar:', error);
                    list.innerHTML = '<div style="padding: 12px; color: red; text-align: center;">❌ Erro: ' + error.message + '</div>';
                    list.classList.add('active');
                });
        });
        
        // Event listeners para recalcular posição
        input.addEventListener('focus', function() {
            console.log('🎯 Campo focado:', inputId);
            checkPosition();
        });
        
        window.addEventListener('scroll', checkPosition);
        window.addEventListener('resize', checkPosition);
        
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
