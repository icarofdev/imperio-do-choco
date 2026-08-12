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
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="../assets/js/theme-init.js?v=20260731-3"></script>
    <title>Velle Dulcis — Chocolates que transformam momentos</title>
    <meta name="description" content="Chocolates artesanais e presentes autorais Velle Dulcis, criados para transformar pequenos gestos em memórias inesquecíveis.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=20260811-3">
    <link rel="stylesheet" href="../assets/css/premium-home.css?v=20260811-3">
    <link rel="stylesheet" href="../assets/css/theme.css?v=20260811-5">
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
    <script src="../assets/js/site-components.js?v=20260811-3"></script>
    <script src="../assets/js/theme.js?v=20260731-3" defer></script>
</head>

<body class="pagina-carregando pagina-principal">
    <header class="topo-site">
    </header>
    <script>
        window.VelleComponents.mountHeader(document.querySelector(".topo-site"), {
            homeHref: "#",
            auth: window.APP_AUTH
        });
    </script>

    <div id="commerce-shell"></div>
    <script>
        window.VelleComponents.mountCommerce(document.getElementById("commerce-shell"), {
            welcomeMessage: window.APP_AUTH.mensagemBoasVindas,
            accountHref: window.APP_AUTH.destinoConta
        });
    </script>

    <main class="home-main">
        <section id="inicio" class="home-hero" aria-labelledby="home-hero-title">
            <div class="home-hero__conteudo">
                <span class="home-hero__rotulo">Velle Dulcis</span>
                <h1 id="home-hero-title">O extraordinário cabe em um instante.</h1>
                <p>Chocolate artesanal, acabamento impecável e sabores que transformam pequenos gestos em memórias inesquecíveis.</p>
                <div class="home-hero__acoes">
                    <a href="#vitrine" class="home-hero__acao home-hero__acao--primaria">Ver chocolates</a>
                    <a href="historia.html" class="home-hero__acao">Nossa história</a>
                </div>
            </div>
            <div class="home-hero__visual" aria-hidden="true">
                <span class="home-hero__halo"></span>
                <img src="../assets/images/products/product-caixa-degustacao-30.webp" alt="" width="535" height="702" fetchpriority="high">
                <span class="home-hero__assinatura">Feito para sentir</span>
            </div>
        </section>

      <!-- NOVO CARROSSEL 3D -->

<section id="colecoes" class="novo-carrossel reveal-section">

    <h2 class="novo-carrossel-titulo">
        Coleções com assinatura
    </h2>
    <p class="novo-carrossel-subtitulo">Criações autorais para celebrar o sabor, o gesto e tudo o que fica na memória.</p>

    <div class="slider-3d">

        <button class="slider-btn prev" type="button" aria-label="Coleção anterior">
            &#10094;
        </button>

        <div class="slider-container">

    <div class="slide active">
        <img src="../assets/images/lifestyle/brigadeiro-artesanal-campaign.webp" alt="Brigadeiro artesanal Velle Dulcis" width="1024" height="1024" loading="lazy">
        <h3>Arte da doçura</h3>
    </div>

    <div class="slide">
        <img src="../assets/images/lifestyle/bolo-pote-caramelo.webp" alt="Bolo de pote de caramelo, chocolate e nozes" width="1309" height="816" loading="lazy">
        <h3>Momentos de colher</h3>
    </div>

    <div class="slide">
        <img src="../assets/images/banners/hero-caixa-presente.webp" alt="Caixa presenteável com seleção de brigadeiros Velle Dulcis" width="960" height="1280" loading="lazy">
        <h3>Presentes autorais</h3>
    </div>

</div>
        <button class="slider-btn next" type="button" aria-label="Próxima coleção">
            &#10095;
        </button>

    </div>

</section>

<!-- FIM NOVO CARROSSEL -->
        <section id="presentes" class="presentes-editorial reveal-section">
            <div class="presentes-editorial__imagem">
                <img src="../assets/images/products/product-caixa-quarteto.webp" alt="Caixa Quarteto Velle com quatro brigadeiros artesanais" width="1000" height="1000" loading="lazy">
            </div>
            <div class="presentes-editorial__conteudo">
                <span class="section-kicker">Presentes Velle</span>
                <h2>Um gesto à altura de quem recebe.</h2>
                <p>Seleções montadas com cuidado, sabores memoráveis e uma apresentação que torna o momento especial antes mesmo da primeira mordida.</p>
                <a class="button button--outline" href="#vitrine">Descobrir presentes</a>
            </div>
        </section>

        <section id="vitrine" class="grid-chocolates">
            <div class="vitrine-intro">
                <p class="vitrine-intro__kicker">Chocolate</p>
                <h1>Nossos chocolates</h1>
                <p>Sabores especiais preparados para presentear e transformar qualquer momento em algo memorável.</p>
            </div>

            <div class="vitrine-toolbar" aria-label="Controles da vitrine">
                <details class="vitrine-toolbar__sort" aria-label="Ordenar produtos">
                    <summary><span data-vitrine-sort-label>Ordenar produtos</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"></path></svg>
                    </summary>
                    <div class="vitrine-toolbar__menu" role="group" aria-label="Opções de ordenação">
                        <button class="vitrine-toolbar__button ativo" type="button" data-vitrine-sort="recommended" aria-pressed="true">Recomendados</button>
                        <button class="vitrine-toolbar__button" type="button" data-vitrine-sort="price-asc" aria-pressed="false">Preço: menor para maior</button>
                    </div>
                </details>
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

        <section class="manifesto reveal-section" aria-label="Essência Velle Dulcis">
            <p class="section-kicker">Nossa essência</p>
            <h2>Do detalhe nasce o inesquecível.</h2>
            <p>Selecionamos cada combinação para equilibrar textura, aroma e delicadeza. Porque luxo, para nós, é transformar cuidado em sabor.</p>
            <a href="historia.html">Conheça nossa história <span aria-hidden="true">→</span></a>
        </section>

    </main>

    <footer id="contato" class="rodape-site">
    </footer>
    <script>
        window.VelleComponents.mountFooter(document.querySelector(".rodape-site"), {
            homeHref: "#"
        });
    </script>

    <script src="../assets/js/products-data.js?v=20260811-2"></script>
    <script src="../assets/js/product-card.js?v=20260811-3"></script>
    <script src="../assets/js/script.js?v=20260811-3"></script>
    <script src="../assets/js/carousel.js?v=20260811-2"></script>
    <script src="../assets/js/premium-home.js?v=20260811-3"></script>

</body>
</html>
