<?php
declare(strict_types=1);

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

function responderJsonAdmin(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function usuarioEhAdmin(): bool
{
    return isset($_SESSION["usuario_id"]) && (string) ($_SESSION["usuario_papel"] ?? "") === "admin";
}

function normalizarListaImagens(array $imagens): array
{
    return array_values(array_unique(array_filter(array_map(
        static fn ($imagem): string => trim((string) $imagem),
        $imagens
    ))));
}

function gerarSlugProduto(string $texto): string
{
    $texto = trim($texto);
    $normalizado = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $texto);
    $normalizado = is_string($normalizado) ? $normalizado : $texto;
    $normalizado = strtolower($normalizado);
    $normalizado = preg_replace('/[^a-z0-9]+/', "-", $normalizado) ?? "";

    return trim($normalizado, "-");
}

function gerarReferenciaProduto(): string
{
    return (string) random_int(1000000000, 9999999999);
}

function normalizarReferenciaProduto(?string $referencia): ?string
{
    $referencia = trim((string) $referencia);

    if ($referencia === "") {
        return null;
    }

    $referencia = preg_replace('/[^A-Za-z0-9-]+/', "", $referencia) ?? "";

    return $referencia !== "" ? strtoupper($referencia) : null;
}

function gerarSlugUnicoProduto(PDO $pdo, string $nome, ?int $ignorarId = null): string
{
    $slugBase = gerarSlugProduto($nome) ?: "produto";
    $slug = $slugBase;
    $contador = 2;

    while (slugProdutoExiste($pdo, $slug, $ignorarId)) {
        $slug = "{$slugBase}-{$contador}";
        $contador += 1;
    }

    return $slug;
}

function slugProdutoExiste(PDO $pdo, string $slug, ?int $ignorarId = null): bool
{
    $sql = "SELECT id FROM produtos WHERE slug = :slug";

    if ($ignorarId !== null) {
        $sql .= " AND id <> :id";
    }

    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $params = ["slug" => $slug];

    if ($ignorarId !== null) {
        $params["id"] = $ignorarId;
    }

    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

function referenciaProdutoExiste(PDO $pdo, string $referencia, ?int $ignorarId = null): bool
{
    $sql = "SELECT id FROM produtos WHERE ref = :ref";

    if ($ignorarId !== null) {
        $sql .= " AND id <> :id";
    }

    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $params = ["ref" => $referencia];

    if ($ignorarId !== null) {
        $params["id"] = $ignorarId;
    }

    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

function gerarReferenciaUnicaProduto(PDO $pdo, ?string $referenciaBase = null): string
{
    $referencia = normalizarReferenciaProduto($referenciaBase) ?? gerarReferenciaProduto();

    while (referenciaProdutoExiste($pdo, $referencia)) {
        $referencia = gerarReferenciaProduto();
    }

    return $referencia;
}

function resolverReferenciaProduto(
    PDO $pdo,
    ?string $referenciaInformada,
    ?string $referenciaAtual = null,
    ?int $ignorarId = null
): string {
    $referenciaInformada = normalizarReferenciaProduto($referenciaInformada);
    $referenciaAtual = normalizarReferenciaProduto($referenciaAtual);

    if ($referenciaInformada !== null) {
        if (referenciaProdutoExiste($pdo, $referenciaInformada, $ignorarId)) {
            responderJsonAdmin(["erro" => "Ja existe um produto com essa referencia."], 409);
        }

        return $referenciaInformada;
    }

    if ($referenciaAtual !== null && !referenciaProdutoExiste($pdo, $referenciaAtual, $ignorarId)) {
        return $referenciaAtual;
    }

    return gerarReferenciaUnicaProduto($pdo);
}

function formatarProdutoAdmin(array $produto): array
{
    $galeria = [];
    $imagensBrutas = $produto["imagens"] ?? "[]";

    if (is_string($imagensBrutas) && $imagensBrutas !== "") {
        $imagensDecodificadas = json_decode($imagensBrutas, true);
        $galeria = is_array($imagensDecodificadas) ? normalizarListaImagens($imagensDecodificadas) : [];
    } elseif (is_array($imagensBrutas)) {
        $galeria = normalizarListaImagens($imagensBrutas);
    }

    $imagemPrincipal = (string) ($produto["img"] ?? "");

    if ($imagemPrincipal !== "" && !in_array($imagemPrincipal, $galeria, true)) {
        array_unshift($galeria, $imagemPrincipal);
    }

    $pesoOriginal = (string) ($produto["peso"] ?? "");
    $pesoGramas = isset($produto["peso_gramas"]) && $produto["peso_gramas"] !== null
        ? max(0, (int) $produto["peso_gramas"])
        : extrairPesoGramas($pesoOriginal);

    return [
        "id" => (int) ($produto["id"] ?? 0),
        "slug" => (string) ($produto["slug"] ?? ""),
        "img" => $imagemPrincipal,
        "nome" => (string) ($produto["nome"] ?? ""),
        "preco" => (float) ($produto["preco"] ?? 0),
        "categoria" => (string) ($produto["categoria"] ?? $produto["tipo"] ?? "Chocolate"),
        "peso" => formatarPesoProduto($pesoOriginal, $pesoGramas),
        "peso_gramas" => $pesoGramas,
        "ref" => (string) ($produto["ref"] ?? ""),
        "destaque" => (string) ($produto["destaque"] ?? ""),
        "descricao" => (string) ($produto["descricao"] ?? ""),
        "imagens" => $galeria,
        "estoque_quantidade" => (int) ($produto["estoque_quantidade"] ?? 0),
        "tipo" => (string) ($produto["tipo"] ?? ""),
    ];
}

function extrairPesoGramas(string $peso): ?int
{
    $peso = strtolower(trim(str_replace(",", ".", $peso)));

    if ($peso === "") {
        return null;
    }

    if (preg_match('/(\d+(?:\.\d+)?)\s*(kg|quilo|kilo|g|gr|grama|gramas)\b/u', $peso, $matches) !== 1) {
        return null;
    }

    $valor = (float) $matches[1];
    $unidade = $matches[2];

    if (in_array($unidade, ["kg", "quilo", "kilo"], true)) {
        $valor *= 1000;
    }

    return max(0, (int) round($valor));
}

function formatarPesoProduto(string $peso, ?int $pesoGramas): string
{
    $peso = trim($peso);

    if ($peso !== "") {
        return $peso;
    }

    if ($pesoGramas === null || $pesoGramas <= 0) {
        return "";
    }

    return $pesoGramas >= 1000 && $pesoGramas % 1000 === 0
        ? ((int) ($pesoGramas / 1000)) . " kg"
        : $pesoGramas . "g";
}

function lerCorpoJsonAdmin(): array
{
    $dados = json_decode(file_get_contents("php://input") ?: "", true);
    return is_array($dados) ? $dados : [];
}

function normalizarPayloadProdutoAdmin(array $dados): array
{
    $nome = trim((string) ($dados["nome"] ?? ""));
    $img = trim((string) ($dados["img"] ?? ""));
    $descricao = trim((string) ($dados["descricao"] ?? ""));
    $categoria = trim((string) ($dados["categoria"] ?? ""));
    $peso = trim((string) ($dados["peso"] ?? ""));
    $ref = normalizarReferenciaProduto((string) ($dados["ref"] ?? ""));
    $destaque = trim((string) ($dados["destaque"] ?? ""));
    $preco = round((float) ($dados["preco"] ?? 0), 2);
    $estoqueQuantidade = max(0, (int) ($dados["estoque_quantidade"] ?? 0));
    $tipo = trim((string) ($dados["tipo"] ?? $categoria));
    $imagens = normalizarListaImagens(is_array($dados["imagens"] ?? null) ? $dados["imagens"] : [$img]);

    return [
        "id" => (int) ($dados["id"] ?? 0),
        "nome" => $nome,
        "img" => $img,
        "descricao" => $descricao,
        "categoria" => $categoria !== "" ? $categoria : "Chocolate",
        "peso" => $peso,
        "peso_gramas" => extrairPesoGramas($peso),
        "ref" => $ref,
        "destaque" => $destaque,
        "preco" => $preco,
        "estoque_quantidade" => $estoqueQuantidade,
        "tipo" => $tipo !== "" ? $tipo : ($categoria !== "" ? $categoria : "Chocolate"),
        "imagens" => $imagens,
    ];
}

function validarPayloadProdutoAdmin(array $produto): ?string
{
    if ($produto["nome"] === "" || $produto["img"] === "" || $produto["preco"] <= 0) {
        return "Preencha imagem, nome e preco valido.";
    }

    return null;
}

function buscarProdutoAdminPorId(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, slug, nome, descricao, preco, categoria, tipo, peso, peso_gramas, ref, destaque, img, imagens, estoque_quantidade, ativo, deleted_at
         FROM produtos
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute(["id" => $id]);
    $produto = $stmt->fetch();

    return is_array($produto) ? $produto : null;
}

function registrarMovimentacaoEstoque(
    PDO $pdo,
    int $produtoId,
    string $tipo,
    int $quantidade,
    ?int $estoqueAntes,
    ?int $estoqueDepois,
    string $origem,
    ?string $observacao,
    ?int $usuarioId
): void {
    if (!tabelasBancoDisponiveis($pdo, ["estoque_movimentacoes"])) {
        return;
    }

    if ($quantidade === 0) {
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO estoque_movimentacoes (
            produto_id, usuario_id, tipo, origem, quantidade, estoque_antes, estoque_depois, observacao
        ) VALUES (
            :produto_id, :usuario_id, :tipo, :origem, :quantidade, :estoque_antes, :estoque_depois, :observacao
        )"
    );
    $stmt->execute([
        "produto_id" => $produtoId,
        "usuario_id" => $usuarioId,
        "tipo" => $tipo,
        "origem" => $origem,
        "quantidade" => $quantidade,
        "estoque_antes" => $estoqueAntes,
        "estoque_depois" => $estoqueDepois,
        "observacao" => $observacao,
    ]);
}

function responderErroDePersistencia(PDOException $exception, string $mensagemPadrao): void
{
    if (excecaoEntradaDuplicada($exception)) {
        $chaveDuplicada = obterChaveDuplicada($exception);

        if ($chaveDuplicada === "produtos_ref_unique") {
            responderJsonAdmin(["erro" => "Ja existe um produto com essa referencia."], 409);
        }

        if ($chaveDuplicada === "produtos_slug_unique") {
            responderJsonAdmin(["erro" => "Ja existe um produto com esse identificador de URL."], 409);
        }
    }

    responderJsonAdmin(["erro" => $mensagemPadrao], 500);
}

function excecaoEntradaDuplicada(PDOException $exception): bool
{
    $info = $exception->errorInfo;
    return (string) ($info[0] ?? "") === "23000" && (int) ($info[1] ?? 0) === 1062;
}

function obterChaveDuplicada(PDOException $exception): string
{
    $mensagem = $exception->getMessage();

    if (preg_match("/for key '([^']+)'/i", $mensagem, $matches) === 1) {
        return (string) $matches[1];
    }

    return "";
}

if (!usuarioEhAdmin()) {
    responderJsonAdmin(["erro" => "Acesso administrativo necessario."], 403);
}

if (!bancoDeDadosDisponivel($pdo) || !($pdo instanceof PDO) || !schemaProdutosDisponivel($pdo)) {
    responderJsonAdmin(["erro" => "Banco de dados indisponivel ou schema de produtos ausente."], 503);
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    try {
        $stmt = $pdo->query(
            "SELECT id, slug, nome, descricao, preco, categoria, tipo, peso, peso_gramas, ref, destaque, img, imagens, estoque_quantidade, ativo, deleted_at
             FROM produtos
             WHERE ativo = 1 AND deleted_at IS NULL
             ORDER BY id DESC"
        );
        $produtos = array_map("formatarProdutoAdmin", $stmt->fetchAll());
        responderJsonAdmin(["produtos" => $produtos]);
    } catch (PDOException $exception) {
        responderJsonAdmin(["erro" => "Nao foi possivel listar os produtos."], 500);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dados = lerCorpoJsonAdmin();

    if ($dados === []) {
        responderJsonAdmin(["erro" => "Dados invalidos."], 422);
    }

    $produto = normalizarPayloadProdutoAdmin($dados);
    $erroValidacao = validarPayloadProdutoAdmin($produto);

    if ($erroValidacao !== null) {
        responderJsonAdmin(["erro" => $erroValidacao], 422);
    }

    try {
        $slug = gerarSlugUnicoProduto($pdo, $produto["nome"]);
        $referencia = resolverReferenciaProduto($pdo, $produto["ref"]);

        $stmt = $pdo->prepare(
            "INSERT INTO produtos (
                slug, nome, descricao, preco, categoria, tipo, peso, peso_gramas, ref, destaque, img, imagens, estoque_quantidade, ativo, criado_por_usuario_id, atualizado_por_usuario_id
            ) VALUES (
                :slug, :nome, :descricao, :preco, :categoria, :tipo, :peso, :peso_gramas, :ref, :destaque, :img, :imagens, :estoque_quantidade, 1, :criado_por_usuario_id, :atualizado_por_usuario_id
            )"
        );
        $stmt->execute([
            "slug" => $slug,
            "nome" => $produto["nome"],
            "descricao" => $produto["descricao"] !== "" ? $produto["descricao"] : null,
            "preco" => $produto["preco"],
            "categoria" => $produto["categoria"],
            "tipo" => $produto["tipo"],
            "peso" => $produto["peso"] !== "" ? $produto["peso"] : null,
            "peso_gramas" => $produto["peso_gramas"],
            "ref" => $referencia,
            "destaque" => $produto["destaque"] !== "" ? $produto["destaque"] : null,
            "img" => $produto["img"],
            "imagens" => json_encode($produto["imagens"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "estoque_quantidade" => $produto["estoque_quantidade"],
            "criado_por_usuario_id" => (int) ($_SESSION["usuario_id"] ?? 0) ?: null,
            "atualizado_por_usuario_id" => (int) ($_SESSION["usuario_id"] ?? 0) ?: null,
        ]);

        $produtoId = (int) $pdo->lastInsertId();
        if ($produto["estoque_quantidade"] > 0) {
            registrarMovimentacaoEstoque(
                $pdo,
                $produtoId,
                "entrada",
                (int) $produto["estoque_quantidade"],
                0,
                (int) $produto["estoque_quantidade"],
                "painel_admin",
                "Estoque inicial do cadastro do produto.",
                (int) ($_SESSION["usuario_id"] ?? 0) ?: null
            );
        }
        $produtoSalvo = buscarProdutoAdminPorId($pdo, $produtoId);

        responderJsonAdmin([
            "sucesso" => true,
            "produto" => formatarProdutoAdmin($produtoSalvo ?: []),
        ], 201);
    } catch (PDOException $exception) {
        responderErroDePersistencia($exception, "Nao foi possivel salvar o produto.");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    $dados = lerCorpoJsonAdmin();

    if ($dados === []) {
        responderJsonAdmin(["erro" => "Dados invalidos."], 422);
    }

    $produto = normalizarPayloadProdutoAdmin($dados);
    $erroValidacao = validarPayloadProdutoAdmin($produto);

    if ($erroValidacao !== null) {
        responderJsonAdmin(["erro" => $erroValidacao], 422);
    }

    if ($produto["id"] <= 0) {
        responderJsonAdmin(["erro" => "Produto invalido."], 422);
    }

    try {
        $produtoAtual = buscarProdutoAdminPorId($pdo, $produto["id"]);

        if ($produtoAtual === null) {
            responderJsonAdmin(["erro" => "Produto nao encontrado."], 404);
        }

        $slug = gerarSlugUnicoProduto($pdo, $produto["nome"], $produto["id"]);
        $referencia = resolverReferenciaProduto(
            $pdo,
            $produto["ref"],
            (string) ($produtoAtual["ref"] ?? ""),
            $produto["id"]
        );
        $estoqueAnterior = (int) ($produtoAtual["estoque_quantidade"] ?? 0);

        $stmt = $pdo->prepare(
            "UPDATE produtos SET
                slug = :slug,
                nome = :nome,
                descricao = :descricao,
                preco = :preco,
                categoria = :categoria,
                tipo = :tipo,
                peso = :peso,
                peso_gramas = :peso_gramas,
                ref = :ref,
                destaque = :destaque,
                img = :img,
                imagens = :imagens,
                estoque_quantidade = :estoque_quantidade,
                atualizado_por_usuario_id = :atualizado_por_usuario_id
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([
            "id" => $produto["id"],
            "slug" => $slug,
            "nome" => $produto["nome"],
            "descricao" => $produto["descricao"] !== "" ? $produto["descricao"] : null,
            "preco" => $produto["preco"],
            "categoria" => $produto["categoria"],
            "tipo" => $produto["tipo"],
            "peso" => $produto["peso"] !== "" ? $produto["peso"] : null,
            "peso_gramas" => $produto["peso_gramas"],
            "ref" => $referencia,
            "destaque" => $produto["destaque"] !== "" ? $produto["destaque"] : null,
            "img" => $produto["img"],
            "imagens" => json_encode($produto["imagens"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "estoque_quantidade" => $produto["estoque_quantidade"],
            "atualizado_por_usuario_id" => (int) ($_SESSION["usuario_id"] ?? 0) ?: null,
        ]);

        $diferencaEstoque = (int) $produto["estoque_quantidade"] - $estoqueAnterior;
        if ($diferencaEstoque !== 0) {
            registrarMovimentacaoEstoque(
                $pdo,
                (int) $produto["id"],
                "ajuste",
                $diferencaEstoque,
                $estoqueAnterior,
                (int) $produto["estoque_quantidade"],
                "painel_admin",
                "Ajuste manual de estoque pelo painel.",
                (int) ($_SESSION["usuario_id"] ?? 0) ?: null
            );
        }

        $produtoAtualizado = buscarProdutoAdminPorId($pdo, $produto["id"]);

        responderJsonAdmin([
            "sucesso" => true,
            "produto" => formatarProdutoAdmin($produtoAtualizado ?: []),
        ]);
    } catch (PDOException $exception) {
        responderErroDePersistencia($exception, "Nao foi possivel atualizar o produto.");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    $dados = lerCorpoJsonAdmin();
    $id = (int) ($dados["id"] ?? 0);

    if ($id <= 0) {
        responderJsonAdmin(["erro" => "Produto invalido."], 422);
    }

    try {
        $produtoAtual = buscarProdutoAdminPorId($pdo, $id);

        if ($produtoAtual === null || (int) ($produtoAtual["ativo"] ?? 1) !== 1) {
            responderJsonAdmin(["erro" => "Produto nao encontrado."], 404);
        }

        $stmt = $pdo->prepare(
            "UPDATE produtos
             SET ativo = 0,
                 deleted_at = CURRENT_TIMESTAMP,
                 atualizado_por_usuario_id = :atualizado_por_usuario_id
             WHERE id = :id
               AND ativo = 1
             LIMIT 1"
        );
        $stmt->execute([
            "id" => $id,
            "atualizado_por_usuario_id" => (int) ($_SESSION["usuario_id"] ?? 0) ?: null,
        ]);

        if ($stmt->rowCount() === 0) {
            responderJsonAdmin(["erro" => "Produto nao encontrado."], 404);
        }

        responderJsonAdmin(["sucesso" => true]);
    } catch (PDOException $exception) {
        responderJsonAdmin(["erro" => "Nao foi possivel remover o produto."], 500);
    }
}

responderJsonAdmin(["erro" => "Metodo nao permitido."], 405);
