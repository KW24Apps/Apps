document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Mapeamento de Campos Interativo v2.0 - Posicionamento Dinâmico');

    // Esconde a tela de loading e mostra o formulário
    const loadingScreen = document.getElementById('loadingScreen');
    const mapeamentoForm = document.getElementById('mapeamentoForm');
    if (loadingScreen && mapeamentoForm) {
        setTimeout(() => {
            loadingScreen.style.display = 'none';
            mapeamentoForm.classList.remove('content-hidden');
            mapeamentoForm.classList.add('fade-in');
        }, 500);
    }

    const searchInputs = document.querySelectorAll('.search-input');
    
    searchInputs.forEach(input => {
        const wrapper = input.closest('.autocomplete-wrapper-map');
        if (!wrapper) return;

        const list = wrapper.querySelector('.autocomplete-list-map');
        const hiddenInput = wrapper.querySelector('input[type="hidden"]');

        if (!list || !hiddenInput) return;

        // Move a lista para o body para evitar problemas de clipping com 'overflow'
        document.body.appendChild(list);

        // Função para posicionar a lista de acordo com o input
        function positionList() {
            if (!list.classList.contains('active')) return;

            const inputRect = input.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            
            list.style.width = `${inputRect.width}px`;
            list.style.left = `${window.scrollX + inputRect.left}px`;
            
            const spaceBelow = windowHeight - inputRect.bottom;
            const spaceAbove = inputRect.top;

            // Decide se mostra acima ou abaixo
            if (spaceBelow < 250 && spaceAbove > spaceBelow) {
                list.style.top = 'auto';
                list.style.bottom = `${windowHeight - inputRect.top - window.scrollY + 2}px`;
            } else {
                list.style.bottom = 'auto';
                list.style.top = `${inputRect.bottom + window.scrollY + 2}px`;
            }
        }

        // Função para filtrar e exibir os campos
        function filterAndShow() {
            const query = input.value.toLowerCase().trim();
            list.innerHTML = '';

            const filteredFields = camposBitrixDisponiveis.filter(campo => 
                campo.title.toLowerCase().includes(query)
            );

            if (filteredFields.length === 0) {
                list.innerHTML = '<div class="autocomplete-item-map no-results">Nenhum campo encontrado</div>';
            } else {
                filteredFields.forEach(campo => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item-map';
                    item.textContent = campo.title;
                    
                    item.addEventListener('click', () => {
                        input.value = campo.title;
                        hiddenInput.value = campo.id;
                        list.classList.remove('active');
                    });
                    
                    list.appendChild(item);
                });
            }
            
            list.classList.add('active');
            positionList();
        }

        input.addEventListener('input', filterAndShow);
        input.addEventListener('focus', filterAndShow);

        // Event listeners para reposicionar a lista
        window.addEventListener('scroll', positionList, true);
        window.addEventListener('resize', positionList);

        // Fechar a lista ao clicar fora
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target) && !list.contains(e.target)) {
                list.classList.remove('active');
            }
        });
    });
});
