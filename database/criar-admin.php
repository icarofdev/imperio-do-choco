<?php
declare(strict_types=1);

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/../app/conexao.php";

$nome = trim(lerVariavelAmbiente("ADMIN_NAME", "Administrador") ?? "Administrador");
$email = mb_strtolower(trim(lerVariavelAmbiente("ADMIN_EMAIL", "") ?? ""), "UTF-8");
$senha = (string) (lerVariavelAmbiente("ADMIN_PASSWORD", "") ?? "");

if (!bancoDeDadosDisponivel($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Nao foi possivel conectar ao banco de dados.\n");
    exit(1);
}

if (!schemaUsuariosDisponivel($pdo)) {
    fwrite(STDERR, "A tabela de usuarios nao existe. Execute database/migrate.php primeiro.\n");
    exit(1);
}

if ($nome === "" || mb_strlen($nome) > 120) {
    fwrite(STDERR, "ADMIN_NAME deve ter entre 1 e 120 caracteres.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    fwrite(STDERR, "Defina um ADMIN_EMAIL valido no ambiente.\n");
    exit(1);
}

if (mb_strlen($senha) < 12) {
    fwrite(STDERR, "ADMIN_PASSWORD deve ter pelo menos 12 caracteres.\n");
    exit(1);
}

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

if (!is_string($senhaHash)) {
    fwrite(STDERR, "Nao foi possivel gerar o hash da senha administrativa.\n");
    exit(1);
}

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

echo "Administrador criado ou atualizado com sucesso.\n";
