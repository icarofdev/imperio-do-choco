<?php
declare(strict_types=1);

require_once __DIR__ . "/conexao.php";

function gerarTokenRecuperacaoSenha(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "=");
}

function hashTokenRecuperacaoSenha(string $token): string
{
    return hash("sha256", $token);
}

function obterUrlBaseAplicacao(): string
{
    $urlConfigurada = rtrim((string) lerVariavelAmbiente("APP_URL", ""), "/");

    if ($urlConfigurada !== "") {
        return $urlConfigurada;
    }

    $httpsAtivo = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "" && $_SERVER["HTTPS"] !== "off";
    $esquema = $httpsAtivo ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    $diretorio = str_replace("\\", "/", dirname((string) ($_SERVER["SCRIPT_NAME"] ?? "")));
    $diretorio = $diretorio === "/" || $diretorio === "." ? "" : rtrim($diretorio, "/");

    return "{$esquema}://{$host}{$diretorio}";
}

function criarUrlRedefinicaoSenha(string $token): string
{
    return obterUrlBaseAplicacao() . "/redefinir-senha.php?token=" . rawurlencode($token);
}

function buscarRecuperacaoSenhaAtiva(PDO $pdo, string $token, bool $bloquear = false): ?array
{
    $token = trim($token);

    if ($token === "" || strlen($token) > 160) {
        return null;
    }

    $sql = "SELECT
                recuperacoes_senha.id,
                recuperacoes_senha.usuario_id,
                recuperacoes_senha.expira_em,
                usuarios.nome,
                usuarios.email
            FROM recuperacoes_senha
            INNER JOIN usuarios ON usuarios.id = recuperacoes_senha.usuario_id
            WHERE recuperacoes_senha.token_hash = :token_hash
              AND recuperacoes_senha.usado_em IS NULL
              AND recuperacoes_senha.expira_em > NOW()
            LIMIT 1";

    if ($bloquear) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(["token_hash" => hashTokenRecuperacaoSenha($token)]);
    $recuperacao = $stmt->fetch();

    return $recuperacao ?: null;
}

function limparRecuperacoesSenhaAntigas(PDO $pdo): void
{
    $pdo->exec(
        "DELETE FROM recuperacoes_senha
         WHERE expira_em < DATE_SUB(NOW(), INTERVAL 1 DAY)
            OR (usado_em IS NOT NULL AND criado_em < DATE_SUB(NOW(), INTERVAL 1 DAY))"
    );
}
