document.addEventListener('DOMContentLoaded', () => {
    // Load the default page on startup
    loadContent('tutorial_api_deal');

    // Sidebar Toggle Logic (Desktop)
    const toggleBtn = document.querySelector('.menu-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }
});

/**
 * Carrega dinamicamente o arquivo markdown e o injeta na página
 * @param {string} fileName O nome do arquivo sem a extensão .md
 */
async function loadContent(fileName) {
    const container = document.getElementById('markdown-container');
    
    // Set loading state
    container.innerHTML = '<div class="loading">Carregando documentação...</div>';

    // Update active link in sidebar
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('onclick') && link.getAttribute('onclick').includes(fileName)) {
            link.classList.add('active');
        }
    });

    try {
        // Fetch the markdown content
        const response = await fetch(`/documentacao/content/${fileName}.md`);
        
        if (!response.ok) {
            throw new Error(`Erro HTTTP: ${response.status}`);
        }
        
        const markdown = await response.text();
        
        // Configura o Marked.js para interpretar quebra de linhas corretamente
        marked.setOptions({
            breaks: true,
            gfm: true
        });

        // Converte e insere no HTML
        container.innerHTML = marked.parse(markdown);
        
    } catch (error) {
        console.error('Falha ao carregar o conteúdo:', error);
        container.innerHTML = `
            <div style="color: #ef4444; padding: 2rem;">
                <h3>Erro ao carregar a documentação</h3>
                <p>Não foi possível carregar o arquivo <strong>${fileName}.md</strong>.</p>
                <p>Detalhe técnico: ${error.message}</p>
            </div>
        `;
    }
}
