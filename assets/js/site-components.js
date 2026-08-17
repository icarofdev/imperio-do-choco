(function () {
    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function normalizeOptions(options = {}) {
        const homeHref = String(options.homeHref || "index.php");
        const auth = options.auth || {};

        return {
            homeHref,
            currentPage: String(options.currentPage || ""),
            surface: Boolean(options.surface),
            auth: {
                authenticated: Boolean(auth.authenticated ?? auth.autenticado),
                name: String(auth.name ?? auth.nome ?? "").trim(),
                role: String(auth.role ?? auth.papel ?? "cliente"),
                accountHref: String(auth.accountHref ?? auth.destinoConta ?? "login.php"),
            },
        };
    }

    function homeLink(homeHref, anchor) {
        return homeHref === "#" ? `#${anchor}` : `${homeHref}#${anchor}`;
    }

    function accountLabel(auth) {
        return auth.authenticated ? (auth.name || "Minha conta") : "Entre ou cadastre-se";
    }

    function headerMarkup(options) {
        const config = normalizeOptions(options);
        const { homeHref, auth } = config;
        const inicio = homeLink(homeHref, "inicio");
        const vitrine = homeLink(homeHref, "vitrine");
        const colecoes = homeLink(homeHref, "colecoes");
        const presentes = homeLink(homeHref, "presentes");
        const contato = homeLink(homeHref, "contato");
        const historiaAtual = config.currentPage === "history" ? ' aria-current="page"' : "";

        return `
            <a class="marca-site brand-link" href="${escapeHtml(inicio)}" aria-label="Velle Dulcis — voltar ao início">
                <img class="marca-site__logo brand-logo" src="../assets/images/logos/velle-dulcis.png" alt="Velle Dulcis" width="459" height="543">
            </a>

            <nav class="menu-topo" aria-label="Principal">
                <a class="menu-topo__inicio" href="${escapeHtml(inicio)}">Início</a>
                <div class="menu-topo__item menu-topo__item--mega">
                    <a href="${escapeHtml(vitrine)}" class="menu-topo__link menu-topo__link--dropdown">
                        <span>Chocolate</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5"></path></svg>
                    </a>
                    <div class="mega-menu" role="group" aria-label="Explorar chocolates">
                        <div class="mega-menu__colunas">
                            <section class="mega-menu__coluna">
                                <p class="mega-menu__titulo">Destaques</p>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__item"><strong>Seleção da Casa</strong><span>Os chocolates que melhor representam a vitrine neste momento.</span></a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__item"><strong>Presentes Especiais</strong><span>Opções pensadas para impressionar com sabor, acabamento e presença.</span></a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__item"><strong>Importados</strong><span>Sabores diferentes para quem quer experimentar algo fora do comum.</span></a>
                            </section>
                            <section class="mega-menu__coluna">
                                <p class="mega-menu__titulo">Tipos</p>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Chocolate recheado</a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Chocolate artesanal</a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Caixas premium</a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Tabletes</a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Importados</a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Mais Vendidos</a>
                            </section>
                            <section class="mega-menu__coluna">
                                <p class="mega-menu__titulo">Momentos</p>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Para presentear</a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Para dividir</a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Para kits</a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Novidades</a>
                                <a href="${escapeHtml(vitrine)}" class="mega-menu__lista-link">Vitrine completa</a>
                            </section>
                        </div>
                        <div class="mega-menu__destaques">
                            <a class="mega-menu__card" href="produto.html?id=tablete-morango">
                                <img src="../assets/images/products/product-tablete-morango.webp" alt="Tablete Morango Cremoso" width="839" height="1000">
                                <div class="mega-menu__card-conteudo"><span class="mega-menu__card-tag">Seleção da casa</span><strong>Tablete Morango Cremoso</strong></div>
                            </a>
                            <a class="mega-menu__card" href="produto.html?id=trufa-velvet-cacao">
                                <img src="../assets/images/products/product-trufa-velvet-cacao.webp" alt="Trufa Velvet Cacao" width="750" height="1000">
                                <div class="mega-menu__card-conteudo"><span class="mega-menu__card-tag">Favorito da vitrine</span><strong>Trufa Velvet Cacao</strong></div>
                            </a>
                        </div>
                    </div>
                </div>
                <a class="menu-topo__colecoes" href="${escapeHtml(colecoes)}">Coleções</a>
                <a class="menu-topo__presentes" href="${escapeHtml(presentes)}">Presentes</a>
                <a href="historia.html"${historiaAtual}>Nossa história</a>
                <a class="menu-topo__contato" href="${escapeHtml(contato)}">Contato</a>
            </nav>

            <div class="acoes-topo" data-theme-toggle-host data-theme-toggle-class="acao-topo acao-topo--tema">
                <button id="abrir-pesquisa" class="acao-topo" type="button" aria-label="Buscar">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7.5"></circle><path d="M16.5 16.5L21 21"></path></svg>
                </button>
                <a id="link-conta" class="acao-topo acao-topo--link acao-topo--conta" href="${escapeHtml(auth.accountHref)}" aria-label="Entrar ou acessar conta" data-logado="${auth.authenticated ? "true" : "false"}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M5 20c1.8-3.3 4.2-5 7-5s5.2 1.7 7 5"></path></svg>
                    <span id="link-conta-texto" data-hide-when-authenticated="true"${auth.authenticated ? " hidden" : ""}>${escapeHtml(accountLabel(auth))}</span>
                </a>
                <a class="acao-topo acao-topo--cadastro" href="cadastro.php"${auth.authenticated ? " hidden" : ""}>Criar conta</a>
                <button id="btn-carrinho" class="acao-topo acao-topo--carrinho" type="button" aria-label="Carrinho" aria-controls="carrinho-lateral" aria-expanded="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8V7a4 4 0 118 0v1"></path><path d="M6 8h12l-1 11H7L6 8z"></path></svg>
                    <span id="qtd-itens">0</span>
                </button>
                <div id="popup-carrinho" class="popup-carrinho" aria-live="polite" aria-hidden="true">
                    <div class="popup-carrinho__seta" aria-hidden="true"></div>
                    <div class="popup-carrinho__conteudo"><div class="popup-carrinho__icone" aria-hidden="true">&#10003;</div><div class="popup-carrinho__texto"><strong>Adicionado com sucesso à sacola!</strong><span id="popup-carrinho-produto">Produto</span></div></div>
                    <div class="popup-carrinho__progresso"><span id="popup-carrinho-barra"></span></div>
                </div>
            </div>

            <button id="menu-mobile-toggle" class="menu-mobile-toggle" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="menu-mobile">
                <svg class="menu-mobile-toggle__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path></svg>
            </button>
            <div id="menu-mobile" class="menu-mobile" aria-hidden="true">
                <button class="menu-mobile__backdrop" type="button" data-menu-mobile-close aria-label="Fechar menu"></button>
                <aside class="menu-mobile__painel" aria-label="Menu mobile">
                    <div class="menu-mobile__topo"><span class="menu-mobile__marca">Menu</span><button class="menu-mobile__fechar" type="button" data-menu-mobile-close aria-label="Fechar menu"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6L6 18"></path></svg></button></div>
                    <div class="menu-mobile__acoes">
                        <button class="menu-mobile__acao" type="button" data-mobile-action="search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7.5"></circle><path d="M16.5 16.5L21 21"></path></svg><span>Buscar</span></button>
                        <a id="link-conta-mobile" class="menu-mobile__acao-link" href="${escapeHtml(auth.accountHref)}" aria-label="Entrar ou acessar conta"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M5 20c1.8-3.3 4.2-5 7-5s5.2 1.7 7 5"></path></svg><span id="link-conta-mobile-texto">${escapeHtml(auth.authenticated ? (auth.name || "Minha conta") : "Entrar")}</span></a>
                        <button class="menu-mobile__acao" type="button" data-mobile-action="cart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8V7a4 4 0 118 0v1"></path><path d="M6 8h12l-1 11H7L6 8z"></path></svg><span>Sacola</span></button>
                    </div>
                    <nav class="menu-mobile__links" aria-label="Menu principal mobile">
                        <a class="menu-mobile__link" href="${escapeHtml(inicio)}"><span>Início</span></a>
                        <a class="menu-mobile__link" href="${escapeHtml(vitrine)}"><span>Chocolate</span></a>
                        <a class="menu-mobile__link" href="${escapeHtml(colecoes)}"><span>Coleções</span></a>
                        <a class="menu-mobile__link" href="${escapeHtml(presentes)}"><span>Presentes</span></a>
                        <a class="menu-mobile__link" href="historia.html"${historiaAtual}><span>Nossa história</span><svg class="menu-mobile__link-seta" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"></path><path d="M13 6l6 6-6 6"></path></svg></a>
                        <a class="menu-mobile__link" href="${escapeHtml(contato)}"><span>Contato</span></a>
                        <a class="menu-mobile__link" href="cadastro.php" data-signup-link${auth.authenticated ? " hidden" : ""}><span>Criar conta</span><svg class="menu-mobile__link-seta" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"></path><path d="M13 6l6 6-6 6"></path></svg></a>
                    </nav>
                </aside>
            </div>
        `;
    }

    function mountHeader(host, options = {}) {
        if (!host) {
            return;
        }

        const config = normalizeOptions(options);
        host.classList.toggle("topo-site--compact", config.surface);
        host.dataset.headerSurface = config.surface ? "true" : "false";
        host.innerHTML = headerMarkup(config);
    }

    function updateHeaderAuth(options = {}) {
        const config = normalizeOptions({ auth: options });
        const { auth } = config;

        document.querySelectorAll("#link-conta, #link-conta-mobile").forEach((link) => {
            link.href = auth.accountHref;
            link.dataset.logado = auth.authenticated ? "true" : "false";
        });

        const desktopLabel = document.getElementById("link-conta-texto");
        const mobileLabel = document.getElementById("link-conta-mobile-texto");

        if (desktopLabel) {
            desktopLabel.hidden = auth.authenticated;
            desktopLabel.textContent = accountLabel(auth);
        }

        if (mobileLabel) {
            mobileLabel.textContent = auth.authenticated ? (auth.name || "Minha conta") : "Entrar";
        }

        document.querySelectorAll(".acao-topo--cadastro, [data-signup-link]").forEach((link) => {
            link.hidden = auth.authenticated;
        });
    }

    function footerMarkup(options = {}) {
        const homeHref = String(options.homeHref || "index.php");
        const vitrine = homeLink(homeHref, "vitrine");

        return `
            <div class="rodape-site__container">
                <div class="rodape-site__newsletter">
                    <div><span class="section-kicker">Cartas Velle</span><h2>Novidades para saborear sem pressa.</h2></div>
                    <form class="newsletter-form" action="#" method="post">
                        <label class="sr-only" for="newsletter-email">Seu melhor e-mail</label>
                        <input id="newsletter-email" type="email" placeholder="Seu melhor e-mail" autocomplete="email" required>
                        <button type="submit" aria-label="Assinar novidades"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></button>
                    </form>
                </div>
                <div class="rodape-site__grid">
                    <section class="rodape-site__coluna"><h3>Shop</h3><a href="${escapeHtml(vitrine)}">Presentes &amp; Kits</a><a href="${escapeHtml(vitrine)}">Mais Vendidos</a><a href="${escapeHtml(vitrine)}">Importados</a><a href="${escapeHtml(vitrine)}">Edições Premium</a></section>
                    <section class="rodape-site__coluna"><h3>Aprenda</h3><a href="historia.html">Nossa História</a><a href="${escapeHtml(vitrine)}">Guia de Sabores</a><a href="${escapeHtml(vitrine)}">Como Montar Presentes</a><a href="${escapeHtml(vitrine)}">Novidades</a></section>
                    <section class="rodape-site__coluna"><h3>Suporte</h3><a href="mailto:contato@velledulcis.com">Dúvidas Frequentes</a><a href="mailto:contato@velledulcis.com">Fale Conosco</a><a href="mailto:contato@velledulcis.com">Entrega e Retirada</a><a href="mailto:contato@velledulcis.com">Trocas e Devoluções</a><a href="mailto:contato@velledulcis.com">Privacidade</a></section>
                    <section class="rodape-site__coluna rodape-site__coluna--contato"><h3>Contato</h3><p>Segunda a sexta, das 9h30 às 12h20</p><a href="tel:+5511992875758">(11) 99287-5758</a><a href="mailto:contato@velledulcis.com">contato@velledulcis.com</a><p>São Paulo, Brasil</p><div class="rodape-site__social"><a href="https://www.instagram.com/velledulcis/" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram Velle Dulcis"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.2" cy="6.8" r="1"></circle></svg></a></div></section>
                </div>
                <div class="rodape-site__base"><p class="rodape-site__credito">Caetano de Campos 3° BT</p><span class="rodape-site__pix" aria-label="Pagamento via Pix" title="Pix"><img src="../assets/images/ui/pix.png" alt="Pix" width="1024" height="1024" loading="lazy"></span><span class="rodape-site__moeda">BRL</span></div>
            </div>
        `;
    }

    function mountFooter(host, options = {}) {
        if (!host) {
            return;
        }

        host.innerHTML = footerMarkup(options);
        const form = host.querySelector(".newsletter-form");
        form?.addEventListener("submit", (event) => {
            event.preventDefault();
            const input = form.querySelector("input");

            if (!input?.checkValidity()) {
                input?.reportValidity();
                return;
            }

            input.value = "";
            input.placeholder = "Cadastro realizado com carinho";
        });
    }

    function commerceMarkup(options = {}) {
        const welcomeMessage = String(options.welcomeMessage || "");
        const accountHref = String(options.accountHref || "login.php");

        return `
            <div id="overlay-pesquisa" class="overlay-pesquisa oculto" role="dialog" aria-modal="true" aria-labelledby="titulo-pesquisa" aria-hidden="true">
                <div class="overlay-pesquisa__cabecalho"><button id="fechar-pesquisa" class="overlay-pesquisa__fechar" type="button" aria-label="Fechar pesquisa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg></button></div>
                <div class="overlay-pesquisa__conteudo"><h2 id="titulo-pesquisa" class="sr-only">Pesquisar chocolates</h2><div class="pesquisa-wrapper"><div class="pesquisa-box"><label class="sr-only" for="barra-pesquisa">Buscar chocolate</label><div class="pesquisa-campo"><span class="pesquisa-icone" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7.5"></circle><path d="M16.5 16.5L21 21"></path></svg></span><input type="search" id="barra-pesquisa" placeholder="Buscar chocolate..." autocomplete="off"><button id="btn-limpar-pesquisa" class="btn-icone oculto" type="button" aria-label="Limpar pesquisa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7l10 10M17 7 7 17"></path></svg></button></div><nav class="pesquisa-atalhos" aria-label="Atalhos de busca"><span class="pesquisa-atalhos__rotulo">Atalhos</span><div id="lista-atalhos-pesquisa" class="pesquisa-atalhos__lista"></div></nav><section id="painel-pesquisa" class="painel-pesquisa" aria-labelledby="titulo-resultados-pesquisa"><div class="painel-pesquisa__topo"><h3 id="titulo-resultados-pesquisa">Resultados</h3><span id="resultado-pesquisa-contagem" aria-live="polite"></span></div><div id="lista-resultados-pesquisa" class="lista-resultados-pesquisa"></div><button id="btn-ver-todos" class="btn-ver-todos" type="button">Veja todos os produtos <span aria-hidden="true">→</span></button></section></div></div></div>
            </div>
            <div id="overlay-carrinho" class="overlay-carrinho" aria-hidden="true"></div>
            <aside id="carrinho-lateral" role="dialog" aria-modal="true" aria-labelledby="titulo-carrinho" aria-hidden="true"><div class="carrinho-topo"><h3 id="titulo-carrinho">Minha sacola</h3><button id="fechar-carrinho" class="carrinho-fechar" type="button" aria-label="Fechar carrinho"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg></button></div><div class="carrinho-corpo"><ul id="lista-carrinho"></ul></div><div class="carrinho-resumo"><h4>Resumo</h4><div id="carrinho-detalhes" class="carrinho-resumo__itens"></div><div class="carrinho-resumo__totais"><div class="carrinho-resumo__linha"><span>Subtotal</span><strong id="subtotal-preco">R$ 0,00</strong></div><div class="carrinho-resumo__linha carrinho-resumo__linha--total"><span>Total</span><strong id="total-preco">R$ 0,00</strong></div></div><button id="finalizar">Finalizar pedido</button></div></aside>
            <div id="popup-remocao" class="popup-remocao" aria-live="polite" aria-hidden="true"><span id="popup-remocao-texto">Produto removido do carrinho.</span><button id="fechar-popup-remocao" class="popup-remocao__fechar" type="button" aria-label="Fechar aviso">&times;</button></div>
            <div id="popup-boas-vindas" class="popup-boas-vindas${welcomeMessage ? " ativo" : ""}" aria-live="polite" aria-hidden="${welcomeMessage ? "false" : "true"}"><div class="popup-boas-vindas__conteudo"><strong>Sessão iniciada</strong><span id="popup-boas-vindas-texto">${escapeHtml(welcomeMessage)}</span></div><button id="fechar-popup-boas-vindas" class="popup-boas-vindas__fechar" type="button" aria-label="Fechar mensagem">&times;</button></div>
            <div id="popup-aviso-conta" class="popup-aviso-conta" aria-live="polite" aria-hidden="true"><div class="popup-aviso-conta__conteudo"><strong>Finalize pedidos com mais segurança</strong><span>Entre na sua conta e cadastre um telefone antes de finalizar a compra.</span></div><div class="popup-aviso-conta__acoes"><a href="${escapeHtml(accountHref)}">Entrar</a><button id="fechar-popup-aviso-conta" type="button" aria-label="Fechar aviso">&times;</button></div></div>
        `;
    }

    function mountCommerce(host, options = {}) {
        if (host) {
            host.innerHTML = commerceMarkup(options);
        }
    }

    window.VelleComponents = {
        mountHeader,
        updateHeaderAuth,
        mountFooter,
        mountCommerce,
    };
}());
