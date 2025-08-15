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
                // ...autocomplete remoto permanece igual...
                fetch('api/bitrix_users.php?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(users => {
                        // ...existing code...
                        list.innerHTML = '';
                        if (users.error) {
                            const div = document.createElement('div');
                            div.textContent = 'Erro: ' + users.error;
                            div.style.color = 'red';
                            list.appendChild(div);
                            list.classList.add('active');
                            return;
                        }
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
