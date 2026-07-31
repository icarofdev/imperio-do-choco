<?php
declare(strict_types=1);

require_once __DIR__ . "/../app/seguranca.php";

require_once __DIR__ . "/../app/conexao.php";

iniciarSessaoSegura();

$usuarioId = (int) ($_SESSION["usuario_id"] ?? 0);
$nomeUsuario = (string) ($_SESSION["usuario_nome"] ?? "");
$emailUsuario = (string) ($_SESSION["usuario_email"] ?? "");
$papelUsuario = (string) ($_SESSION["usuario_papel"] ?? "cliente");

if ($usuarioId <= 0) {
    header("Location: login.php");
    exit;
}

if ($papelUsuario === "admin") {
    header("Location: admin.php");
    exit;
}

function buscarEnderecosConta(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        "SELECT id, rotulo, destinatario_nome, telefone, cep, logradouro, numero, complemento, bairro, cidade, estado, referencia, principal
         FROM enderecos
         WHERE usuario_id = :usuario_id
         ORDER BY principal DESC, id DESC"
    );
    $stmt->execute(["usuario_id" => $usuarioId]);

    return $stmt->fetchAll();
}

function buscarPedidosConta(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        "SELECT p.id, p.numero_pedido, p.status, p.status_pagamento, p.total, p.realizado_em,
                COALESCE(COUNT(pi.id), 0) AS total_itens
         FROM pedidos p
         LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
         WHERE p.usuario_id = :usuario_id
         GROUP BY p.id, p.numero_pedido, p.status, p.status_pagamento, p.total, p.realizado_em
         ORDER BY p.id DESC"
    );
    $stmt->execute(["usuario_id" => $usuarioId]);

    return $stmt->fetchAll();
}

function buscarItensPedidoConta(PDO $pdo, int $pedidoId, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        "SELECT pi.produto_nome, pi.quantidade, pi.preco_unitario, pi.subtotal_item
         FROM pedido_itens pi
         INNER JOIN pedidos p ON p.id = pi.pedido_id
         WHERE pi.pedido_id = :pedido_id AND p.usuario_id = :usuario_id
         ORDER BY pi.id ASC"
    );
    $stmt->execute([
        "pedido_id" => $pedidoId,
        "usuario_id" => $usuarioId,
    ]);

    return $stmt->fetchAll();
}

$erro = "";
$sucesso = "";
$pedidoRecemCriado = isset($_GET["pedido"]) ? trim((string) $_GET["pedido"]) : "";
$bancoDisponivel = bancoDeDadosDisponivel($pdo) && ($pdo instanceof PDO ? schemaComercialDisponivel($pdo) : false);

if ($_SERVER["REQUEST_METHOD"] === "POST" && !tokenCsrfValido(obterTokenCsrfRequisicao())) {
    $erro = "Sessao expirada. Recarregue a pagina e tente novamente.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $bancoDisponivel) {
    $rotulo = trim((string) ($_POST["rotulo"] ?? ""));
    $destinatarioNome = trim((string) ($_POST["destinatario_nome"] ?? ""));
    $telefone = trim((string) ($_POST["telefone"] ?? ""));
    $cep = trim((string) ($_POST["cep"] ?? ""));
    $logradouro = trim((string) ($_POST["logradouro"] ?? ""));
    $numero = trim((string) ($_POST["numero"] ?? ""));
    $complemento = trim((string) ($_POST["complemento"] ?? ""));
    $bairro = trim((string) ($_POST["bairro"] ?? ""));
    $cidade = trim((string) ($_POST["cidade"] ?? ""));
    $estado = strtoupper(trim((string) ($_POST["estado"] ?? "")));
    $referencia = trim((string) ($_POST["referencia"] ?? ""));
    $principal = isset($_POST["principal"]) ? 1 : 0;

    if ($destinatarioNome === "" || $telefone === "" || $cep === "" || $logradouro === "" || $numero === "" || $bairro === "" || $cidade === "" || strlen($estado) !== 2) {
        $erro = "Preencha nome, telefone, CEP, logradouro, numero, bairro, cidade e UF.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($principal === 1) {
                $stmtReset = $pdo->prepare("UPDATE enderecos SET principal = 0 WHERE usuario_id = :usuario_id");
                $stmtReset->execute(["usuario_id" => $usuarioId]);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO enderecos (
                    usuario_id, rotulo, destinatario_nome, telefone, cep, logradouro, numero, complemento, bairro, cidade, estado, referencia, principal
                ) VALUES (
                    :usuario_id, :rotulo, :destinatario_nome, :telefone, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :referencia, :principal
                )"
            );
            $stmt->execute([
                "usuario_id" => $usuarioId,
                "rotulo" => $rotulo !== "" ? $rotulo : null,
                "destinatario_nome" => $destinatarioNome,
                "telefone" => $telefone !== "" ? $telefone : null,
                "cep" => $cep,
                "logradouro" => $logradouro,
                "numero" => $numero,
                "complemento" => $complemento !== "" ? $complemento : null,
                "bairro" => $bairro,
                "cidade" => $cidade,
                "estado" => $estado,
                "referencia" => $referencia !== "" ? $referencia : null,
                "principal" => $principal,
            ]);

            if ($principal === 0) {
                $stmtPrincipal = $pdo->prepare("SELECT 1 FROM enderecos WHERE usuario_id = :usuario_id AND principal = 1 LIMIT 1");
                $stmtPrincipal->execute(["usuario_id" => $usuarioId]);

                if (!$stmtPrincipal->fetchColumn()) {
                    $pdo->prepare("UPDATE enderecos SET principal = 1 WHERE id = :id")->execute([
                        "id" => (int) $pdo->lastInsertId(),
                    ]);
                }
            }

            $pdo->commit();
            $sucesso = "Endereco salvo com sucesso.";
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = "Nao foi possivel salvar o endereco agora.";
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && !$bancoDisponivel) {
    $erro = "Banco comercial indisponivel. Execute as migracoes antes de usar a conta.";
}

$enderecos = $bancoDisponivel ? buscarEnderecosConta($pdo, $usuarioId) : [];
$pedidos = $bancoDisponivel ? buscarPedidosConta($pdo, $usuarioId) : [];
$pedidoSelecionado = isset($_GET["pedido_id"]) ? (int) $_GET["pedido_id"] : 0;
$itensPedidoSelecionado = $pedidoSelecionado > 0 && $bancoDisponivel ? buscarItensPedidoConta($pdo, $pedidoSelecionado, $usuarioId) : [];
$mostrarFormularioEndereco = $_SERVER["REQUEST_METHOD"] === "POST";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="../assets/js/theme-init.js?v=20260731-3"></script>
    <title>Minha conta | Velle Dulcis</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20260527-1">
    <link rel="stylesheet" href="../assets/css/theme.css?v=20260731-3">
    <script src="../assets/js/theme.js?v=20260731-3" defer></script>
</head>
<body class="account-page admin-page admin-page--account">
    <div class="admin-shell">
        <div class="admin-topbar">
            <a class="admin-topbar__voltar" href="index.php">Voltar para a vitrine</a>

            <div class="admin-topbar__acoes" data-theme-toggle-host>
                <span class="admin-topbar__usuario"><?php echo htmlspecialchars($nomeUsuario, ENT_QUOTES, "UTF-8"); ?></span>
                <a class="admin-topbar__sair" href="logout.php">Sair</a>
            </div>
        </div>

        <section class="admin-card admin-card--account account-section">
            <div class="admin-card__header admin-card__header--account-page">
                <div>
                    <p class="admin-card__eyebrow">Profile</p>
                    <h1>Minha conta</h1>
                </div>
            </div>

            <p class="form-container__intro">Gerencie seus dados, enderecos salvos e pedidos realizados em um unico lugar.</p>

            <?php if ($pedidoRecemCriado !== ""): ?>
                <p class="admin-status" data-status="success">Pedido <?php echo htmlspecialchars($pedidoRecemCriado, ENT_QUOTES, "UTF-8"); ?> finalizado com sucesso.</p>
            <?php endif; ?>
            <?php if ($erro !== ""): ?>
                <p class="admin-status" data-status="error"><?php echo htmlspecialchars($erro, ENT_QUOTES, "UTF-8"); ?></p>
            <?php elseif ($sucesso !== ""): ?>
                <p class="admin-status" data-status="success"><?php echo htmlspecialchars($sucesso, ENT_QUOTES, "UTF-8"); ?></p>
            <?php elseif (!$bancoDisponivel): ?>
                <p class="admin-status" data-status="info">Banco comercial indisponivel. Execute `database/migrate.php`.</p>
            <?php endif; ?>

            <div class="admin-form__grid admin-form__grid--account-profile">
                <label class="admin-field">
                    <span class="admin-field__label">Nome</span>
                    <input type="text" value="<?php echo htmlspecialchars($nomeUsuario, ENT_QUOTES, "UTF-8"); ?>" readonly>
                </label>

                <label class="admin-field">
                    <span class="admin-field__label">Email</span>
                    <input type="email" value="<?php echo htmlspecialchars($emailUsuario, ENT_QUOTES, "UTF-8"); ?>" readonly>
                </label>
            </div>
        </section>

        <section class="admin-card account-section">
            <div class="admin-card__header admin-card__header--account-inline">
                <div>
                    <p class="admin-card__eyebrow">Addresses</p>
                    <h2>Enderecos salvos</h2>
                </div>
                <button
                    id="toggle-endereco-form"
                    class="account-add-button"
                    type="button"
                    aria-expanded="<?php echo $mostrarFormularioEndereco ? "true" : "false"; ?>"
                    aria-controls="endereco-form-wrapper"
                >
                    + Add
                </button>
            </div>

            <div id="endereco-form-wrapper" class="account-form-wrapper<?php echo $mostrarFormularioEndereco ? " is-open" : ""; ?>" <?php echo $mostrarFormularioEndereco ? "" : "hidden"; ?>>
                <div class="account-form-card">
                    <div>
                        <p class="form-container__intro">Adicione um endereco para agilizar a finalizacao dos seus proximos pedidos.</p>
                    </div>

                    <form method="post" class="admin-form" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo tokenCsrfHtml(); ?>">

                        <div class="admin-form__grid">
                            <label class="admin-field">
                                <span class="admin-field__label">Rotulo do endereco</span>
                                <input type="text" name="rotulo" placeholder="Ex: Casa, trabalho ou presente">
                            </label>

                            <label class="admin-field">
                                <span class="admin-field__label">Nome do destinatario</span>
                                <input type="text" name="destinatario_nome" placeholder="Nome completo de quem recebe" required>
                            </label>

                            <label class="admin-field">
                                <span class="admin-field__label">Telefone para finalizar compra</span>
                                <input type="tel" name="telefone" placeholder="(11) 90000-0000" required>
                            </label>

                            <label class="admin-field">
                                <span class="admin-field__label">CEP</span>
                                <input type="text" name="cep" placeholder="00000-000" required>
                            </label>

                            <label class="admin-field admin-field--full">
                                <span class="admin-field__label">Logradouro</span>
                                <input type="text" name="logradouro" placeholder="Rua, avenida ou alameda" required>
                            </label>

                            <label class="admin-field">
                                <span class="admin-field__label">Numero</span>
                                <input type="text" name="numero" placeholder="Numero" required>
                            </label>

                            <label class="admin-field">
                                <span class="admin-field__label">Complemento</span>
                                <input type="text" name="complemento" placeholder="Apartamento, bloco ou casa">
                            </label>

                            <label class="admin-field">
                                <span class="admin-field__label">Bairro</span>
                                <input type="text" name="bairro" placeholder="Bairro" required>
                            </label>

                            <label class="admin-field">
                                <span class="admin-field__label">Cidade</span>
                                <input type="text" name="cidade" placeholder="Cidade" required>
                            </label>

                            <label class="admin-field">
                                <span class="admin-field__label">UF</span>
                                <input type="text" name="estado" maxlength="2" placeholder="UF" required>
                            </label>

                            <label class="admin-field admin-field--full">
                                <span class="admin-field__label">Referencia de entrega</span>
                                <input type="text" name="referencia" placeholder="Ponto de referencia para facilitar a entrega">
                            </label>
                        </div>

                        <label class="admin-checkbox" for="principal">
                            <input id="principal" type="checkbox" name="principal" value="1" checked>
                            <span>Definir como endereco principal</span>
                        </label>

                        <div class="admin-toolbar">
                            <button id="btn-publicar" type="submit">Salvar endereco</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($enderecos === []): ?>
                <div class="account-empty-state">
                    <p>Nenhum endereco adicionado ainda.</p>
                </div>
            <?php else: ?>
                <div class="account-stack-list">
                <?php foreach ($enderecos as $endereco): ?>
                    <article class="account-list-card">
                        <div class="account-list-card__top">
                            <strong><?php echo htmlspecialchars((string) ($endereco["rotulo"] ?: $endereco["destinatario_nome"]), ENT_QUOTES, "UTF-8"); ?></strong>
                            <span class="account-pill"><?php echo (int) ($endereco["principal"] ?? 0) === 1 ? "Principal" : "Endereco"; ?></span>
                        </div>
                        <p class="admin-product-item__meta">
                            <?php echo htmlspecialchars((string) $endereco["destinatario_nome"], ENT_QUOTES, "UTF-8"); ?>
                        </p>
                        <p class="admin-product-item__meta">
                                <?php
                                echo htmlspecialchars(
                                    (string) $endereco["logradouro"] . ", " . (string) $endereco["numero"] . " - " .
                                    (string) $endereco["bairro"] . " - " . (string) $endereco["cidade"] . "/" . (string) $endereco["estado"] .
                                    " - CEP " . (string) $endereco["cep"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>
                        </p>
                        <?php if (!empty($endereco["referencia"])): ?>
                            <p class="admin-product-item__meta">Referencia: <?php echo htmlspecialchars((string) $endereco["referencia"], ENT_QUOTES, "UTF-8"); ?></p>
                        <?php endif; ?>
                        <p class="admin-product-item__meta">Telefone: <?php echo htmlspecialchars((string) ($endereco["telefone"] ?: "Nao cadastrado"), ENT_QUOTES, "UTF-8"); ?></p>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin-card account-section">
            <div class="admin-card__header admin-card__header--account-inline">
                <div>
                    <p class="admin-card__eyebrow">Historico</p>
                    <h2>Historico de vendas</h2>
                </div>
            </div>
            <?php if ($pedidos === []): ?>
                <div class="account-empty-state">
                    <p>Voce ainda nao finalizou nenhum pedido.</p>
                </div>
            <?php else: ?>
                <div class="account-stack-list">
                <?php foreach ($pedidos as $pedido): ?>
                    <article class="account-list-card">
                        <div class="account-list-card__top">
                            <strong><?php echo htmlspecialchars((string) $pedido["numero_pedido"], ENT_QUOTES, "UTF-8"); ?></strong>
                            <span class="account-price">R$ <?php echo number_format((float) $pedido["total"], 2, ",", "."); ?></span>
                        </div>
                        <p class="admin-product-item__meta">Status: <?php echo htmlspecialchars((string) $pedido["status"], ENT_QUOTES, "UTF-8"); ?></p>
                        <p class="admin-product-item__meta">Pagamento: <?php echo htmlspecialchars((string) $pedido["status_pagamento"], ENT_QUOTES, "UTF-8"); ?></p>
                        <p class="admin-product-item__meta">Itens: <?php echo (int) $pedido["total_itens"]; ?> | Data: <?php echo htmlspecialchars((string) ($pedido["realizado_em"] ?? ""), ENT_QUOTES, "UTF-8"); ?></p>
                        <div class="account-list-card__actions">
                            <a class="admin-toolbar__secondary admin-toolbar__secondary--compact" href="conta.php?pedido_id=<?php echo (int) $pedido["id"]; ?>">Ver itens</a>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($itensPedidoSelecionado !== []): ?>
            <section class="admin-card account-section">
                <div class="admin-card__header admin-card__header--account-inline">
                    <div>
                        <p class="admin-card__eyebrow">Detalhes</p>
                        <h2>Itens do pedido</h2>
                    </div>
                </div>
                <div class="account-stack-list">
                <?php foreach ($itensPedidoSelecionado as $item): ?>
                    <article class="account-list-card">
                        <div class="account-list-card__top">
                            <strong><?php echo htmlspecialchars((string) $item["produto_nome"], ENT_QUOTES, "UTF-8"); ?></strong>
                            <span class="account-price">R$ <?php echo number_format((float) $item["subtotal_item"], 2, ",", "."); ?></span>
                        </div>
                        <p class="admin-product-item__meta">Quantidade: <?php echo (int) $item["quantidade"]; ?> | Unitario: R$ <?php echo number_format((float) $item["preco_unitario"], 2, ",", "."); ?></p>
                    </article>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
    <script>
        (function () {
            const toggleButton = document.getElementById("toggle-endereco-form");
            const formWrapper = document.getElementById("endereco-form-wrapper");

            if (!toggleButton || !formWrapper) {
                return;
            }

            toggleButton.addEventListener("click", () => {
                const abrir = toggleButton.getAttribute("aria-expanded") !== "true";
                toggleButton.setAttribute("aria-expanded", abrir ? "true" : "false");
                formWrapper.hidden = !abrir;
                formWrapper.classList.toggle("is-open", abrir);
            });
        }());
    </script>
</body>
</html>
