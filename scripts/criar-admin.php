<?php
declare(strict_types=1);

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . "/app/conexao.php";

if (!bancoDeDadosDisponivel($pdo)) {
    fwrite(STDERR, "Não foi possível conectar ao banco. Verifique o arquivo .env.\n");
    exit(1);
}

if (!schemaUsuariosDisponivel($pdo)) {
    fwrite(STDERR, "A tabela de usuários não existe. Execute database/migrate.php primeiro.\n");
    exit(1);
}

$nome = trim((string) (getenv("ADMIN_NAME") ?: "Administrador"));
$email = mb_strtolower(trim((string) (getenv("ADMIN_EMAIL") ?: "")), "UTF-8");
$senha = (string) (getenv("ADMIN_PASSWORD") ?: "");

if ($nome === "") {
    fwrite(STDERR, "ADMIN_NAME não pode ser vazio.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Defina ADMIN_EMAIL com um endereço válido.\n");
    exit(1);
}

if (strlen($senha) < 12) {
    fwrite(STDERR, "ADMIN_PASSWORD deve ter pelo menos 12 caracteres.\n");
    exit(1);
}

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

if ($senhaHash === false) {
    fwrite(STDERR, "Não foi possível gerar o hash da senha.\n");
    exit(1);
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha_hash, papel)
         VALUES (:nome, :email, :senha_hash, 'admin')
         ON DUPLICATE KEY UPDATE
            nome = VALUES(nome),
            senha_hash = VALUES(senha_hash),
            papel = 'admin'"
    );
    $stmt->execute([
        "nome" => $nome,
        "email" => $email,
        "senha_hash" => $senhaHash,
    ]);
} catch (PDOException $exception) {
    fwrite(STDERR, "Não foi possível criar ou atualizar o administrador.\n");
    error_log("[Velle Dulcis][admin-cli] " . $exception->getMessage());
    exit(1);
}

fwrite(STDOUT, "Administrador criado ou atualizado com sucesso.\n");
