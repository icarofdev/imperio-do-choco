<?php
declare(strict_types=1);

require_once __DIR__ . "/../app/seguranca.php";

iniciarSessaoSegura();

$usuarioAutenticado = isset($_SESSION["usuario_id"]);
$usuarioNome = (string) ($_SESSION["usuario_nome"] ?? "");
$usuarioPapel = (string) ($_SESSION["usuario_papel"] ?? "cliente");
$mensagemBoasVindas = "";

if (isset($_SESSION["flash_boas_vindas"])) {
    $mensagemBoasVindas = (string) $_SESSION["flash_boas_vindas"];
    unset($_SESSION["flash_boas_vindas"]);
}

$destinoConta = $usuarioAutenticado
    ? ($usuarioPapel === "admin" ? "admin.php" : "conta.php")
    : "login.php";
$rotuloConta = $usuarioAutenticado ? "" : "Entre ou cadastre-se";
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velle Dulcis</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=20260602-8">
</head>

<body class="pagina-carregando pagina-principal">
    <script src="../assets/js/theme-init.js"></script>

    <header class="topo-site">
        <div class="marca-site">
            <img class="marca-site__logo" src="../assets/img/logo-velle-dulcis.png" alt="Velle Dulcis">
        </div>

        <nav class="menu-topo" aria-label="Principal">
            <div class="menu-topo__item menu-topo__item--mega">
                <a href="#vitrine" class="menu-topo__link menu-topo__link--dropdown">
                    <span>Chocolate</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 10l5 5 5-5"></path>
                    </svg>
                </a>

                <div class="mega-menu" role="group" aria-label="Explorar chocolates">
                    <div class="mega-menu__colunas">
                        <section class="mega-menu__coluna">
                            <p class="mega-menu__titulo">Destaques</p>
                            <a href="#vitrine" class="mega-menu__item">
                                <strong>Seleção da Casa</strong>
                                <span>Os chocolates que melhor representam a vitrine neste momento.</span>
                            </a>
                            <a href="#vitrine" class="mega-menu__item">
                                <strong>Presentes Especiais</strong>
                                <span>Opções pensadas para impressionar com sabor, acabamento e presença.</span>
                            </a>
                            <a href="#vitrine" class="mega-menu__item">
                                <strong>Importados</strong>
                                <span>Sabores diferentes para quem quer experimentar algo fora do comum.</span>
                            </a>
                        </section>

                        <section class="mega-menu__coluna">
                            <p class="mega-menu__titulo">Tipos</p>
                            <a href="#vitrine" class="mega-menu__lista-link">Chocolate recheado</a>
                            <a href="#vitrine" class="mega-menu__lista-link">Chocolate artesanal</a>
                            <a href="#vitrine" class="mega-menu__lista-link">Caixas premium</a>
                            <a href="#vitrine" class="mega-menu__lista-link">Tabletes</a>
                            <a href="#vitrine" class="mega-menu__lista-link">Importados</a>
                            <a href="#vitrine" class="mega-menu__lista-link">Mais Vendidos</a>
                        </section>

                        <section class="mega-menu__coluna">
                            <p class="mega-menu__titulo">Momentos</p>
                            <a href="#vitrine" class="mega-menu__lista-link">Para presentear</a>
                            <a href="#vitrine" class="mega-menu__lista-link">Para dividir</a>
                            <a href="#vitrine" class="mega-menu__lista-link">Para kits</a>
                            <a href="#vitrine" class="mega-menu__lista-link">Novidades</a>
                            <a href="#vitrine" class="mega-menu__lista-link">Vitrine completa</a>
                        </section>
                    </div>

                    <div class="mega-menu__destaques">
                        <a class="mega-menu__card" href="produto.html?id=jojo-moranguinho">
                            <img src="https://www.rickdoces.com.br/estatico/rickdoces/images/temp/620_alpinellawithstrawberry100gr.jpeg?v=1761647260" alt="Jojo Moranguinho">
                            <div class="mega-menu__card-conteudo">
                                <span class="mega-menu__card-tag">Seleção da casa</span>
                                <strong>Jojo Moranguinho</strong>
                            </div>
                        </a>

                        <a class="mega-menu__card" href="produto.html?id=beicinho-de-chocolate">
                            <img src="https://www.rickdoces.com.br/estatico/rickdoces/images/temp/620_560cde9e09eea7d7770f1c1d5db26d91.jpeg?v=1768478586" alt="Beicinho de Chocolate">
                            <div class="mega-menu__card-conteudo">
                                <span class="mega-menu__card-tag">Favorito da vitrine</span>
                                <strong>Beicinho de Chocolate</strong>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <a href="historia.html">Nossa história</a>
        </nav>

        <div class="acoes-topo">
            <button id="btn-tema" class="acao-topo acao-topo--tema" type="button" aria-label="Alternar modo escuro" title="Alternar tema">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 12.8A8.5 8.5 0 1111.2 3a6.5 6.5 0 109.8 9.8z"></path>
                </svg>
            </button>
            <button id="abrir-pesquisa" class="acao-topo" type="button" aria-label="Buscar">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7.5"></circle>
                    <path d="M16.5 16.5L21 21"></path>
                </svg>
            </button>
            <a
                id="link-conta"
                class="acao-topo acao-topo--link acao-topo--conta"
                href="<?php echo htmlspecialchars($destinoConta, ENT_QUOTES, "UTF-8"); ?>"
                aria-label="Entrar ou acessar conta"
                data-logado="<?php echo $usuarioAutenticado ? "true" : "false"; ?>"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M5 20c1.8-3.3 4.2-5 7-5s5.2 1.7 7 5"></path>
                </svg>
                <span id="link-conta-texto" data-hide-when-authenticated="true" <?php echo $usuarioAutenticado ? 'hidden' : ''; ?>>
                    <?php echo htmlspecialchars($rotuloConta, ENT_QUOTES, "UTF-8"); ?>
                </span>
            </a>
            <?php if (!$usuarioAutenticado): ?>
                <a class="acao-topo acao-topo--cadastro" href="cadastro.php">
                    Criar conta
                </a>
            <?php endif; ?>
            <button id="btn-carrinho" class="acao-topo acao-topo--carrinho" type="button" aria-label="Carrinho">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 8V7a4 4 0 118 0v1"></path>
                    <path d="M6 8h12l-1 11H7L6 8z"></path>
                </svg>
                <span id="qtd-itens">0</span>
            </button>

            <div id="popup-carrinho" class="popup-carrinho" aria-live="polite" aria-hidden="true">
                <div class="popup-carrinho__seta" aria-hidden="true"></div>
                <div class="popup-carrinho__conteudo">
                    <div class="popup-carrinho__icone" aria-hidden="true">&#10003;</div>
                    <div class="popup-carrinho__texto">
                        <strong>Adicionado com sucesso a sacola!</strong>
                        <span id="popup-carrinho-produto">Produto</span>
                    </div>
                </div>
                <div class="popup-carrinho__progresso">
                    <span id="popup-carrinho-barra"></span>
                </div>
            </div>
        </div>

        <button id="menu-mobile-toggle" class="menu-mobile-toggle" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="menu-mobile">
            <svg class="menu-mobile-toggle__icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7h16"></path>
                <path d="M4 12h16"></path>
                <path d="M4 17h16"></path>
            </svg>
        </button>

        <div id="menu-mobile" class="menu-mobile" aria-hidden="true">
            <button class="menu-mobile__backdrop" type="button" data-menu-mobile-close aria-label="Fechar menu"></button>
            <aside class="menu-mobile__painel" aria-label="Menu mobile">
                <div class="menu-mobile__topo">
                    <span class="menu-mobile__marca">Menu</span>
                    <button class="menu-mobile__fechar" type="button" data-menu-mobile-close aria-label="Fechar menu">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 6l12 12"></path>
                            <path d="M18 6L6 18"></path>
                        </svg>
                    </button>
                </div>

                <div class="menu-mobile__acoes">
                    <button class="menu-mobile__acao" type="button" data-mobile-action="theme">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M21 12.8A8.5 8.5 0 1111.2 3a6.5 6.5 0 109.8 9.8z"></path>
                        </svg>
                        <span>Tema</span>
                    </button>
                    <button class="menu-mobile__acao" type="button" data-mobile-action="search">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7.5"></circle>
                            <path d="M16.5 16.5L21 21"></path>
                        </svg>
                        <span>Buscar</span>
                    </button>
                    <a
                        id="link-conta-mobile"
                        class="menu-mobile__acao-link"
                        href="<?php echo htmlspecialchars($destinoConta, ENT_QUOTES, "UTF-8"); ?>"
                        aria-label="Entrar ou acessar conta"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M5 20c1.8-3.3 4.2-5 7-5s5.2 1.7 7 5"></path>
                        </svg>
                        <span id="link-conta-mobile-texto"><?php echo $usuarioAutenticado ? "Minha conta" : "Entrar"; ?></span>
                    </a>
                    <button class="menu-mobile__acao" type="button" data-mobile-action="cart">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8 8V7a4 4 0 118 0v1"></path>
                            <path d="M6 8h12l-1 11H7L6 8z"></path>
                        </svg>
                        <span>Sacola</span>
                    </button>
                </div>

                <nav class="menu-mobile__links" aria-label="Menu principal mobile">
                    <a class="menu-mobile__link" href="#vitrine">
                        <span>Chocolate</span>
                        <svg class="menu-mobile__link-seta" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14"></path>
                            <path d="M13 6l6 6-6 6"></path>
                        </svg>
                    </a>
                    <a class="menu-mobile__link" href="historia.html">
                        <span>Nossa história</span>
                        <svg class="menu-mobile__link-seta" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14"></path>
                            <path d="M13 6l6 6-6 6"></path>
                        </svg>
                    </a>
                    <?php if (!$usuarioAutenticado): ?>
                        <a class="menu-mobile__link" href="cadastro.php">
                            <span>Criar conta</span>
                            <svg class="menu-mobile__link-seta" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M5 12h14"></path>
                                <path d="M13 6l6 6-6 6"></path>
                            </svg>
                        </a>
                    <?php endif; ?>
                </nav>
            </aside>
        </div>
    </header>

    <div id="overlay-pesquisa" class="overlay-pesquisa oculto">
        <div class="overlay-pesquisa__cabecalho">
            <button id="fechar-pesquisa" class="overlay-pesquisa__fechar" type="button" aria-label="Fechar pesquisa">&times;</button>
        </div>

        <div class="overlay-pesquisa__conteudo">
            <h2>Pesquisar Chocolates</h2>

            <div class="pesquisa-wrapper">
                <div class="pesquisa-box">
                    <div class="pesquisa-campo">
                        <span class="pesquisa-icone" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">
                                <circle cx="11" cy="11" r="7.5"></circle>
                                <path d="M16.5 16.5L21 21"></path>
                            </svg>
                        </span>
                        <input type="text" id="barra-pesquisa" placeholder="Buscar chocolate...">
                        <button id="btn-limpar-pesquisa" class="btn-icone oculto" type="button" aria-label="Limpar pesquisa">&times;</button>
                    </div>

                    <div class="pesquisa-atalhos" aria-label="Atalhos de busca">
                        <span class="pesquisa-atalhos__rotulo">Atalhos rápidos</span>
                        <div id="lista-atalhos-pesquisa" class="pesquisa-atalhos__lista"></div>
                    </div>

                    <div id="painel-pesquisa" class="painel-pesquisa">
                        <div class="painel-pesquisa__bloco">
                            <h3>Sugestões</h3>
                            <div id="lista-sugestoes" class="lista-sugestoes"></div>
                        </div>

                        <div class="painel-pesquisa__bloco">
                            <div class="painel-pesquisa__topo">
                                <button id="btn-ver-todos" class="btn-ver-todos" type="button">Veja todos os produtos</button>
                            </div>
                            <div id="lista-resultados-pesquisa" class="lista-resultados-pesquisa"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="home-main">
        <section class="home-hero" aria-labelledby="home-hero-title">
            <div class="home-hero__conteudo">
                <span class="home-hero__rotulo">Velle Dulcis</span>
                <h1 id="home-hero-title">Chocolate com presença de presente</h1>
                <p>Uma vitrine mais calma, elegantemente pronta para mostrar os sabores especiais da casa.</p>
                <div class="home-hero__acoes">
                    <a href="#vitrine" class="home-hero__acao home-hero__acao--primaria">Ver chocolates</a>
                    <a href="historia.html" class="home-hero__acao">Nossa história</a>
                </div>
            </div>
        </section>

      <!-- NOVO CARROSSEL 3D -->

<section class="novo-carrossel">

    <h2 class="novo-carrossel-titulo">
        Chocolates Premium
    </h2>

    <div class="slider-3d">

        <button class="slider-btn prev">
            &#10094;
        </button>

        <div class="slider-container">

    <div class="slide active">
        <img src="WhatsApp Image 2026-05-12 at 08.21.24.jpeg" alt="">
        <h3>Trufle d'Or</h3>
    </div>

    <div class="slide">
        <img src="WhatsApp Image 2026-05-12 at 09.17.31.jpeg" alt="">
        <h3>Praline de Liège</h3>
    </div>

    <div class="slide">
        <img src="WhatsApp Image 2026-05-14 at 10.40.57.jpeg" alt="">
        <h3>Coeur Framboise</h3>
    </div>

    <div class="slide">
        <img src="WhatsApp Image 2026-05-14 at 11.50.05.jpeg" alt="">
        <h3>Gianduja Swirl</h3>
    </div>

    <div class="slide">
        <img src="WhatsApp Image 2026-05-14 at 10.56.35.jpeg" alt="">
        <h3>Mendiant Deluxe</h3>
    </div>

    <!-- NOVOS -->

    <div class="slide">
        <img src="WhatsApp Image 2026-05-14 at 11.52.17.jpeg" alt="">
        <h3>Noisette Royale</h3>
    </div>

    <div class="slide">
        <img src="WhatsApp Image 2026-05-14 at 11.55.52.jpeg" alt="">
        <h3>Velvet Cacao</h3>
    </div>

    <div class="slide">
        <img src="WhatsApp Image 2026-05-14 at 12.04.47.jpeg" alt="">
        <h3>Caramel Étoilé</h3>
    </div>

</div>
        <button class="slider-btn next">
            &#10095;
        </button>

    </div>

</section>

<!-- FIM NOVO CARROSSEL -->
        <section id="vitrine" class="grid-chocolates">
            <div class="vitrine-intro">
                <p class="vitrine-intro__kicker">Chocolate</p>
                <h1>Nossos chocolates</h1>
                <p>Sabores especiais preparados para presentear e transformar qualquer momento em algo memorável.</p>
            </div>

            <div class="vitrine-toolbar" aria-label="Controles da vitrine">
                <div class="vitrine-toolbar__sort" aria-label="Ordenar produtos">
                    <button class="vitrine-toolbar__button ativo" type="button" data-vitrine-sort="recommended" aria-pressed="true">Recomendados</button>
                    <button class="vitrine-toolbar__button" type="button" data-vitrine-sort="price-asc" aria-pressed="false">Preço: menor para maior</button>
                </div>
                <div class="vitrine-toolbar__lado">
                    <p id="vitrine-count" class="vitrine-toolbar__count" aria-live="polite">Exibindo: 0 / 0</p>
                    <div class="vitrine-setas" aria-label="Navegar produtos">
                        <button class="vitrine-seta vitrine-seta--oculta" type="button" data-vitrine-scroll="prev" aria-label="Ver produtos anteriores" aria-hidden="true" tabindex="-1">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M15 6l-6 6 6 6"></path>
                            </svg>
                        </button>
                        <button class="vitrine-seta" type="button" data-vitrine-scroll="next" aria-label="Ver mais produtos">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M9 6l6 6-6 6"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div id="filtros-categoria" class="filtros-categoria" aria-label="Filtrar produtos por categoria"></div>
            <div id="container-cards" class="container"></div>
        </section>
    </main>

    <footer class="rodape-site">
        <div class="rodape-site__container">
            <div class="rodape-site__grid">
                <section class="rodape-site__coluna">
                    <h3>Shop</h3>
                    <a href="#vitrine">Presentes &amp; Kits</a>
                    <a href="#vitrine">Mais Vendidos</a>
                    <a href="#vitrine">Importados</a>
                    <a href="#vitrine">Edições Premium</a>
                </section>

                <section class="rodape-site__coluna">
                    <h3>Aprenda</h3>
                    <a href="historia.html">Nossa Historia</a>
                    <a href="#vitrine">Guia de Sabores</a>
                    <a href="#vitrine">Como Montar Presentes</a>
                    <a href="#vitrine">Novidades</a>
                </section>

                <section class="rodape-site__coluna">
                    <h3>Suporte</h3>
                    <a href="mailto:contato@velledulcis.com">Duvidas Frequentes</a>
                    <a href="mailto:contato@velledulcis.com">Fale Conosco</a>
                    <a href="mailto:contato@velledulcis.com">Entrega e Retirada</a>
                    <a href="mailto:contato@velledulcis.com">Trocas e Devolucoes</a>
                    <a href="mailto:contato@velledulcis.com">Privacidade</a>
                </section>

                <section class="rodape-site__coluna rodape-site__coluna--contato">
                    <h3>Contato</h3>
                    <p>Segunda a sexta, das 7h às 12h</p>
                    <p>(11) 4002-8922</p>
                    <p>contato@velledulcis.com</p>
                    <p>São Paulo, Brasil</p>

                    <div class="rodape-site__social">
                        <a href="https://www.instagram.com/velledulcis/" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram Velle Dulcis">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3.5" y="3.5" width="17" height="17" rx="5"></rect>
                                <circle cx="12" cy="12" r="4"></circle>
                                <circle cx="17.2" cy="6.8" r="1"></circle>
                            </svg>
                        </a>
                        <span class="rodape-site__pix" aria-label="Pix" title="Pix">
                            <img src="../assets/img/logo-pix-icone-1024.png" alt="Pix">
                        </span>
                    </div>
                </section>
            </div>

            <div class="rodape-site__base">
                <p>&copy; Velle Dulcis 2026</p>
                <span class="rodape-site__moeda">BRL</span>
            </div>
        </div>
    </footer>

    <div id="overlay-carrinho" class="overlay-carrinho" aria-hidden="true"></div>

    <aside id="carrinho-lateral">
        <div class="carrinho-topo">
            <h3>Minha sacola</h3>
            <button id="fechar-carrinho" class="carrinho-fechar" type="button" aria-label="Fechar carrinho">&times;</button>
        </div>

        <div class="carrinho-corpo">
            <ul id="lista-carrinho"></ul>
        </div>

        <div class="carrinho-resumo">
            <div class="carrinho-resumo__linha">
                <span>Subtotal</span>
                <strong id="subtotal-preco">R$ 0,00</strong>
            </div>
            <div class="carrinho-resumo__linha carrinho-resumo__linha--total">
                <span>Total</span>
                <strong id="total-preco">R$ 0,00</strong>
            </div>
            <button id="finalizar">Finalizar pedido</button>
        </div>
    </aside>

    <div id="popup-remocao" class="popup-remocao" aria-live="polite" aria-hidden="true">
        <span id="popup-remocao-texto">Produto removido do carrinho.</span>
        <button id="fechar-popup-remocao" class="popup-remocao__fechar" type="button" aria-label="Fechar aviso">&times;</button>
    </div>

    <div
        id="popup-boas-vindas"
        class="popup-boas-vindas<?php echo $mensagemBoasVindas !== "" ? " ativo" : ""; ?>"
        aria-live="polite"
        aria-hidden="<?php echo $mensagemBoasVindas !== "" ? "false" : "true"; ?>"
    >
        <div class="popup-boas-vindas__conteudo">
            <strong>Sessao iniciada</strong>
            <span id="popup-boas-vindas-texto"><?php echo htmlspecialchars($mensagemBoasVindas, ENT_QUOTES, "UTF-8"); ?></span>
        </div>
        <button id="fechar-popup-boas-vindas" class="popup-boas-vindas__fechar" type="button" aria-label="Fechar mensagem">&times;</button>
    </div>

    <div id="popup-aviso-conta" class="popup-aviso-conta" aria-live="polite" aria-hidden="true">
        <div class="popup-aviso-conta__conteudo">
            <strong>Finalize pedidos com mais segurança</strong>
            <span>Entre na sua conta e cadastre um telefone antes de finalizar a compra.</span>
        </div>
        <div class="popup-aviso-conta__acoes">
            <a href="<?php echo htmlspecialchars($destinoConta, ENT_QUOTES, "UTF-8"); ?>">Entrar</a>
            <button id="fechar-popup-aviso-conta" type="button" aria-label="Fechar aviso">&times;</button>
        </div>
    </div>

    <script>
        window.APP_CSRF_TOKEN = <?php echo json_encode(obterTokenCsrf(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.APP_AUTH = {
            autenticado: <?php echo $usuarioAutenticado ? "true" : "false"; ?>,
            nome: <?php echo json_encode($usuarioNome, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            papel: <?php echo json_encode($usuarioPapel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            destinoConta: <?php echo json_encode($destinoConta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            mensagemBoasVindas: <?php echo json_encode($mensagemBoasVindas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        };
    </script>
    <script src="../assets/js/products-data.js?v=20260513-1"></script>
    <script src="../assets/js/script.js?v=20260602-2"></script>

</body>
</html>
