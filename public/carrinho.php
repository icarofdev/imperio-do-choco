<?php
declare(strict_types=1);

require_once __DIR__ . "/../app/seguranca.php";

iniciarSessaoSegura();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../app/conexao.php";

function responderJson(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function buscarProdutoCarrinhoPorId(PDO $pdo, int $produtoId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, slug, nome, preco, img
         FROM produtos
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute(["id" => $produtoId]);
    $produto = $stmt->fetch();

    return is_array($produto) ? $produto : null;
}

function buscarProdutoCarrinhoPorSlug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, slug, nome, preco, img
         FROM produtos
         WHERE slug = :slug
         LIMIT 1"
    );
    $stmt->execute(["slug" => $slug]);
    $produto = $stmt->fetch();

    return is_array($produto) ? $produto : null;
}

function normalizarItemCarrinhoRecebido(array $item): ?array
{
    $nome = trim((string) ($item["nome"] ?? ""));
    $chave = trim((string) ($item["chave"] ?? ""));
    $slug = trim((string) ($item["slug"] ?? ""));
    $imagem = trim((string) ($item["imagem"] ?? ""));
    $preco = round((float) ($item["preco"] ?? 0), 2);
    $quantidade = (int) ($item["qtd"] ?? 0);
    $produtoId = (int) ($item["produto_id"] ?? 0);

    if ($nome === "" || $quantidade <= 0) {
        return null;
    }

    $chaveNormalizada = $chave !== "" ? $chave : ($slug !== "" ? $slug : gerarSlugProduto($nome));

    if ($chaveNormalizada === "") {
        return null;
    }

    return [
        "produto_id" => $produtoId > 0 ? $produtoId : null,
        "chave" => mb_substr($chaveNormalizada, 0, 190),
        "slug" => $slug !== "" ? mb_substr($slug, 0, 190) : null,
        "nome" => mb_substr($nome, 0, 180),
        "preco" => $preco >= 0 ? $preco : 0,
        "imagem" => $imagem !== "" ? $imagem : null,
        "qtd" => $quantidade,
    ];
}

function resolverItemCarrinho(PDO $pdo, array $item): array
{
    $produto = null;

    if (($item["produto_id"] ?? null) !== null) {
        $produto = buscarProdutoCarrinhoPorId($pdo, (int) $item["produto_id"]);
    }

    if ($produto === null && !empty($item["slug"])) {
        $produto = buscarProdutoCarrinhoPorSlug($pdo, (string) $item["slug"]);
    }

    if ($produto === null) {
        return [
            "produto_id" => null,
            "chave" => $item["chave"],
            "slug" => $item["slug"],
            "nome" => $item["nome"],
            "preco" => $item["preco"],
            "imagem" => $item["imagem"],
            "qtd" => $item["qtd"],
        ];
    }

    $slug = trim((string) ($produto["slug"] ?? ""));

    return [
        "produto_id" => (int) ($produto["id"] ?? 0),
        "chave" => $slug !== "" ? $slug : $item["chave"],
        "slug" => $slug !== "" ? $slug : ($item["slug"] ?? null),
        "nome" => (string) ($produto["nome"] ?? $item["nome"]),
        "preco" => round((float) ($produto["preco"] ?? $item["preco"]), 2),
        "imagem" => trim((string) ($produto["img"] ?? "")) !== ""
            ? (string) $produto["img"]
            : ($item["imagem"] ?? null),
        "qtd" => $item["qtd"],
    ];
}

if (!isset($_SESSION["usuario_id"])) {
    responderJson(["erro" => "Usuario nao autenticado."], 401);
}

if (!bancoDeDadosDisponivel($pdo)) {
    responderJson(["erro" => "Banco de dados indisponivel."], 503);
}

if (!schemaCarrinhoDisponivel($pdo)) {
    responderJson(["erro" => "Schema do carrinho nao aplicado. Execute database/migrate.php antes de usar esta funcionalidade."], 503);
}

$usuarioId = (int) $_SESSION["usuario_id"];

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    try {
        $stmt = $pdo->prepare(
            "SELECT produto_id, chave_produto, produto_slug, produto_nome, preco_unitario, imagem, quantidade
             FROM carrinho_itens
             WHERE usuario_id = :usuario_id
             ORDER BY id ASC"
        );
        $stmt->execute(["usuario_id" => $usuarioId]);

        $itens = array_map(static function (array $item): array {
            return [
                "produto_id" => $item["produto_id"] !== null ? (int) $item["produto_id"] : null,
                "chave" => (string) $item["chave_produto"],
                "slug" => $item["produto_slug"] !== null ? (string) $item["produto_slug"] : "",
                "nome" => (string) $item["produto_nome"],
                "preco" => round((float) $item["preco_unitario"], 2),
                "imagem" => $item["imagem"] !== null ? (string) $item["imagem"] : "",
                "qtd" => (int) $item["quantidade"],
            ];
        }, $stmt->fetchAll());

        responderJson(["sucesso" => true, "itens" => $itens]);
    } catch (PDOException $exception) {
        responderJson(["erro" => "Nao foi possivel carregar o carrinho."], 500);
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderJson(["erro" => "Metodo nao permitido."], 405);
}

$payload = json_decode((string) file_get_contents("php://input"), true);

if (!is_array($payload)) {
    responderJson(["erro" => "Payload invalido."], 400);
}

if (!tokenCsrfValido(obterTokenCsrfRequisicao($payload))) {
    responderJson(["erro" => "Sessao expirada. Recarregue a pagina e tente novamente."], 419);
}

$itensRecebidos = $payload["itens"] ?? [];

if (!is_array($itensRecebidos)) {
    responderJson(["erro" => "Lista de itens invalida."], 400);
}

$itensNormalizados = [];

foreach ($itensRecebidos as $item) {
    if (!is_array($item)) {
        continue;
    }

    $normalizado = normalizarItemCarrinhoRecebido($item);

    if ($normalizado === null) {
        continue;
    }

    $resolvido = resolverItemCarrinho($pdo, $normalizado);
    $chave = (string) $resolvido["chave"];

    if (isset($itensNormalizados[$chave])) {
        $itensNormalizados[$chave]["qtd"] += $resolvido["qtd"];
        continue;
    }

    $itensNormalizados[$chave] = $resolvido;
}

$itensNormalizados = array_values($itensNormalizados);

try {
    $pdo->beginTransaction();

    $delete = $pdo->prepare("DELETE FROM carrinho_itens WHERE usuario_id = :usuario_id");
    $delete->execute(["usuario_id" => $usuarioId]);

    if ($itensNormalizados !== []) {
        $insert = $pdo->prepare(
            "INSERT INTO carrinho_itens (
                usuario_id, produto_id, chave_produto, produto_slug, produto_nome, preco_unitario, imagem, quantidade
            ) VALUES (
                :usuario_id, :produto_id, :chave_produto, :produto_slug, :produto_nome, :preco_unitario, :imagem, :quantidade
            )"
        );

        foreach ($itensNormalizados as $item) {
            $insert->execute([
                "usuario_id" => $usuarioId,
                "produto_id" => $item["produto_id"],
                "chave_produto" => $item["chave"],
                "produto_slug" => $item["slug"],
                "produto_nome" => $item["nome"],
                "preco_unitario" => $item["preco"],
                "imagem" => $item["imagem"],
                "quantidade" => $item["qtd"],
            ]);
        }
    }

    $pdo->commit();
    responderJson(["sucesso" => true, "itens" => $itensNormalizados]);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responderJson(["erro" => "Nao foi possivel salvar o carrinho."], 500);
}
