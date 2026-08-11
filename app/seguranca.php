<?php
declare(strict_types=1);

final class BancoSessaoHandler implements SessionHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private int $tempoDeVida
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT dados FROM sessoes WHERE id = :id AND expira_em >= :agora LIMIT 1"
            );
            $stmt->execute([
                "id" => $id,
                "agora" => time(),
            ]);
            $dados = $stmt->fetchColumn();

            return is_string($dados) ? $dados : "";
        } catch (PDOException $exception) {
            error_log("[Velle Dulcis][session] Falha ao ler sessao (codigo=" . $exception->getCode() . ").");
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessoes (id, dados, ip, user_agent, expira_em)
                 VALUES (:id, :dados, :ip, :user_agent, :expira_em)
                 ON DUPLICATE KEY UPDATE
                    dados = VALUES(dados),
                    ip = VALUES(ip),
                    user_agent = VALUES(user_agent),
                    expira_em = VALUES(expira_em)"
            );

            return $stmt->execute([
                "id" => $id,
                "dados" => $data,
                "ip" => substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45) ?: null,
                "user_agent" => substr((string) ($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255) ?: null,
                "expira_em" => time() + $this->tempoDeVida,
            ]);
        } catch (PDOException $exception) {
            error_log("[Velle Dulcis][session] Falha ao salvar sessao (codigo=" . $exception->getCode() . ").");
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessoes WHERE id = :id");
            return $stmt->execute(["id" => $id]);
        } catch (PDOException $exception) {
            error_log("[Velle Dulcis][session] Falha ao destruir sessao (codigo=" . $exception->getCode() . ").");
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessoes WHERE expira_em < :agora");
            $stmt->execute(["agora" => time()]);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            error_log("[Velle Dulcis][session] Falha ao limpar sessoes (codigo=" . $exception->getCode() . ").");
            return false;
        }
    }
}

function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $httpsAtivo = requisicaoHttpsAtiva();
    $tempoDeVida = max(900, (int) obterVariavelAmbienteSessao("SESSION_LIFETIME", "7200"));
    $driver = strtolower(obterVariavelAmbienteSessao(
        "SESSION_DRIVER",
        ambienteVercel() ? "database" : "files"
    ));

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.gc_maxlifetime", (string) $tempoDeVida);

    if ($driver === "database") {
        configurarSessoesNoBanco($tempoDeVida);
    } elseif ($driver === "files") {
        configurarSessoesEmArquivos();
    } else {
        throw new RuntimeException("SESSION_DRIVER deve ser 'files' ou 'database'.");
    }

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => $httpsAtivo,
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    if (!session_start()) {
        throw new RuntimeException("Nao foi possivel iniciar uma sessao segura.");
    }
}

function ambienteVercel(): bool
{
    $valor = $_ENV["VERCEL"] ?? $_SERVER["VERCEL"] ?? getenv("VERCEL");
    return in_array(strtolower((string) $valor), ["1", "true", "yes"], true);
}

function requisicaoHttpsAtiva(): bool
{
    $https = strtolower((string) ($_SERVER["HTTPS"] ?? ""));
    $protocoloEncaminhado = strtolower(trim(explode(",", (string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ""))[0]));

    return ($https !== "" && $https !== "off") || $protocoloEncaminhado === "https";
}

function obterVariavelAmbienteSessao(string $nome, string $padrao): string
{
    $valor = $_ENV[$nome] ?? $_SERVER[$nome] ?? getenv($nome);

    return $valor === false || $valor === null || $valor === "" ? $padrao : (string) $valor;
}

function configurarSessoesEmArquivos(): void
{
    $diretorioBase = ambienteVercel()
        ? sys_get_temp_dir()
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . ".runtime";
    $diretorioSessoes = rtrim($diretorioBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "sessions";

    if (!is_dir($diretorioSessoes) && !mkdir($diretorioSessoes, 0770, true) && !is_dir($diretorioSessoes)) {
        throw new RuntimeException("Nao foi possivel preparar o diretorio privado de sessoes.");
    }

    session_save_path($diretorioSessoes);
}

function configurarSessoesNoBanco(int $tempoDeVida): void
{
    global $pdo;

    require_once __DIR__ . "/conexao.php";

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException("O banco de dados de sessoes esta indisponivel.");
    }

    if (!schemaSessoesDisponivel($pdo)) {
        throw new RuntimeException("A tabela de sessoes ainda nao foi migrada.");
    }

    session_set_save_handler(new BancoSessaoHandler($pdo, $tempoDeVida), true);
}

function regenerarSessaoAutenticada(): void
{
    iniciarSessaoSegura();
    session_regenerate_id(true);
}

function obterTokenCsrf(): string
{
    iniciarSessaoSegura();

    if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function tokenCsrfHtml(): string
{
    return htmlspecialchars(obterTokenCsrf(), ENT_QUOTES, "UTF-8");
}

function obterTokenCsrfRequisicao(array $dados = []): string
{
    $cabecalho = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";

    if (is_string($cabecalho) && trim($cabecalho) !== "") {
        return trim($cabecalho);
    }

    $postToken = $_POST["csrf_token"] ?? null;

    if (is_string($postToken) && trim($postToken) !== "") {
        return trim($postToken);
    }

    $jsonToken = $dados["csrf_token"] ?? null;

    return is_string($jsonToken) ? trim($jsonToken) : "";
}

function tokenCsrfValido(string $token): bool
{
    iniciarSessaoSegura();

    return isset($_SESSION["csrf_token"])
        && is_string($_SESSION["csrf_token"])
        && $token !== ""
        && hash_equals($_SESSION["csrf_token"], $token);
}
