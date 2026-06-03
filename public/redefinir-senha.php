<?php
declare(strict_types=1);

require_once __DIR__ . "/../app/seguranca.php";

require_once __DIR__ . "/../app/conexao.php";
require_once __DIR__ . "/../app/recuperacao-senha.php";

iniciarSessaoSegura();

$erro = "";
$sucesso = "";
$token = trim((string) ($_POST["token"] ?? $_GET["token"] ?? ""));
$bancoDisponivel = bancoDeDadosDisponivel($pdo)
    && ($pdo instanceof PDO ? schemaRecuperacaoSenhaDisponivel($pdo) : false);
$mensagemBancoIndisponivel = "O banco de dados esta indisponivel no momento. Tente novamente mais tarde.";
$recuperacao = null;

if ($bancoDisponivel && $token !== "") {
    try {
        $recuperacao = buscarRecuperacaoSenhaAtiva($pdo, $token);
    } catch (Throwable $exception) {
        $erro = "Nao foi possivel validar o link agora.";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $bancoDisponivel) {
    $senha = trim((string) ($_POST["senha"] ?? ""));
    $confirmacaoSenha = trim((string) ($_POST["confirmar_senha"] ?? ""));

    if (!tokenCsrfValido(obterTokenCsrfRequisicao())) {
        $erro = "Sessao expirada. Recarregue a pagina e tente novamente.";
    } elseif ($token === "" || $recuperacao === null) {
        $erro = "Este link de redefinicao expirou ou ja foi usado.";
    } elseif ($senha === "" || $confirmacaoSenha === "") {
        $erro = "Preencha e confirme a nova senha.";
    } elseif (mb_strlen($senha) < 6) {
        $erro = "A senha precisa ter pelo menos 6 caracteres.";
    } elseif ($senha !== $confirmacaoSenha) {
        $erro = "A confirmacao de senha nao confere.";
    } else {
        try {
            $pdo->beginTransaction();
            $recuperacaoBloqueada = buscarRecuperacaoSenhaAtiva($pdo, $token, true);

            if ($recuperacaoBloqueada === null) {
                $pdo->rollBack();
                $erro = "Este link de redefinicao expirou ou ja foi usado.";
            } else {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $updateSenha = $pdo->prepare("UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id");
                $updateSenha->execute([
                    "senha_hash" => $senhaHash,
                    "id" => (int) $recuperacaoBloqueada["usuario_id"],
                ]);

                $updateTokens = $pdo->prepare(
                    "UPDATE recuperacoes_senha
                     SET usado_em = NOW()
                     WHERE usuario_id = :usuario_id AND usado_em IS NULL"
                );
                $updateTokens->execute(["usuario_id" => (int) $recuperacaoBloqueada["usuario_id"]]);

                $pdo->commit();
                $sucesso = "Senha alterada com sucesso. Agora voce ja pode entrar.";
                $recuperacao = null;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro = "Nao foi possivel alterar a senha agora. Tente novamente em instantes.";
        }
    }
}

$linkValido = $bancoDisponivel && $token !== "" && $recuperacao !== null && $sucesso === "";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Senha | Velle Dulcis</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css?v=20260527-1">
</head>
<body class="login-body login-body--customer">
    <script src="../assets/js/theme-init.js"></script>
    <main class="login-modal-shell">
        <section class="login-modal" aria-labelledby="nova-senha-title">
            <a class="login-modal__close" href="login.php" aria-label="Fechar e voltar para o login">
                <span aria-hidden="true">&times;</span>
            </a>

            <div class="login-modal__intro">
                <a class="login-modal__brand" href="index.php" aria-label="Voltar para a vitrine">
                    <img src="../assets/img/logo-velle-dulcis.png" alt="Velle Dulcis">
                </a>
                <h1 id="nova-senha-title">Nova senha</h1>
                <p>
                    Crie uma senha nova para recuperar o acesso a sua conta.
                </p>
            </div>

            <?php if ($linkValido): ?>
                <form method="post" class="login-form login-form--customer" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo tokenCsrfHtml(); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, "UTF-8"); ?>">

                    <label class="login-form__field login-form__field--customer">
                        <span>Nova senha*</span>
                        <input
                            type="password"
                            name="senha"
                            autocomplete="new-password"
                            placeholder="Minimo de 6 caracteres"
                            required
                        >
                    </label>

                    <label class="login-form__field login-form__field--customer">
                        <span>Confirmar senha*</span>
                        <input
                            type="password"
                            name="confirmar_senha"
                            autocomplete="new-password"
                            placeholder="Repita sua senha"
                            required
                        >
                    </label>

                    <button type="submit">Alterar senha</button>

                    <?php if ($erro !== ""): ?>
                        <p class="login-form__message" aria-live="polite">
                            <?php echo htmlspecialchars($erro, ENT_QUOTES, "UTF-8"); ?>
                        </p>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <div class="login-form login-form--customer">
                    <?php if ($sucesso !== ""): ?>
                        <p class="login-form__message sucesso" aria-live="polite">
                            <?php echo htmlspecialchars($sucesso, ENT_QUOTES, "UTF-8"); ?>
                        </p>
                        <a class="login-form__create" href="login.php">
                            Entrar agora
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    <?php else: ?>
                        <p class="login-form__message" aria-live="polite">
                            <?php echo htmlspecialchars($erro !== "" ? $erro : ($bancoDisponivel ? "Este link de redefinicao expirou ou ja foi usado." : $mensagemBancoIndisponivel), ENT_QUOTES, "UTF-8"); ?>
                        </p>
                        <a class="login-form__create" href="esqueci-senha.php">
                            Pedir novo link
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p class="login-modal__note">
                Depois da troca, qualquer link antigo de recuperacao deixa de funcionar.
            </p>
        </section>
    </main>
</body>
</html>
