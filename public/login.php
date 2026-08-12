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
    <link rel="stylesheet" href="../assets/css/theme.css?v=20260811-5">
    <link rel="stylesheet" href="../assets/css/registration.css?v=20260811-2">
    <script src="../assets/js/theme.js?v=20260731-3" defer></script>
</head>
<body class="login-body registration-page" data-theme-toggle-floating>
    <main class="registration-shell">
        <section class="registration-card registration-card--login" aria-labelledby="login-title">
            <a class="registration-close" href="index.php" aria-label="Fechar e voltar para a loja">
                <span aria-hidden="true">&times;</span>
            </a>

            <aside class="registration-story" aria-label="Universo Velle Dulcis">
                <img
                    class="registration-story__image"
                    src="../assets/images/about/chocolate-drip-closeup.webp"
                    alt="Chocolate Velle Dulcis em detalhe"
                    width="1024"
                    height="1024"
                >
                <div class="registration-story__veil" aria-hidden="true"></div>
                <div class="registration-story__content">
                    <a class="registration-brand" href="index.php" aria-label="Velle Dulcis — voltar para a loja">
                        <img src="../assets/images/logos/velle-dulcis.png" alt="Velle Dulcis" width="459" height="543">
                    </a>
                    <div class="registration-story__copy">
                        <p class="registration-eyebrow">Sua conta</p>
                        <h1 id="login-title">Bem-vindo de volta.</h1>
                        <p>Entre para acompanhar pedidos e acessar sua conta.</p>
                    </div>
                    <p class="registration-story__signature">Chocolate feito para permanecer na memória.</p>
                </div>
            </aside>

            <div class="registration-form-panel">
                <header class="registration-header">
                    <p class="registration-eyebrow">Velle Dulcis</p>
                    <h2>Entrar</h2>
                    <p>Use seu e-mail e sua senha para continuar.</p>
                </header>

                <?php if ($sucesso !== ""): ?>
                    <div class="auth-alert auth-alert--success" role="status" aria-live="polite">
                        <span class="auth-alert__icon" aria-hidden="true">✓</span>
                        <p><?php echo htmlspecialchars($sucesso, ENT_QUOTES, "UTF-8"); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($erro !== "" || !$bancoDisponivel): ?>
                    <div class="auth-alert" role="alert" aria-live="polite">
                        <span class="auth-alert__icon" aria-hidden="true">!</span>
                        <p><?php echo htmlspecialchars($erro !== "" ? $erro : $mensagemBancoIndisponivel, ENT_QUOTES, "UTF-8"); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" class="registration-form login-account-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo tokenCsrfHtml(); ?>">

                    <label class="registration-field">
                        <span>E-mail</span>
                        <input
                            type="email"
                            name="email"
                            autocomplete="email"
                            inputmode="email"
                            placeholder="voce@exemplo.com"
                            value="<?php echo htmlspecialchars($emailPreenchido, ENT_QUOTES, "UTF-8"); ?>"
                            required
                        >
                    </label>

                    <label class="registration-field">
                        <span>Senha</span>
                        <input
                            type="password"
                            name="senha"
                            autocomplete="current-password"
                            placeholder="Digite sua senha"
                            required
                        >
                    </label>

                    <div class="login-account-form__meta">
                        <a class="login-account-form__assist" href="esqueci-senha.php">Esqueceu sua senha?</a>
                    </div>

                    <button class="registration-submit" type="submit">
                        <span class="registration-submit__label">Entrar</span>
                    </button>

                    <p class="registration-form__legal">Clientes seguem para a própria área e administradores entram no painel ao usar as credenciais corretas.</p>
                </form>

                <p class="registration-login-link">Ainda não tem uma conta? <a href="cadastro.php">Criar conta</a></p>
            </div>
        </section>
    </main>
</body>
</html>
