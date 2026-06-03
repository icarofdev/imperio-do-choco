<?php
declare(strict_types=1);

carregarVariaveisAmbiente(dirname(__DIR__) . "/.env");

$databaseConfig = obterConfiguracaoBanco();
$host = $databaseConfig["host"];
$port = $databaseConfig["port"];
$dbname = $databaseConfig["dbname"];
$user = $databaseConfig["user"];
$pass = $databaseConfig["pass"];
$appEnv = strtolower(lerVariavelAmbiente("APP_ENV", "development") ?? "development");
$pdo = null;
$databaseConnectionError = "";

if (in_array($appEnv, ["prod", "production"], true) && ($user === "root" || $pass === "")) {
    $databaseConnectionError = "Configuracao de banco insegura para producao.";
} else {
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        $databaseConnectionError = "Erro na conexao com o banco de dados.";
    }
}

function carregarVariaveisAmbiente(string $arquivo): void
{
    static $arquivosCarregados = [];

    if (isset($arquivosCarregados[$arquivo]) || !is_file($arquivo)) {
        return;
    }

    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($linhas === false) {
        return;
    }

    foreach ($linhas as $linha) {
        $linha = trim($linha);

        if ($linha === "" || str_starts_with($linha, "#") || !str_contains($linha, "=")) {
            continue;
        }

        [$chave, $valor] = explode("=", $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);

        if ($chave === "") {
            continue;
        }

        if (
            (str_starts_with($valor, "\"") && str_ends_with($valor, "\"")) ||
            (str_starts_with($valor, "'") && str_ends_with($valor, "'"))
        ) {
            $valor = substr($valor, 1, -1);
        }

        if (getenv($chave) === false) {
            putenv("{$chave}={$valor}");
        }

        $_ENV[$chave] = $valor;
        $_SERVER[$chave] = $valor;
    }

    $arquivosCarregados[$arquivo] = true;
}

function lerVariavelAmbiente(string $nome, ?string $padrao = null): ?string
{
    $valor = $_ENV[$nome] ?? $_SERVER[$nome] ?? getenv($nome);

    if ($valor === false || $valor === null || $valor === "") {
        return $padrao;
    }

    return (string) $valor;
}

function obterConfiguracaoBanco(): array
{
    return [
        "host" => lerVariavelAmbiente("DB_HOST", "127.0.0.1"),
        "port" => lerVariavelAmbiente("DB_PORT", "3306"),
        "dbname" => lerVariavelAmbiente("DB_NAME", "imperio_do_choco"),
        "user" => lerVariavelAmbiente("DB_USER", "root"),
        "pass" => lerVariavelAmbiente("DB_PASS", ""),
    ];
}

function bancoDeDadosDisponivel($pdo): bool
{
    return $pdo instanceof PDO;
}

function listarTabelasExistentes(PDO $pdo): array
{
    static $cache = [];

    $cacheKey = spl_object_hash($pdo);

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
    $tabelas = array_map(
        static fn (array $linha): string => (string) ($linha["TABLE_NAME"] ?? ""),
        $stmt->fetchAll()
    );

    $cache[$cacheKey] = array_values(array_filter($tabelas));

    return $cache[$cacheKey];
}

function listarTabelasAusentes(PDO $pdo, array $tabelas): array
{
    $existentes = array_flip(listarTabelasExistentes($pdo));

    return array_values(array_filter(
        array_map(static fn ($tabela): string => trim((string) $tabela), $tabelas),
        static fn (string $tabela): bool => $tabela !== "" && !isset($existentes[$tabela])
    ));
}

function tabelasBancoDisponiveis(PDO $pdo, array $tabelas): bool
{
    return listarTabelasAusentes($pdo, $tabelas) === [];
}

function schemaUsuariosDisponivel(PDO $pdo): bool
{
    return tabelasBancoDisponiveis($pdo, ["usuarios"]);
}

function schemaRecuperacaoSenhaDisponivel(PDO $pdo): bool
{
    return tabelasBancoDisponiveis($pdo, ["usuarios", "recuperacoes_senha"]);
}

function schemaProdutosDisponivel(PDO $pdo): bool
{
    return tabelasBancoDisponiveis($pdo, ["produtos"]);
}

function schemaCarrinhoDisponivel(PDO $pdo): bool
{
    return tabelasBancoDisponiveis($pdo, ["usuarios", "produtos", "carrinho_itens"]);
}

function schemaComercialDisponivel(PDO $pdo): bool
{
    return tabelasBancoDisponiveis(
        $pdo,
        ["usuarios", "produtos", "enderecos", "pedidos", "pedido_itens", "estoque_movimentacoes"]
    );
}
