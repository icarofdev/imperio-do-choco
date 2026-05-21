<?php
declare(strict_types=1);

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

$senha = (string) ($argv[1] ?? "");

if ($senha === "") {
    fwrite(STDERR, "Uso: C:\\xampp\\php\\php.exe gerar-senha.php \"sua-senha\"\n");
    exit(1);
}

echo password_hash($senha, PASSWORD_DEFAULT) . PHP_EOL;
