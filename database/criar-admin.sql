-- Execute este arquivo depois de rodar database/migrate.php
USE imperio_do_choco;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    papel VARCHAR(20) NOT NULL DEFAULT 'cliente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO usuarios (nome, email, senha_hash, papel)
VALUES (
    'Administrador',
    'admin@imperiodochocolate.com',
    '$2y$10$RjXUJWcAQblmiZxivjb0peFRMrSuH9PRxK2j37QHY6bBwmiO/z3vG',
    'admin'
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    senha_hash = VALUES(senha_hash),
    papel = VALUES(papel);
