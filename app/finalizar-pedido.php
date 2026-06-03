<?php
declare(strict_types=1);

require_once __DIR__ . "/seguranca.php";

iniciarSessaoSegura();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

function responderJsonCheckout(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function buscarEnderecoPrincipalUsuario(PDO $pdo, int $usuarioId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, destinatario_nome, telefone, cep, logradouro, numero, bairro, cidade, estado
         FROM enderecos
         WHERE usuario_id = :usuario_id
         ORDER BY principal DESC, id ASC
         LIMIT 1"
    );
    $stmt->execute(["usuario_id" => $usuarioId]);
    $endereco = $stmt->fetch();

    return is_array($endereco) ? $endereco : null;
}

function gerarNumeroPedido(PDO $pdo): string
{
    do {
        $numero = "VEL-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare("SELECT 1 FROM pedidos WHERE numero_pedido = :numero LIMIT 1");
        $stmt->execute(["numero" => $numero]);
    } while ($stmt->fetchColumn());

    return $numero;
}

function smsAtivo(): bool
{
    $valor = strtolower((string) lerVariavelAmbiente("SMS_ENABLED", "false"));

    return !in_array($valor, ["0", "false", "off", "no", "nao"], true);
}

function normalizarTelefoneSms(?string $telefone): string
{
    $telefone = trim((string) $telefone);

    if ($telefone === "") {
        return "";
    }

    $telefone = preg_replace("/[^\d+]/", "", $telefone) ?? "";

    if (str_starts_with($telefone, "+")) {
        return $telefone;
    }

    if (str_starts_with($telefone, "55") && strlen($telefone) >= 12) {
        return "+" . $telefone;
    }

    if (strlen($telefone) >= 10) {
        return "+55" . $telefone;
    }

    return $telefone;
}

function obterDestinoSms(array $endereco): string
{
    $destinoConfigurado = lerVariavelAmbiente("SMS_TO")
        ?? lerVariavelAmbiente("SMS_DESTINATARIO")
        ?? lerVariavelAmbiente("TWILIO_TO");

    if ($destinoConfigurado !== null) {
        return normalizarTelefoneSms($destinoConfigurado);
    }

    return normalizarTelefoneSms((string) ($endereco["telefone"] ?? ""));
}

function enviarRequisicaoHttp(
    string $url,
    string $metodo,
    array $headers,
    string $corpo,
    ?string $usuario = null,
    ?string $senha = null
): array {
    if (function_exists("curl_init")) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $corpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
        ]);

        if ($usuario !== null && $senha !== null) {
            curl_setopt($curl, CURLOPT_USERPWD, "{$usuario}:{$senha}");
        }

        $resposta = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $erro = curl_error($curl);
        curl_close($curl);

        return [
            "ok" => $erro === "" && $status >= 200 && $status < 300,
            "status" => $status,
            "erro" => $erro,
            "resposta" => is_string($resposta) ? $resposta : "",
        ];
    }

    $headersTexto = implode("\r\n", $headers);

    if ($usuario !== null && $senha !== null) {
        $headersTexto .= "\r\nAuthorization: Basic " . base64_encode("{$usuario}:{$senha}");
    }

    $contexto = stream_context_create([
        "http" => [
            "method" => $metodo,
            "header" => $headersTexto,
            "content" => $corpo,
            "timeout" => 12,
            "ignore_errors" => true,
        ],
    ]);
    $resposta = @file_get_contents($url, false, $contexto);
    $status = 0;

    foreach (($http_response_header ?? []) as $cabecalho) {
        if (preg_match("/^HTTP\/\S+\s+(\d+)/", $cabecalho, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        "ok" => $resposta !== false && $status >= 200 && $status < 300,
        "status" => $status,
        "erro" => $resposta === false ? "Falha na requisicao HTTP." : "",
        "resposta" => is_string($resposta) ? $resposta : "",
    ];
}

function enviarSmsPedido(string $numeroPedido, float $total, array $endereco): array
{
    if (!smsAtivo()) {
        return ["ok" => false, "motivo" => "SMS desativado por SMS_ENABLED."];
    }

    $destino = obterDestinoSms($endereco);

    if ($destino === "") {
        return ["ok" => false, "motivo" => "Nenhum telefone configurado para SMS_TO/SMS_DESTINATARIO/TWILIO_TO ou endereco."];
    }

    $mensagem = sprintf(
        "Velle Dulcis: pedido %s recebido. Total R$ %s.",
        $numeroPedido,
        number_format($total, 2, ",", ".")
    );
    $provider = strtolower((string) lerVariavelAmbiente("SMS_PROVIDER", ""));
    $twilioSid = lerVariavelAmbiente("TWILIO_ACCOUNT_SID");
    $twilioToken = lerVariavelAmbiente("TWILIO_AUTH_TOKEN");
    $twilioFrom = lerVariavelAmbiente("TWILIO_FROM");

    if ($provider === "twilio" || ($twilioSid !== null && $twilioToken !== null && $twilioFrom !== null)) {
        if ($twilioSid === null || $twilioToken === null || $twilioFrom === null) {
            return ["ok" => false, "motivo" => "Variaveis Twilio incompletas."];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/" . rawurlencode($twilioSid) . "/Messages.json";
        $resultado = enviarRequisicaoHttp(
            $url,
            "POST",
            ["Content-Type: application/x-www-form-urlencoded"],
            http_build_query([
                "To" => $destino,
                "From" => $twilioFrom,
                "Body" => $mensagem,
            ]),
            $twilioSid,
            $twilioToken
        );

        return $resultado + ["provider" => "twilio"];
    }

    $apiUrl = lerVariavelAmbiente("SMS_API_URL") ?? lerVariavelAmbiente("SMS_WEBHOOK_URL");

    if ($apiUrl === null) {
        return ["ok" => false, "motivo" => "Configure SMS_API_URL/SMS_WEBHOOK_URL ou variaveis TWILIO_* no .env."];
    }

    $headers = ["Content-Type: application/json", "Accept: application/json"];
    $token = lerVariavelAmbiente("SMS_API_TOKEN") ?? lerVariavelAmbiente("SMS_TOKEN") ?? lerVariavelAmbiente("API_TOKEN");
    $apiKey = lerVariavelAmbiente("SMS_API_KEY") ?? lerVariavelAmbiente("API_KEY");

    if ($token !== null) {
        $headers[] = "Authorization: Bearer {$token}";
    }

    if ($apiKey !== null) {
        $headers[] = "X-API-Key: {$apiKey}";
    }

    $resultado = enviarRequisicaoHttp(
        $apiUrl,
        "POST",
        $headers,
        json_encode([
            "to" => $destino,
            "from" => lerVariavelAmbiente("SMS_FROM"),
            "message" => $mensagem,
            "pedido" => $numeroPedido,
            "total" => $total,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: "{}"
    );

    return $resultado + ["provider" => "generic"];
}

function registrarMovimentacaoPedidoEstoque(
    PDO $pdo,
    int $produtoId,
    int $pedidoId,
    int $usuarioId,
    int $quantidade,
    int $estoqueAntes,
    int $estoqueDepois,
    string $observacao
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO estoque_movimentacoes (
            produto_id, pedido_id, usuario_id, tipo, origem, quantidade, estoque_antes, estoque_depois, observacao
        ) VALUES (
            :produto_id, :pedido_id, :usuario_id, 'saida', 'checkout', :quantidade, :estoque_antes, :estoque_depois, :observacao
        )"
    );
    $stmt->execute([
        "produto_id" => $produtoId,
        "pedido_id" => $pedidoId,
        "usuario_id" => $usuarioId,
        "quantidade" => -abs($quantidade),
        "estoque_antes" => $estoqueAntes,
        "estoque_depois" => $estoqueDepois,
        "observacao" => $observacao,
    ]);
}

if (!isset($_SESSION["usuario_id"])) {
    responderJsonCheckout(["erro" => "Usuario nao autenticado."], 401);
}

if (!bancoDeDadosDisponivel($pdo)) {
    responderJsonCheckout(["erro" => "Banco de dados indisponivel."], 503);
}

if (!schemaComercialDisponivel($pdo) || !schemaCarrinhoDisponivel($pdo)) {
    responderJsonCheckout(["erro" => "Schema comercial nao aplicado. Execute database/migrate.php antes de finalizar pedidos."], 503);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderJsonCheckout(["erro" => "Metodo nao permitido."], 405);
}

if (!tokenCsrfValido(obterTokenCsrfRequisicao())) {
    responderJsonCheckout(["erro" => "Sessao expirada. Recarregue a pagina e tente novamente."], 419);
}

$usuarioId = (int) $_SESSION["usuario_id"];
$endereco = buscarEnderecoPrincipalUsuario($pdo, $usuarioId);

if ($endereco === null) {
    responderJsonCheckout([
        "erro" => "Cadastre um endereco principal antes de finalizar o pedido.",
        "codigo" => "endereco_principal_ausente",
    ], 409);
}

try {
    $pdo->beginTransaction();

    $stmtCarrinho = $pdo->prepare(
        "SELECT ci.id, ci.produto_id, ci.chave_produto, ci.produto_slug, ci.produto_nome, ci.preco_unitario, ci.imagem, ci.quantidade,
                p.slug AS produto_slug_atual, p.ref AS produto_ref_atual, p.nome AS produto_nome_atual, p.preco AS produto_preco_atual,
                p.estoque_quantidade, p.ativo, p.deleted_at
         FROM carrinho_itens ci
         LEFT JOIN produtos p ON p.id = ci.produto_id
         WHERE ci.usuario_id = :usuario_id
         ORDER BY ci.id ASC"
         . " FOR UPDATE"
    );
    $stmtCarrinho->execute(["usuario_id" => $usuarioId]);
    $itensCarrinho = $stmtCarrinho->fetchAll();

    if ($itensCarrinho === []) {
        $pdo->rollBack();
        responderJsonCheckout(["erro" => "Seu carrinho esta vazio."], 422);
    }

    $numeroPedido = gerarNumeroPedido($pdo);
    $subtotal = 0.0;
    $itensNormalizados = [];

    foreach ($itensCarrinho as $item) {
        $quantidade = (int) ($item["quantidade"] ?? 0);
        $produtoId = $item["produto_id"] !== null ? (int) $item["produto_id"] : null;
        $produtoAtivo = (int) ($item["ativo"] ?? 0) === 1 && $item["deleted_at"] === null;

        if ($quantidade <= 0) {
            throw new RuntimeException("Foi encontrado um item invalido no carrinho.");
        }

        if ($produtoId === null || !$produtoAtivo) {
            throw new RuntimeException("Um dos itens do carrinho nao esta mais disponivel.");
        }

        $estoqueAtual = (int) ($item["estoque_quantidade"] ?? 0);
        if ($estoqueAtual < $quantidade) {
            throw new RuntimeException("Estoque insuficiente para o item " . (string) ($item["produto_nome_atual"] ?? $item["produto_nome"] ?? "selecionado") . ".");
        }

        $precoUnitario = round((float) ($item["produto_preco_atual"] ?? $item["preco_unitario"] ?? 0), 2);
        $subtotalItem = round($precoUnitario * $quantidade, 2);
        $subtotal += $subtotalItem;

        $itensNormalizados[] = [
            "produto_id" => $produtoId,
            "produto_slug" => (string) ($item["produto_slug_atual"] ?? $item["produto_slug"] ?? ""),
            "produto_ref" => (string) ($item["produto_ref_atual"] ?? ""),
            "produto_nome" => (string) ($item["produto_nome_atual"] ?? $item["produto_nome"] ?? "Produto"),
            "quantidade" => $quantidade,
            "preco_unitario" => $precoUnitario,
            "subtotal_item" => $subtotalItem,
            "estoque_antes" => $estoqueAtual,
            "estoque_depois" => $estoqueAtual - $quantidade,
        ];
    }

    $subtotal = round($subtotal, 2);
    $frete = 0.0;
    $desconto = 0.0;
    $total = round($subtotal + $frete - $desconto, 2);

    $stmtPedido = $pdo->prepare(
        "INSERT INTO pedidos (
            usuario_id, endereco_entrega_id, numero_pedido, status, moeda, subtotal, desconto, frete, total,
            metodo_pagamento, status_pagamento, observacoes, realizado_em
        ) VALUES (
            :usuario_id, :endereco_entrega_id, :numero_pedido, 'aguardando_pagamento', 'BRL', :subtotal, :desconto, :frete, :total,
            'pendente', 'pendente', :observacoes, CURRENT_TIMESTAMP
        )"
    );
    $stmtPedido->execute([
        "usuario_id" => $usuarioId,
        "endereco_entrega_id" => (int) $endereco["id"],
        "numero_pedido" => $numeroPedido,
        "subtotal" => $subtotal,
        "desconto" => $desconto,
        "frete" => $frete,
        "total" => $total,
        "observacoes" => "Pedido gerado a partir do carrinho da conta.",
    ]);

    $pedidoId = (int) $pdo->lastInsertId();

    $stmtPedidoItem = $pdo->prepare(
        "INSERT INTO pedido_itens (
            pedido_id, produto_id, produto_slug, produto_ref, produto_nome, quantidade, preco_unitario, subtotal_item
        ) VALUES (
            :pedido_id, :produto_id, :produto_slug, :produto_ref, :produto_nome, :quantidade, :preco_unitario, :subtotal_item
        )"
    );
    $stmtAtualizarEstoque = $pdo->prepare(
        "UPDATE produtos
         SET estoque_quantidade = :estoque_quantidade,
             atualizado_por_usuario_id = :usuario_id
         WHERE id = :produto_id
         LIMIT 1"
    );

    foreach ($itensNormalizados as $item) {
        $stmtPedidoItem->execute([
            "pedido_id" => $pedidoId,
            "produto_id" => $item["produto_id"],
            "produto_slug" => $item["produto_slug"] !== "" ? $item["produto_slug"] : null,
            "produto_ref" => $item["produto_ref"] !== "" ? $item["produto_ref"] : null,
            "produto_nome" => $item["produto_nome"],
            "quantidade" => $item["quantidade"],
            "preco_unitario" => $item["preco_unitario"],
            "subtotal_item" => $item["subtotal_item"],
        ]);

        $stmtAtualizarEstoque->execute([
            "estoque_quantidade" => $item["estoque_depois"],
            "usuario_id" => $usuarioId,
            "produto_id" => $item["produto_id"],
        ]);

        registrarMovimentacaoPedidoEstoque(
            $pdo,
            (int) $item["produto_id"],
            $pedidoId,
            $usuarioId,
            (int) $item["quantidade"],
            (int) $item["estoque_antes"],
            (int) $item["estoque_depois"],
            "Baixa de estoque na finalizacao do pedido {$numeroPedido}."
        );
    }

    $stmtLimparCarrinho = $pdo->prepare("DELETE FROM carrinho_itens WHERE usuario_id = :usuario_id");
    $stmtLimparCarrinho->execute(["usuario_id" => $usuarioId]);

    $pdo->commit();

    $resultadoSms = enviarSmsPedido($numeroPedido, $total, $endereco);

    if (!($resultadoSms["ok"] ?? false)) {
        error_log("SMS do pedido {$numeroPedido} nao enviado: " . (string) ($resultadoSms["motivo"] ?? $resultadoSms["erro"] ?? "falha desconhecida"));
    }

    responderJsonCheckout([
        "sucesso" => true,
        "pedido_id" => $pedidoId,
        "numero_pedido" => $numeroPedido,
        "total" => $total,
        "sms_enviado" => (bool) ($resultadoSms["ok"] ?? false),
    ], 201);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responderJsonCheckout(["erro" => $exception->getMessage() !== "" ? $exception->getMessage() : "Nao foi possivel finalizar o pedido."], 422);
}
