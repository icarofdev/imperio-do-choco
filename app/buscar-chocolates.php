<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

function formatarPesoProduto(string $peso, ?int $pesoGramas): string
{
    $peso = trim($peso);

    if ($peso !== "") {
        return $peso;
    }

    if ($pesoGramas !== null && $pesoGramas > 0) {
        return "{$pesoGramas}g";
    }

    return "";
}

if (!bancoDeDadosDisponivel($pdo) || !schemaProdutosDisponivel($pdo)) {
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
        $stmt = $pdo->query(
        "SELECT id, slug, nome, descricao, preco, categoria, tipo, peso, peso_gramas, ref, destaque, img, imagens, estoque_quantidade
         FROM produtos
         WHERE ativo = 1 AND deleted_at IS NULL
         ORDER BY id DESC"
    );
    $registros = $stmt->fetchAll();

    $produtos = array_map(static function (array $registro): array {
        $galeria = [];
        $galeriaBruta = $registro["imagens"] ?? "[]";

        if (is_string($galeriaBruta) && $galeriaBruta !== "") {
            $galeriaDecodificada = json_decode($galeriaBruta, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($galeriaDecodificada)) {
                $galeria = array_values(array_filter($galeriaDecodificada, "is_string"));
            }
        }

        $pesoGramas = array_key_exists("peso_gramas", $registro) && $registro["peso_gramas"] !== null
            ? (int) $registro["peso_gramas"]
            : null;

        return [
            "id" => (int) ($registro["id"] ?? 0),
            "slug" => (string) ($registro["slug"] ?? ""),
            "img" => (string) ($registro["img"] ?? ""),
            "nome" => (string) ($registro["nome"] ?? "Chocolate sem nome"),
            "preco" => (float) ($registro["preco"] ?? 0),
            "categoria" => (string) ($registro["categoria"] ?? $registro["tipo"] ?? "Chocolate"),
            "tipo" => (string) ($registro["tipo"] ?? $registro["categoria"] ?? "Chocolate"),
            "peso" => formatarPesoProduto((string) ($registro["peso"] ?? ""), $pesoGramas),
            "peso_gramas" => $pesoGramas,
            "ref" => (string) ($registro["ref"] ?? ""),
            "destaque" => (string) ($registro["destaque"] ?? "Selecao da casa"),
            "descricao" => (string) ($registro["descricao"] ?? ""),
            "imagens" => $galeria,
            "estoque_quantidade" => (int) ($registro["estoque_quantidade"] ?? 0),
        ];
    }, $registros);

    echo json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $exception) {
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
