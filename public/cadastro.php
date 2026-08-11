<?php
declare(strict_types=1);

require_once __DIR__ . "/../app/seguranca.php";
require_once __DIR__ . "/../app/conexao.php";

iniciarSessaoSegura();

if (isset($_SESSION["usuario_id"])) {
    $destino = (string) ($_SESSION["usuario_papel"] ?? "cliente") === "admin" ? "admin.php" : "conta.php";
    header("Location: " . $destino);
    exit;
}

function cadastroEscape(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, "UTF-8");
}

function cadastroEhEntradaDuplicada(PDOException $exception): bool
{
    $codigoDriver = (int) ($exception->errorInfo[1] ?? 0);

    return $codigoDriver === 1062 || (string) $exception->getCode() === "23000";
}

$nomePreenchido = "";
$emailPreenchido = "";
$alerta = "";
$alertaTipo = "error";
$errosCampos = [];
$conexaoDisponivel = bancoDeDadosDisponivel($pdo);
$schemaUsuariosDisponivel = $conexaoDisponivel && $pdo instanceof PDO && schemaUsuariosDisponivel($pdo);

if (!$conexaoDisponivel) {
    $alerta = "Não foi possível conectar ao banco de dados. Tente novamente em instantes.";
} elseif (!$schemaUsuariosDisponivel) {
    $alerta = "O cadastro está temporariamente indisponível porque a estrutura do banco precisa ser atualizada.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nomeBruto = trim((string) ($_POST["nome"] ?? ""));
    $nomeNormalizado = preg_replace('/\s+/u', ' ', $nomeBruto);
    $nomePreenchido = is_string($nomeNormalizado) ? $nomeNormalizado : $nomeBruto;
    $emailPreenchido = mb_strtolower(trim((string) ($_POST["email"] ?? "")), "UTF-8");
    $senha = (string) ($_POST["senha"] ?? "");
    $confirmacaoSenha = (string) ($_POST["confirmar_senha"] ?? "");
    $alerta = "";

    if (!tokenCsrfValido(obterTokenCsrfRequisicao())) {
        $alerta = "Sua sessão expirou. Recarregue a página e tente novamente.";
    } else {
        if ($nomePreenchido === "") {
            $errosCampos["nome"] = "Informe seu nome.";
        } elseif (mb_strlen($nomePreenchido) < 2 || mb_strlen($nomePreenchido) > 120) {
            $errosCampos["nome"] = "Use entre 2 e 120 caracteres.";
        }

        if ($emailPreenchido === "") {
            $errosCampos["email"] = "Informe seu e-mail.";
        } elseif (mb_strlen($emailPreenchido) > 150 || !filter_var($emailPreenchido, FILTER_VALIDATE_EMAIL)) {
            $errosCampos["email"] = "Digite um e-mail válido.";
        }

        if ($senha === "") {
            $errosCampos["senha"] = "Crie uma senha.";
        } elseif (mb_strlen($senha) < 8) {
            $errosCampos["senha"] = "Use pelo menos 8 caracteres.";
        }

        if ($confirmacaoSenha === "") {
            $errosCampos["confirmar_senha"] = "Confirme sua senha.";
        } elseif ($senha !== $confirmacaoSenha) {
            $errosCampos["confirmar_senha"] = "As senhas não coincidem.";
        }

        if ($errosCampos !== []) {
            $alerta = "Revise os campos destacados para continuar.";
        } elseif (!$conexaoDisponivel) {
            $alerta = "Não foi possível conectar ao banco de dados. Tente novamente em instantes.";
        } elseif (!$schemaUsuariosDisponivel) {
            $alerta = "O cadastro está temporariamente indisponível porque a estrutura do banco precisa ser atualizada.";
        } else {
            try {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                if (!is_string($senhaHash)) {
                    throw new RuntimeException("Falha ao gerar o hash da senha.");
                }

                $insert = $pdo->prepare(
                    "INSERT INTO usuarios (nome, email, senha_hash, papel) VALUES (:nome, :email, :senha_hash, :papel)"
                );
                $insert->execute([
                    "nome" => $nomePreenchido,
                    "email" => $emailPreenchido,
                    "senha_hash" => $senhaHash,
                    "papel" => "cliente",
                ]);

                $_SESSION["flash_cadastro_sucesso"] = "Conta criada com sucesso. Entre para continuar sua experiência Velle Dulcis.";
                http_response_code(303);
                header("Location: login.php");
                exit;
            } catch (PDOException $exception) {
                if (cadastroEhEntradaDuplicada($exception)) {
                    $errosCampos["email"] = "Já existe uma conta com este e-mail.";
                    $alerta = "Este e-mail já está cadastrado. Entre na sua conta ou use outro endereço.";
                } else {
                    error_log("[Velle Dulcis][cadastro] Falha ao criar usuario: " . $exception->getMessage());
                    $alerta = "Não foi possível criar sua conta agora. Tente novamente em instantes.";
                }
            } catch (Throwable $exception) {
                error_log("[Velle Dulcis][cadastro] Falha inesperada: " . $exception->getMessage());
                $alerta = "Não foi possível criar sua conta agora. Tente novamente em instantes.";
            }
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
    <title>Criar Conta | Velle Dulcis</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css?v=20260527-1">
    <link rel="stylesheet" href="../assets/css/theme.css?v=20260811-4">
    <link rel="stylesheet" href="../assets/css/registration.css?v=20260731-1">
    <script src="../assets/js/theme.js?v=20260731-3" defer></script>
    <script src="../assets/js/registration.js?v=20260731-1" defer></script>
</head>
<body class="login-body registration-page" data-theme-toggle-floating>
    <main class="registration-shell">
        <section class="registration-card" aria-labelledby="cadastro-title">
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
                        <p class="registration-eyebrow">Maison de chocolate</p>
                        <h1 id="cadastro-title">Seu próximo capítulo começa aqui.</h1>
                        <p>Uma conta aproxima você de lançamentos, presentes e experiências criadas com cuidado em cada detalhe.</p>
                    </div>
                    <p class="registration-story__signature">Chocolate feito para permanecer na memória.</p>
                </div>
            </aside>

            <div class="registration-form-panel">
                <header class="registration-header">
                    <p class="registration-eyebrow">Bem-vindo à Velle Dulcis</p>
                    <h2>Crie sua conta</h2>
                    <p>Preencha seus dados para acompanhar pedidos e tornar suas próximas escolhas mais simples.</p>
                </header>

                <?php if ($alerta !== ""): ?>
                    <div class="auth-alert auth-alert--<?php echo cadastroEscape($alertaTipo); ?>" role="alert" aria-live="polite">
                        <span class="auth-alert__icon" aria-hidden="true">!</span>
                        <p><?php echo cadastroEscape($alerta); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" class="registration-form" data-registration-form>
                    <input type="hidden" name="csrf_token" value="<?php echo tokenCsrfHtml(); ?>">

                    <label class="registration-field<?php echo isset($errosCampos["nome"]) ? " has-error" : ""; ?>">
                        <span>Nome completo</span>
                        <input
                            type="text"
                            name="nome"
                            autocomplete="name"
                            placeholder="Como podemos chamar você?"
                            value="<?php echo cadastroEscape($nomePreenchido); ?>"
                            minlength="2"
                            maxlength="120"
                            aria-invalid="<?php echo isset($errosCampos["nome"]) ? "true" : "false"; ?>"
                            <?php echo isset($errosCampos["nome"]) ? 'aria-describedby="erro-nome"' : ""; ?>
                            required
                        >
                        <?php if (isset($errosCampos["nome"])): ?><small id="erro-nome"><?php echo cadastroEscape($errosCampos["nome"]); ?></small><?php endif; ?>
                    </label>

                    <label class="registration-field<?php echo isset($errosCampos["email"]) ? " has-error" : ""; ?>">
                        <span>E-mail</span>
                        <input
                            type="email"
                            name="email"
                            autocomplete="email"
                            inputmode="email"
                            placeholder="voce@exemplo.com"
                            value="<?php echo cadastroEscape($emailPreenchido); ?>"
                            maxlength="150"
                            aria-invalid="<?php echo isset($errosCampos["email"]) ? "true" : "false"; ?>"
                            <?php echo isset($errosCampos["email"]) ? 'aria-describedby="erro-email"' : ""; ?>
                            required
                        >
                        <?php if (isset($errosCampos["email"])): ?><small id="erro-email"><?php echo cadastroEscape($errosCampos["email"]); ?></small><?php endif; ?>
                    </label>

                    <div class="registration-form__passwords">
                        <label class="registration-field<?php echo isset($errosCampos["senha"]) ? " has-error" : ""; ?>">
                            <span>Senha</span>
                            <input
                                type="password"
                                name="senha"
                                autocomplete="new-password"
                                placeholder="Mínimo de 8 caracteres"
                                minlength="8"
                                aria-invalid="<?php echo isset($errosCampos["senha"]) ? "true" : "false"; ?>"
                                <?php echo isset($errosCampos["senha"]) ? 'aria-describedby="erro-senha"' : ""; ?>
                                required
                            >
                            <?php if (isset($errosCampos["senha"])): ?><small id="erro-senha"><?php echo cadastroEscape($errosCampos["senha"]); ?></small><?php endif; ?>
                        </label>

                        <label class="registration-field<?php echo isset($errosCampos["confirmar_senha"]) ? " has-error" : ""; ?>">
                            <span>Confirmar senha</span>
                            <input
                                type="password"
                                name="confirmar_senha"
                                autocomplete="new-password"
                                placeholder="Repita sua senha"
                                minlength="8"
                                aria-invalid="<?php echo isset($errosCampos["confirmar_senha"]) ? "true" : "false"; ?>"
                                <?php echo isset($errosCampos["confirmar_senha"]) ? 'aria-describedby="erro-confirmar-senha"' : ""; ?>
                                required
                            >
                            <?php if (isset($errosCampos["confirmar_senha"])): ?><small id="erro-confirmar-senha"><?php echo cadastroEscape($errosCampos["confirmar_senha"]); ?></small><?php endif; ?>
                        </label>
                    </div>

                    <button class="registration-submit" type="submit"<?php echo !$conexaoDisponivel || !$schemaUsuariosDisponivel ? " disabled" : ""; ?>>
                        <span class="registration-submit__label">Criar minha conta</span>
                        <span class="registration-submit__loading" aria-hidden="true">
                            <span class="registration-spinner"></span>
                            Criando conta…
                        </span>
                    </button>

                    <p class="registration-form__legal">Ao criar sua conta, você confirma que leu e aceita os termos e a política de privacidade da Velle Dulcis.</p>
                </form>

                <p class="registration-login-link">Já tem uma conta? <a href="login.php">Entrar</a></p>
            </div>
        </section>
    </main>
</body>
</html>
