document.addEventListener('DOMContentLoaded', function() {
    // --- Criação do Portal de Autocomplete ---
    let portal = document.createElement('div');
    portal.className = 'autocomplete-portal';
    document.body.appendChild(portal);

    let currentInput = null;
    let searchTimeout = null;
    let activeOptionIndex = -1;

    // --- Funções Utilitárias ---
    function positionPortal() {
        if (!currentInput || !portal.classList.contains('visible')) return;

        const rect = currentInput.getBoundingClientRect();
        portal.style.left = `${rect.left}px`;
        portal.style.top = `${rect.bottom + window.scrollY + 2}px`;
        portal.style.width = `${rect.width}px`;
    }

    function showPortal() {
        portal.classList.add('visible');
        positionPortal();
    }

    function hidePortal() {
        portal.classList.remove('visible');
        currentInput = null;
        activeOptionIndex = -1;
    }

    // --- Lógica do Autocomplete ---
    function setupAutocomplete(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', 'autocomplete-listbox');

        input.addEventListener('focus', () => {
            currentInput = input;
        });

        input.addEventListener('input', () => {
            if (searchTimeout) clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => fetchSuggestions(input.value), 300);
        });

        input.addEventListener('keydown', handleKeyboardNavigation);
    }

    async function fetchSuggestions(query) {
        if (query.length < 2) {
            portal.innerHTML = '';
            hidePortal();
            return;
        }

        const urlParams = new URLSearchParams(window.location.search);
        const cliente = urlParams.get('cliente') || '';
        const url = `/Apps/public/form/api/bitrix_users.php?q=${encodeURIComponent(query)}&cliente=${encodeURIComponent(cliente)}`;

        try {
            const response = await fetch(url);
            const users = await response.json();
            renderSuggestions(users);
        } catch (error) {
            console.error('Erro ao buscar sugestões:', error);
        }
    }

    function renderSuggestions(users) {
        portal.innerHTML = '';
        if (users.length === 0) {
            hidePortal();
            return;
        }

        const listbox = document.createElement('div');
        listbox.id = 'autocomplete-listbox';
        listbox.setAttribute('role', 'listbox');

        users.forEach((user, index) => {
            const option = document.createElement('div');
            option.className = 'option';
            option.textContent = user.name;
            option.setAttribute('role', 'option');
            option.dataset.userid = user.id;
            option.dataset.index = index;

            option.addEventListener('mousedown', () => selectOption(user));
            listbox.appendChild(option);
        });

        portal.appendChild(listbox);
        showPortal();
    }

    function selectOption(user) {
        if (currentInput) {
            currentInput.value = user.name;
            currentInput.dataset.userid = user.id;
        }
        hidePortal();
    }

    function handleKeyboardNavigation(e) {
        const options = portal.querySelectorAll('.option');
        if (options.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                activeOptionIndex = (activeOptionIndex + 1) % options.length;
                updateHighlightedOption();
                break;
            case 'ArrowUp':
                e.preventDefault();
                activeOptionIndex = (activeOptionIndex - 1 + options.length) % options.length;
                updateHighlightedOption();
                break;
            case 'Enter':
                e.preventDefault();
                if (activeOptionIndex > -1) {
                    options[activeOptionIndex].dispatchEvent(new Event('mousedown'));
                }
                break;
            case 'Escape':
                hidePortal();
                break;
        }
    }

    function updateHighlightedOption() {
        const options = portal.querySelectorAll('.option');
        options.forEach((option, index) => {
            if (index === activeOptionIndex) {
                option.classList.add('highlighted');
                option.scrollIntoView({ block: 'nearest' });
            } else {
                option.classList.remove('highlighted');
            }
        });
    }

    // --- Event Listeners Globais ---
    window.addEventListener('resize', positionPortal);
    window.addEventListener('scroll', positionPortal, true);
    document.addEventListener('click', (e) => {
        if (currentInput && !currentInput.contains(e.target) && !portal.contains(e.target)) {
            hidePortal();
        }
    });

    // --- Inicialização ---
    setupAutocomplete('responsavel');
    setupAutocomplete('solicitante');

    // (O restante do seu código de formulário, como o handler de submit, permanece o mesmo)
    const form = document.getElementById('importacaoForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            const responsavelId = document.getElementById('responsavel').dataset.userid || '';
            const solicitanteId = document.getElementById('solicitante').dataset.userid || '';
            
            formData.append('responsavel_id', responsavelId);
            formData.append('solicitante_id', solicitanteId);
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Enviando...';
            submitBtn.disabled = true;
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(text => {
                try {
                    const resp = JSON.parse(text);
                    if (resp.sucesso && resp.next_url) {
                        window.location.href = resp.next_url;
                    } else {
                        const mensagem = document.getElementById('mensagem');
                        if (mensagem) {
                            mensagem.textContent = resp.mensagem || 'Erro desconhecido';
                            mensagem.style.color = 'red';
                        }
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                } catch (e) {
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
                const mensagem = document.getElementById('mensagem');
                if (mensagem) {
                    mensagem.textContent = 'Erro ao enviar: ' + error.message;
                    mensagem.style.color = 'red';
                }
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});
