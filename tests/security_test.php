<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/app/seguranca.php";

$checks = 0;

function assertSecurity(bool $condition, string $message): void
{
    global $checks;
    $checks += 1;

    if (!$condition) {
        fwrite(STDERR, "FALHA: {$message}\n");
        exit(1);
    }
}

$token = obterTokenCsrf();

assertSecurity((bool) preg_match('/^[a-f0-9]{64}$/', $token), "o token CSRF deve ter 64 caracteres hexadecimais");
assertSecurity(tokenCsrfValido($token), "o token da sessao deve ser aceito");
assertSecurity(!tokenCsrfValido("token-invalido"), "um token diferente deve ser rejeitado");
assertSecurity(obterTokenCsrf() === $token, "o token deve permanecer estavel durante a sessao");

$_POST["csrf_token"] = $token;
assertSecurity(obterTokenCsrfRequisicao() === $token, "o token enviado por formulario deve ser lido");

$_SERVER["HTTP_X_CSRF_TOKEN"] = "token-do-cabecalho";
assertSecurity(
    obterTokenCsrfRequisicao(["csrf_token" => "token-json"]) === "token-do-cabecalho",
    "o cabecalho deve ter prioridade sobre formulario e JSON"
);

unset($_SERVER["HTTP_X_CSRF_TOKEN"], $_POST["csrf_token"]);
assertSecurity(
    obterTokenCsrfRequisicao(["csrf_token" => $token]) === $token,
    "o token enviado em JSON deve ser lido"
);

session_destroy();
fwrite(STDOUT, "OK: {$checks} verificacoes de seguranca.\n");
