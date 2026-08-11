<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set("display_errors", "0");
ini_set("log_errors", "1");

header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-Frame-Options: SAMEORIGIN");
header("Cache-Control: no-store, max-age=0");

$requestPath = rawurldecode((string) (parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?? "/"));
$requestPath = "/" . ltrim(preg_replace('#/+#', '/', $requestPath) ?? "/", "/");

if (str_contains($requestPath, "\0") || str_contains($requestPath, "..")) {
    responderNaoEncontrado();
}

if (PHP_SAPI === "cli-server" && str_starts_with($requestPath, "/assets/")) {
    return false;
}

$caminhoNormalizado = trim($requestPath, "/");

if ($caminhoNormalizado === "public") {
    $caminhoNormalizado = "public/index.php";
}

$rotasPhp = [
    "" => "index.php",
    "index.php" => "index.php",
    "admin.php" => "admin.php",
    "admin-produtos.php" => "admin-produtos.php",
    "buscar-chocolates.php" => "buscar-chocolates.php",
    "cadastro.php" => "cadastro.php",
    "carrinho.php" => "carrinho.php",
    "conta.php" => "conta.php",
    "esqueci-senha.php" => "esqueci-senha.php",
    "finalizar-pedido.php" => "finalizar-pedido.php",
    "login.php" => "login.php",
    "logout.php" => "logout.php",
    "redefinir-senha.php" => "redefinir-senha.php",
    "sessao-usuario.php" => "sessao-usuario.php",
];

$aliasesLegados = [
    "admin_produtos.php" => "admin-produtos.php",
    "buscar_chocolates.php" => "buscar-chocolates.php",
    "esqueci_senha.php" => "esqueci-senha.php",
    "finalizar_pedido.php" => "finalizar-pedido.php",
    "redefinir_senha.php" => "redefinir-senha.php",
    "sessao_usuario.php" => "sessao-usuario.php",
];

$semPrefixoPublico = str_starts_with($caminhoNormalizado, "public/")
    ? substr($caminhoNormalizado, strlen("public/"))
    : $caminhoNormalizado;

if (isset($aliasesLegados[$semPrefixoPublico])) {
    $prefixo = str_starts_with($caminhoNormalizado, "public/") ? "/public/" : "/";
    header("Location: " . $prefixo . $aliasesLegados[$semPrefixoPublico], true, 308);
    exit;
}

$rotasEstaticas = [
    "historia.html" => "historia.html",
    "produto.html" => "produto.html",
];

$_SERVER["SCRIPT_NAME"] = $requestPath;
$_SERVER["PHP_SELF"] = $requestPath;

ob_start();

try {
    if (array_key_exists($semPrefixoPublico, $rotasPhp)) {
        require dirname(__DIR__) . "/public/" . $rotasPhp[$semPrefixoPublico];
        ob_end_flush();
        exit;
    }

    if (isset($rotasEstaticas[$semPrefixoPublico])) {
        header("Content-Type: text/html; charset=UTF-8");
        readfile(dirname(__DIR__) . "/public/" . $rotasEstaticas[$semPrefixoPublico]);
        ob_end_flush();
        exit;
    }

    ob_end_clean();
    responderNaoEncontrado();
} catch (Throwable $exception) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $appEnv = strtolower((string) ($_ENV["APP_ENV"] ?? $_SERVER["APP_ENV"] ?? getenv("APP_ENV") ?: "production"));
    $detalhe = in_array($appEnv, ["prod", "production"], true)
        ? "detalhes suprimidos em producao"
        : (preg_replace('/[\r\n]+/', ' ', $exception->getMessage()) ?: "sem detalhes");
    error_log(sprintf(
        "[Velle Dulcis][http] Erro nao tratado em %s (%s, codigo=%s, %s)",
        $requestPath,
        $exception::class,
        (string) $exception->getCode(),
        $detalhe
    ));
    http_response_code(500);
    header("Content-Type: text/html; charset=UTF-8");
    echo "<!doctype html><html lang=\"pt-br\"><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Serviço indisponível</title><body><main><h1>Não foi possível abrir esta página</h1><p>Tente novamente em alguns instantes.</p></main></body></html>";
    exit;
}

function responderNaoEncontrado(): never
{
    http_response_code(404);
    header("Content-Type: text/html; charset=UTF-8");
    echo "<!doctype html><html lang=\"pt-br\"><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Página não encontrada</title><body><main><h1>Página não encontrada</h1><p><a href=\"/\">Voltar para a loja</a></p></main></body></html>";
    exit;
}
