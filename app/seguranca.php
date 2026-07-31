<?php
declare(strict_types=1);

function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $httpsAtivo = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "" && $_SERVER["HTTPS"] !== "off";
    $diretorioSessoes = dirname(__DIR__) . DIRECTORY_SEPARATOR . ".runtime" . DIRECTORY_SEPARATOR . "sessions";

    if (!is_dir($diretorioSessoes) && !mkdir($diretorioSessoes, 0770, true) && !is_dir($diretorioSessoes)) {
        throw new RuntimeException("Nao foi possivel preparar o diretorio privado de sessoes.");
    }

    session_save_path($diretorioSessoes);

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => $httpsAtivo,
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    if (!session_start()) {
        throw new RuntimeException("Nao foi possivel iniciar uma sessao segura.");
    }
}

function regenerarSessaoAutenticada(): void
{
    iniciarSessaoSegura();
    session_regenerate_id(true);
}

function obterTokenCsrf(): string
{
    iniciarSessaoSegura();

    if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function tokenCsrfHtml(): string
{
    return htmlspecialchars(obterTokenCsrf(), ENT_QUOTES, "UTF-8");
}

function obterTokenCsrfRequisicao(array $dados = []): string
{
    $cabecalho = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";

    if (is_string($cabecalho) && trim($cabecalho) !== "") {
        return trim($cabecalho);
    }

    $postToken = $_POST["csrf_token"] ?? null;

    if (is_string($postToken) && trim($postToken) !== "") {
        return trim($postToken);
    }

    $jsonToken = $dados["csrf_token"] ?? null;

    return is_string($jsonToken) ? trim($jsonToken) : "";
}

function tokenCsrfValido(string $token): bool
{
    iniciarSessaoSegura();

    return isset($_SESSION["csrf_token"])
        && is_string($_SESSION["csrf_token"])
        && $token !== ""
        && hash_equals($_SESSION["csrf_token"], $token);
}
