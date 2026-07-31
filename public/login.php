<?php
declare(strict_types=1);

require_once __DIR__ . "/../app/seguranca.php";

require_once __DIR__ . "/../app/conexao.php";

iniciarSessaoSegura();

function redirecionarUsuarioPorPapel(string $papel, ?string $nomeUsuario = null): void
{
    if ($papel === "admin") {
        header("Location: admin.php");
        exit;
    }

    if ($nomeUsuario !== null && $nomeUsuario !== "") {
        $_SESSION["flash_boas_vindas"] = "Bem-vindo de volta, {$nomeUsuario}.";
    }

    header("Location: index.php");
    exit;
}

if (isset($_SESSION["usuario_id"])) {
    redirecionarUsuarioPorPapel((string) ($_SESSION["usuario_papel"] ?? "cliente"));
}

$erro = "";
$sucesso = isset($_SESSION["flash_cadastro_sucesso"]) && is_string($_SESSION["flash_cadastro_sucesso"])
    ? $_SESSION["flash_cadastro_sucesso"]
    : "";
unset($_SESSION["flash_cadastro_sucesso"]);
$emailPreenchido = "";
$conexaoDisponivel = bancoDeDadosDisponivel($pdo);
$schemaUsuariosDisponivel = $conexaoDisponivel && $pdo instanceof PDO && schemaUsuariosDisponivel($pdo);
$bancoDisponivel = $conexaoDisponivel && $schemaUsuariosDisponivel;
$mensagemBancoIndisponivel = !$conexaoDisponivel
    ? "Não foi possível conectar ao banco de dados. Tente novamente em instantes."
    : "O login está temporariamente indisponível porque a estrutura do banco precisa ser atualizada.";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $emailPreenchido = mb_strtolower(trim((string) ($_POST["email"] ?? "")), "UTF-8");
    $senha = (string) ($_POST["senha"] ?? "");

    if (!tokenCsrfValido(obterTokenCsrfRequisicao())) {
        $erro = "Sessao expirada. Recarregue a pagina e tente novamente.";
    } elseif ($emailPreenchido === "" || $senha === "") {
        $erro = "Preencha email e senha para continuar.";
    } elseif (!$bancoDisponivel) {
        $erro = $mensagemBancoIndisponivel;
    } else {
        try {
            $sql = "SELECT id, nome, email, senha_hash, papel FROM usuarios WHERE email = :email LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(["email" => $emailPreenchido]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, (string) $usuario["senha_hash"])) {
                regenerarSessaoAutenticada();
                $_SESSION["usuario_id"] = (int) $usuario["id"];
                $_SESSION["usuario_nome"] = (string) $usuario["nome"];
                $_SESSION["usuario_email"] = (string) $usuario["email"];
                $_SESSION["usuario_papel"] = (string) ($usuario["papel"] ?? "cliente");

                redirecionarUsuarioPorPapel($_SESSION["usuario_papel"], $_SESSION["usuario_nome"]);
            }

            $erro = "Email ou senha invalidos.";
        } catch (PDOException $exception) {
            error_log("[Velle Dulcis][login] Falha ao consultar usuario: " . $exception->getMessage());
            $erro = "Nao foi possivel validar o login agora. Tente novamente em instantes.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="../assets/js/theme-init.js?v=20260731-3"></script>
    <title>Entrar | Velle Dulcis</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css?v=20260527-1">
    <link rel="stylesheet" href="../assets/css/theme.css?v=20260731-3">
    <link rel="stylesheet" href="../assets/css/registration.css?v=20260731-1">
    <script src="../assets/js/theme.js?v=20260731-3" defer></script>
</head>
<body class="login-body login-body--customer" data-theme-toggle-floating>
    <main class="login-modal-shell">
        <section class="login-modal" aria-labelledby="login-title">
            <a class="login-modal__close" href="index.php" aria-label="Fechar e voltar para a vitrine">
                <span aria-hidden="true">&times;</span>
            </a>

            <div class="login-modal__intro">
                <a class="login-modal__brand" href="index.php" aria-label="Voltar para a vitrine">
                    <img src="../assets/images/logos/velle-dulcis.png" alt="Velle Dulcis" width="459" height="543">
                </a>
                <h1 id="login-title">Login</h1>
                <p>
                    Entre para acompanhar pedidos e acessar sua conta. Se o perfil for administrativo, o painel abre automaticamente apos a autenticacao.
                </p>
            </div>

            <form method="post" class="login-form login-form--customer" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo tokenCsrfHtml(); ?>">

                <?php if ($sucesso !== ""): ?>
                    <div class="auth-alert auth-alert--success" role="status" aria-live="polite">
                        <span class="auth-alert__icon" aria-hidden="true">✓</span>
                        <p><?php echo htmlspecialchars($sucesso, ENT_QUOTES, "UTF-8"); ?></p>
                    </div>
                <?php endif; ?>

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

                <label class="login-form__field login-form__field--customer">
                    <span>Senha*</span>
                    <input
                        type="password"
                        name="senha"
                        autocomplete="current-password"
                        placeholder="Senha"
                        required
                    >
                </label>

                <div class="login-form__meta">
                    <a class="login-form__assist" href="esqueci-senha.php">Esqueceu sua senha?</a>
                </div>

                <button type="submit">Entrar</button>

                <?php if ($erro !== "" || !$bancoDisponivel): ?>
                    <p id="login-mensagem" class="login-form__message" aria-live="polite">
                        <?php echo htmlspecialchars($erro !== "" ? $erro : $mensagemBancoIndisponivel, ENT_QUOTES, "UTF-8"); ?>
                    </p>
                <?php endif; ?>

                <a class="login-form__create" href="cadastro.php">
                    Criar conta
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </form>

            <p class="login-modal__note">
                Clientes seguem para a propria area e administradores entram no painel ao usar as credenciais corretas.
            </p>
        </section>
    </main>
</body>
</html>
