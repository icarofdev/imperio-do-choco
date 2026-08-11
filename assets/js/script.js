const listaCarrinho = document.getElementById("lista-carrinho");
const qtdItens = document.getElementById("qtd-itens");
const btnCarrinho = document.getElementById("btn-carrinho");
const carrinhoLateral = document.getElementById("carrinho-lateral");
const overlayCarrinho = document.getElementById("overlay-carrinho");
const fecharCarrinho = document.getElementById("fechar-carrinho");
const tituloCarrinho = document.querySelector(".carrinho-topo h3");
const resumoCarrinho = document.querySelector(".carrinho-resumo");
const subtotalPreco = document.getElementById("subtotal-preco");
const totalPrecoElement = document.getElementById("total-preco");
const abrirPesquisa = document.getElementById("abrir-pesquisa");
const overlayPesquisa = document.getElementById("overlay-pesquisa");
const fecharPesquisa = document.getElementById("fechar-pesquisa");
const barraPesquisa = document.getElementById("barra-pesquisa");
const btnLimparPesquisa = document.getElementById("btn-limpar-pesquisa");
const btnVerTodos = document.getElementById("btn-ver-todos");
const container = document.getElementById("container-cards");
const vitrineCount = document.getElementById("vitrine-count");
const vitrineSortButtons = document.querySelectorAll("[data-vitrine-sort]");
const vitrineScrollButtons = document.querySelectorAll("[data-vitrine-scroll]");
const instagramLinks = document.querySelectorAll('a[aria-label="Instagram"]');
const painelPesquisa = document.getElementById("painel-pesquisa");
const listaSugestoes = document.getElementById("lista-sugestoes");
const listaResultadosPesquisa = document.getElementById("lista-resultados-pesquisa");
const listaAtalhosPesquisa = document.getElementById("lista-atalhos-pesquisa");
const filtrosCategoria = document.getElementById("filtros-categoria");
const popupCarrinho = document.getElementById("popup-carrinho");
const popupCarrinhoProduto = document.getElementById("popup-carrinho-produto");
const popupCarrinhoBarra = document.getElementById("popup-carrinho-barra");
const popupRemocao = document.getElementById("popup-remocao");
const popupRemocaoTexto = document.getElementById("popup-remocao-texto");
const fecharPopupRemocao = document.getElementById("fechar-popup-remocao");
const popupBoasVindas = document.getElementById("popup-boas-vindas");
const fecharPopupBoasVindas = document.getElementById("fechar-popup-boas-vindas");
const popupAvisoConta = document.getElementById("popup-aviso-conta");
const fecharPopupAvisoConta = document.getElementById("fechar-popup-aviso-conta");
const btnFinalizarPedido = document.getElementById("finalizar");
const headerSite = document.querySelector(".topo-site");
const menuMobileToggle = document.getElementById("menu-mobile-toggle");
const menuMobile = document.getElementById("menu-mobile");
const menuMobileCloseButtons = document.querySelectorAll("[data-menu-mobile-close]");
const menuMobileActionButtons = document.querySelectorAll("[data-mobile-action]");
const menuMobileLinks = document.querySelectorAll(".menu-mobile__link, .menu-mobile__acao-link");
const HOME_ROUTE_STORAGE_KEY = "imperio_home_route";
const VITRINE_SORT_STORAGE_KEY = "imperio_vitrine_sort";
const VITRINE_SORT_OPTIONS = new Set(["recommended", "price-asc"]);
const CART_SYNC_URL = "carrinho.php";
const CHECKOUT_URL = "finalizar-pedido.php";
const PRODUCT_IMAGE_FALLBACK = "../assets/images/products/product-tablete-classico.webp";
const APP_CSRF_TOKEN = String(window.APP_CSRF_TOKEN || "");
const rotaHomeAtual = "index.php";
const HEADER_COMPACT_ENTER_SCROLL = 80;
const HEADER_COMPACT_EXIT_SCROLL = 28;
const MOBILE_MENU_CLOSE_DURATION = 320;

aplicarLinkDaConta();
sessionStorage.setItem(HOME_ROUTE_STORAGE_KEY, rotaHomeAtual);

let timeoutFechamentoMenuMobile;

function menuMobileAberto() {
    return Boolean(menuMobile && menuMobile.classList.contains("ativo"));
}

function abrirMenuMobile() {
    if (!menuMobile || !menuMobileToggle) {
        return;
    }

    fecharPopupAvisoContaComEstado();
    clearTimeout(timeoutFechamentoMenuMobile);
    menuMobile.classList.remove("menu-mobile--fechando");
    menuMobile.classList.add("ativo");
    menuMobile.setAttribute("aria-hidden", "false");
    menuMobileToggle.setAttribute("aria-expanded", "true");
    document.body.classList.add("menu-mobile-aberto");
    document.body.classList.add("sem-rolagem");
}

function fecharMenuMobile() {
    if (!menuMobile || !menuMobileToggle) {
        return;
    }

    if (!menuMobile.classList.contains("ativo") && !menuMobile.classList.contains("menu-mobile--fechando")) {
        return;
    }

    clearTimeout(timeoutFechamentoMenuMobile);
    menuMobile.classList.remove("ativo");
    menuMobile.classList.add("menu-mobile--fechando");
    menuMobileToggle.setAttribute("aria-expanded", "false");

    timeoutFechamentoMenuMobile = window.setTimeout(() => {
        menuMobile.classList.remove("menu-mobile--fechando");
        menuMobile.setAttribute("aria-hidden", "true");
        document.body.classList.remove("menu-mobile-aberto");

        if (overlayPesquisa.classList.contains("oculto") && !carrinhoLateral.classList.contains("ativo")) {
            document.body.classList.remove("sem-rolagem");
        }
    }, MOBILE_MENU_CLOSE_DURATION);
}

function atualizarEstadoHeader() {
    if (!headerSite) {
        return;
    }

    if (headerSite.dataset.headerSurface === "true") {
        headerSite.classList.add("topo-site--compact");
        return;
    }

    const headerCompacto = headerSite.classList.contains("topo-site--compact");
    const scrollAtual = window.scrollY;

    if (!headerCompacto && scrollAtual >= HEADER_COMPACT_ENTER_SCROLL) {
        headerSite.classList.add("topo-site--compact");
        return;
    }

    if (headerCompacto && scrollAtual <= HEADER_COMPACT_EXIT_SCROLL) {
        headerSite.classList.remove("topo-site--compact");
    }
}

atualizarEstadoHeader();

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

menuMobileActionButtons.forEach((button) => {
    button.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();

        const action = button.dataset.mobileAction;
        fecharMenuMobile();

        window.setTimeout(() => {
            if (action === "search") {
                abrirOverlayPesquisa();
            }

            if (action === "cart") {
                void abrirCarrinho();
            }
        }, MOBILE_MENU_CLOSE_DURATION + 20);
    });
});

window.addEventListener("scroll", () => {
    atualizarEstadoHeader();
    reposicionarPopupCarrinhoAtivo();
}, { passive: true });
window.addEventListener("resize", () => {
    if (window.innerWidth > 768 && menuMobileAberto()) {
        fecharMenuMobile();
    }

    reposicionarPopupCarrinhoAtivo();
});
window.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && menuMobileAberto()) {
        fecharMenuMobile();
    }
});

let carrinho = [];
let listaChocolates = [];
let popupCarrinhoTimeout;
let popupCarrinhoInicio = 0;
let popupCarrinhoTempoRestante = 0;
let popupCarrinhoUltimoProduto = "";
let popupRemocaoTimeout;
let popupBoasVindasTimeout;
let popupAvisoContaTimeout;
let sincronizandoCarrinho = false;
let timeoutFechamentoPesquisa;
let carregamentoCarrinhoPromise = Promise.resolve();
let ordenacaoVitrine = obterOrdenacaoInicial();
let categoriaAtiva = "";
let vitrineArrastando = false;
let vitrineArrasteInicioX = 0;
let vitrineScrollInicio = 0;

const DURACAO_POPUP_CARRINHO = 4000;

function obterRotasApp() {
    return {
        home: "index.php",
        login: "login.php",
        conta: "conta.php",
    };
}

function aplicarLinkDaConta() {
    const rotas = obterRotasApp();
    const destinoConta = window.APP_AUTH?.destinoConta || rotas.login;
    const usuarioAutenticado = Boolean(window.APP_AUTH?.autenticado);
    const nomeUsuario = String(window.APP_AUTH?.nome || "").trim();

    document.querySelectorAll("#link-conta, #link-conta-mobile").forEach((link) => {
        link.href = destinoConta;
        link.dataset.logado = usuarioAutenticado ? "true" : "false";
    });

    document.querySelectorAll("[data-hide-when-authenticated]").forEach((elemento) => {
        elemento.hidden = usuarioAutenticado;
    });

    const textoContaMobile = document.getElementById("link-conta-mobile-texto");
    const textoConta = document.getElementById("link-conta-texto");

    if (textoContaMobile) {
        textoContaMobile.textContent = usuarioAutenticado ? (nomeUsuario || "Minha conta") : "Entrar";
    }

    if (textoConta && !usuarioAutenticado) {
        textoConta.textContent = "Minha conta";
    }
}

function esconderPopupCarrinho() {
    popupCarrinho.classList.remove("ativo");
    popupCarrinho.setAttribute("aria-hidden", "true");
}

function encerrarPopupCarrinho() {
    clearTimeout(popupCarrinhoTimeout);
    popupCarrinhoInicio = 0;
    popupCarrinhoTempoRestante = 0;
    popupCarrinhoUltimoProduto = "";
    esconderPopupCarrinho();
}

function animarBarraPopup(duracao, proporcaoInicial = 1) {
    popupCarrinhoBarra.style.transition = "none";
    popupCarrinhoBarra.style.transform = `scaleX(${proporcaoInicial})`;
    popupCarrinhoBarra.offsetHeight;
    popupCarrinhoBarra.style.transition = `transform ${duracao}ms linear`;
    popupCarrinhoBarra.style.transform = "scaleX(0)";
}

function posicionarPopupCarrinho() {
    if (!popupCarrinho || !btnCarrinho) {
        return;
    }

    const margemTela = window.innerWidth <= 768 ? 14 : 12;
    const larguraPopup = Math.min(window.innerWidth <= 768 ? 300 : 340, window.innerWidth - margemTela * 2);
    const carrinhoRect = btnCarrinho.getBoundingClientRect();
    const centroCarrinho = carrinhoRect.left + carrinhoRect.width / 2;
    const esquerdaIdeal = carrinhoRect.right - larguraPopup + 26;
    const esquerdaPopup = Math.min(
        Math.max(esquerdaIdeal, margemTela),
        window.innerWidth - larguraPopup - margemTela
    );
    const setaTamanho = window.innerWidth <= 768 ? 16 : 20;
    const setaLeft = Math.min(
        Math.max(centroCarrinho - esquerdaPopup - setaTamanho / 2, 18),
        larguraPopup - 28
    );

    popupCarrinho.style.setProperty("--popup-carrinho-top", `${carrinhoRect.bottom + 14}px`);
    popupCarrinho.style.setProperty("--popup-carrinho-left", `${esquerdaPopup}px`);
    popupCarrinho.style.setProperty("--popup-carrinho-seta-left", `${setaLeft}px`);
}

function reposicionarPopupCarrinhoAtivo() {
    if (popupCarrinho && popupCarrinho.classList.contains("ativo")) {
        posicionarPopupCarrinho();
    }
}

function mostrarPopupCarrinho(nomeProduto, duracao = DURACAO_POPUP_CARRINHO, proporcaoInicial = 1) {
    clearTimeout(popupCarrinhoTimeout);
    popupCarrinhoUltimoProduto = nomeProduto;
    popupCarrinhoTempoRestante = duracao;

    if (carrinhoLateral.classList.contains("ativo")) {
        popupCarrinhoInicio = 0;
        popupCarrinhoBarra.style.transition = "none";
        popupCarrinhoBarra.style.transform = `scaleX(${proporcaoInicial})`;
        esconderPopupCarrinho();
        return;
    }

    popupCarrinhoInicio = Date.now();

    popupCarrinhoProduto.textContent = nomeProduto;
    posicionarPopupCarrinho();
    popupCarrinho.classList.add("ativo");
    popupCarrinho.setAttribute("aria-hidden", "false");
    animarBarraPopup(duracao, proporcaoInicial);

    popupCarrinhoTimeout = setTimeout(encerrarPopupCarrinho, duracao);
}

function pausarPopupCarrinho() {
    if (!popupCarrinho.classList.contains("ativo") || !popupCarrinhoInicio) {
        return;
    }

    const tempoDecorrido = Date.now() - popupCarrinhoInicio;
    popupCarrinhoTempoRestante = Math.max(0, popupCarrinhoTempoRestante - tempoDecorrido);

    clearTimeout(popupCarrinhoTimeout);

    if (popupCarrinhoTempoRestante <= 0) {
        encerrarPopupCarrinho();
        return;
    }

    const proporcaoRestante = popupCarrinhoTempoRestante / DURACAO_POPUP_CARRINHO;
    popupCarrinhoBarra.style.transition = "none";
    popupCarrinhoBarra.style.transform = `scaleX(${proporcaoRestante})`;
    popupCarrinhoInicio = 0;
    esconderPopupCarrinho();
}

function retomarPopupCarrinho() {
    if (!popupCarrinhoUltimoProduto || popupCarrinhoTempoRestante <= 0 || carrinhoLateral.classList.contains("ativo")) {
        return;
    }

    mostrarPopupCarrinho(
        popupCarrinhoUltimoProduto,
        popupCarrinhoTempoRestante,
        popupCarrinhoTempoRestante / DURACAO_POPUP_CARRINHO
    );
}

function mostrarPopupRemocao(nomeProduto) {
    mostrarPopupAcao(`${nomeProduto} removido do carrinho.`);
}

function mostrarPopupAcao(mensagem) {
    clearTimeout(popupRemocaoTimeout);
    popupRemocaoTexto.textContent = mensagem;
    popupRemocao.classList.add("ativo");
    popupRemocao.setAttribute("aria-hidden", "false");

    popupRemocaoTimeout = setTimeout(() => {
        popupRemocao.classList.remove("ativo");
        popupRemocao.setAttribute("aria-hidden", "true");
    }, 3500);
}

function fecharPopupBoasVindasComEstado() {
    if (!popupBoasVindas) {
        return;
    }

    clearTimeout(popupBoasVindasTimeout);
    popupBoasVindas.classList.remove("ativo");
    popupBoasVindas.setAttribute("aria-hidden", "true");
}

function exibirPopupBoasVindas() {
    if (!popupBoasVindas || !window.APP_AUTH || !window.APP_AUTH.mensagemBoasVindas) {
        return;
    }

    popupBoasVindas.classList.add("ativo");
    popupBoasVindas.setAttribute("aria-hidden", "false");
    popupBoasVindasTimeout = setTimeout(fecharPopupBoasVindasComEstado, 4200);
}

function fecharPopupAvisoContaComEstado() {
    if (!popupAvisoConta) {
        return;
    }

    clearTimeout(popupAvisoContaTimeout);
    popupAvisoConta.classList.remove("ativo");
    popupAvisoConta.setAttribute("aria-hidden", "true");
}

function exibirPopupAvisoConta() {
    if (!popupAvisoConta || usuarioTemContaLogada() || menuMobileAberto()) {
        return;
    }

    clearTimeout(popupAvisoContaTimeout);
    popupAvisoContaTimeout = window.setTimeout(() => {
        if (usuarioTemContaLogada() || menuMobileAberto()) {
            fecharPopupAvisoContaComEstado();
            return;
        }

        popupAvisoConta.classList.add("ativo");
        popupAvisoConta.setAttribute("aria-hidden", "false");
    }, 700);
}

function obterQuantidadeNoCarrinho(nomeProduto) {
    const item = carrinho.find((produto) => produto.nome === nomeProduto);
    return item ? item.qtd : 0;
}

function usuarioTemCarrinhoPersistente() {
    return Boolean(window.APP_AUTH && window.APP_AUTH.autenticado);
}

function usuarioTemContaLogada() {
    if (typeof usuarioEstaAutenticado === "function") {
        return usuarioEstaAutenticado();
    }

    return usuarioTemCarrinhoPersistente();
}

function obterDadosProduto(nomeProduto, imagemProduto = "") {
    return listaChocolates.find((produto) =>
        produto.nome === nomeProduto && (!imagemProduto || produto.imagem === imagemProduto)
    ) || listaChocolates.find((produto) => produto.nome === nomeProduto) || null;
}

function normalizarItemCarrinho(item) {
    const nome = String(item?.nome || "").trim();
    const imagem = String(item?.imagem || "").trim();
    const produtoRelacionado = obterDadosProduto(nome, imagem);
    const produtoIdBruto = item?.produto_id ?? item?.produtoId ?? produtoRelacionado?.id ?? null;
    const produtoId = Number.parseInt(String(produtoIdBruto ?? ""), 10);
    const slug = String(item?.slug || produtoRelacionado?.slug || "").trim();
    const chave = String(
        item?.chave || slug || (typeof slugifyProductName === "function" ? slugifyProductName(nome) : nome)
    ).trim();

    return {
        produto_id: Number.isInteger(produtoId) && produtoId > 0 ? produtoId : null,
        chave,
        slug,
        nome,
        preco: Number(item?.preco || 0),
        imagem: imagem || produtoRelacionado?.imagem || "",
        qtd: Math.max(1, Number.parseInt(item?.qtd, 10) || 1),
    };
}

function obterPayloadCarrinho() {
    return carrinho
        .map((item) => normalizarItemCarrinho(item))
        .filter((item) => item.nome && item.chave && item.qtd > 0);
}

function adicionarCsrfAoPayload(payload) {
    return APP_CSRF_TOKEN ? { ...payload, csrf_token: APP_CSRF_TOKEN } : payload;
}

function adicionarHeaderCsrf(headers = {}) {
    return APP_CSRF_TOKEN ? { ...headers, "X-CSRF-Token": APP_CSRF_TOKEN } : headers;
}

async function persistirCarrinhoNoServidor() {
    if (!usuarioTemCarrinhoPersistente() || sincronizandoCarrinho) {
        return;
    }

    try {
        const resposta = await fetch(CART_SYNC_URL, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                ...adicionarHeaderCsrf(),
            },
            body: JSON.stringify(adicionarCsrfAoPayload({
                itens: obterPayloadCarrinho(),
            })),
        });

        if (!resposta.ok) {
            console.warn("Nao foi possivel salvar o carrinho da conta.", resposta.status);
        }
    } catch (erro) {
        console.warn("Nao foi possivel salvar o carrinho da conta.", erro);
    }
}

function salvarCarrinhoLocal() {
    if (usuarioTemCarrinhoPersistente()) {
        return;
    }

    try {
        localStorage.setItem("carrinho", JSON.stringify(carrinho));
    } catch (erro) {
        console.warn("Nao foi possivel salvar o carrinho local.", erro);
    }
}

function carregarCarrinhoLocal() {
    try {
        const carrinhoSalvo = JSON.parse(localStorage.getItem("carrinho")) || [];
        carrinho = Array.isArray(carrinhoSalvo)
            ? carrinhoSalvo.map((item) => normalizarItemCarrinho(item)).filter((item) => item.nome && item.chave)
            : [];
    } catch (erro) {
        console.warn("Nao foi possivel ler o carrinho local.", erro);
        carrinho = [];
    }
}

async function carregarCarrinhoDaConta() {
    if (!usuarioTemCarrinhoPersistente()) {
        carregarCarrinhoLocal();
        atualizarCarrinho(false);
        return;
    }

    sincronizandoCarrinho = true;

    try {
        const resposta = await fetch(CART_SYNC_URL, {
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
            },
        });

        if (!resposta.ok) {
            return;
        }

        const dados = await resposta.json();
        carrinho = Array.isArray(dados.itens)
            ? dados.itens.map((item) => normalizarItemCarrinho(item)).filter((item) => item.nome && item.chave)
            : [];
        atualizarCarrinho(false);
    } catch (erro) {
        console.warn("Nao foi possivel carregar o carrinho salvo da conta.", erro);
    } finally {
        sincronizandoCarrinho = false;
    }
}

function persistirCarrinhoDeSaida() {
    if (!usuarioTemCarrinhoPersistente()) {
        salvarCarrinhoLocal();
        return;
    }

    const payload = JSON.stringify(adicionarCsrfAoPayload({
        itens: obterPayloadCarrinho(),
    }));

    if (navigator.sendBeacon) {
        navigator.sendBeacon(CART_SYNC_URL, new Blob([payload], { type: "application/json" }));
    }
}

async function finalizarPedido() {
    if (!usuarioTemCarrinhoPersistente()) {
        window.location.href = obterRotasApp().login;
        return;
    }

    const itensParaFinalizar = obterPayloadCarrinho();

    if (itensParaFinalizar.length === 0) {
        mostrarPopupAcao("Seu carrinho esta vazio.");
        return;
    }

    if (btnFinalizarPedido) {
        btnFinalizarPedido.disabled = true;
        btnFinalizarPedido.textContent = "Finalizando...";
    }

    try {
        await persistirCarrinhoNoServidor();

        const resposta = await fetch(CHECKOUT_URL, {
            method: "POST",
            credentials: "same-origin",
            headers: adicionarHeaderCsrf({
                "Accept": "application/json",
            }),
        });
        const dados = await resposta.json();

        if (!resposta.ok) {
            if (dados?.codigo === "endereco_principal_ausente") {
                window.location.href = obterRotasApp().conta;
                return;
            }

            if (dados?.codigo === "telefone_principal_ausente") {
                window.location.href = obterRotasApp().conta;
                return;
            }

            mostrarPopupAcao(String(dados?.erro || "Nao foi possivel finalizar o pedido."));
            return;
        }

        carrinho = [];
        atualizarCarrinho(false);
        localStorage.removeItem("carrinho");
        mostrarPopupAcao(`Pedido ${dados.numero_pedido} finalizado com sucesso.`);
        window.location.href = `${obterRotasApp().conta}?pedido=${encodeURIComponent(dados.numero_pedido || "")}`;
    } catch (erro) {
        console.warn("Nao foi possivel finalizar o pedido.", erro);
        mostrarPopupAcao("Nao foi possivel finalizar o pedido.");
    } finally {
        if (btnFinalizarPedido) {
            btnFinalizarPedido.disabled = false;
            btnFinalizarPedido.textContent = "Finalizar pedido";
        }
    }
}

function obterOrdenacaoInicial() {
    try {
        const ordenacaoSalva = localStorage.getItem(VITRINE_SORT_STORAGE_KEY);

        if (VITRINE_SORT_OPTIONS.has(ordenacaoSalva)) {
            return ordenacaoSalva;
        }
    } catch (erro) {
        console.warn("Nao foi possivel ler a ordenacao da vitrine.", erro);
    }

    return "recommended";
}

function salvarOrdenacaoVitrine() {
    try {
        localStorage.setItem(VITRINE_SORT_STORAGE_KEY, ordenacaoVitrine);
    } catch (erro) {
        console.warn("Nao foi possivel salvar a ordenacao da vitrine.", erro);
    }
}

function atualizarBotoesOrdenacao() {
    vitrineSortButtons.forEach((button) => {
        const ativo = button.dataset.vitrineSort === ordenacaoVitrine;
        button.classList.toggle("ativo", ativo);
        button.setAttribute("aria-pressed", ativo ? "true" : "false");
    });
}

function ordenarChocolates(chocolates) {
    const listaOrdenada = [...chocolates];

    if (ordenacaoVitrine === "price-asc") {
        listaOrdenada.sort((produtoA, produtoB) => {
            const precoA = Number(produtoA.preco) || 0;
            const precoB = Number(produtoB.preco) || 0;

            if (precoA !== precoB) {
                return precoA - precoB;
            }

            return String(produtoA.nome || "").localeCompare(String(produtoB.nome || ""), "pt-BR");
        });
    }

    return listaOrdenada;
}

function atualizarContadorVitrine(quantidadeExibida) {
    if (vitrineCount) {
        vitrineCount.textContent = `Exibindo: ${quantidadeExibida} / ${listaChocolates.length}`;
    }
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function aplicarFallbackImagemProduto(imagem) {
    if (!imagem) {
        return;
    }

    imagem.addEventListener("error", () => {
        if (imagem.dataset.fallbackApplied === "true") {
            return;
        }

        imagem.dataset.fallbackApplied = "true";
        imagem.src = PRODUCT_IMAGE_FALLBACK;
    });
}

function aplicarFallbackImagensProdutos(raiz) {
    if (!raiz) {
        return;
    }

    raiz.querySelectorAll("img").forEach(aplicarFallbackImagemProduto);
}

function configurarArrasteVitrine() {
    if (!container) {
        return;
    }

    container.addEventListener("pointerdown", (event) => {
        if (event.button !== 0 || event.target.closest("button, a")) {
            return;
        }

        vitrineArrastando = true;
        vitrineArrasteInicioX = event.clientX;
        vitrineScrollInicio = container.scrollLeft;
        container.setPointerCapture(event.pointerId);
    });

    container.addEventListener("pointermove", (event) => {
        if (!vitrineArrastando) {
            return;
        }

        const deslocamento = event.clientX - vitrineArrasteInicioX;
        container.scrollLeft = vitrineScrollInicio - deslocamento;
    });

    ["pointerup", "pointercancel", "pointerleave"].forEach((evento) => {
        container.addEventListener(evento, () => {
            vitrineArrastando = false;
        });
    });
}

function rolarVitrine(direcao) {
    if (!container) {
        return;
    }

    const primeiroCard = container.querySelector(".card");
    const deslocamento = primeiroCard
        ? primeiroCard.getBoundingClientRect().width + 22
        : container.clientWidth * 0.85;

    container.scrollBy({
        left: direcao === "prev" ? -deslocamento : deslocamento,
        behavior: "smooth",
    });
}

function atualizarSetasVitrine() {
    if (!container || vitrineScrollButtons.length === 0) {
        return;
    }

    const tolerancia = 2;
    const podeVoltar = container.scrollLeft > tolerancia;
    const podeAvancar = container.scrollLeft + container.clientWidth < container.scrollWidth - tolerancia;

    vitrineScrollButtons.forEach((button) => {
        const podeUsar = button.dataset.vitrineScroll === "prev" ? podeVoltar : podeAvancar;
        button.classList.toggle("vitrine-seta--oculta", !podeUsar);
        button.disabled = !podeUsar;
        button.setAttribute("aria-hidden", podeUsar ? "false" : "true");
        button.setAttribute("tabindex", podeUsar ? "0" : "-1");
    });
}

function posicionarSetasVitrine() {
    if (!container) {
        return;
    }

    const vitrine = document.getElementById("vitrine");
    const primeiraImagem = container.querySelector(".card__imagem-box");

    if (!vitrine || !primeiraImagem) {
        return;
    }

    const vitrineRect = vitrine.getBoundingClientRect();
    const imagemRect = primeiraImagem.getBoundingClientRect();
    const centroImagem = imagemRect.top + imagemRect.height / 2 - vitrineRect.top;
    vitrine.style.setProperty("--vitrine-setas-top", `${centroImagem}px`);
}

function normalizarTextoBusca(valor) {
    return String(valor || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();
}

function produtoCorrespondeBusca(produto, termoBusca) {
    const campos = [produto.nome, produto.categoria, produto.destaque, produto.peso];
    return campos.some((campo) => normalizarTextoBusca(campo).includes(termoBusca));
}

function produtoCorrespondeCategoria(produto, categoria) {
    return !categoria || normalizarTextoBusca(produto.categoria) === categoria;
}

function obterChocolatesVisiveisPelaBusca() {
    const texto = normalizarTextoBusca(barraPesquisa?.value || "");

    return listaChocolates.filter((choc) => {
        const correspondeBusca = !texto || produtoCorrespondeBusca(choc, texto);
        const correspondeCategoria = produtoCorrespondeCategoria(choc, categoriaAtiva);

        return correspondeBusca && correspondeCategoria;
    });
}

function atualizarEstadoFiltrosCategoria() {
    if (!filtrosCategoria) {
        return;
    }

    filtrosCategoria.querySelectorAll(".filtro-categoria").forEach((botao) => {
        const ativo = botao.dataset.categoria === categoriaAtiva;
        botao.classList.toggle("ativo", ativo);
        botao.setAttribute("aria-pressed", ativo ? "true" : "false");
    });
}

function renderizarFiltrosCategoria() {
    if (!filtrosCategoria) {
        return;
    }

    const categorias = [...new Set(
        listaChocolates
            .map((produto) => String(produto.categoria || "").trim())
            .filter(Boolean)
    )];

    filtrosCategoria.innerHTML = "";

    if (categorias.length <= 1) {
        categoriaAtiva = "";
        return;
    }

    [{ label: "Todos", valor: "" }, ...categorias.map((categoria) => ({
        label: categoria,
        valor: normalizarTextoBusca(categoria),
    }))].forEach((filtro) => {
        const botao = document.createElement("button");
        botao.type = "button";
        botao.className = "filtro-categoria";
        botao.dataset.categoria = filtro.valor;
        botao.textContent = filtro.label;
        botao.setAttribute("aria-pressed", filtro.valor === categoriaAtiva ? "true" : "false");
        botao.addEventListener("click", () => {
            categoriaAtiva = filtro.valor;
            const visiveis = obterChocolatesVisiveisPelaBusca();

            renderizarChocolates(visiveis);
            atualizarPainelPesquisa(normalizarTextoBusca(barraPesquisa?.value || ""), visiveis);
            atualizarEstadoFiltrosCategoria();
        });
        filtrosCategoria.appendChild(botao);
    });

    atualizarEstadoFiltrosCategoria();
}

function criarBotaoResultadoPesquisa(produto) {
    const botao = document.createElement("button");
    botao.type = "button";
    botao.className = "resultado-pesquisa__botao";
    botao.textContent = "+";
    botao.setAttribute("aria-label", `Adicionar ${produto.nome} ao carrinho`);
    botao.addEventListener("click", () => {
        adicionarAoCarrinho(produto.nome, produto.preco, produto.imagem);
    });

    return botao;
}

function criarItemPesquisa(produto) {
    const item = document.createElement("article");
    item.className = "resultado-pesquisa";

    item.innerHTML = `
        <img src="${escapeHtml(produto.imagem)}" alt="${escapeHtml(produto.nome)}">
        <div class="resultado-pesquisa__info">
            <strong>${escapeHtml(produto.nome)}</strong>
            <p>${escapeHtml(produto.categoria || produto.destaque || "Chocolate")}</p>
            <span>${formatarPreco(produto.preco)}</span>
        </div>
    `;
    item.appendChild(criarBotaoResultadoPesquisa(produto));
    aplicarFallbackImagensProdutos(item);

    return item;
}

function atualizarPainelPesquisa(termoBusca, resultados = []) {
    if (!listaSugestoes || !listaResultadosPesquisa) {
        return;
    }

    const sugestoes = (resultados.length > 0 ? resultados : listaChocolates).slice(0, 4);
    listaSugestoes.innerHTML = "";
    listaResultadosPesquisa.innerHTML = "";

    sugestoes.forEach((produto) => {
        listaSugestoes.appendChild(criarItemPesquisa(produto));
    });

    if (termoBusca && resultados.length === 0) {
        listaResultadosPesquisa.innerHTML = '<p class="pesquisa-vazia">Nenhum chocolate encontrado.</p>';
        return;
    }

    resultados.slice(0, 8).forEach((produto) => {
        listaResultadosPesquisa.appendChild(criarItemPesquisa(produto));
    });
}

function renderizarAtalhosPesquisa() {
    if (!listaAtalhosPesquisa) {
        return;
    }

    const categorias = [...new Set(listaChocolates.map((produto) => produto.categoria).filter(Boolean))].slice(0, 5);
    listaAtalhosPesquisa.innerHTML = "";

    categorias.forEach((categoria) => {
        const botao = document.createElement("button");
        botao.type = "button";
        botao.className = "pesquisa-atalho";
        botao.textContent = categoria;
        botao.addEventListener("click", () => {
            barraPesquisa.value = categoria;
            aplicarPesquisa();
        });
        listaAtalhosPesquisa.appendChild(botao);
    });
}

function aplicarPesquisa() {
    const texto = normalizarTextoBusca(barraPesquisa?.value || "");
    const filtrados = obterChocolatesVisiveisPelaBusca();

    renderizarChocolates(filtrados);
    atualizarPainelPesquisa(texto, filtrados);
    btnLimparPesquisa?.classList.toggle("oculto", !texto);
}

function abrirOverlayPesquisa() {
    if (!overlayPesquisa || !barraPesquisa) {
        return;
    }

    clearTimeout(timeoutFechamentoPesquisa);
    overlayPesquisa.classList.remove("oculto");
    overlayPesquisa.classList.remove("overlay-pesquisa--visivel");
    document.body.classList.add("sem-rolagem");
    atualizarPainelPesquisa(normalizarTextoBusca(barraPesquisa.value), obterChocolatesVisiveisPelaBusca());
    requestAnimationFrame(() => {
        overlayPesquisa.classList.add("overlay-pesquisa--visivel");
    });
    barraPesquisa.focus();
}

function fecharOverlayPesquisa() {
    if (!overlayPesquisa) {
        return;
    }

    overlayPesquisa.classList.remove("overlay-pesquisa--visivel");

    clearTimeout(timeoutFechamentoPesquisa);
    timeoutFechamentoPesquisa = window.setTimeout(() => {
        overlayPesquisa.classList.add("oculto");
    }, 280);

    if (!carrinhoLateral.classList.contains("ativo")) {
        document.body.classList.remove("sem-rolagem");
    }
}

function limparPesquisa() {
    barraPesquisa.value = "";
    btnLimparPesquisa.classList.add("oculto");
    const filtrados = obterChocolatesVisiveisPelaBusca();
    renderizarChocolates(filtrados);
    atualizarPainelPesquisa("", filtrados);
    barraPesquisa.focus();
}

function renderizarChocolates(chocolates) {
    if (!container) {
        return;
    }

    container.innerHTML = "";
    container.scrollLeft = 0;
    const chocolatesOrdenados = ordenarChocolates(chocolates);
    atualizarContadorVitrine(chocolatesOrdenados.length);

    if (chocolatesOrdenados.length === 0) {
        container.innerHTML = "<p>Nenhum chocolate encontrado.</p>";
        atualizarSetasVitrine();
        return;
    }

    chocolatesOrdenados.forEach((choc, index) => {
        const card = window.VelleProductCard?.create(choc, {
            index,
            quantity: obterQuantidadeNoCarrinho(choc.nome),
            onAdd: (produto) => adicionarAoCarrinho(produto.nome, produto.preco, produto.imagem),
        });

        if (!card) {
            return;
        }

        aplicarFallbackImagensProdutos(card);
        container.appendChild(card);
    });

    requestAnimationFrame(() => {
        posicionarSetasVitrine();
        atualizarSetasVitrine();
    });
}

function formatarPreco(valor) {
    return `R$ ${Number(valor || 0).toFixed(2).replace(".", ",")}`;
}

function obterSugestoesCarrinhoVazio() {
    if (listaChocolates.length <= 2) {
        return [...listaChocolates];
    }

    const embaralhados = [...listaChocolates].sort(() => Math.random() - 0.5);
    const sugestoes = [];
    const categoriasUsadas = new Set();

    for (const item of embaralhados) {
        const categoria = String(item.categoria || "").trim().toLowerCase();

        if (categoria && categoriasUsadas.has(categoria)) {
            continue;
        }

        sugestoes.push(item);

        if (categoria) {
            categoriasUsadas.add(categoria);
        }

        if (sugestoes.length === 3) {
            return sugestoes;
        }
    }

    for (const item of embaralhados) {
        if (!sugestoes.includes(item)) {
            sugestoes.push(item);
        }

        if (sugestoes.length === 3) {
            break;
        }
    }

    return sugestoes;
}

window.abrirVitrinePrincipal = function () {
    fecharPainelCarrinho();
    const vitrine = document.getElementById("vitrine");

    if (vitrine) {
        vitrine.scrollIntoView({ behavior: "smooth", block: "start" });
        return;
    }

    window.location.href = `${rotaHomeAtual}#vitrine`;
};

window.adicionarSugestaoCarrinho = function (slugProduto, botao) {
    const produto = listaChocolates.find((item) => item.slug === slugProduto);

    if (!produto) {
        return;
    }

    if (botao) {
        botao.classList.remove("carrinho-vazio__adicionar--ativo");
        botao.offsetHeight;
        botao.classList.add("carrinho-vazio__adicionar--ativo");
    }

    window.setTimeout(() => {
        window.adicionarAoCarrinho(produto.nome, produto.preco, produto.imagem);
    }, 170);
};

async function carregarChocolates() {
    listaChocolates = await carregarTodosChocolates();
    renderizarChocolates(listaChocolates);
    renderizarAtalhosPesquisa();
    renderizarFiltrosCategoria();
    atualizarCarrinho(false);
}

window.adicionarAoCarrinho = function (nome, preco, imagem) {
    const itemExistente = carrinho.find((item) => item.nome === nome);
    const produtoEntrouNaSacola = !itemExistente;

    if (itemExistente) {
        itemExistente.qtd++;
    } else {
        const produtoRelacionado = obterDadosProduto(nome, imagem);
        carrinho.push(normalizarItemCarrinho({
            nome,
            preco,
            imagem,
            qtd: 1,
            slug: produtoRelacionado?.slug || "",
        }));
    }

    atualizarCarrinho();

    if (produtoEntrouNaSacola) {
        mostrarPopupCarrinho(nome);
    } else {
        mostrarPopupAcao("Produto foi adicionado ao carrinho !!!");
    }
};

function atualizarCarrinho(devePersistir = true) {
    listaCarrinho.innerHTML = "";

    let totalItens = 0;
    let totalPreco = 0;

    if (tituloCarrinho) {
        tituloCarrinho.textContent = `Minha sacola (${carrinho.reduce((soma, item) => soma + item.qtd, 0)})`;
    }

    if (carrinho.length === 0) {
        const sugestoes = obterSugestoesCarrinhoVazio();

        listaCarrinho.innerHTML = `
            <li class="carrinho-vazio">
                <div class="carrinho-vazio__resumo">
                    <p class="carrinho-vazio__frete">Você está a poucos passos de montar uma seleção especial.</p>
                    <h4>Sua sacola está vazia.</h4>
                    <p>Adicione seus chocolates favoritos para começar seu pedido.</p>
                    <button type="button" class="carrinho-vazio__cta" onclick="abrirVitrinePrincipal()">Comprar agora</button>
                </div>

                <div class="carrinho-vazio__sugestoes">
                    <p class="carrinho-vazio__titulo">Você também pode gostar</p>
                    ${sugestoes.map((item) => `
                        <article class="carrinho-vazio__item">
                            <img src="${item.imagem}" alt="${item.nome}">
                            <div class="carrinho-vazio__item-info">
                                <strong>${item.nome}</strong>
                                <span>${formatarPreco(item.preco)}</span>
                            </div>
                            <button type="button" class="carrinho-vazio__adicionar" onclick='event.stopPropagation(); adicionarSugestaoCarrinho(${JSON.stringify(item.slug)}, this)'>Adicionar +</button>
                        </article>
                    `).join("")}
                </div>
            </li>
        `;
        aplicarFallbackImagensProdutos(listaCarrinho);

        qtdItens.textContent = "0";
        subtotalPreco.textContent = "R$ 0,00";
        totalPrecoElement.textContent = "R$ 0,00";

        if (resumoCarrinho) {
            resumoCarrinho.hidden = true;
        }

        if (container) {
            renderizarChocolates(listaChocolates);
        }

        if (devePersistir) {
            salvarCarrinhoLocal();
            persistirCarrinhoNoServidor();
        }

        return;
    }

    if (resumoCarrinho) {
        resumoCarrinho.hidden = false;
    }

    carrinho.forEach((item, index) => {
        totalItens += item.qtd;
        totalPreco += item.preco * item.qtd;

        const li = document.createElement("li");
        li.innerHTML = `
            <div class="item-carrinho">
                <img class="item-carrinho__imagem" src="${item.imagem}" alt="${item.nome}">

                <div class="item-carrinho__conteudo">
                    <div class="item-carrinho__topo">
                        <div class="item-carrinho__info">
                            <strong>${item.nome}</strong>
                            <span class="item-carrinho__preco">R$ ${(item.preco * item.qtd).toFixed(2).replace(".", ",")}</span>
                        </div>

                        <button type="button" class="item-carrinho__remover-total" onclick="event.stopPropagation(); removerItemCompleto(${index}, this)" aria-label="Remover ${item.nome} do carrinho">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 7h16"></path>
                                <path d="M9 7V5h6v2"></path>
                                <path d="M8 7l1 12h6l1-12"></path>
                                <path d="M10 11v5"></path>
                                <path d="M14 11v5"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="item-carrinho__rodape">
                        <div class="item-carrinho__acoes">
                            <button type="button" onclick="event.stopPropagation(); removerItem(${index}, this)">-</button>
                            <span>${item.qtd}</span>
                            <button type="button" onclick="event.stopPropagation(); adicionarItem(${index})">+</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        aplicarFallbackImagensProdutos(li);
        listaCarrinho.appendChild(li);
    });

    qtdItens.textContent = totalItens;

    const totalFormatado = "R$ " + totalPreco.toFixed(2).replace(".", ",");
    subtotalPreco.textContent = totalFormatado;
    totalPrecoElement.textContent = totalFormatado;

    if (container) {
        renderizarChocolates(listaChocolates);
    }

    if (devePersistir) {
        salvarCarrinhoLocal();
        persistirCarrinhoNoServidor();
    }
}

window.adicionarItem = function (index) {
    carrinho[index].qtd++;
    atualizarCarrinho();
};

function animarReducaoItem(botao) {
    const item = botao ? botao.closest("li") : null;

    if (!item) {
        return;
    }

    item.classList.remove("item-carrinho--reduzindo");
    item.offsetHeight;
    item.classList.add("item-carrinho--reduzindo");

    window.setTimeout(() => {
        item.classList.remove("item-carrinho--reduzindo");
    }, 320);
}

function animarRemocaoCompleta(botao, aoFinalizar) {
    const item = botao ? botao.closest("li") : null;

    if (!item) {
        aoFinalizar();
        return;
    }

    const botaoExcluir = item.querySelector(".item-carrinho__remover-total");

    if (botaoExcluir) {
        botaoExcluir.classList.add("item-carrinho__remover-total--ativo");
    }

    item.classList.add("item-carrinho--saindo");

    window.setTimeout(() => {
        aoFinalizar();
    }, 280);
}

window.removerItem = function (index, botao) {
    const item = carrinho[index];

    if (!item) {
        return;
    }

    if (item.qtd > 1) {
        item.qtd--;
        atualizarCarrinho();
        animarReducaoItem(botao);
        return;
    }

    animarRemocaoCompleta(botao, () => {
        carrinho.splice(index, 1);
        atualizarCarrinho();
    });
};

window.removerItemCompleto = function (index, botao) {
    if (!carrinho[index]) {
        return;
    }

    animarRemocaoCompleta(botao, () => {
        carrinho.splice(index, 1);
        atualizarCarrinho();
    });
};

window.removerItemHome = function (nomeProduto) {
    const index = carrinho.findIndex((item) => item.nome === nomeProduto);

    if (index === -1) {
        return;
    }

    const itemRemovido = carrinho[index];

    if (carrinho[index].qtd > 1) {
        carrinho[index].qtd--;
    } else {
        carrinho.splice(index, 1);
    }

    atualizarCarrinho();
    mostrarPopupRemocao(itemRemovido.nome);
};

async function abrirCarrinho() {
    if (menuMobileAberto()) {
        fecharMenuMobile();
    }

    if (sincronizandoCarrinho) {
        await carregamentoCarrinhoPromise;
    }

    atualizarCarrinho(false);
    pausarPopupCarrinho();
    carrinhoLateral.classList.add("ativo");
    overlayCarrinho.classList.add("ativo");
    document.body.classList.add("sem-rolagem");
}

function fecharPainelCarrinho() {
    if (!carrinhoLateral.classList.contains("ativo")) {
        return;
    }

    carrinhoLateral.classList.remove("ativo");
    overlayCarrinho.classList.remove("ativo");
    retomarPopupCarrinho();

    if (overlayPesquisa.classList.contains("oculto") && !menuMobileAberto()) {
        document.body.classList.remove("sem-rolagem");
    }
}

btnCarrinho.addEventListener("click", (e) => {
    if (carrinhoLateral.classList.contains("ativo")) {
        fecharPainelCarrinho();
    } else {
        void abrirCarrinho();
    }

    e.stopPropagation();
});

fecharCarrinho.addEventListener("click", fecharPainelCarrinho);
overlayCarrinho.addEventListener("click", fecharPainelCarrinho);

document.addEventListener("click", (e) => {
    if (carrinhoLateral.classList.contains("ativo") && !carrinhoLateral.contains(e.target) && !btnCarrinho.contains(e.target)) {
        fecharPainelCarrinho();
    }

    if (!e.target.closest(".pesquisa-box")) {
        return;
    }
});

if (abrirPesquisa) {
    abrirPesquisa.addEventListener("click", abrirOverlayPesquisa);
}

if (fecharPesquisa) {
    fecharPesquisa.addEventListener("click", fecharOverlayPesquisa);
}

if (barraPesquisa) {
    barraPesquisa.addEventListener("input", aplicarPesquisa);
}

if (btnLimparPesquisa) {
    btnLimparPesquisa.addEventListener("click", limparPesquisa);
}

if (btnVerTodos) {
    btnVerTodos.addEventListener("click", () => {
        categoriaAtiva = "";
        atualizarEstadoFiltrosCategoria();
        limparPesquisa();
        fecharOverlayPesquisa();
        window.abrirVitrinePrincipal();
    });
}

if (fecharPopupRemocao) {
    fecharPopupRemocao.addEventListener("click", () => {
        clearTimeout(popupRemocaoTimeout);
        popupRemocao.classList.remove("ativo");
        popupRemocao.setAttribute("aria-hidden", "true");
    });
}

if (fecharPopupBoasVindas) {
    fecharPopupBoasVindas.addEventListener("click", fecharPopupBoasVindasComEstado);
}

if (fecharPopupAvisoConta) {
    fecharPopupAvisoConta.addEventListener("click", fecharPopupAvisoContaComEstado);
}

if (btnFinalizarPedido) {
    btnFinalizarPedido.addEventListener("click", finalizarPedido);
}

atualizarBotoesOrdenacao();
configurarArrasteVitrine();
posicionarSetasVitrine();
atualizarSetasVitrine();

if (container) {
    container.addEventListener("scroll", atualizarSetasVitrine, { passive: true });
}

window.addEventListener("resize", () => {
    posicionarSetasVitrine();
    atualizarSetasVitrine();
});

vitrineSortButtons.forEach((button) => {
    button.addEventListener("click", () => {
        const proximaOrdenacao = button.dataset.vitrineSort;

        if (!VITRINE_SORT_OPTIONS.has(proximaOrdenacao) || ordenacaoVitrine === proximaOrdenacao) {
            return;
        }

        ordenacaoVitrine = proximaOrdenacao;
        salvarOrdenacaoVitrine();
        atualizarBotoesOrdenacao();
        renderizarChocolates(obterChocolatesVisiveisPelaBusca());
    });
});

vitrineScrollButtons.forEach((button) => {
    button.addEventListener("click", () => {
        rolarVitrine(button.dataset.vitrineScroll);
    });
});

instagramLinks.forEach((link) => {
    link.addEventListener("click", (event) => {
        const novaJanela = window.open(link.href, "_blank", "noopener,noreferrer");

        if (novaJanela) {
            event.preventDefault();
            return;
        }

        link.removeAttribute("target");
    });
});

window.addEventListener("storage", carregarChocolates);
window.addEventListener("pagehide", persistirCarrinhoDeSaida);
window.addEventListener("beforeunload", persistirCarrinhoDeSaida);

carregamentoCarrinhoPromise = carregarCarrinhoDaConta();

Promise.all([
    carregarChocolates(),
    carregamentoCarrinhoPromise,
]).finally(() => {
    document.body.classList.remove("pagina-carregando");
});
exibirPopupBoasVindas();
exibirPopupAvisoConta();
