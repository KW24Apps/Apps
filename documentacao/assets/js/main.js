/**
 * DOCUMENTAÇÃO KW24 — JS
 * Funcionalidades: carregamento de markdown, syntax highlighting,
 * copy buttons, TOC, busca na sidebar, sidebar collapse/mobile.
 */

// =====================================================================
// MAPA DE PÁGINAS (nome de arquivo → label para breadcrumb/tooltip)
// =====================================================================
const PAGE_MAP = {
    'intro':             'Início',
    'tutorial_api_deal': 'Negócios (Deals)',
};

// =====================================================================
// INICIALIZAÇÃO
// =====================================================================
document.addEventListener('DOMContentLoaded', () => {

    // Injeta tooltips nos nav-links para o modo colapsado
    document.querySelectorAll('.nav-link[data-page]').forEach(link => {
        const page = link.getAttribute('data-page');
        link.setAttribute('data-tooltip', PAGE_MAP[page] || page);
    });

    // Configura Marked.js
    marked.setOptions({
        breaks: true,
        gfm: true,
        mangle: false,
        headerIds: true,
    });

    // Configura highlight.js
    hljs.configure({ ignoreUnescapedHTML: true });

    // Carrega a página inicial (via hash ou padrão)
    const hash = window.location.hash.replace('#', '');
    loadPage(hash && PAGE_MAP[hash] ? hash : 'intro');

    // ----- Sidebar collapse (desktop) -----
    const collapseBtn = document.getElementById('collapseBtn');
    if (collapseBtn) {
        collapseBtn.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }

    // ----- Mobile sidebar toggle -----
    const mobileToggle = document.getElementById('mobileToggle');
    const overlay      = document.getElementById('sidebarOverlay');

    if (mobileToggle) {
        mobileToggle.addEventListener('click', () => {
            document.body.classList.toggle('mobile-sidebar-open');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            document.body.classList.remove('mobile-sidebar-open');
        });
    }

    // ----- Search na sidebar -----
    const searchInput = document.getElementById('searchInput');
    const searchClear = document.getElementById('searchClear');

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            filterNav(q);
            searchClear.style.display = q ? 'flex' : 'none';
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', () => {
            searchInput.value = '';
            filterNav('');
            searchClear.style.display = 'none';
            searchInput.focus();
        });
    }

    // ----- Cliques nos links da nav -----
    document.querySelectorAll('.nav-link[data-page]').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const page = link.getAttribute('data-page');
            loadPage(page);

            // Fecha sidebar no mobile
            if (window.innerWidth < 768) {
                document.body.classList.remove('mobile-sidebar-open');
            }
        });
    });
});

// =====================================================================
// CARREGAMENTO DE PÁGINA (MARKDOWN)
// =====================================================================
async function loadPage(page) {
    const contentArea = document.getElementById('contentArea');
    const tocPanel    = document.getElementById('tocPanel');
    const tocNav      = document.getElementById('tocNav');

    // Estado de loading
    contentArea.innerHTML = `
        <div class="loading-state">
            <div class="spinner"></div>
            <span>Carregando...</span>
        </div>`;
    tocPanel.classList.remove('visible');
    tocNav.innerHTML = '';

    // Atualiza nav ativa
    setActiveLink(page);
    updateBreadcrumb(page);
    window.location.hash = page;

    try {
        const res = await fetch(`/documentacao/content/${page}.md`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const markdown = await res.text();

        // Converte markdown → HTML
        const rawHtml = marked.parse(markdown);
        contentArea.innerHTML = rawHtml;

        // Aplica syntax highlighting
        contentArea.querySelectorAll('pre code').forEach(block => {
            hljs.highlightElement(block);
        });

        // Adiciona cabeçalho e botão de cópia em cada bloco de código
        contentArea.querySelectorAll('pre').forEach(pre => {
            decorateCodeBlock(pre);
        });

        // Gera IDs para headings e monta TOC
        buildTOC(contentArea, tocNav, tocPanel);

        // Scroll ao topo do conteúdo
        contentArea.scrollTo(0, 0);

    } catch (err) {
        console.error('Erro ao carregar página:', err);
        contentArea.innerHTML = `
            <div class="error-state">
                <h3><i class="fas fa-circle-exclamation"></i> Página não encontrada</h3>
                <p>Não foi possível carregar <code>${page}.md</code>.</p>
                <p>${err.message}</p>
            </div>`;
    }
}

// =====================================================================
// DECORAR BLOCO DE CÓDIGO (header + copy button)
// =====================================================================
function decorateCodeBlock(pre) {
    const code  = pre.querySelector('code');
    if (!code) return;

    // Detecta linguagem pelo class do highlight.js
    const langClass = Array.from(code.classList).find(c => c.startsWith('language-'));
    const lang      = langClass ? langClass.replace('language-', '') : 'code';

    // Cria o header
    const header = document.createElement('div');
    header.className = 'code-header';
    header.innerHTML = `
        <span class="code-lang">${lang}</span>
        <button class="copy-btn" title="Copiar código">
            <i class="fas fa-copy"></i> Copiar
        </button>`;

    pre.insertBefore(header, code);

    // Evento de cópia
    header.querySelector('.copy-btn').addEventListener('click', async () => {
        const btn  = header.querySelector('.copy-btn');
        const text = code.innerText;
        try {
            await navigator.clipboard.writeText(text);
            btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
            btn.classList.add('copied');
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-copy"></i> Copiar';
                btn.classList.remove('copied');
            }, 2000);
        } catch {
            btn.innerHTML = '<i class="fas fa-xmark"></i> Erro';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-copy"></i> Copiar';
            }, 2000);
        }
    });
}

// =====================================================================
// CONSTRUÇÃO DO SUMÁRIO (TOC)
// =====================================================================
function buildTOC(contentArea, tocNav, tocPanel) {
    const headings = contentArea.querySelectorAll('h2, h3');
    if (headings.length < 2) return;

    const fragment = document.createDocumentFragment();
    let idCounter  = {};

    headings.forEach(h => {
        // Gera ID único a partir do texto
        let id = h.textContent
            .toLowerCase()
            .replace(/[^\w\sÀ-ú]/g, '')
            .trim()
            .replace(/\s+/g, '-');

        idCounter[id] = (idCounter[id] || 0) + 1;
        if (idCounter[id] > 1) id += `-${idCounter[id]}`;

        h.id = id;

        const a = document.createElement('a');
        a.href      = `#${id}`;
        a.className = `toc-link ${h.tagName === 'H3' ? 'h3' : ''}`;
        a.textContent = h.textContent;
        a.addEventListener('click', e => {
            e.preventDefault();
            h.scrollIntoView({ behavior: 'smooth' });
        });
        fragment.appendChild(a);
    });

    tocNav.appendChild(fragment);
    tocPanel.classList.add('visible');

    // Observa scroll para destacar seção ativa no TOC
    observeTOCScroll(contentArea, tocNav);
}

function observeTOCScroll(contentArea, tocNav) {
    const headings = contentArea.querySelectorAll('h2[id], h3[id]');
    if (!headings.length) return;

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.id;
                tocNav.querySelectorAll('.toc-link').forEach(l => {
                    l.classList.toggle('active', l.getAttribute('href') === `#${id}`);
                });
            }
        });
    }, { rootMargin: '-10% 0% -70% 0%', threshold: 0 });

    headings.forEach(h => observer.observe(h));
}

// =====================================================================
// ATUALIZA LINK ATIVO NA SIDEBAR
// =====================================================================
function setActiveLink(page) {
    document.querySelectorAll('.nav-link[data-page]').forEach(link => {
        link.classList.toggle('active', link.getAttribute('data-page') === page);
    });
}

// =====================================================================
// ATUALIZA BREADCRUMB DA TOPBAR
// =====================================================================
function updateBreadcrumb(page) {
    const el = document.getElementById('breadcrumbPage');
    if (el) el.textContent = PAGE_MAP[page] || page;
}

// =====================================================================
// FILTRO DE BUSCA NA SIDEBAR
// =====================================================================
function filterNav(query) {
    const links = document.querySelectorAll('.nav-link[data-page]');

    // Remove mensagem anterior
    document.querySelectorAll('.no-results').forEach(el => el.remove());

    let visibleCount = 0;

    links.forEach(link => {
        const text    = link.querySelector('.nav-text')?.textContent?.toLowerCase() || '';
        const page    = link.getAttribute('data-page');
        const tooltip = (PAGE_MAP[page] || '').toLowerCase();
        const match   = !query || text.includes(query) || tooltip.includes(query);

        link.parentElement.classList.toggle('hidden', !match);
        if (match) visibleCount++;
    });

    // Mostra labels de seção somente se houver itens visíveis
    document.querySelectorAll('.nav-group-label').forEach(label => {
        const ul      = label.nextElementSibling;
        const visible = ul && ul.querySelectorAll('li:not(.hidden)').length > 0;
        label.style.display = visible ? '' : 'none';
    });

    // Mensagem "sem resultados"
    if (query && visibleCount === 0) {
        const msg = document.createElement('div');
        msg.className   = 'no-results';
        msg.textContent = 'Nenhum resultado encontrado.';
        document.getElementById('sidebarNav').appendChild(msg);
    }
}
