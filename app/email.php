<?php
declare(strict_types=1);

if (!function_exists("lerVariavelAmbiente")) {
    require_once __DIR__ . "/conexao.php";
}

function enviarEmailTransacional(string $paraEmail, string $paraNome, string $assunto, string $html, string $texto): array
{
    $paraEmail = trim($paraEmail);

    if (!filter_var($paraEmail, FILTER_VALIDATE_EMAIL)) {
        return ["sucesso" => false, "mensagem" => "Destinatario de email invalido."];
    }

    $provider = strtolower(trim((string) lerVariavelAmbiente("EMAIL_PROVIDER", "")));

    if ($provider === "") {
        $appEnv = strtolower((string) lerVariavelAmbiente("APP_ENV", "development"));
        $provider = in_array($appEnv, ["prod", "production"], true) ? "smtp" : "log";
    }

    return match ($provider) {
        "brevo" => enviarEmailBrevo($paraEmail, $paraNome, $assunto, $html, $texto),
        "smtp" => enviarEmailSmtp($paraEmail, $paraNome, $assunto, $html, $texto),
        "log" => registrarEmailEmLog($paraEmail, $paraNome, $assunto, $texto),
        default => ["sucesso" => false, "mensagem" => "Provedor de email nao configurado."],
    };
}

function obterRemetenteEmail(): array
{
    return [
        "email" => trim((string) lerVariavelAmbiente("EMAIL_FROM", "contato@velledulcis.com")),
        "nome" => trim((string) lerVariavelAmbiente("EMAIL_FROM_NAME", "Velle Dulcis")),
    ];
}

function registrarEmailEmLog(string $paraEmail, string $paraNome, string $assunto, string $texto): array
{
    $diretorio = dirname(__DIR__) . "/logs";

    if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
        return ["sucesso" => false, "mensagem" => "Nao foi possivel criar o diretorio de logs."];
    }

    $conteudo = implode(PHP_EOL, [
        "----- " . date("Y-m-d H:i:s") . " -----",
        "Para: " . ($paraNome !== "" ? "{$paraNome} <{$paraEmail}>" : $paraEmail),
        "Assunto: {$assunto}",
        "",
        $texto,
        "",
    ]);

    $gravou = file_put_contents($diretorio . "/emails.log", $conteudo, FILE_APPEND | LOCK_EX);

    return [
        "sucesso" => $gravou !== false,
        "mensagem" => $gravou !== false ? "Email registrado em log." : "Nao foi possivel registrar o email em log.",
    ];
}

function enviarEmailBrevo(string $paraEmail, string $paraNome, string $assunto, string $html, string $texto): array
{
    $apiKey = trim((string) lerVariavelAmbiente("BREVO_API_KEY", ""));

    if ($apiKey === "") {
        return ["sucesso" => false, "mensagem" => "BREVO_API_KEY nao configurada."];
    }

    if (!function_exists("curl_init")) {
        return ["sucesso" => false, "mensagem" => "A extensao cURL do PHP precisa estar ativa para usar a Brevo API."];
    }

    $remetente = obterRemetenteEmail();
    $payload = [
        "sender" => [
            "email" => $remetente["email"],
            "name" => $remetente["nome"],
        ],
        "to" => [[
            "email" => $paraEmail,
            "name" => $paraNome !== "" ? $paraNome : $paraEmail,
        ]],
        "subject" => $assunto,
        "htmlContent" => $html,
        "textContent" => $texto,
    ];

    $curl = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "api-key: {$apiKey}",
            "content-type: application/json",
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 20,
    ]);

    $resposta = curl_exec($curl);
    $erroCurl = curl_error($curl);
    $codigoHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($resposta === false || $codigoHttp < 200 || $codigoHttp >= 300) {
        return [
            "sucesso" => false,
            "mensagem" => $erroCurl !== "" ? $erroCurl : "Brevo retornou HTTP {$codigoHttp}.",
        ];
    }

    return ["sucesso" => true, "mensagem" => "Email enviado pela Brevo."];
}

function enviarEmailSmtp(string $paraEmail, string $paraNome, string $assunto, string $html, string $texto): array
{
    $host = trim((string) lerVariavelAmbiente("SMTP_HOST", ""));
    $port = (int) lerVariavelAmbiente("SMTP_PORT", "587");
    $usuario = trim((string) lerVariavelAmbiente("SMTP_USER", ""));
    $senha = (string) lerVariavelAmbiente("SMTP_PASS", "");
    $criptografia = strtolower(trim((string) lerVariavelAmbiente("SMTP_ENCRYPTION", "tls")));
    $remetente = obterRemetenteEmail();

    if ($host === "" || $remetente["email"] === "") {
        return ["sucesso" => false, "mensagem" => "SMTP_HOST e EMAIL_FROM precisam estar configurados."];
    }

    try {
        $destino = ($criptografia === "ssl" ? "ssl://" : "") . $host . ":" . $port;
        $socket = stream_socket_client($destino, $codigoErro, $mensagemErro, 20, STREAM_CLIENT_CONNECT);

        if (!$socket) {
            return ["sucesso" => false, "mensagem" => "Nao foi possivel conectar ao SMTP: {$mensagemErro}."];
        }

        stream_set_timeout($socket, 20);
        smtpEsperarResposta($socket, [220]);

        $ehloHost = parse_url((string) lerVariavelAmbiente("APP_URL", "http://localhost"), PHP_URL_HOST) ?: "localhost";
        smtpEnviarComando($socket, "EHLO {$ehloHost}", [250]);

        if ($criptografia === "tls" || $criptografia === "starttls") {
            smtpEnviarComando($socket, "STARTTLS", [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ["sucesso" => false, "mensagem" => "Nao foi possivel iniciar TLS no SMTP."];
            }

            smtpEnviarComando($socket, "EHLO {$ehloHost}", [250]);
        }

        if ($usuario !== "" || $senha !== "") {
            smtpEnviarComando($socket, "AUTH LOGIN", [334]);
            smtpEnviarComando($socket, base64_encode($usuario), [334]);
            smtpEnviarComando($socket, base64_encode($senha), [235]);
        }

        smtpEnviarComando($socket, "MAIL FROM:<{$remetente["email"]}>", [250]);
        smtpEnviarComando($socket, "RCPT TO:<{$paraEmail}>", [250, 251]);
        smtpEnviarComando($socket, "DATA", [354]);

        $mensagem = construirMensagemSmtp($remetente, $paraEmail, $paraNome, $assunto, $html, $texto);
        fwrite($socket, prepararDadosSmtp($mensagem) . "\r\n.\r\n");
        smtpEsperarResposta($socket, [250]);
        smtpEnviarComando($socket, "QUIT", [221]);
        fclose($socket);

        return ["sucesso" => true, "mensagem" => "Email enviado por SMTP."];
    } catch (Throwable $exception) {
        if (isset($socket) && is_resource($socket)) {
            fclose($socket);
        }

        return ["sucesso" => false, "mensagem" => $exception->getMessage()];
    }
}

function smtpEnviarComando($socket, string $comando, array $codigosEsperados): void
{
    fwrite($socket, $comando . "\r\n");
    smtpEsperarResposta($socket, $codigosEsperados);
}

function smtpEsperarResposta($socket, array $codigosEsperados): void
{
    $linhas = [];

    while (($linha = fgets($socket, 515)) !== false) {
        $linhas[] = rtrim($linha, "\r\n");

        if (preg_match('/^\d{3}\s/', $linha) === 1) {
            break;
        }
    }

    if ($linhas === []) {
        throw new RuntimeException("SMTP nao respondeu.");
    }

    $codigo = (int) substr($linhas[0], 0, 3);

    if (!in_array($codigo, $codigosEsperados, true)) {
        throw new RuntimeException("SMTP respondeu {$codigo}.");
    }
}

function construirMensagemSmtp(array $remetente, string $paraEmail, string $paraNome, string $assunto, string $html, string $texto): string
{
    $boundary = "=_velle_" . bin2hex(random_bytes(12));
    $headers = [
        "Date: " . date(DATE_RFC2822),
        "From: " . formatarEnderecoEmail($remetente["email"], $remetente["nome"]),
        "To: " . formatarEnderecoEmail($paraEmail, $paraNome),
        "Subject: " . codificarCabecalhoEmail($assunto),
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
    ];

    return implode("\r\n", $headers)
        . "\r\n\r\n--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . normalizarQuebrasEmail($texto)
        . "\r\n\r\n--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . normalizarQuebrasEmail($html)
        . "\r\n\r\n--{$boundary}--";
}

function prepararDadosSmtp(string $mensagem): string
{
    $linhas = explode("\n", str_replace(["\r\n", "\r"], "\n", $mensagem));

    foreach ($linhas as &$linha) {
        $linha = rtrim($linha, "\n");

        if (str_starts_with($linha, ".")) {
            $linha = "." . $linha;
        }
    }
    unset($linha);

    return implode("\r\n", $linhas);
}

function normalizarQuebrasEmail(string $conteudo): string
{
    return str_replace(["\r\n", "\r"], "\n", $conteudo);
}

function formatarEnderecoEmail(string $email, string $nome = ""): string
{
    $nome = trim(preg_replace('/[\r\n]+/', " ", $nome) ?? "");

    if ($nome === "") {
        return "<{$email}>";
    }

    return codificarCabecalhoEmail($nome) . " <{$email}>";
}

function codificarCabecalhoEmail(string $valor): string
{
    $valor = trim(preg_replace('/[\r\n]+/', " ", $valor) ?? "");

    return "=?UTF-8?B?" . base64_encode($valor) . "?=";
}
