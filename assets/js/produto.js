(function () {
    const productContainer = document.getElementById("produto-container");
    const HOME_ROUTE_STORAGE_KEY = "imperio_home_route";
    let productSession = {
        autenticado: false,
        admin: false,
        papel: null,
        nome: "",
        destinoConta: "login.php",
        csrfToken: "",
    };

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatPrice(value) {
        return new Intl.NumberFormat("pt-BR", {
            style: "currency",
            currency: "BRL",
        }).format(Number(value || 0));
    }

    function getHomeRoute() {
        return sessionStorage.getItem(HOME_ROUTE_STORAGE_KEY) || "index.php";
    }

    function getMeasurementLabel(value) {
        const normalized = String(value || "").trim();

        if (/\b\d+(?:[.,]\d+)?\s*(?:mg|g|kg)\b/i.test(normalized)) {
            return "Peso";
        }

        if (/^\d+\s+/i.test(normalized)) {
            return "Conteúdo";
        }

        return "Apresentação";
    }

    function getProductDetails(product) {
        const details = [];
        const presentation = String(product.peso || "").trim();
        const presentationLabel = getMeasurementLabel(presentation);
        const weightInGrams = Number(product.peso_gramas);

        if (product.categoria) {
            details.push({ label: "Categoria", value: product.categoria });
        }

        if (Number.isFinite(weightInGrams) && weightInGrams > 0) {
            const formattedWeight = weightInGrams >= 1000 && weightInGrams % 1000 === 0
                ? `${weightInGrams / 1000} kg`
                : `${weightInGrams} g`;
            details.push({ label: "Peso", value: formattedWeight });
        }

        if (presentation && (presentationLabel !== "Peso" || !(weightInGrams > 0))) {
            details.push({ label: presentationLabel, value: presentation });
        }

        if (product.destaque) {
            details.push({ label: "Destaque", value: product.destaque });
        }

        if (productSession.admin && product.ref) {
            details.push({ label: "Referência", value: product.ref });
        }

        return details;
    }

    async function loadProductSession() {
        try {
            const response = await fetch("sessao-usuario.php", {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
                cache: "no-store",
            });

            if (!response.ok) {
                return productSession;
            }

            const data = await response.json();
            productSession = {
                autenticado: Boolean(data?.autenticado),
                admin: Boolean(data?.admin),
                papel: data?.papel || null,
                nome: String(data?.nome || ""),
                destinoConta: String(data?.destino_conta || "login.php"),
                csrfToken: String(data?.csrf_token || ""),
            };
        } catch (error) {
            console.warn("Não foi possível carregar a sessão nesta página.", error);
        }

        window.APP_AUTH = {
            autenticado: productSession.autenticado,
            nome: productSession.nome,
            papel: productSession.papel,
            destinoConta: productSession.destinoConta,
            mensagemBoasVindas: "",
        };
        window.APP_CSRF_TOKEN = productSession.csrfToken;
        window.VelleComponents?.updateHeaderAuth({
            authenticated: productSession.autenticado,
            name: productSession.nome,
            role: productSession.papel,
            accountHref: productSession.destinoConta,
        });

        const loginLink = document.querySelector("#popup-aviso-conta a");
        if (loginLink) {
            loginLink.href = productSession.destinoConta;
        }

        return productSession;
    }

    function loadStorefrontScript() {
        if (typeof window.adicionarAoCarrinho === "function") {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = "../assets/js/script.js?v=20260811-1";
            script.onload = resolve;
            script.onerror = () => reject(new Error("Não foi possível carregar os recursos compartilhados da loja."));
            document.body.appendChild(script);
        });
    }

    function renderEmptyState() {
        const homeRoute = getHomeRoute();
        productContainer.innerHTML = `
            <section class="produto-estado" aria-labelledby="produto-estado-titulo">
                <p class="produto-kicker">Velle Dulcis</p>
                <h1 id="produto-estado-titulo">Produto não encontrado</h1>
                <p>Este item não está disponível na vitrine atual.</p>
                <a class="produto-link" href="${escapeHtml(homeRoute)}#vitrine">Voltar para a vitrine <span aria-hidden="true">→</span></a>
            </section>
        `;
        document.body.classList.remove("pagina-carregando");
    }

    function renderGallery(product, gallery) {
        const thumbnails = gallery.length > 1
            ? `<div class="produto-miniaturas" aria-label="Outras imagens do produto">${gallery.map((image, index) => `
                <button type="button" class="produto-miniatura${index === 0 ? " ativo" : ""}" data-imagem="${escapeHtml(image)}" aria-label="Visualizar imagem ${index + 1} de ${escapeHtml(product.nome)}" aria-pressed="${index === 0 ? "true" : "false"}">
                    <img src="${escapeHtml(image)}" alt="" loading="lazy">
                </button>
            `).join("")}</div>`
            : "";

        return `
            <div class="produto-galeria">
                <div class="produto-imagem-principal">
                    <img id="produto-imagem-atual" src="${escapeHtml(gallery[0] || "")}" alt="${escapeHtml(product.nome)}" fetchpriority="high" decoding="async">
                </div>
                ${thumbnails}
            </div>
        `;
    }

    function renderProduct(product, relatedProducts) {
        const homeRoute = getHomeRoute();
        const gallerySource = Array.isArray(product.imagens) ? product.imagens.filter(Boolean) : [];
        const gallery = gallerySource.length > 0 ? gallerySource : [product.imagem].filter(Boolean);
        const details = getProductDetails(product);
        const category = String(product.categoria || "Chocolate");
        const description = String(product.descricao || "").trim();
        const highlight = String(product.destaque || "").trim();
        const detailMarkup = details.map((detail) => `
            <div class="produto-detalhe-item">
                <dt>${escapeHtml(detail.label)}</dt>
                <dd>${escapeHtml(detail.value)}</dd>
            </div>
        `).join("");

        productContainer.innerHTML = `
            <article class="produto-showcase" aria-labelledby="produto-titulo">
                <section class="produto-hero">
                    ${renderGallery(product, gallery)}
                    <div class="produto-info">
                        <nav class="produto-breadcrumb" aria-label="Navegação estrutural">
                            <a href="${escapeHtml(homeRoute)}">Início</a><span aria-hidden="true">/</span><a href="${escapeHtml(homeRoute)}#vitrine">Chocolate</a>
                        </nav>
                        <p class="produto-kicker">${escapeHtml(category)}</p>
                        <h1 id="produto-titulo">${escapeHtml(product.nome)}</h1>
                        <p class="produto-preco">${escapeHtml(formatPrice(product.preco))}</p>
                        ${description ? `<p class="produto-descricao">${escapeHtml(description)}</p>` : ""}
                        <div class="produto-acoes">
                            <button class="button produto-adicionar" type="button">Adicionar à sacola</button>
                            <a class="produto-link" href="${escapeHtml(homeRoute)}#vitrine">Continuar explorando <span aria-hidden="true">→</span></a>
                        </div>
                        ${highlight ? `<p class="produto-resumo">${escapeHtml(highlight)}${product.peso ? ` · ${escapeHtml(product.peso)}` : ""}</p>` : ""}
                    </div>
                </section>

                ${details.length > 0 ? `
                    <section class="produto-detalhes-secao" aria-labelledby="produto-detalhes-titulo">
                        <div class="produto-secao-cabecalho"><p class="produto-kicker">Informações do produto</p><h2 id="produto-detalhes-titulo">Detalhes</h2></div>
                        <dl class="produto-detalhes-lista">${detailMarkup}</dl>
                    </section>
                ` : ""}

                ${highlight && description ? `
                    <section class="produto-editorial" aria-labelledby="produto-editorial-titulo">
                        <p class="produto-kicker">Seleção editorial</p>
                        <div class="produto-editorial__conteudo">
                            <h2 id="produto-editorial-titulo">Por que este chocolate se destaca</h2>
                            <div><strong>${escapeHtml(highlight)}</strong><p>${escapeHtml(description)}</p></div>
                        </div>
                    </section>
                ` : ""}

                ${relatedProducts.length > 0 ? `
                    <section class="produto-relacionados-secao" aria-labelledby="produto-relacionados-titulo">
                        <div class="produto-relacionados-head"><div><p class="produto-kicker">Descubra também</p><h2 id="produto-relacionados-titulo">Você também pode gostar</h2></div><a class="produto-link" href="${escapeHtml(homeRoute)}#vitrine">Ver toda a vitrine <span aria-hidden="true">→</span></a></div>
                        <div class="produto-relacionados-lista"></div>
                    </section>
                ` : ""}
            </article>
        `;

        const addButton = productContainer.querySelector(".produto-adicionar");
        addButton?.addEventListener("click", () => {
            window.adicionarAoCarrinho?.(product.nome, product.preco, product.imagem);
            addButton.textContent = "Adicionado à sacola";
            window.setTimeout(() => {
                addButton.textContent = "Adicionar à sacola";
            }, 1800);
        });

        const currentImage = document.getElementById("produto-imagem-atual");
        const thumbnailButtons = [...productContainer.querySelectorAll(".produto-miniatura")];
        thumbnailButtons.forEach((button) => {
            button.addEventListener("click", () => {
                if (!currentImage || !button.dataset.imagem) {
                    return;
                }

                currentImage.src = button.dataset.imagem;
                thumbnailButtons.forEach((item) => {
                    const active = item === button;
                    item.classList.toggle("ativo", active);
                    item.setAttribute("aria-pressed", String(active));
                });
            });
        });

        const relatedList = productContainer.querySelector(".produto-relacionados-lista");
        relatedProducts.forEach((item, index) => {
            const card = window.VelleProductCard?.create(item, {
                index,
                onAdd: (relatedProduct) => window.adicionarAoCarrinho?.(relatedProduct.nome, relatedProduct.preco, relatedProduct.imagem),
            });

            if (card) {
                relatedList?.appendChild(card);
            }
        });
    }

    async function initializeProductPage() {
        const identifier = new URLSearchParams(window.location.search).get("id");

        await loadProductSession();

        try {
            await loadStorefrontScript();
        } catch (error) {
            console.warn(error);
        }

        if (!identifier) {
            renderEmptyState();
            return;
        }

        const product = await buscarChocolatePorIdentificador(identifier);
        if (!product) {
            renderEmptyState();
            return;
        }

        document.title = `${product.nome} | Velle Dulcis`;
        const allProducts = await carregarTodosChocolates();
        const relatedProducts = allProducts
            .filter((item) => item.id !== product.id)
            .sort((productA, productB) => {
                const categoryA = productA.categoria === product.categoria ? 1 : 0;
                const categoryB = productB.categoria === product.categoria ? 1 : 0;
                return categoryB - categoryA || String(productA.nome || "").localeCompare(String(productB.nome || ""), "pt-BR");
            })
            .slice(0, 4);

        renderProduct(product, relatedProducts);
        document.body.classList.remove("pagina-carregando");
    }

    initializeProductPage();
}());
