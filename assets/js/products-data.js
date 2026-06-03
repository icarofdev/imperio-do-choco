const STORAGE_KEY_CHOCOLATES = "meus_chocolates";
const STORAGE_KEY_CHOCOLATES_REMOVIDOS = "meus_chocolates_removidos";
const STORAGE_KEY_CHOCOLATES_BACKUP = "meus_chocolates_backup";
const CATALOGO_BASE_URL = "../assets/data/catalogo-inicial.json";

function slugifyProductName(texto) {
    return String(texto || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
}

function gerarIdProduto() {
    return `choc-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function gerarRefProduto() {
    return String(Math.floor(1000000000 + Math.random() * 9000000000));
}

function paginaAtualUsaPhp() {
    return /\.php($|\?)/i.test(window.location.pathname);
}

function ambientePermiteApiPhp() {
    return window.location.protocol === "http:" || window.location.protocol === "https:";
}

function normalizarChocolate(chocolate, index = 0) {
    const nome = chocolate.nome || "Chocolate sem nome";
    const preco = Number(chocolate.preco) || 0;
    const imagemPrincipal = chocolate.img || chocolate.imagem_url || chocolate.imagem || "";
    const imagens = Array.isArray(chocolate.imagens)
        ? chocolate.imagens.filter(Boolean)
        : [imagemPrincipal].filter(Boolean);

    return {
        id: chocolate.id || `produto-${index}-${slugifyProductName(nome) || "chocolate"}`,
        slug: chocolate.slug || slugifyProductName(nome) || `chocolate-${index + 1}`,
        ref: chocolate.ref || gerarRefProduto(),
        nome,
        preco,
        imagem: imagemPrincipal,
        imagens: imagens.length > 0 ? imagens : [imagemPrincipal].filter(Boolean),
        descricao: chocolate.descricao || "Um chocolate especial preparado para transformar qualquer momento em algo memoravel.",
        categoria: chocolate.categoria || "Chocolate",
        tipo: chocolate.tipo || chocolate.categoria || "Chocolate",
        peso: chocolate.peso || "",
        peso_gramas: chocolate.peso_gramas !== null
            && chocolate.peso_gramas !== undefined
            && Number.isFinite(Number(chocolate.peso_gramas))
            ? Math.max(0, Number(chocolate.peso_gramas))
            : null,
        destaque: chocolate.destaque || "Selecao da casa",
        estoque_quantidade: Number.isFinite(Number(chocolate.estoque_quantidade))
            ? Math.max(0, Number(chocolate.estoque_quantidade))
            : 0,
    };
}

function mesclarChocolates(...listas) {
    const catalogoPorChave = new Map();
    let indexGlobal = 0;

    listas.flat().forEach((chocolate) => {
        const chocolateNormalizado = normalizarChocolate(chocolate, indexGlobal);
        const chave = chocolateNormalizado.slug || chocolateNormalizado.id;

        catalogoPorChave.set(chave, chocolateNormalizado);
        indexGlobal += 1;
    });

    return Array.from(catalogoPorChave.values());
}

async function buscarCatalogoBase() {
    try {
        const resposta = await fetch(CATALOGO_BASE_URL);

        if (!resposta.ok) {
            return [];
        }

        return await resposta.json();
    } catch (erro) {
        console.warn("Nao foi possivel carregar o catalogo base.", erro);
        return [];
    }
}

async function buscarChocolatesRemotos() {
    if (!ambientePermiteApiPhp()) {
        return [];
    }

    try {
        const resposta = await fetch("buscar-chocolates.php");
        const tipoConteudo = resposta.headers.get("content-type") || "";

        if (!resposta.ok || !tipoConteudo.includes("application/json")) {
            return [];
        }

        return await resposta.json();
    } catch (erro) {
        console.warn("Fonte remota indisponivel, usando apenas o catalogo local.", erro);
        return [];
    }
}

function lerChocolatesLocais() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY_CHOCOLATES)) || [];
    } catch (erro) {
        console.warn("Nao foi possivel ler os chocolates salvos localmente.", erro);
        return [];
    }
}

function salvarChocolatesLocais(chocolates) {
    localStorage.setItem(STORAGE_KEY_CHOCOLATES, JSON.stringify(chocolates));
}

function lerBackupChocolatesRemovidos() {
    try {
        const backup = JSON.parse(localStorage.getItem(STORAGE_KEY_CHOCOLATES_BACKUP)) || [];
        return Array.isArray(backup) ? backup : [];
    } catch (erro) {
        console.warn("Nao foi possivel ler o backup local dos chocolates removidos.", erro);
        return [];
    }
}

function salvarBackupChocolatesRemovidos(chocolates) {
    localStorage.setItem(STORAGE_KEY_CHOCOLATES_BACKUP, JSON.stringify(Array.isArray(chocolates) ? chocolates : []));
}

function lerChavesChocolatesRemovidos() {
    try {
        const removidos = JSON.parse(localStorage.getItem(STORAGE_KEY_CHOCOLATES_REMOVIDOS)) || [];

        if (!Array.isArray(removidos)) {
            return [];
        }

        return removidos
            .map((item) => String(item || "").trim())
            .filter(Boolean);
    } catch (erro) {
        console.warn("Nao foi possivel ler os chocolates removidos localmente.", erro);
        return [];
    }
}

function salvarChavesChocolatesRemovidos(chaves) {
    const listaNormalizada = [...new Set(
        (Array.isArray(chaves) ? chaves : [])
            .map((item) => String(item || "").trim())
            .filter(Boolean)
    )];

    localStorage.setItem(STORAGE_KEY_CHOCOLATES_REMOVIDOS, JSON.stringify(listaNormalizada));
}

function obterChaveProduto(produto) {
    if (!produto) {
        return "";
    }

    return produto.id || produto.slug || slugifyProductName(produto.nome);
}

function removerChocolateDoCatalogoLocal(chaveProduto) {
    const chaveNormalizada = String(chaveProduto || "").trim();

    if (!chaveNormalizada) {
        return;
    }

    const chocolatesLocais = lerChocolatesLocais();
    const indiceLocal = chocolatesLocais.findIndex((item) => obterChaveProduto(item) === chaveNormalizada);

    if (indiceLocal >= 0) {
        const [produtoRemovido] = chocolatesLocais.splice(indiceLocal, 1);
        const backup = lerBackupChocolatesRemovidos().filter((item) => obterChaveProduto(item) !== chaveNormalizada);
        backup.push(produtoRemovido);
        salvarBackupChocolatesRemovidos(backup);
        salvarChocolatesLocais(chocolatesLocais);
    }

    const chavesRemovidas = lerChavesChocolatesRemovidos();

    if (!chavesRemovidas.includes(chaveNormalizada)) {
        chavesRemovidas.push(chaveNormalizada);
        salvarChavesChocolatesRemovidos(chavesRemovidas);
    }
}

function restaurarChocolateRemovido(chaveProduto) {
    const chaveNormalizada = String(chaveProduto || "").trim();

    if (!chaveNormalizada) {
        return;
    }

    const chavesAtualizadas = lerChavesChocolatesRemovidos().filter((item) => item !== chaveNormalizada);
    salvarChavesChocolatesRemovidos(chavesAtualizadas);
}

function restaurarChocolateDoBackup(chaveProduto) {
    const chaveNormalizada = String(chaveProduto || "").trim();

    if (!chaveNormalizada) {
        return null;
    }

    const backup = lerBackupChocolatesRemovidos();
    const indiceBackup = backup.findIndex((item) => obterChaveProduto(item) === chaveNormalizada);

    if (indiceBackup < 0) {
        restaurarChocolateRemovido(chaveNormalizada);
        return null;
    }

    const [produto] = backup.splice(indiceBackup, 1);
    salvarBackupChocolatesRemovidos(backup);

    const chocolatesLocais = lerChocolatesLocais();
    const jaExiste = chocolatesLocais.some((item) => obterChaveProduto(item) === chaveNormalizada);

    if (!jaExiste) {
        chocolatesLocais.push(produto);
        salvarChocolatesLocais(chocolatesLocais);
    }

    restaurarChocolateRemovido(chaveNormalizada);
    return produto;
}

async function carregarCatalogoCompleto() {
    const catalogoBase = await buscarCatalogoBase();
    const chocolatesRemotos = await buscarChocolatesRemotos();
    const chocolatesLocais = lerChocolatesLocais();

    return mesclarChocolates(catalogoBase, chocolatesRemotos, chocolatesLocais);
}

async function carregarTodosChocolates() {
    const chocolates = await carregarCatalogoCompleto();
    const chavesRemovidas = new Set(lerChavesChocolatesRemovidos());

    return chocolates.filter((chocolate) => !chavesRemovidas.has(obterChaveProduto(chocolate)));
}

async function carregarChocolatesRemovidos() {
    const chavesRemovidas = lerChavesChocolatesRemovidos();

    if (chavesRemovidas.length === 0) {
        return [];
    }

    const catalogoCompleto = await carregarCatalogoCompleto();
    const backup = lerBackupChocolatesRemovidos();
    const catalogoPorChave = new Map();

    catalogoCompleto.forEach((produto) => {
        catalogoPorChave.set(obterChaveProduto(produto), normalizarChocolate(produto));
    });

    backup.forEach((produto) => {
        catalogoPorChave.set(obterChaveProduto(produto), normalizarChocolate(produto));
    });

    return chavesRemovidas
        .map((chave) => catalogoPorChave.get(chave))
        .filter(Boolean);
}

async function buscarChocolatePorIdentificador(identificador) {
    const chocolates = await carregarTodosChocolates();

    return chocolates.find((chocolate) =>
        chocolate.id === identificador || chocolate.slug === identificador
    ) || null;
}
