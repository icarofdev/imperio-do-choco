(function () {
    const form = document.getElementById("admin-form");

    if (!form) {
        return;
    }

    const OPTIONS = window.ADMIN_PANEL_OPTIONS || {};
    const REMOTE_API_URL = String(OPTIONS.remoteApiUrl || "admin-produtos.php").trim();
    const ADMIN_CSRF_TOKEN = String(OPTIONS.csrfToken || "");
    const FALLBACK_IMAGE = "../assets/img/logo-velle-dulcis.png";
    const DEFAULT_PRODUCT_DESCRIPTION = "Um chocolate especial preparado para transformar qualquer momento em algo memorável.";
    const currencyFormatter = new Intl.NumberFormat("pt-BR", {
        style: "currency",
        currency: "BRL",
    });

    const elements = {
        status: document.getElementById("admin-status"),
        user: document.getElementById("admin-usuario"),
        logout: document.getElementById("btn-sair"),
        publish: document.getElementById("btn-publicar"),
        clear: document.getElementById("btn-limpar"),
        cancelEdit: document.getElementById("btn-cancelar-edicao"),
        search: document.getElementById("admin-busca"),
        sourceFilter: document.getElementById("admin-filtro-origem"),
        sort: document.getElementById("admin-ordenacao"),
        clearFilters: document.getElementById("btn-limpar-filtros"),
        removedToggle: document.getElementById("btn-toggle-removidos"),
        productList: document.getElementById("admin-lista-produtos"),
        removedList: document.getElementById("admin-lista-removidos"),
        modeResume: document.getElementById("admin-mode-resumo"),
        modeLabel: document.getElementById("admin-mode-label"),
        modeText: document.getElementById("admin-mode-texto"),
        totalProducts: document.getElementById("admin-total-produtos"),
        totalLocals: document.getElementById("admin-total-locais"),
        totalRemoved: document.getElementById("admin-total-removidos"),
        totalCategories: document.getElementById("admin-total-categorias"),
        fields: {
            image: document.getElementById("imgUrl"),
            name: document.getElementById("nome"),
            price: document.getElementById("preco"),
            category: document.getElementById("categoria"),
            weight: document.getElementById("peso"),
            ref: document.getElementById("ref"),
            highlight: document.getElementById("destaque"),
            description: document.getElementById("descricao"),
            gallery: document.getElementById("galeria"),
        },
        preview: {
            image: document.getElementById("admin-preview-imagem"),
            highlight: document.getElementById("admin-preview-destaque"),
            name: document.getElementById("admin-preview-nome"),
            price: document.getElementById("admin-preview-preco"),
            category: document.getElementById("admin-preview-categoria"),
            weight: document.getElementById("admin-preview-peso"),
            description: document.getElementById("admin-preview-descricao"),
            slug: document.getElementById("admin-preview-slug"),
            ref: document.getElementById("admin-preview-ref"),
            gallery: document.getElementById("admin-preview-galeria"),
        },
    };

    const state = {
        products: [],
        removed: [],
        localKeys: new Set(),
        remoteKeys: new Set(),
        editingKey: null,
        removedOpen: false,
        filters: {
            query: "",
            source: "todos",
            sort: "nome-asc",
        },
        statusTimer: null,
    };

    function normalizeSearch(value) {
        return String(value || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .trim();
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatCurrency(value) {
        return currencyFormatter.format(Number(value) || 0);
    }

    function normalizeKey(value) {
        return String(value || "").trim();
    }

    function uniqueLines(...groups) {
        return [...new Set(
            groups
                .flat()
                .map((item) => String(item || "").trim())
                .filter(Boolean)
        )];
    }

    function getGalleryImages(primaryImage, rawGallery) {
        return uniqueLines(
            primaryImage,
            String(rawGallery || "").split(/\r?\n/)
        );
    }

    function getProductKey(product) {
        return normalizeKey(product?._key || obterChaveProduto(product));
    }

    function inferProductOrigin(productKey, hintedOrigin = "") {
        const origin = normalizeKey(hintedOrigin).toLowerCase();

        if (origin === "local" || origin === "remoto" || origin === "catalogo") {
            return origin;
        }

        if (state.localKeys.has(productKey)) {
            return "local";
        }

        if (state.remoteKeys.has(productKey)) {
            return "remoto";
        }

        return "catalogo";
    }

    function readRemovedKeys() {
        return new Set(
            lerChavesChocolatesRemovidos()
                .map((item) => normalizeKey(item))
                .filter(Boolean)
        );
    }

    function writeRemovedKeys(keys) {
        salvarChavesChocolatesRemovidos(Array.from(keys));
    }

    function readRemovedBackup() {
        const backup = lerBackupChocolatesRemovidos();
        return Array.isArray(backup) ? backup : [];
    }

    function writeRemovedBackup(products) {
        salvarBackupChocolatesRemovidos(Array.isArray(products) ? products : []);
    }

    function storeRemovedBackupEntry(product, origin) {
        const productKey = getProductKey(product);

        if (!productKey) {
            return;
        }

        const backup = readRemovedBackup().filter((item) => getProductKey(item) !== productKey);
        backup.push({
            ...normalizarChocolate(product),
            origem: inferProductOrigin(productKey, origin),
        });
        writeRemovedBackup(backup);
    }

    function removeRemovedBackupEntry(productKey) {
        const backup = readRemovedBackup().filter((item) => getProductKey(item) !== productKey);
        writeRemovedBackup(backup);
    }

    function removeLocalProductByKey(productKey) {
        const localProducts = lerChocolatesLocais().filter((item) => getProductKey(normalizarChocolate(item)) !== productKey);
        salvarChocolatesLocais(localProducts);
    }

    function restoreLocalProduct(product) {
        const productKey = getProductKey(product);
        const localProducts = lerChocolatesLocais().filter((item) => getProductKey(normalizarChocolate(item)) !== productKey);
        localProducts.push(normalizarChocolate(product));
        salvarChocolatesLocais(localProducts);
    }

    function markProductAsRemoved(product, origin) {
        const productKey = getProductKey(product);

        if (!productKey) {
            return;
        }

        storeRemovedBackupEntry(product, origin);

        const removedKeys = readRemovedKeys();
        removedKeys.add(productKey);
        writeRemovedKeys(removedKeys);
    }

    function clearRemovedProduct(productKey) {
        const removedKeys = readRemovedKeys();
        removedKeys.delete(productKey);
        writeRemovedKeys(removedKeys);
        removeRemovedBackupEntry(productKey);
    }

    async function requestRemoteProducts(method = "GET", payload = null, fallbackMessage = "Não foi possível processar a solicitação.") {
        if (!REMOTE_API_URL) {
            throw new Error("API administrativa indisponível.");
        }

        const requestOptions = {
            method,
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
            },
        };

        if (ADMIN_CSRF_TOKEN && method !== "GET") {
            requestOptions.headers["X-CSRF-Token"] = ADMIN_CSRF_TOKEN;
        }

        if (payload !== null) {
            const requestPayload = ADMIN_CSRF_TOKEN && method !== "GET"
                ? { ...payload, csrf_token: ADMIN_CSRF_TOKEN }
                : payload;
            requestOptions.headers["Content-Type"] = "application/json";
            requestOptions.body = JSON.stringify(requestPayload);
        }

        const response = await fetch(REMOTE_API_URL, requestOptions);
        let data = {};

        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }

        if (!response.ok) {
            throw new Error(data.erro || fallbackMessage);
        }

        return data;
    }

    async function fetchRemoteProducts() {
        if (!REMOTE_API_URL) {
            return [];
        }

        try {
            const data = await requestRemoteProducts("GET", null, "Não foi possível carregar os produtos salvos no banco.");
            return Array.isArray(data.produtos) ? data.produtos : [];
        } catch (error) {
            console.warn("Não foi possível carregar os produtos remotos.", error);
            return [];
        }
    }

    function buildRemotePayload(existingProduct) {
        const values = getFormValues();
        const image = values.image || existingProduct?.imagem || FALLBACK_IMAGE;
        const gallery = getGalleryImages(image, values.gallery);
        const name = values.name || existingProduct?.nome || "Chocolate sem nome";

        return {
            img: image,
            nome: name,
            preco: values.price,
            categoria: values.category || existingProduct?.categoria || "Chocolate",
            peso: values.weight || existingProduct?.peso || "",
            ref: values.ref || existingProduct?.ref || "",
            destaque: values.highlight || existingProduct?.destaque || "Seleção da casa",
            descricao: values.description || existingProduct?.descricao || DEFAULT_PRODUCT_DESCRIPTION,
            imagens: gallery,
            tipo: values.category || existingProduct?.categoria || "Chocolate",
        };
    }

    function buildRemotePayloadFromProduct(product) {
        const normalized = normalizarChocolate(product);

        return {
            img: normalized.imagem || FALLBACK_IMAGE,
            nome: normalized.nome || "Chocolate sem nome",
            preco: Number(normalized.preco) || 0,
            categoria: normalized.categoria || "Chocolate",
            peso: normalized.peso || "",
            ref: normalized.ref || "",
            destaque: normalized.destaque || "Seleção da casa",
            descricao: normalized.descricao || DEFAULT_PRODUCT_DESCRIPTION,
            imagens: Array.isArray(normalized.imagens) && normalized.imagens.length > 0
                ? normalized.imagens
                : [normalized.imagem].filter(Boolean),
            tipo: normalized.categoria || "Chocolate",
        };
    }

    function getEditingProduct() {
        return state.products.find((product) => product._key === state.editingKey) || null;
    }

    function getFormValues() {
        return {
            image: String(elements.fields.image.value || "").trim(),
            name: String(elements.fields.name.value || "").trim(),
            price: Number(String(elements.fields.price.value || "").replace(",", ".")) || 0,
            category: String(elements.fields.category.value || "").trim(),
            weight: String(elements.fields.weight.value || "").trim(),
            ref: String(elements.fields.ref.value || "").trim(),
            highlight: String(elements.fields.highlight.value || "").trim(),
            description: String(elements.fields.description.value || "").trim(),
            gallery: String(elements.fields.gallery.value || "").trim(),
        };
    }

    function buildProductFromForm(existingProduct) {
        const values = getFormValues();
        const base = existingProduct || {};
        const image = values.image || base.imagem || FALLBACK_IMAGE;
        const gallery = getGalleryImages(image, values.gallery);
        const name = values.name || "Chocolate sem nome";

        return normalizarChocolate({
            ...base,
            id: base.id || gerarIdProduto(),
            slug: base.slug || slugifyProductName(name) || `produto-${Date.now()}`,
            ref: values.ref || base.ref || gerarRefProduto(),
            nome: name,
            preco: values.price,
            categoria: values.category || base.categoria || "Chocolate",
            peso: values.weight || base.peso || "",
            destaque: values.highlight || base.destaque || "Seleção da casa",
            descricao: values.description || base.descricao || DEFAULT_PRODUCT_DESCRIPTION,
            img: image,
            imagem: image,
            imagens: gallery,
        });
    }

    function mapProduct(product) {
        const normalized = normalizarChocolate(product);
        const key = getProductKey(normalized);
        const origin = inferProductOrigin(key, product?.origem || product?._origin);
        const isLocal = origin === "local";
        const isRemote = origin === "remoto";

        return {
            ...normalized,
            _key: key,
            _isLocal: isLocal,
            _isRemote: isRemote,
            _origin: origin,
        };
    }

    function renderUserName() {
        if (!elements.user) {
            return;
        }

        const currentText = String(elements.user.textContent || "").trim();

        if (!currentText) {
            const userName = String(OPTIONS.userName || "Administrador").trim();
            elements.user.textContent = `Logado como ${userName}`;
        }
    }

    function showStatus(message, type = "info") {
        if (!elements.status) {
            return;
        }

        clearTimeout(state.statusTimer);
        elements.status.hidden = false;
        elements.status.dataset.status = type;
        elements.status.textContent = message;

        state.statusTimer = window.setTimeout(() => {
            hideStatus();
        }, 4200);
    }

    function hideStatus() {
        if (!elements.status) {
            return;
        }

        elements.status.hidden = true;
        elements.status.textContent = "";
        delete elements.status.dataset.status;
    }

    function updateModeUI() {
        const editingProduct = getEditingProduct();
        const isEditing = Boolean(editingProduct);
        const targetLabel = editingProduct?._isRemote
            ? "item salvo no banco"
            : editingProduct?._isLocal
                ? "item salvo localmente"
                : "item do catálogo";

        if (elements.modeResume) {
            elements.modeResume.textContent = isEditing ? `Editando ${editingProduct.nome}` : "Novo produto";
        }

        if (elements.modeLabel) {
            elements.modeLabel.textContent = isEditing ? "Editar produto" : "Novo produto";
        }

        if (elements.modeText) {
            elements.modeText.textContent = isEditing
                ? `Ajuste os dados e salve para atualizar este ${targetLabel}.`
                : "Preencha os campos para criar um produto novo. Novos cadastros são enviados ao banco e refletidos na vitrine.";
        }

        if (elements.publish) {
            elements.publish.textContent = isEditing ? "Salvar alterações" : "Publicar produto";
        }

        if (elements.cancelEdit) {
            elements.cancelEdit.hidden = !isEditing;
        }
    }

    function updatePreview() {
        const existingProduct = getEditingProduct();
        const values = getFormValues();
        const previewName = values.name || "Chocolate sem nome";
        const previewCategory = values.category || existingProduct?.categoria || "Categoria";
        const previewWeight = values.weight || existingProduct?.peso || "Peso não informado";
        const previewHighlight = values.highlight || existingProduct?.destaque || "Seleção da casa";
        const previewDescription = values.description || existingProduct?.descricao || "Adicione imagem, destaque e descrição para acompanhar o produto antes de publicar.";
        const previewImage = values.image || existingProduct?.imagem || FALLBACK_IMAGE;
        const previewSlug = existingProduct?.slug || slugifyProductName(previewName) || "será gerado automaticamente";
        const previewRef = values.ref || existingProduct?.ref || "será gerada automaticamente";
        const galleryImages = getGalleryImages(previewImage, values.gallery);

        elements.preview.image.src = previewImage;
        elements.preview.highlight.textContent = previewHighlight;
        elements.preview.name.textContent = previewName;
        elements.preview.price.textContent = formatCurrency(values.price || existingProduct?.preco || 0);
        elements.preview.category.textContent = previewCategory;
        elements.preview.weight.textContent = previewWeight;
        elements.preview.description.textContent = previewDescription;
        elements.preview.slug.textContent = previewSlug;
        elements.preview.ref.textContent = previewRef;
        elements.preview.gallery.textContent = `${galleryImages.length} ${galleryImages.length === 1 ? "imagem" : "imagens"}`;
    }

    function resetForm(options = {}) {
        form.reset();
        state.editingKey = null;
        updateModeUI();
        updatePreview();

        if (!options.keepStatus) {
            hideStatus();
        }
    }

    function fillForm(product) {
        elements.fields.image.value = product.imagem || "";
        elements.fields.name.value = product.nome || "";
        elements.fields.price.value = Number(product.preco) || "";
        elements.fields.category.value = product.categoria || "";
        elements.fields.weight.value = product.peso || "";
        elements.fields.ref.value = product.ref || "";
        elements.fields.highlight.value = product.destaque || "";
        elements.fields.description.value = product.descricao || "";
        elements.fields.gallery.value = (product.imagens || [])
            .filter((image) => image && image !== product.imagem)
            .join("\n");
    }

    function compareProducts(a, b) {
        switch (state.filters.sort) {
            case "preco-desc":
                return (Number(b.preco) || 0) - (Number(a.preco) || 0);
            case "preco-asc":
                return (Number(a.preco) || 0) - (Number(b.preco) || 0);
            case "categoria":
                return String(a.categoria || "").localeCompare(String(b.categoria || ""), "pt-BR");
            case "nome-asc":
            default:
                return String(a.nome || "").localeCompare(String(b.nome || ""), "pt-BR");
        }
    }

    function getFilteredProducts() {
        const query = normalizeSearch(state.filters.query);

        return state.products
            .filter((product) => {
                if (state.filters.source === "locais" && !product._isLocal) {
                    return false;
                }

                if (state.filters.source === "catalogo" && product._isLocal) {
                    return false;
                }

                if (!query) {
                    return true;
                }

                const searchable = normalizeSearch([
                    product.nome,
                    product.categoria,
                    product.slug,
                    product.ref,
                    product.destaque,
                    product.peso,
                    product.descricao,
                ].join(" "));

                return searchable.includes(query);
            })
            .sort(compareProducts);
    }

    function renderMetrics() {
        const categories = new Set(
            state.products
                .map((product) => String(product.categoria || "").trim())
                .filter(Boolean)
        );

        elements.totalProducts.textContent = String(state.products.length);
        elements.totalLocals.textContent = String(state.localKeys.size);
        elements.totalRemoved.textContent = String(state.removed.length);
        elements.totalCategories.textContent = String(categories.size);
    }

    function renderEmptyState(message) {
        return `<p class="admin-product-list__empty">${escapeHtml(message)}</p>`;
    }

    function bindCardImageFallbacks(container) {
        container.querySelectorAll("img").forEach((image) => {
            image.addEventListener("error", () => {
                if (image.dataset.fallbackApplied === "true") {
                    return;
                }

                image.dataset.fallbackApplied = "true";
                image.src = FALLBACK_IMAGE;
            }, { once: true });
        });
    }

    function renderProductCard(product) {
        const meta = [
            product.categoria || "Chocolate",
            product.peso || "Peso não informado",
            product.ref || "Sem referência",
        ].join(" | ");

        const sourceLabel = product._isLocal ? "Local" : product._isRemote ? "Banco" : "Catálogo";
        const sourceClass = product._isLocal ? "admin-chip--local" : "admin-chip--catalogo";
        const detailLabel = product.destaque || "Sem destaque";

        return `
            <article class="admin-product-item">
                <div class="admin-product-item__media">
                    <img src="${escapeHtml(product.imagem || FALLBACK_IMAGE)}" alt="${escapeHtml(product.nome)}" loading="lazy">
                </div>

                <div class="admin-product-item__content">
                    <div class="admin-product-item__top">
                        <strong>${escapeHtml(product.nome)}</strong>
                        <span class="admin-product-item__price">${escapeHtml(formatCurrency(product.preco))}</span>
                    </div>

                    <p class="admin-product-item__meta">${escapeHtml(meta)}</p>

                    <div class="admin-product-item__chips">
                        <span class="admin-chip ${sourceClass}">${escapeHtml(sourceLabel)}</span>
                        <span class="admin-chip">${escapeHtml(product.slug)}</span>
                        <span class="admin-chip">${escapeHtml(detailLabel)}</span>
                    </div>
                </div>

                <div class="admin-product-item__actions">
                    <button class="admin-action admin-action--ghost" type="button" data-action="edit" data-key="${escapeHtml(product._key)}">Editar</button>
                    <button class="admin-action admin-action--danger" type="button" data-action="remove" data-key="${escapeHtml(product._key)}">Remover</button>
                </div>
            </article>
        `;
    }

    function renderRemovedCard(product) {
        const meta = [
            product.categoria || "Chocolate",
            product.peso || "Peso não informado",
            product.ref || "Sem referência",
        ].join(" | ");
        const originLabel = product._origin === "remoto"
            ? "Banco"
            : product._origin === "local"
                ? "Local"
                : "Catálogo";

        return `
            <article class="admin-product-item admin-product-item--removed">
                <div class="admin-product-item__media">
                    <img src="${escapeHtml(product.imagem || FALLBACK_IMAGE)}" alt="${escapeHtml(product.nome)}" loading="lazy">
                </div>

                <div class="admin-product-item__content">
                    <div class="admin-product-item__top">
                        <strong>${escapeHtml(product.nome)}</strong>
                        <span class="admin-product-item__price">${escapeHtml(formatCurrency(product.preco))}</span>
                    </div>

                    <p class="admin-product-item__meta">${escapeHtml(meta)}</p>

                    <div class="admin-product-item__chips">
                        <span class="admin-chip admin-chip--removed">Removido</span>
                        <span class="admin-chip">${escapeHtml(originLabel)}</span>
                        <span class="admin-chip">${escapeHtml(product.slug)}</span>
                    </div>
                </div>

                <div class="admin-product-item__actions">
                    <button class="admin-action admin-action--success" type="button" data-action="restore" data-key="${escapeHtml(product._key)}">Restaurar</button>
                </div>
            </article>
        `;
    }

    function renderProductList() {
        const products = getFilteredProducts();

        elements.productList.innerHTML = products.length > 0
            ? products.map(renderProductCard).join("")
            : renderEmptyState("Nenhum produto encontrado com os filtros atuais.");

        bindCardImageFallbacks(elements.productList);
    }

    function renderRemovedList() {
        elements.removedList.hidden = !state.removedOpen;
        elements.removedToggle.setAttribute("aria-expanded", state.removedOpen ? "true" : "false");
        elements.removedToggle.textContent = state.removedOpen ? "Ocultar itens removidos" : "Reexibir itens removidos";

        elements.removedList.innerHTML = state.removed.length > 0
            ? state.removed.map(renderRemovedCard).join("")
            : renderEmptyState("Nenhum item removido por aqui.");

        bindCardImageFallbacks(elements.removedList);
    }

    async function loadData() {
        const [catalogProducts, remoteProducts, localProducts] = await Promise.all([
            typeof buscarCatalogoBase === "function" ? buscarCatalogoBase() : Promise.resolve([]),
            fetchRemoteProducts(),
            Promise.resolve(lerChocolatesLocais()),
        ]);
        const removedKeys = readRemovedKeys();
        const completeCatalog = mesclarChocolates(catalogProducts, remoteProducts, localProducts);
        const catalogByKey = new Map();
        const backup = readRemovedBackup();

        state.localKeys = new Set(localProducts.map((product) => getProductKey(normalizarChocolate(product))));
        state.remoteKeys = new Set(remoteProducts.map((product) => getProductKey(normalizarChocolate(product))));

        completeCatalog.forEach((product) => {
            const normalized = normalizarChocolate(product);
            catalogByKey.set(getProductKey(normalized), normalized);
        });

        state.products = completeCatalog
            .map(mapProduct)
            .filter((product) => !removedKeys.has(product._key));

        state.removed = Array.from(removedKeys)
            .map((productKey) => {
                const backupProduct = backup.find((item) => getProductKey(item) === productKey);

                if (backupProduct) {
                    return mapProduct(backupProduct);
                }

                const catalogProduct = catalogByKey.get(productKey);
                return catalogProduct ? mapProduct(catalogProduct) : null;
            })
            .filter(Boolean);

        if (state.editingKey && !state.products.some((product) => product._key === state.editingKey)) {
            state.editingKey = null;
        }

        updateModeUI();
        renderMetrics();
        renderProductList();
        renderRemovedList();
        renderUserName();
        updatePreview();
    }

    function persistLocalProduct(product, existingKey) {
        const localProducts = lerChocolatesLocais();
        const baseKey = normalizeKey(existingKey || getProductKey(product));
        const updatedProducts = localProducts.filter((item) => getProductKey(normalizarChocolate(item)) !== baseKey);

        updatedProducts.push(product);
        salvarChocolatesLocais(updatedProducts);
        clearRemovedProduct(baseKey);
    }

    function handleEdit(productKey) {
        const product = state.products.find((item) => item._key === productKey);

        if (!product) {
            return;
        }

        state.editingKey = productKey;
        fillForm(product);
        updateModeUI();
        updatePreview();
        hideStatus();

        document.getElementById("admin-titulo")?.scrollIntoView({
            behavior: "smooth",
            block: "start",
        });
    }

    async function handleRemove(productKey) {
        const product = state.products.find((item) => item._key === productKey);

        if (!product) {
            return;
        }

        const confirmed = window.confirm(`Remover "${product.nome}" da vitrine?`);

        if (!confirmed) {
            return;
        }

        try {
            if (product._isRemote) {
                await requestRemoteProducts(
                    "DELETE",
                    { id: Number(product.id) },
                    "Não foi possível remover o produto salvo no banco."
                );
                markProductAsRemoved(product, "remoto");
            } else {
                if (product._isLocal) {
                    removeLocalProductByKey(productKey);
                    markProductAsRemoved(product, "local");
                } else {
                    markProductAsRemoved(product, "catalogo");
                }
            }
        } catch (error) {
            console.error("Não foi possível remover o produto.", error);
            showStatus(error.message || "Não foi possível remover o produto.", "error");
            return;
        }

        if (state.editingKey === productKey) {
            resetForm({ keepStatus: true });
        }

        showStatus("Produto removido da vitrine. Você pode restaurar na lista de backup.", "success");
        state.removedOpen = true;
        await loadData();
    }

    async function handleRestore(productKey) {
        const product = state.removed.find((item) => item._key === productKey);

        if (!product) {
            return;
        }

        try {
            if (product._origin === "remoto") {
                await requestRemoteProducts(
                    "POST",
                    buildRemotePayloadFromProduct(product),
                    "Não foi possível restaurar o produto no banco."
                );
            } else if (product._origin === "local") {
                restoreLocalProduct(product);
            }

            clearRemovedProduct(productKey);
        } catch (error) {
            console.error("Não foi possível restaurar o produto.", error);
            showStatus(error.message || "Não foi possível restaurar o produto.", "error");
            return;
        }

        showStatus("Produto restaurado com sucesso.", "success");

        state.removedOpen = true;
        await loadData();
    }

    function bindPreviewEvents() {
        Object.values(elements.fields).forEach((field) => {
            field.addEventListener("input", updatePreview);
        });
    }

    function bindToolbarEvents() {
        elements.search.addEventListener("input", (event) => {
            state.filters.query = event.target.value;
            renderProductList();
        });

        elements.sourceFilter.addEventListener("change", (event) => {
            state.filters.source = event.target.value;
            renderProductList();
        });

        elements.sort.addEventListener("change", (event) => {
            state.filters.sort = event.target.value;
            renderProductList();
        });

        elements.clearFilters.addEventListener("click", () => {
            state.filters.query = "";
            state.filters.source = "todos";
            state.filters.sort = "nome-asc";
            elements.search.value = "";
            elements.sourceFilter.value = "todos";
            elements.sort.value = "nome-asc";
            renderProductList();
        });

        elements.removedToggle.addEventListener("click", () => {
            state.removedOpen = !state.removedOpen;
            renderRemovedList();
        });

        elements.clear.addEventListener("click", () => {
            resetForm();
            showStatus("Campos limpos. Pronto para cadastrar outro produto.", "info");
        });

        elements.cancelEdit.addEventListener("click", () => {
            resetForm();
            showStatus("Edicao cancelada.", "info");
        });
    }

    function bindListEvents() {
        elements.productList.addEventListener("click", async (event) => {
            const button = event.target.closest("button[data-action]");

            if (!button) {
                return;
            }

            const productKey = button.dataset.key || "";
            const action = button.dataset.action;

            if (action === "edit") {
                handleEdit(productKey);
                return;
            }

            if (action === "remove") {
                await handleRemove(productKey);
            }
        });

        elements.removedList.addEventListener("click", async (event) => {
            const button = event.target.closest("button[data-action='restore']");

            if (!button) {
                return;
            }

            await handleRestore(button.dataset.key || "");
        });
    }

    function bindLogout() {
        if (!elements.logout || OPTIONS.logoutMode !== "local") {
            return;
        }

        elements.logout.addEventListener("click", () => {
            encerrarSessaoAutenticada();
            window.location.href = OPTIONS.loginUrl || "login.php";
        });
    }

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        try {
            const existingProduct = getEditingProduct();
            const isEditingRemoteProduct = Boolean(existingProduct && existingProduct._isRemote);
            const shouldPublishToRemote = !existingProduct || isEditingRemoteProduct;

            if (shouldPublishToRemote) {
                const method = isEditingRemoteProduct ? "PUT" : "POST";
                const payload = buildRemotePayload(existingProduct);

                if (isEditingRemoteProduct) {
                    payload.id = Number(existingProduct.id);
                }

                await requestRemoteProducts(
                    method,
                    payload,
                    isEditingRemoteProduct
                        ? "Não foi possível atualizar o produto salvo no banco."
                        : "Não foi possível publicar o produto no banco."
                );
            } else {
                const product = buildProductFromForm(existingProduct);
                persistLocalProduct(product, existingProduct?._key);
            }

            resetForm({ keepStatus: true });
            showStatus(
                !existingProduct
                    ? "Produto publicado com sucesso. A vitrine já pode usar esse cadastro do banco."
                    : isEditingRemoteProduct
                        ? "Produto atualizado no banco com sucesso."
                        : existingProduct._isLocal
                            ? "Produto local atualizado com sucesso."
                            : "Produto do catálogo personalizado localmente com sucesso.",
                "success"
            );
            await loadData();
        } catch (error) {
            console.error("Não foi possível salvar o produto.", error);
            showStatus(error.message || "Não foi possível salvar o produto. Tente novamente.", "error");
        }
    });

    if (elements.preview.image) {
        elements.preview.image.addEventListener("error", () => {
            if (elements.preview.image.src.includes(FALLBACK_IMAGE)) {
                return;
            }

            elements.preview.image.src = FALLBACK_IMAGE;
        });
    }

    bindPreviewEvents();
    bindToolbarEvents();
    bindListEvents();
    bindLogout();
    updateModeUI();
    updatePreview();

    loadData().catch((error) => {
        console.error("Não foi possível carregar o painel admin.", error);
        showStatus("Não foi possível carregar o catálogo neste momento.", "error");
    });
})();
