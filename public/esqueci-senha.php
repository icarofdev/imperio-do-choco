<?php
declare(strict_types=1);

require_once __DIR__ . "/../app/seguranca.php";

require_once __DIR__ . "/../app/conexao.php";
require_once __DIR__ . "/../app/email.php";
require_once __DIR__ . "/../app/recuperacao-senha.php";

iniciarSessaoSegura();

$erro = "";
$sucesso = "";
$emailPreenchido = "";
$bancoDisponivel = bancoDeDadosDisponivel($pdo)
    && ($pdo instanceof PDO ? schemaRecuperacaoSenhaDisponivel($pdo) : false);
$mensagemBancoIndisponivel = "O banco de dados esta indisponivel no momento. Tente novamente mais tarde.";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $emailPreenchido = trim((string) ($_POST["email"] ?? ""));

    if (!tokenCsrfValido(obterTokenCsrfRequisicao())) {
        $erro = "Sessao expirada. Recarregue a pagina e tente novamente.";
    } elseif ($emailPreenchido === "") {
        $erro = "Digite seu email para continuar.";
    } elseif (!filter_var($emailPreenchido, FILTER_VALIDATE_EMAIL)) {
        $erro = "Digite um email valido.";
    } elseif (!$bancoDisponivel) {
        $erro = $mensagemBancoIndisponivel;
    } else {
        try {
            limparRecuperacoesSenhaAntigas($pdo);

            $stmt = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE email = :email LIMIT 1");
            $stmt->execute(["email" => $emailPreenchido]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                $token = gerarTokenRecuperacaoSenha();
                $link = criarUrlRedefinicaoSenha($token);

                $pdo->prepare(
                    "UPDATE recuperacoes_senha
                     SET usado_em = NOW()
                     WHERE usuario_id = :usuario_id AND usado_em IS NULL"
                )->execute(["usuario_id" => (int) $usuario["id"]]);

                $insert = $pdo->prepare(
                    "INSERT INTO recuperacoes_senha (usuario_id, token_hash, expira_em, ip_solicitante)
                     VALUES (:usuario_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR), :ip_solicitante)"
                );
                $insert->execute([
                    "usuario_id" => (int) $usuario["id"],
                    "token_hash" => hashTokenRecuperacaoSenha($token),
                    "ip_solicitante" => substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45),
                ]);

                $resultadoEmail = enviarEmailRecuperacaoSenha(
                    (string) $usuario["email"],
                    (string) $usuario["nome"],
                    $link
                );

                if (!$resultadoEmail["sucesso"]) {
                    error_log("Falha ao enviar recuperacao de senha: " . $resultadoEmail["mensagem"]);
                }
            }

            $sucesso = "Se existir uma conta com este email, enviaremos um link para redefinir a senha em instantes.";
        } catch (Throwable $exception) {
            $erro = "Nao foi possivel iniciar a recuperacao agora. Tente novamente em instantes.";
        }
    }
}

function enviarEmailRecuperacaoSenha(string $email, string $nome, string $link): array
{
    $nomeSeguro = trim($nome) !== "" ? trim($nome) : "cliente";
    $linkHtml = htmlspecialchars($link, ENT_QUOTES, "UTF-8");
    $assunto = "Redefinicao de senha Velle Dulcis";
    $texto = "Ola, {$nomeSeguro}.\n\n"
        . "Recebemos uma solicitacao para redefinir sua senha na Velle Dulcis.\n"
        . "Acesse o link abaixo em ate 1 hora:\n{$link}\n\n"
        . "Se voce nao pediu isso, ignore esta mensagem.";
    $html = "<p>Ola, " . htmlspecialchars($nomeSeguro, ENT_QUOTES, "UTF-8") . ".</p>"
        . "<p>Recebemos uma solicitacao para redefinir sua senha na Velle Dulcis.</p>"
        . "<p><a href=\"{$linkHtml}\">Redefinir senha</a></p>"
        . "<p>Este link expira em 1 hora. Se voce nao pediu isso, ignore esta mensagem.</p>";

    return enviarEmailTransacional($email, $nome, $assunto, $html, $texto);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="../assets/js/theme-init.js?v=20260731-3"></script>
    <title>Recuperar Senha | Velle Dulcis</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css?v=20260527-1">
    <link rel="stylesheet" href="../assets/css/theme.css?v=20260811-4">
    <script src="../assets/js/theme.js?v=20260731-3" defer></script>
</head>
<body class="login-body login-body--customer" data-theme-toggle-floating>
    <main class="login-modal-shell">
        <section class="login-modal" aria-labelledby="recuperar-title">
            <a class="login-modal__close" href="login.php" aria-label="Fechar e voltar para o login">
                <span aria-hidden="true">&times;</span>
            </a>

            <div class="login-modal__intro">
                <a class="login-modal__brand" href="index.php" aria-label="Voltar para a vitrine">
                    <img src="../assets/images/logos/velle-dulcis.png" alt="Velle Dulcis" width="459" height="543">
                </a>
                <h1 id="recuperar-title">Recuperar senha</h1>
                <p>
                    Informe o email da sua conta e enviaremos um link temporario para criar uma nova senha.
                </p>
            </div>

            <form method="post" class="login-form login-form--customer" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo tokenCsrfHtml(); ?>">

                <label class="login-form__field login-form__field--customer">
                    <span>Email*</span>
                    <input
                        type="email"
                        name="email"
                        autocomplete="email"
                        placeholder="Email"
                        value="<?php echo htmlspecialchars($emailPreenchido, ENT_QUOTES, "UTF-8"); ?>"
                        required
                    >
                </label>

                <button type="submit">Enviar link</button>

                <?php if ($erro !== "" || (!$bancoDisponivel && $sucesso === "")): ?>
                    <p class="login-form__message" aria-live="polite">
                        <?php echo htmlspecialchars($erro !== "" ? $erro : $mensagemBancoIndisponivel, ENT_QUOTES, "UTF-8"); ?>
                    </p>
                <?php elseif ($sucesso !== ""): ?>
                    <p class="login-form__message sucesso" aria-live="polite">
                        <?php echo htmlspecialchars($sucesso, ENT_QUOTES, "UTF-8"); ?>
                    </p>
                <?php endif; ?>

                <a class="login-form__create" href="login.php">
                    Voltar ao login
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </form>

            <p class="login-modal__note">
                O link vale por 1 hora e deixa de funcionar depois que a senha for alterada.
            </p>
        </section>
    </main>
</body>
</html>
