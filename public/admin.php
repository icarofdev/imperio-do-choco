<?php
declare(strict_types=1);

require_once __DIR__ . "/../app/seguranca.php";

iniciarSessaoSegura();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$papelUsuario = (string) ($_SESSION["usuario_papel"] ?? "cliente");

if ($papelUsuario !== "admin") {
    header("Location: conta.php");
    exit;
}

$nomeUsuario = (string) ($_SESSION["usuario_nome"] ?? "Administrador");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="../assets/js/theme-init.js?v=20260731-3"></script>
    <link rel="stylesheet" href="../assets/css/admin.css?v=20260527-1">
    <link rel="stylesheet" href="../assets/css/theme.css?v=20260811-3">
    <script src="../assets/js/theme.js?v=20260731-3" defer></script>
    <title>Admin | Velle Dulcis</title>
</head>
<body class="admin-page">
    <main class="admin-shell">
        <header class="admin-topbar">
            <a class="admin-topbar__voltar" href="index.php">Voltar para a vitrine</a>

            <div class="admin-topbar__acoes" data-theme-toggle-host>
                <p id="admin-usuario" class="admin-topbar__usuario" aria-live="polite">
                    <?php echo "Logado como " . htmlspecialchars($nomeUsuario, ENT_QUOTES, "UTF-8"); ?>
                </p>
                <a id="btn-sair" class="admin-topbar__sair" href="logout.php">Sair</a>
            </div>
        </header>

        <section class="admin-overview" aria-label="Resumo do painel">
            <article class="admin-metric-card">
                <span class="admin-metric-card__label">Produtos ativos</span>
                <strong id="admin-total-produtos" class="admin-metric-card__value">0</strong>
                <p class="admin-metric-card__text">Itens visiveis agora na vitrine.</p>
            </article>

            <article class="admin-metric-card">
                <span class="admin-metric-card__label">Itens locais</span>
                <strong id="admin-total-locais" class="admin-metric-card__value">0</strong>
                <p class="admin-metric-card__text">Produtos criados ou sobrescritos neste navegador.</p>
            </article>

            <article class="admin-metric-card">
                <span class="admin-metric-card__label">Itens removidos</span>
                <strong id="admin-total-removidos" class="admin-metric-card__value">0</strong>
                <p class="admin-metric-card__text">Produtos ocultados que podem ser restaurados.</p>
            </article>

            <article class="admin-metric-card">
                <span class="admin-metric-card__label">Categorias</span>
                <strong id="admin-total-categorias" class="admin-metric-card__value">0</strong>
                <p class="admin-metric-card__text">Variedade atual de grupos no catálogo.</p>
            </article>
        </section>

        <div class="admin-workspace">
            <section class="form-container admin-card" aria-labelledby="admin-titulo">
                <div class="admin-card__header">
                    <div>
                        <p class="admin-card__eyebrow">Painel administrativo</p>
                        <h1 id="admin-titulo">Cadastrar ou editar produto</h1>
                    </div>

                    <aside class="admin-summary" aria-live="polite">
                        <span class="admin-summary__label">Modo atual</span>
                        <strong id="admin-mode-resumo" class="admin-summary__value admin-summary__value--compact">Novo produto</strong>
                    </aside>
                </div>

                <p class="form-container__intro">
                    Publique novos chocolates, ajuste itens existentes e acompanhe uma previa do card antes de salvar.
                </p>

                <div class="admin-mode">
                    <div class="admin-mode__content">
                        <strong id="admin-mode-label">Novo produto</strong>
                        <span id="admin-mode-texto">Preencha os campos para criar um produto novo na vitrine local.</span>
                    </div>
                    <button id="btn-cancelar-edicao" class="admin-toolbar__secondary admin-toolbar__secondary--compact" type="button" hidden>Cancelar edicao</button>
                </div>

                <p id="admin-status" class="admin-status" aria-live="polite" hidden></p>

                <form id="admin-form" class="admin-form">
                    <div class="admin-form__grid">
                        <label class="admin-field admin-field--full" for="imgUrl">
                            <span class="admin-field__label">Imagem principal</span>
                            <span class="admin-field__hint">Use uma URL completa ou um caminho local valido.</span>
                            <input type="text" id="imgUrl" placeholder="URL da imagem principal" required>
                        </label>

                        <label class="admin-field" for="nome">
                            <span class="admin-field__label">Nome do chocolate</span>
                            <input type="text" id="nome" placeholder="Nome do chocolate" required>
                        </label>

                        <label class="admin-field" for="preco">
                            <span class="admin-field__label">Preço</span>
                            <input type="number" id="preco" placeholder="Ex: 25.90" step="0.01" min="0.01" inputmode="decimal" required>
                        </label>

                        <label class="admin-field" for="categoria">
                            <span class="admin-field__label">Categoria</span>
                            <input type="text" id="categoria" placeholder="Ex: Ovos de Páscoa">
                        </label>

                        <label class="admin-field" for="peso">
                            <span class="admin-field__label">Peso ou tamanho</span>
                            <input type="text" id="peso" placeholder="Ex: 210g">
                        </label>

                        <label class="admin-field" for="ref">
                            <span class="admin-field__label">Referencia</span>
                            <input type="text" id="ref" placeholder="Codigo interno ou SKU">
                        </label>

                        <label class="admin-field" for="destaque">
                            <span class="admin-field__label">Selo de destaque</span>
                            <input type="text" id="destaque" placeholder="Ex: 119 pontos Kop Club">
                        </label>

                        <label class="admin-field admin-field--full" for="descricao">
                            <span class="admin-field__label">Descrição</span>
                            <textarea id="descricao" placeholder="Descrição do produto"></textarea>
                        </label>

                        <label class="admin-field admin-field--full" for="galeria">
                            <span class="admin-field__label">Galeria complementar</span>
                            <span class="admin-field__hint">Adicione uma URL por linha para montar a galeria do produto.</span>
                            <textarea id="galeria" placeholder="Outras imagens (uma URL por linha)"></textarea>
                        </label>
                    </div>

                    <div class="admin-toolbar">
                        <button id="btn-publicar" type="submit">Publicar produto</button>
                        <button id="btn-limpar" class="admin-toolbar__secondary" type="button">Limpar campos</button>
                    </div>
                </form>
            </section>

            <aside class="admin-card admin-preview-card" aria-labelledby="admin-preview-titulo">
                <div class="admin-card__header">
                    <div>
                        <p class="admin-card__eyebrow">Previa ao vivo</p>
                        <h2 id="admin-preview-titulo">Como o cliente vai ver</h2>
                    </div>
                </div>

                <div class="admin-preview">
                    <div class="admin-preview__media">
                        <img id="admin-preview-imagem" class="admin-preview__imagem" src="../assets/images/products/product-tablete-classico.webp" alt="Prévia do produto" width="754" height="1000">
                    </div>

                    <div class="admin-preview__body">
                        <span id="admin-preview-destaque" class="admin-preview__badge">Seleção da casa</span>
                        <h3 id="admin-preview-nome">Chocolate sem nome</h3>
                        <p id="admin-preview-preco" class="admin-preview__preco">R$ 0,00</p>

                        <div class="admin-preview__meta">
                            <span id="admin-preview-categoria">Categoria</span>
                            <span id="admin-preview-peso">Peso não informado</span>
                        </div>

                        <p id="admin-preview-descricao" class="admin-preview__descricao">
                            Adicione imagem, destaque e descrição para acompanhar o produto antes de publicar.
                        </p>

                        <dl class="admin-preview__details">
                            <div>
                                <dt>Slug</dt>
                                <dd id="admin-preview-slug">será gerado automaticamente</dd>
                            </div>
                            <div>
                                <dt>Referencia</dt>
                                <dd id="admin-preview-ref">será gerada automaticamente</dd>
                            </div>
                            <div>
                                <dt>Galeria</dt>
                                <dd id="admin-preview-galeria">1 imagem</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </aside>
        </div>

        <section class="admin-card" aria-labelledby="admin-lista-titulo">
            <div class="admin-card__header">
                <div>
                    <p class="admin-card__eyebrow">Gestão de catálogo</p>
                    <h2 id="admin-lista-titulo">Produtos cadastrados</h2>
                </div>
            </div>

            <p class="form-container__intro">
                Pesquise por nome, categoria, slug ou referência. O painel combina catálogo base, produtos do banco e ajustes locais sem perder o controle de cada origem.
            </p>

            <div class="admin-list-toolbar">
                <label class="admin-search-field" for="admin-busca">
                    <span class="admin-search-field__label">Buscar produto</span>
                    <input id="admin-busca" type="search" placeholder="Nome, categoria, slug ou referência">
                </label>

                <label class="admin-select-field" for="admin-filtro-origem">
                    <span class="admin-search-field__label">Origem</span>
                    <select id="admin-filtro-origem">
                        <option value="todos">Todos</option>
                        <option value="locais">Somente locais</option>
                        <option value="catalogo">Somente catálogo</option>
                    </select>
                </label>

                <label class="admin-select-field" for="admin-ordenacao">
                    <span class="admin-search-field__label">Ordenar por</span>
                    <select id="admin-ordenacao">
                        <option value="nome-asc">Nome (A-Z)</option>
                        <option value="preco-desc">Maior preço</option>
                        <option value="preco-asc">Menor preço</option>
                        <option value="categoria">Categoria</option>
                    </select>
                </label>

                <button id="btn-limpar-filtros" class="admin-toolbar__secondary admin-toolbar__secondary--compact" type="button">Limpar filtros</button>
            </div>

            <div id="admin-lista-produtos" class="admin-product-list" aria-live="polite"></div>
        </section>

        <section class="admin-card" aria-labelledby="admin-removidos-titulo">
            <div class="admin-card__header">
                <div>
                    <p class="admin-card__eyebrow">Backup local</p>
                    <h2 id="admin-removidos-titulo">Itens removidos</h2>
                </div>
                <button id="btn-toggle-removidos" class="admin-toolbar__secondary admin-toolbar__secondary--compact" type="button" aria-expanded="false">
                    Reexibir itens removidos
                </button>
            </div>

            <p class="form-container__intro">
                Abra a lista para ver chocolates apagados neste navegador e restaurar os que quiser.
            </p>

            <div id="admin-lista-removidos" class="admin-product-list" aria-live="polite" hidden></div>
        </section>
    </main>

    <script>
        window.ADMIN_PANEL_OPTIONS = {
            loginUrl: "login.php",
            logoutMode: "server",
            userName: <?php echo json_encode($nomeUsuario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            remoteApiUrl: "admin-produtos.php",
            csrfToken: <?php echo json_encode(obterTokenCsrf(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        };
    </script>
    <script src="../assets/js/products-data.js?v=20260731-2"></script>
    <script src="../assets/js/admin-painel.js?v=20260731-2"></script>
</body>
</html>
