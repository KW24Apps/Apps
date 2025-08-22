document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Mapeamento de Campos Interativo v1.0');

    // Esconde a tela de loading e mostra o formulário
    const loadingScreen = document.getElementById('loadingScreen');
    const mapeamentoForm = document.getElementById('mapeamentoForm');
    if (loadingScreen && mapeamentoForm) {
        setTimeout(() => {
            loadingScreen.style.display = 'none';
            mapeamentoForm.classList.remove('content-hidden');
            mapeamentoForm.classList.add('fade-in');
        }, 500); // Delay menor, pois não há busca inicial
    }

    const searchInputs = document.querySelectorAll('.search-input');
    
    searchInputs.forEach(input => {
        const wrapper = input.closest('.autocomplete-wrapper-map');
        if (!wrapper) return;

        const list = wrapper.querySelector('.autocomplete-list-map');
        const hiddenInput = wrapper.querySelector('input[type="hidden"]');

        if (!list || !hiddenInput) return;

        // Função para filtrar e exibir os campos
        function filterAndShow() {
            const query = input.value.toLowerCase().trim();
            list.innerHTML = '';

            const filteredFields = camposBitrixDisponiveis.filter(campo => {
                return campo.title.toLowerCase().includes(query);
            });

            if (filteredFields.length === 0) {
                list.innerHTML = '<div class="autocomplete-item-map no-results">Nenhum campo encontrado</div>';
            } else {
                filteredFields.forEach(campo => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item-map';
                    item.textContent = campo.title;
                    item.dataset.id = campo.id;
                    
                    item.addEventListener('click', () => {
                        input.value = campo.title;
                        hiddenInput.value = campo.id;
                        list.classList.remove('active');
                    });
                    
                    list.appendChild(item);
                });
            }
            
            list.classList.add('active');
        }

        input.addEventListener('input', filterAndShow);
        
        input.addEventListener('focus', filterAndShow);

        // Fechar a lista ao clicar fora
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                list.classList.remove('active');
            }
        });
    });
});
