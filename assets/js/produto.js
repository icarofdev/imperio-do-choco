const produtoContainer = document.getElementById("produto-container");
const HOME_ROUTE_STORAGE_KEY = "imperio_home_route";
const menuMobileToggle = document.getElementById("menu-mobile-toggle");
const menuMobile = document.getElementById("menu-mobile");
const menuMobileCloseButtons = document.querySelectorAll("[data-menu-mobile-close]");
const menuMobileLinks = document.querySelectorAll(".menu-mobile__link, .menu-mobile__acao-link");
let sessaoProduto = {
    autenticado: false,
    admin: false,
    papel: null,
};

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function formatarPreco(valor) {
    return `R$ ${Number(valor).toFixed(2).replace(".", ",")}`;
}

function obterRotaHome() {
    return sessionStorage.getItem(HOME_ROUTE_STORAGE_KEY) || "index.php";
}

function atualizarLinksDeRetorno() {
    const rotaHome = obterRotaHome();

    document.querySelectorAll("[data-home-link]").forEach((link) => {
        const ancora = String(link.dataset.homeAnchor || "").replace(/^#/, "");
        link.setAttribute("href", ancora ? `${rotaHome}#${ancora}` : rotaHome);
    });
}

function menuMobileAberto() {
    return Boolean(menuMobile && menuMobile.classList.contains("ativo"));
}

function abrirMenuMobile() {
    if (!menuMobile || !menuMobileToggle) {
        return;
    }

    menuMobile.classList.add("ativo");
    menuMobile.setAttribute("aria-hidden", "false");
    menuMobileToggle.setAttribute("aria-expanded", "true");
    document.body.classList.add("sem-rolagem");
}

function fecharMenuMobile() {
    if (!menuMobile || !menuMobileToggle) {
        return;
    }

    menuMobile.classList.remove("ativo");
    menuMobile.setAttribute("aria-hidden", "true");
    menuMobileToggle.setAttribute("aria-expanded", "false");
    document.body.classList.remove("sem-rolagem");
}

function criarEstadoVazio() {
    const rotaHome = obterRotaHome();

    produtoContainer.innerHTML = `
        <section class="produto-estado">
            <p>Produto não encontrado.</p>
            <a href="${rotaHome}">Voltar para a vitrine</a>
        </section>
    `;
}

function obterResumoCuradoria(produto) {
    const categoria = String(produto.categoria || "").toLowerCase();

    if (categoria.includes("recheado")) {
        return `${produto.nome} traduz a nossa curadoria de recheados com textura cremosa, visual marcante e uma leitura mais envolvente do sabor.`;
    }

    if (categoria.includes("artesanal")) {
        return `${produto.nome} entra na vitrine como uma escolha artesanal de acabamento delicado, pensada para quem valoriza sabor e presença no mesmo gesto.`;
    }

    if (categoria.includes("presente")) {
        return `${produto.nome} foi selecionado para momentos de presente, com composição visual forte e um perfil que funciona muito bem em ocasiões especiais.`;
    }

    if (categoria.includes("premium") || categoria.includes("caixa")) {
        return `${produto.nome} apresenta uma proposta mais sofisticada, com foco em impacto visual, curadoria refinada e presença de vitrine.`;
    }

    if (categoria.includes("import")) {
        return `${produto.nome} amplia a vitrine com uma leitura importada, ideal para quem procura algo diferente e com identidade propria.`;
    }

    return `${produto.nome} foi escolhido para a vitrine por equilibrar sabor, acabamento e uma apresentacao elegante para consumo ou presente.`;
}

function obterMomentoIdeal(produto) {
    const categoria = String(produto.categoria || "").toLowerCase();

    if (categoria.includes("recheado")) {
        return "Ideal para quem gosta de chocolates mais cremosos, com uma entrega intensa logo no primeiro contato e uma sensacao mais indulgente ao longo da degustacao.";
    }

    if (categoria.includes("presente")) {
        return "Funciona muito bem para presentear, montar kits especiais ou criar uma experiência mais memorável em ocasiões afetivas.";
    }

    if (categoria.includes("premium") || categoria.includes("caixa")) {
        return "Excelente para momentos em que a apresentacao importa tanto quanto o sabor, com leitura mais sofisticada e visual de destaque.";
    }

    if (categoria.includes("import")) {
        return "Uma boa escolha para quem quer variar a selecao da casa com um item diferente, mais curioso e com perfil fora do comum.";
    }

    return "Perfeito para transformar uma pausa simples em um momento mais especial, seja para consumo próprio, para dividir ou para compor uma seleção presenteável.";
}

function obterDetalhesProduto(produto) {
    return [
        {
            rotulo: "Categoria",
            valor: produto.categoria || "Chocolate",
            apoio: "Linha em que este produto melhor se encaixa dentro da vitrine.",
        },
        {
            rotulo: "Peso",
            valor: produto.peso || "Edicao especial",
            apoio: "Formato e porte pensados para consumo individual ou para presentear.",
        },
        {
            rotulo: "Destaque",
            valor: produto.destaque || "Seleção da casa",
            apoio: "Selo curatorial usado para orientar a escolha visual e sensorial.",
        },
    ];
}

async function carregarSessaoProduto() {
    try {
        const resposta = await fetch("sessao-usuario.php", {
            headers: {
                Accept: "application/json",
            },
            credentials: "same-origin",
            cache: "no-store",
        });

        if (!resposta.ok) {
            return sessaoProduto;
        }

        const dados = await resposta.json();

        sessaoProduto = {
            autenticado: Boolean(dados?.autenticado),
            admin: Boolean(dados?.admin),
            papel: dados?.papel || null,
        };
    } catch (error) {
        sessaoProduto = {
            autenticado: false,
            admin: false,
            papel: null,
        };
    }

    return sessaoProduto;
}

function renderizarProduto(produto, relacionados) {
    const rotaHome = obterRotaHome();
    const categoria = produto.categoria || "Chocolate";
    const destaque = produto.destaque || "Seleção da casa";
    const referencia = produto.ref || "Sem referência";
    const descricao = produto.descricao || "Um chocolate especial preparado para transformar qualquer momento em algo memorável.";
    const imagens = Array.isArray(produto.imagens) ? produto.imagens.filter(Boolean) : [];
    const galeria = imagens.length > 0 ? imagens : [produto.imagem].filter(Boolean);
    const rotaVitrine = `${rotaHome}#vitrine`;
    const resumoCuradoria = obterResumoCuradoria(produto);
    const momentoIdeal = obterMomentoIdeal(produto);
    const podeVerReferencia = Boolean(sessaoProduto.admin);
    const detalhes = podeVerReferencia
        ? [
            ...obterDetalhesProduto(produto),
            {
                rotulo: "Referencia",
                valor: referencia,
                apoio: "Codigo interno para localizar este item com mais rapidez.",
            },
        ]
        : obterDetalhesProduto(produto);
    const miniaturas = galeria.map((imagem, index) => `
        <button type="button" class="produto-miniatura${index === 0 ? " ativo" : ""}" data-imagem="${escapeHtml(imagem)}" aria-label="Visualizar imagem ${index + 1} de ${escapeHtml(produto.nome)}" aria-pressed="${index === 0 ? "true" : "false"}">
            <img src="${escapeHtml(imagem)}" alt="${escapeHtml(produto.nome)}" loading="lazy">
        </button>
    `).join("");

    const detalhesCardsHtml = detalhes.map((detalhe) => `
        <article class="produto-spec">
            <span>${escapeHtml(detalhe.rotulo)}</span>
            <strong>${escapeHtml(detalhe.valor)}</strong>
            <p>${escapeHtml(detalhe.apoio)}</p>
        </article>
    `).join("");

    const detalhesStoryHtml = detalhes.map((detalhe) => `
        <div class="produto-story__item">
            <span>${escapeHtml(detalhe.rotulo)}</span>
            <strong>${escapeHtml(detalhe.valor)}</strong>
        </div>
    `).join("");

    const relacionadosHtml = relacionados.map((item) => `
        <a class="produto-relacionado" href="produto.html?id=${encodeURIComponent(item.slug)}">
            <img src="${escapeHtml(item.imagem)}" alt="${escapeHtml(item.nome)}" loading="lazy" decoding="async">
            <p class="produto-relacionado__categoria">${escapeHtml(item.categoria || "Chocolate")}</p>
            <strong>${escapeHtml(item.nome)}</strong>
            <span>${escapeHtml(formatarPreco(item.preco))}</span>
        </a>
    `).join("");

    produtoContainer.innerHTML = `
        <section class="produto-showcase">
            <div class="produto-marquee" aria-label="Curadoria Velle Dulcis">
                <span class="produto-marquee__linha" aria-hidden="true"></span>
                <p>Curadoria Velle Dulcis</p>
                <span class="produto-marquee__linha" aria-hidden="true"></span>
            </div>

            <section class="produto-detalhe">
                <div class="produto-coluna produto-coluna--media">
                    <div class="produto-galeria">
                        <div class="produto-imagem-principal">
                            <span class="produto-selo">${escapeHtml(destaque)}</span>
                            <img id="produto-imagem-atual" src="${escapeHtml(galeria[0] || "")}" alt="${escapeHtml(produto.nome)}" loading="eager" decoding="async">
                        </div>
                        <div class="produto-miniaturas" aria-label="Miniaturas do produto">
                            ${miniaturas}
                        </div>
                    </div>

                    <div class="produto-essencia">
                        <p class="produto-essencia__eyebrow">Seleção editorial</p>
                        <h2>Por que este chocolate se destaca</h2>
                        <p>${escapeHtml(resumoCuradoria)}</p>
                    </div>
                </div>

                <div class="produto-info">
                    <div class="produto-breadcrumb">
                        <a href="${escapeHtml(rotaHome)}">Início</a>
                        <span>&rsaquo;</span>
                        <span>${escapeHtml(categoria)}</span>
                        <span>&rsaquo;</span>
                        <span>${escapeHtml(produto.nome)}</span>
                    </div>

                    <p class="produto-kicker">${escapeHtml(categoria)}</p>
                    <h1>${escapeHtml(produto.nome)}</h1>

                    <div class="produto-resumo-meta">
                        ${produto.peso ? `<span>${escapeHtml(produto.peso)}</span>` : ""}
                        ${podeVerReferencia ? `<span>Ref. ${escapeHtml(referencia)}</span>` : ""}
                    </div>

                    <p class="produto-preco">${escapeHtml(formatarPreco(produto.preco))}</p>

                    <div class="produto-chips" aria-label="Destaques do produto">
                        <span class="produto-chip">${escapeHtml(destaque)}</span>
                        <span class="produto-chip">${escapeHtml(categoria)}</span>
                        ${produto.peso ? `<span class="produto-chip">${escapeHtml(produto.peso)}</span>` : ""}
                    </div>

                    <p class="produto-descricao">${escapeHtml(descricao)}</p>

                    <div class="produto-acoes">
                        <a class="produto-cta" href="${escapeHtml(rotaVitrine)}">Ver vitrine completa</a>
                        <a class="produto-cta produto-cta--secundaria" href="${escapeHtml(rotaHome)}">Continuar explorando</a>
                    </div>

                    <div class="produto-specs">
                        ${detalhesCardsHtml}
                    </div>
                </div>
            </section>

            <section class="produto-story">
                <article class="produto-story__bloco">
                    <p class="produto-story__eyebrow">Experiência</p>
                    <h2>Uma escolha pensada para momentos especiais</h2>
                    <p>${escapeHtml(momentoIdeal)}</p>
                </article>

                <article class="produto-story__bloco produto-story__bloco--lista">
                    <p class="produto-story__eyebrow">Ficha rapida</p>
                    <div class="produto-story__itens">
                        ${detalhesStoryHtml}
                    </div>
                </article>
            </section>

            ${relacionados.length > 0 ? `
                <section class="produto-relacionados-secao">
                    <div class="produto-relacionados-head">
                        <div>
                            <p class="produto-relacionados-head__eyebrow">Continue explorando</p>
                            <h2>Outros chocolates com a mesma leitura de vitrine</h2>
                        </div>
                        <a class="produto-relacionados-head__link" href="${escapeHtml(rotaVitrine)}">Ver toda a vitrine</a>
                    </div>
                    <div class="produto-relacionados-lista">${relacionadosHtml}</div>
                </section>
            ` : ""}
        </section>
    `;

    const imagemAtual = document.getElementById("produto-imagem-atual");
    const botoesMiniatura = produtoContainer.querySelectorAll(".produto-miniatura");

    botoesMiniatura.forEach((botao) => {
        botao.addEventListener("click", () => {
            const imagemSelecionada = botao.dataset.imagem;

            if (!imagemAtual || !imagemSelecionada) {
                return;
            }

            imagemAtual.src = imagemSelecionada;
            imagemAtual.alt = produto.nome;
            botoesMiniatura.forEach((item) => {
                item.classList.remove("ativo");
                item.setAttribute("aria-pressed", "false");
            });
            botao.classList.add("ativo");
            botao.setAttribute("aria-pressed", "true");
        });
    });
}

async function iniciarPaginaProduto() {
    const params = new URLSearchParams(window.location.search);
    const identificador = params.get("id");

    await carregarSessaoProduto();

    if (!identificador) {
        criarEstadoVazio();
        return;
    }

    const produto = await buscarChocolatePorIdentificador(identificador);

    if (!produto) {
        criarEstadoVazio();
        return;
    }

    document.title = `${produto.nome} | Velle Dulcis`;

    const chocolates = await carregarTodosChocolates();
    const relacionados = chocolates
        .filter((item) => item.id !== produto.id)
        .sort((itemA, itemB) => {
            const mesmaCategoriaA = itemA.categoria === produto.categoria ? 1 : 0;
            const mesmaCategoriaB = itemB.categoria === produto.categoria ? 1 : 0;

            if (mesmaCategoriaA !== mesmaCategoriaB) {
                return mesmaCategoriaB - mesmaCategoriaA;
            }

            return String(itemA.nome || "").localeCompare(String(itemB.nome || ""), "pt-BR");
        })
        .slice(0, 4);

    renderizarProduto(produto, relacionados);
}

atualizarLinksDeRetorno();

if (menuMobileToggle) {
    menuMobileToggle.addEventListener("click", () => {
        if (menuMobileAberto()) {
            fecharMenuMobile();
            return;
        }

        abrirMenuMobile();
    });
}

menuMobileCloseButtons.forEach((button) => {
    button.addEventListener("click", fecharMenuMobile);
});

menuMobileLinks.forEach((link) => {
    link.addEventListener("click", fecharMenuMobile);
});

window.addEventListener("resize", () => {
    if (window.innerWidth > 768 && menuMobileAberto()) {
        fecharMenuMobile();
    }
});

window.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && menuMobileAberto()) {
        fecharMenuMobile();
    }
});

iniciarPaginaProduto();
