(function () {
    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatPrice(value) {
        return `R$ ${Number(value || 0).toFixed(2).replace(".", ",")}`;
    }

    function summary(product) {
        return [product.categoria, product.peso].map((item) => String(item || "").trim()).filter(Boolean).join(" · ");
    }

    function create(product, options = {}) {
        const index = Number(options.index || 0);
        const quantity = Math.max(0, Number(options.quantity || 0));
        const card = document.createElement("article");
        card.className = "card";
        card.style.setProperty("--card-stagger-index", String(index % 12));
        card.innerHTML = `
            <a class="card__imagem-link" href="produto.html?id=${encodeURIComponent(product.slug)}">
                <div class="card__imagem-box"><img src="${escapeHtml(product.imagem)}" alt="${escapeHtml(product.nome)}" loading="lazy" decoding="async"></div>
            </a>
            <div class="card__conteudo">
                <a class="card__link" href="produto.html?id=${encodeURIComponent(product.slug)}">
                    <p class="card__descricao">${escapeHtml(summary(product))}</p>
                    <div class="card__linha"><h3>${escapeHtml(product.nome)}</h3><p class="card__preco">${formatPrice(product.preco)}</p></div>
                    ${product.destaque ? `<span class="card__selo">${escapeHtml(product.destaque)}</span>` : ""}
                    ${quantity > 0 ? `<span class="card__quantidade-info">${quantity} na sacola</span>` : ""}
                </a>
            </div>
        `;

        const addButton = document.createElement("button");
        addButton.type = "button";
        addButton.className = "card__cta";
        addButton.textContent = "Adicionar";
        addButton.setAttribute("aria-label", `Adicionar ${product.nome} ao carrinho`);
        addButton.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            options.onAdd?.(product, addButton);
        });
        card.querySelector(".card__conteudo")?.appendChild(addButton);
        card.querySelectorAll("img").forEach((image) => {
            image.addEventListener("error", () => {
                if (image.dataset.fallbackApplied === "true") {
                    return;
                }

                image.dataset.fallbackApplied = "true";
                image.src = "../assets/images/products/product-tablete-classico.webp";
            });
        });
        return card;
    }

    window.VelleProductCard = { create };
}());
