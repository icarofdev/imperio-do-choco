<?php
declare(strict_types=1);

carregarVariaveisAmbiente(dirname(__DIR__) . "/.env");

$databaseConfig = obterConfiguracaoBanco();
$host = $databaseConfig["host"];
$port = $databaseConfig["port"];
$dbname = $databaseConfig["dbname"];
$user = $databaseConfig["user"];
$pass = $databaseConfig["pass"];
$charset = $databaseConfig["charset"];
$appEnv = strtolower(lerVariavelAmbiente("APP_ENV", "development") ?? "development");
$pdo = null;
$databaseConnectionError = "";
$databaseConnectionErrorCode = "";

if (in_array($appEnv, ["prod", "production"], true) && ($user === "root" || $pass === "")) {
    $databaseConnectionError = "Configuracao de banco insegura para producao.";
} else {
    try {
        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];
        $sslCa = prepararCertificadoCaBanco();

        if ($sslCa !== null && defined("PDO::MYSQL_ATTR_SSL_CA")) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        }

        if ($sslCa !== null && defined("PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT")) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}",
            $user,
            $pass,
            $pdoOptions
        );
    } catch (PDOException $exception) {
        $databaseConnectionError = "Erro na conexao com o banco de dados.";
        $databaseConnectionErrorCode = (string) $exception->getCode();
        registrarErroConexaoBanco($exception, $appEnv);
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

        $valorExistente = $_ENV[$chave] ?? $_SERVER[$chave] ?? getenv($chave);

        if ($valorExistente === false || $valorExistente === null) {
            putenv("{$chave}={$valor}");
            $_ENV[$chave] = $valor;
            $_SERVER[$chave] = $valor;
        }
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
    $charset = lerVariavelAmbiente("DB_CHARSET", "utf8mb4") ?? "utf8mb4";

    if (preg_match('/^[A-Za-z0-9_]+$/', $charset) !== 1) {
        $charset = "utf8mb4";
    }

    return [
        "host" => lerVariavelAmbiente("DB_HOST", "127.0.0.1"),
        "port" => lerVariavelAmbiente("DB_PORT", "3307"),
        "dbname" => lerVariavelAmbiente("DB_DATABASE", lerVariavelAmbiente("DB_NAME", "imperio_do_choco")),
        "user" => lerVariavelAmbiente("DB_USERNAME", lerVariavelAmbiente("DB_USER", "root")),
        "pass" => lerVariavelAmbiente("DB_PASSWORD", lerVariavelAmbiente("DB_PASS", "")),
        "charset" => $charset,
    ];
}

function prepararCertificadoCaBanco(): ?string
{
    $caminho = lerVariavelAmbiente("DB_SSL_CA");

    if ($caminho !== null) {
        $caminho = trim($caminho);

        if ($caminho !== "" && is_file($caminho)) {
            return realpath($caminho) ?: $caminho;
        }
    }

    $certificadoBase64 = lerVariavelAmbiente("DB_SSL_CA_BASE64");

    if ($certificadoBase64 === null) {
        return null;
    }

    $certificado = base64_decode(preg_replace('/\s+/', '', $certificadoBase64) ?? "", true);

    if (!is_string($certificado) || !str_contains($certificado, "BEGIN CERTIFICATE")) {
        throw new RuntimeException("O certificado CA do banco esta invalido.");
    }

    $arquivo = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . "velle-dulcis-db-ca-"
        . substr(hash("sha256", $certificado), 0, 16)
        . ".pem";

    if (!is_file($arquivo) && file_put_contents($arquivo, $certificado, LOCK_EX) === false) {
        throw new RuntimeException("Nao foi possivel preparar o certificado CA do banco.");
    }

    return $arquivo;
}

function registrarErroConexaoBanco(PDOException $exception, string $appEnv): void
{
    $codigo = (string) $exception->getCode();
    $mensagem = in_array($appEnv, ["prod", "production"], true)
        ? "detalhes suprimidos em producao"
        : preg_replace('/[\r\n]+/', ' ', $exception->getMessage());

    error_log(sprintf(
        "[Velle Dulcis][database] Falha PDO (codigo=%s, %s)",
        $codigo !== "" ? $codigo : "sem-codigo",
        $mensagem !== "" ? $mensagem : "sem-detalhes"
    ));
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

function schemaSessoesDisponivel(PDO $pdo): bool
{
    return tabelasBancoDisponiveis($pdo, ["sessoes"]);
}
