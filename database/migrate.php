<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/app/conexao.php";

header("Content-Type: text/plain; charset=UTF-8");

if (!bancoDeDadosDisponivel($pdo)) {
    echo "ERRO: nao foi possivel conectar ao banco para executar as migracoes.\n";
    echo $databaseConnectionError !== "" ? $databaseConnectionError . "\n" : "";
    exit(1);
}

echo "Iniciando migracoes do schema do projeto...\n";

garantirTabelaMigracoes($pdo);

$migracoes = [
    [
        "id" => "001_usuarios_schema",
        "descricao" => "Cria e endurece a tabela de usuarios",
        "executar" => static function (PDO $pdo): void {
            migrarUsuarios($pdo);
        },
    ],
    [
        "id" => "002_produtos_schema",
        "descricao" => "Cria e endurece a tabela de produtos",
        "executar" => static function (PDO $pdo): void {
            migrarProdutos($pdo);
        },
    ],
    [
        "id" => "003_carrinho_schema",
        "descricao" => "Cria e endurece a tabela de carrinho com relacoes",
        "executar" => static function (PDO $pdo): void {
            migrarCarrinho($pdo);
        },
    ],
    [
        "id" => "004_comercial_schema",
        "descricao" => "Cria enderecos, pedidos, itens do pedido e movimentacoes de estoque",
        "executar" => static function (PDO $pdo): void {
            migrarComercial($pdo);
        },
    ],
    [
        "id" => "005_recuperacao_senha_schema",
        "descricao" => "Cria a tabela de tokens para recuperacao de senha",
        "executar" => static function (PDO $pdo): void {
            migrarRecuperacaoSenha($pdo);
        },
    ],
];

foreach ($migracoes as $migracao) {
    $id = (string) $migracao["id"];
    $descricao = (string) $migracao["descricao"];

    if (migracaoJaAplicada($pdo, $id)) {
        echo "[ok] {$id} - {$descricao} (ja aplicada)\n";
        continue;
    }

    echo "[..] {$id} - {$descricao}\n";

    try {
        $executarMigracao = $migracao["executar"];
        $executarMigracao($pdo);
        registrarMigracao($pdo, $id, $descricao);
        echo "[ok] {$id} concluida\n";
    } catch (Throwable $exception) {
        echo "[erro] {$id} falhou: " . $exception->getMessage() . "\n";
        exit(1);
    }
}

echo "Migracoes finalizadas com sucesso.\n";

function garantirTabelaMigracoes(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            id VARCHAR(120) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            aplicada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function migracaoJaAplicada(PDO $pdo, string $id): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM schema_migrations WHERE id = :id LIMIT 1");
    $stmt->execute(["id" => $id]);

    return (bool) $stmt->fetchColumn();
}

function registrarMigracao(PDO $pdo, string $id, string $descricao): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO schema_migrations (id, descricao) VALUES (:id, :descricao)"
    );
    $stmt->execute([
        "id" => $id,
        "descricao" => $descricao,
    ]);
}

function tabelaExiste(PDO $pdo, string $tabela): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela
         LIMIT 1"
    );
    $stmt->execute(["tabela" => $tabela]);

    return (bool) $stmt->fetchColumn();
}

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND COLUMN_NAME = :coluna
         LIMIT 1"
    );
    $stmt->execute([
        "tabela" => $tabela,
        "coluna" => $coluna,
    ]);

    return (bool) $stmt->fetchColumn();
}

function indiceExiste(PDO $pdo, string $tabela, string $indice): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND INDEX_NAME = :indice
         LIMIT 1"
    );
    $stmt->execute([
        "tabela" => $tabela,
        "indice" => $indice,
    ]);

    return (bool) $stmt->fetchColumn();
}

function constraintExiste(PDO $pdo, string $tabela, string $constraint): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND CONSTRAINT_NAME = :constraint
         LIMIT 1"
    );
    $stmt->execute([
        "tabela" => $tabela,
        "constraint" => $constraint,
    ]);

    return (bool) $stmt->fetchColumn();
}

function foreignKeyExiste(PDO $pdo, string $tabela, string $constraint): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND CONSTRAINT_NAME = :constraint
         LIMIT 1"
    );
    $stmt->execute([
        "tabela" => $tabela,
        "constraint" => $constraint,
    ]);

    return (bool) $stmt->fetchColumn();
}

function adicionarColunaSeAusente(PDO $pdo, string $tabela, string $coluna, string $definicao): void
{
    if (colunaExiste($pdo, $tabela, $coluna)) {
        return;
    }

    $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN {$definicao}");
}

function garantirIndice(PDO $pdo, string $tabela, array $nomesPossiveis, string $sqlCriacao): void
{
    foreach ($nomesPossiveis as $nome) {
        if (indiceExiste($pdo, $tabela, $nome)) {
            return;
        }
    }

    $pdo->exec($sqlCriacao);
}

function garantirCheck(PDO $pdo, string $tabela, string $constraint, string $sqlCriacao): void
{
    if (constraintExiste($pdo, $tabela, $constraint)) {
        return;
    }

    $pdo->exec($sqlCriacao);
}

function garantirForeignKey(PDO $pdo, string $tabela, string $constraint, string $sqlCriacao): void
{
    if (foreignKeyExiste($pdo, $tabela, $constraint)) {
        return;
    }

    $pdo->exec($sqlCriacao);
}

function migrarUsuarios(PDO $pdo): void
{
    if (!tabelaExiste($pdo, "usuarios")) {
        $pdo->exec(
            "CREATE TABLE usuarios (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nome VARCHAR(120) NOT NULL,
                email VARCHAR(150) NOT NULL,
                senha_hash VARCHAR(255) NOT NULL,
                papel ENUM('admin', 'cliente') NOT NULL DEFAULT 'cliente',
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY usuarios_email_unique (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    adicionarColunaSeAusente($pdo, "usuarios", "nome", "nome VARCHAR(120) DEFAULT NULL AFTER id");
    adicionarColunaSeAusente($pdo, "usuarios", "email", "email VARCHAR(150) DEFAULT NULL AFTER nome");
    adicionarColunaSeAusente($pdo, "usuarios", "senha_hash", "senha_hash VARCHAR(255) DEFAULT NULL AFTER email");
    adicionarColunaSeAusente($pdo, "usuarios", "papel", "papel VARCHAR(20) DEFAULT 'cliente' AFTER senha_hash");
    adicionarColunaSeAusente($pdo, "usuarios", "criado_em", "criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    adicionarColunaSeAusente(
        $pdo,
        "usuarios",
        "atualizado_em",
        "atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    );

    $pdo->exec(
        "UPDATE usuarios
         SET nome = CONCAT('Usuario ', id)
         WHERE TRIM(COALESCE(nome, '')) = ''"
    );
    $pdo->exec(
        "UPDATE usuarios
         SET email = CONCAT('usuario-', id, '@ajuste.local')
         WHERE TRIM(COALESCE(email, '')) = ''"
    );
    $senhaTemporariaHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmtSenha = $pdo->prepare(
        "UPDATE usuarios
         SET senha_hash = :senha_hash
         WHERE senha_hash IS NULL OR TRIM(COALESCE(senha_hash, '')) = ''"
    );
    $stmtSenha->execute(["senha_hash" => $senhaTemporariaHash]);
    $pdo->exec(
        "UPDATE usuarios
         SET papel = 'cliente'
         WHERE papel IS NULL OR TRIM(COALESCE(papel, '')) = '' OR papel NOT IN ('admin', 'cliente')"
    );

    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN nome VARCHAR(120) NOT NULL");
    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN email VARCHAR(150) NOT NULL");
    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN senha_hash VARCHAR(255) NOT NULL");
    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN papel ENUM('admin', 'cliente') NOT NULL DEFAULT 'cliente'");

    garantirIndice(
        $pdo,
        "usuarios",
        ["usuarios_email_unique", "email"],
        "ALTER TABLE usuarios ADD UNIQUE INDEX usuarios_email_unique (email)"
    );
}

function migrarProdutos(PDO $pdo): void
{
    if (!tabelaExiste($pdo, "produtos")) {
        $pdo->exec(
            "CREATE TABLE produtos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug VARCHAR(190) NOT NULL,
                nome VARCHAR(120) NOT NULL,
                descricao TEXT DEFAULT NULL,
                preco DECIMAL(10,2) NOT NULL,
                categoria VARCHAR(80) NOT NULL DEFAULT 'Chocolate',
                tipo VARCHAR(50) NOT NULL DEFAULT 'Chocolate',
                peso VARCHAR(40) DEFAULT NULL,
                peso_gramas INT UNSIGNED DEFAULT NULL,
                ref VARCHAR(40) DEFAULT NULL,
                destaque VARCHAR(80) DEFAULT NULL,
                img TEXT DEFAULT NULL,
                imagens JSON DEFAULT NULL,
                estoque_quantidade INT UNSIGNED NOT NULL DEFAULT 0,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY produtos_slug_unique (slug),
                UNIQUE KEY produtos_ref_unique (ref),
                KEY produtos_categoria_idx (categoria),
                KEY produtos_tipo_idx (tipo),
                KEY produtos_destaque_idx (destaque),
                KEY produtos_preco_idx (preco),
                CONSTRAINT chk_produtos_preco_positivo CHECK (preco > 0),
                CONSTRAINT chk_produtos_estoque_nao_negativo CHECK (estoque_quantidade >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    adicionarColunaSeAusente($pdo, "produtos", "slug", "slug VARCHAR(190) DEFAULT NULL AFTER id");
    adicionarColunaSeAusente($pdo, "produtos", "nome", "nome VARCHAR(120) DEFAULT NULL AFTER slug");
    adicionarColunaSeAusente($pdo, "produtos", "descricao", "descricao TEXT DEFAULT NULL AFTER nome");
    adicionarColunaSeAusente($pdo, "produtos", "preco", "preco DECIMAL(10,2) DEFAULT NULL AFTER descricao");
    adicionarColunaSeAusente($pdo, "produtos", "categoria", "categoria VARCHAR(80) DEFAULT NULL AFTER preco");
    adicionarColunaSeAusente($pdo, "produtos", "tipo", "tipo VARCHAR(50) DEFAULT NULL AFTER categoria");
    adicionarColunaSeAusente($pdo, "produtos", "peso", "peso VARCHAR(40) DEFAULT NULL AFTER tipo");
    adicionarColunaSeAusente($pdo, "produtos", "peso_gramas", "peso_gramas INT UNSIGNED DEFAULT NULL AFTER peso");
    adicionarColunaSeAusente($pdo, "produtos", "ref", "ref VARCHAR(40) DEFAULT NULL AFTER peso_gramas");
    adicionarColunaSeAusente($pdo, "produtos", "destaque", "destaque VARCHAR(80) DEFAULT NULL AFTER ref");
    adicionarColunaSeAusente($pdo, "produtos", "img", "img TEXT DEFAULT NULL AFTER destaque");
    adicionarColunaSeAusente($pdo, "produtos", "imagens", "imagens JSON DEFAULT NULL AFTER img");
    adicionarColunaSeAusente(
        $pdo,
        "produtos",
        "estoque_quantidade",
        "estoque_quantidade INT UNSIGNED NOT NULL DEFAULT 0 AFTER imagens"
    );
    adicionarColunaSeAusente($pdo, "produtos", "criado_em", "criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    adicionarColunaSeAusente(
        $pdo,
        "produtos",
        "atualizado_em",
        "atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    );

    $pdo->exec(
        "UPDATE produtos
         SET nome = CONCAT('Produto ', id)
         WHERE TRIM(COALESCE(nome, '')) = ''"
    );
    $pdo->exec(
        "UPDATE produtos
         SET preco = 0.01
         WHERE preco IS NULL OR preco <= 0"
    );
    $pdo->exec(
        "UPDATE produtos
         SET categoria = 'Chocolate'
         WHERE categoria IS NULL OR TRIM(COALESCE(categoria, '')) = ''"
    );
    $pdo->exec(
        "UPDATE produtos
         SET tipo = categoria
         WHERE tipo IS NULL OR TRIM(COALESCE(tipo, '')) = ''"
    );
    $pdo->exec(
        "UPDATE produtos
         SET estoque_quantidade = 0
         WHERE estoque_quantidade IS NULL OR estoque_quantidade < 0"
    );

    preencherSlugsProdutos($pdo);
    preencherReferenciasProdutos($pdo);
    preencherPesosProdutos($pdo);

    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN slug VARCHAR(190) NOT NULL");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN nome VARCHAR(120) NOT NULL");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN preco DECIMAL(10,2) NOT NULL");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN categoria VARCHAR(80) NOT NULL DEFAULT 'Chocolate'");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN tipo VARCHAR(50) NOT NULL DEFAULT 'Chocolate'");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN peso VARCHAR(40) DEFAULT NULL");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN peso_gramas INT UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN ref VARCHAR(40) DEFAULT NULL");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN destaque VARCHAR(80) DEFAULT NULL");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN estoque_quantidade INT UNSIGNED NOT NULL DEFAULT 0");

    garantirIndice(
        $pdo,
        "produtos",
        ["produtos_slug_unique"],
        "ALTER TABLE produtos ADD UNIQUE INDEX produtos_slug_unique (slug)"
    );
    garantirIndice(
        $pdo,
        "produtos",
        ["produtos_ref_unique"],
        "ALTER TABLE produtos ADD UNIQUE INDEX produtos_ref_unique (ref)"
    );
    garantirIndice(
        $pdo,
        "produtos",
        ["produtos_categoria_idx"],
        "ALTER TABLE produtos ADD INDEX produtos_categoria_idx (categoria)"
    );
    garantirIndice(
        $pdo,
        "produtos",
        ["produtos_tipo_idx"],
        "ALTER TABLE produtos ADD INDEX produtos_tipo_idx (tipo)"
    );
    garantirIndice(
        $pdo,
        "produtos",
        ["produtos_destaque_idx"],
        "ALTER TABLE produtos ADD INDEX produtos_destaque_idx (destaque)"
    );
    garantirIndice(
        $pdo,
        "produtos",
        ["produtos_preco_idx"],
        "ALTER TABLE produtos ADD INDEX produtos_preco_idx (preco)"
    );

    garantirCheck(
        $pdo,
        "produtos",
        "chk_produtos_preco_positivo",
        "ALTER TABLE produtos ADD CONSTRAINT chk_produtos_preco_positivo CHECK (preco > 0)"
    );
    garantirCheck(
        $pdo,
        "produtos",
        "chk_produtos_estoque_nao_negativo",
        "ALTER TABLE produtos ADD CONSTRAINT chk_produtos_estoque_nao_negativo CHECK (estoque_quantidade >= 0)"
    );
}

function migrarCarrinho(PDO $pdo): void
{
    if (!tabelaExiste($pdo, "carrinho_itens")) {
        $pdo->exec(
            "CREATE TABLE carrinho_itens (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT UNSIGNED NOT NULL,
                produto_id INT UNSIGNED DEFAULT NULL,
                chave_produto VARCHAR(190) NOT NULL,
                produto_slug VARCHAR(190) DEFAULT NULL,
                produto_nome VARCHAR(180) NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                imagem TEXT DEFAULT NULL,
                quantidade INT UNSIGNED NOT NULL DEFAULT 1,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY usuario_item_unico (usuario_id, chave_produto),
                KEY usuario_id_idx (usuario_id),
                KEY carrinho_produto_idx (produto_id),
                CONSTRAINT fk_carrinho_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
                CONSTRAINT fk_carrinho_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL,
                CONSTRAINT chk_carrinho_quantidade_positiva CHECK (quantidade > 0),
                CONSTRAINT chk_carrinho_preco_nao_negativo CHECK (preco_unitario >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    adicionarColunaSeAusente($pdo, "carrinho_itens", "usuario_id", "usuario_id INT UNSIGNED DEFAULT NULL AFTER id");
    adicionarColunaSeAusente($pdo, "carrinho_itens", "produto_id", "produto_id INT UNSIGNED DEFAULT NULL AFTER usuario_id");
    adicionarColunaSeAusente($pdo, "carrinho_itens", "chave_produto", "chave_produto VARCHAR(190) DEFAULT NULL AFTER produto_id");
    adicionarColunaSeAusente($pdo, "carrinho_itens", "produto_slug", "produto_slug VARCHAR(190) DEFAULT NULL AFTER chave_produto");
    adicionarColunaSeAusente($pdo, "carrinho_itens", "produto_nome", "produto_nome VARCHAR(180) DEFAULT NULL AFTER produto_slug");
    adicionarColunaSeAusente(
        $pdo,
        "carrinho_itens",
        "preco_unitario",
        "preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER produto_nome"
    );
    adicionarColunaSeAusente($pdo, "carrinho_itens", "imagem", "imagem TEXT DEFAULT NULL AFTER preco_unitario");
    adicionarColunaSeAusente($pdo, "carrinho_itens", "quantidade", "quantidade INT UNSIGNED NOT NULL DEFAULT 1 AFTER imagem");
    adicionarColunaSeAusente(
        $pdo,
        "carrinho_itens",
        "criado_em",
        "criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER quantidade"
    );
    adicionarColunaSeAusente(
        $pdo,
        "carrinho_itens",
        "atualizado_em",
        "atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em"
    );

    $pdo->exec(
        "UPDATE carrinho_itens
         SET quantidade = 1
         WHERE quantidade IS NULL OR quantidade <= 0"
    );
    $pdo->exec("DELETE FROM carrinho_itens WHERE usuario_id IS NULL");
    $pdo->exec(
        "UPDATE carrinho_itens
         SET produto_nome = CONCAT('Item ', id)
         WHERE TRIM(COALESCE(produto_nome, '')) = ''"
    );
    $pdo->exec(
        "UPDATE carrinho_itens
         SET chave_produto = COALESCE(NULLIF(TRIM(produto_slug), ''), CONCAT('item-', id))
         WHERE TRIM(COALESCE(chave_produto, '')) = ''"
    );
    $pdo->exec(
        "UPDATE carrinho_itens
         SET preco_unitario = 0.00
         WHERE preco_unitario IS NULL OR preco_unitario < 0"
    );

    limparCarrinhosOrfaos($pdo);
    preencherProdutoIdNoCarrinho($pdo);

    $pdo->exec("ALTER TABLE carrinho_itens MODIFY COLUMN usuario_id INT UNSIGNED NOT NULL");
    $pdo->exec("ALTER TABLE carrinho_itens MODIFY COLUMN produto_id INT UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE carrinho_itens MODIFY COLUMN chave_produto VARCHAR(190) NOT NULL");
    $pdo->exec("ALTER TABLE carrinho_itens MODIFY COLUMN produto_nome VARCHAR(180) NOT NULL");
    $pdo->exec("ALTER TABLE carrinho_itens MODIFY COLUMN preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    $pdo->exec("ALTER TABLE carrinho_itens MODIFY COLUMN quantidade INT UNSIGNED NOT NULL DEFAULT 1");

    garantirIndice(
        $pdo,
        "carrinho_itens",
        ["usuario_item_unico", "carrinho_usuario_item_unique"],
        "ALTER TABLE carrinho_itens ADD UNIQUE INDEX carrinho_usuario_item_unique (usuario_id, chave_produto)"
    );
    garantirIndice(
        $pdo,
        "carrinho_itens",
        ["usuario_id_idx", "carrinho_usuario_idx"],
        "ALTER TABLE carrinho_itens ADD INDEX carrinho_usuario_idx (usuario_id)"
    );
    garantirIndice(
        $pdo,
        "carrinho_itens",
        ["carrinho_produto_idx"],
        "ALTER TABLE carrinho_itens ADD INDEX carrinho_produto_idx (produto_id)"
    );

    garantirCheck(
        $pdo,
        "carrinho_itens",
        "chk_carrinho_quantidade_positiva",
        "ALTER TABLE carrinho_itens ADD CONSTRAINT chk_carrinho_quantidade_positiva CHECK (quantidade > 0)"
    );
    garantirCheck(
        $pdo,
        "carrinho_itens",
        "chk_carrinho_preco_nao_negativo",
        "ALTER TABLE carrinho_itens ADD CONSTRAINT chk_carrinho_preco_nao_negativo CHECK (preco_unitario >= 0)"
    );

    garantirForeignKey(
        $pdo,
        "carrinho_itens",
        "fk_carrinho_usuario",
        "ALTER TABLE carrinho_itens
         ADD CONSTRAINT fk_carrinho_usuario
         FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE"
    );
    garantirForeignKey(
        $pdo,
        "carrinho_itens",
        "fk_carrinho_produto",
        "ALTER TABLE carrinho_itens
         ADD CONSTRAINT fk_carrinho_produto
         FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL"
    );
}

function migrarComercial(PDO $pdo): void
{
    migrarSoftDeleteProdutos($pdo);
    migrarEnderecos($pdo);
    migrarPedidos($pdo);
    migrarPedidoItens($pdo);
    migrarEstoqueMovimentacoes($pdo);
}

function migrarRecuperacaoSenha(PDO $pdo): void
{
    if (!tabelaExiste($pdo, "recuperacoes_senha")) {
        $pdo->exec(
            "CREATE TABLE recuperacoes_senha (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expira_em DATETIME NOT NULL,
                usado_em DATETIME DEFAULT NULL,
                ip_solicitante VARCHAR(45) DEFAULT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY recuperacoes_token_unique (token_hash),
                KEY recuperacoes_usuario_idx (usuario_id),
                KEY recuperacoes_expira_idx (expira_em),
                CONSTRAINT fk_recuperacoes_usuario
                    FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    adicionarColunaSeAusente($pdo, "recuperacoes_senha", "usuario_id", "usuario_id INT UNSIGNED NOT NULL AFTER id");
    adicionarColunaSeAusente($pdo, "recuperacoes_senha", "token_hash", "token_hash CHAR(64) NOT NULL AFTER usuario_id");
    adicionarColunaSeAusente($pdo, "recuperacoes_senha", "expira_em", "expira_em DATETIME NOT NULL AFTER token_hash");
    adicionarColunaSeAusente($pdo, "recuperacoes_senha", "usado_em", "usado_em DATETIME DEFAULT NULL AFTER expira_em");
    adicionarColunaSeAusente($pdo, "recuperacoes_senha", "ip_solicitante", "ip_solicitante VARCHAR(45) DEFAULT NULL AFTER usado_em");
    adicionarColunaSeAusente($pdo, "recuperacoes_senha", "criado_em", "criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER ip_solicitante");

    garantirIndice(
        $pdo,
        "recuperacoes_senha",
        ["recuperacoes_token_unique", "token_hash"],
        "ALTER TABLE recuperacoes_senha ADD UNIQUE INDEX recuperacoes_token_unique (token_hash)"
    );
    garantirIndice(
        $pdo,
        "recuperacoes_senha",
        ["recuperacoes_usuario_idx"],
        "ALTER TABLE recuperacoes_senha ADD INDEX recuperacoes_usuario_idx (usuario_id)"
    );
    garantirIndice(
        $pdo,
        "recuperacoes_senha",
        ["recuperacoes_expira_idx"],
        "ALTER TABLE recuperacoes_senha ADD INDEX recuperacoes_expira_idx (expira_em)"
    );
    garantirForeignKey(
        $pdo,
        "recuperacoes_senha",
        "fk_recuperacoes_usuario",
        "ALTER TABLE recuperacoes_senha
         ADD CONSTRAINT fk_recuperacoes_usuario
         FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE"
    );
}

function migrarSoftDeleteProdutos(PDO $pdo): void
{
    adicionarColunaSeAusente($pdo, "produtos", "ativo", "ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER estoque_quantidade");
    adicionarColunaSeAusente($pdo, "produtos", "deleted_at", "deleted_at TIMESTAMP NULL DEFAULT NULL AFTER ativo");
    adicionarColunaSeAusente($pdo, "produtos", "criado_por_usuario_id", "criado_por_usuario_id INT UNSIGNED DEFAULT NULL AFTER deleted_at");
    adicionarColunaSeAusente($pdo, "produtos", "atualizado_por_usuario_id", "atualizado_por_usuario_id INT UNSIGNED DEFAULT NULL AFTER criado_por_usuario_id");

    $pdo->exec("UPDATE produtos SET ativo = 1 WHERE ativo IS NULL");
    $pdo->exec("ALTER TABLE produtos MODIFY COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1");

    garantirIndice(
        $pdo,
        "produtos",
        ["produtos_ativo_idx"],
        "ALTER TABLE produtos ADD INDEX produtos_ativo_idx (ativo)"
    );

    garantirForeignKey(
        $pdo,
        "produtos",
        "fk_produtos_criado_por_usuario",
        "ALTER TABLE produtos
         ADD CONSTRAINT fk_produtos_criado_por_usuario
         FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL"
    );
    garantirForeignKey(
        $pdo,
        "produtos",
        "fk_produtos_atualizado_por_usuario",
        "ALTER TABLE produtos
         ADD CONSTRAINT fk_produtos_atualizado_por_usuario
         FOREIGN KEY (atualizado_por_usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL"
    );
}

function migrarEnderecos(PDO $pdo): void
{
    if (!tabelaExiste($pdo, "enderecos")) {
        $pdo->exec(
            "CREATE TABLE enderecos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT UNSIGNED NOT NULL,
                rotulo VARCHAR(80) DEFAULT NULL,
                destinatario_nome VARCHAR(120) NOT NULL,
                telefone VARCHAR(30) DEFAULT NULL,
                cep VARCHAR(20) NOT NULL,
                logradouro VARCHAR(150) NOT NULL,
                numero VARCHAR(20) NOT NULL,
                complemento VARCHAR(120) DEFAULT NULL,
                bairro VARCHAR(120) NOT NULL,
                cidade VARCHAR(120) NOT NULL,
                estado CHAR(2) NOT NULL,
                referencia VARCHAR(150) DEFAULT NULL,
                principal TINYINT(1) NOT NULL DEFAULT 0,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY enderecos_usuario_idx (usuario_id),
                KEY enderecos_principal_idx (usuario_id, principal),
                CONSTRAINT fk_enderecos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function migrarPedidos(PDO $pdo): void
{
    if (!tabelaExiste($pdo, "pedidos")) {
        $pdo->exec(
            "CREATE TABLE pedidos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT UNSIGNED NOT NULL,
                endereco_entrega_id INT UNSIGNED DEFAULT NULL,
                numero_pedido VARCHAR(30) NOT NULL,
                status ENUM('rascunho', 'aguardando_pagamento', 'pago', 'em_preparo', 'enviado', 'entregue', 'cancelado') NOT NULL DEFAULT 'rascunho',
                moeda CHAR(3) NOT NULL DEFAULT 'BRL',
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                frete DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                metodo_pagamento VARCHAR(50) DEFAULT NULL,
                status_pagamento ENUM('pendente', 'autorizado', 'pago', 'falhou', 'estornado') NOT NULL DEFAULT 'pendente',
                observacoes TEXT DEFAULT NULL,
                realizado_em TIMESTAMP NULL DEFAULT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY pedidos_numero_unique (numero_pedido),
                KEY pedidos_usuario_idx (usuario_id),
                KEY pedidos_status_idx (status),
                KEY pedidos_pagamento_idx (status_pagamento),
                CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT,
                CONSTRAINT fk_pedidos_endereco FOREIGN KEY (endereco_entrega_id) REFERENCES enderecos (id) ON DELETE SET NULL,
                CONSTRAINT chk_pedidos_subtotal_nao_negativo CHECK (subtotal >= 0),
                CONSTRAINT chk_pedidos_desconto_nao_negativo CHECK (desconto >= 0),
                CONSTRAINT chk_pedidos_frete_nao_negativo CHECK (frete >= 0),
                CONSTRAINT chk_pedidos_total_nao_negativo CHECK (total >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function migrarPedidoItens(PDO $pdo): void
{
    if (!tabelaExiste($pdo, "pedido_itens")) {
        $pdo->exec(
            "CREATE TABLE pedido_itens (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                pedido_id INT UNSIGNED NOT NULL,
                produto_id INT UNSIGNED DEFAULT NULL,
                produto_slug VARCHAR(190) DEFAULT NULL,
                produto_ref VARCHAR(40) DEFAULT NULL,
                produto_nome VARCHAR(180) NOT NULL,
                quantidade INT UNSIGNED NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL,
                subtotal_item DECIMAL(10,2) NOT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY pedido_itens_pedido_idx (pedido_id),
                KEY pedido_itens_produto_idx (produto_id),
                CONSTRAINT fk_pedido_itens_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE,
                CONSTRAINT fk_pedido_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL,
                CONSTRAINT chk_pedido_itens_quantidade_positiva CHECK (quantidade > 0),
                CONSTRAINT chk_pedido_itens_preco_nao_negativo CHECK (preco_unitario >= 0),
                CONSTRAINT chk_pedido_itens_subtotal_nao_negativo CHECK (subtotal_item >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function migrarEstoqueMovimentacoes(PDO $pdo): void
{
    if (!tabelaExiste($pdo, "estoque_movimentacoes")) {
        $pdo->exec(
            "CREATE TABLE estoque_movimentacoes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                produto_id INT UNSIGNED NOT NULL,
                pedido_id INT UNSIGNED DEFAULT NULL,
                usuario_id INT UNSIGNED DEFAULT NULL,
                tipo ENUM('entrada', 'saida', 'ajuste', 'reserva', 'liberacao') NOT NULL,
                origem VARCHAR(50) NOT NULL DEFAULT 'sistema',
                quantidade INT NOT NULL,
                estoque_antes INT DEFAULT NULL,
                estoque_depois INT DEFAULT NULL,
                observacao VARCHAR(255) DEFAULT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY estoque_movimentacoes_produto_idx (produto_id),
                KEY estoque_movimentacoes_pedido_idx (pedido_id),
                KEY estoque_movimentacoes_usuario_idx (usuario_id),
                KEY estoque_movimentacoes_tipo_idx (tipo),
                CONSTRAINT fk_estoque_movimentacoes_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE RESTRICT,
                CONSTRAINT fk_estoque_movimentacoes_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE SET NULL,
                CONSTRAINT fk_estoque_movimentacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
                CONSTRAINT chk_estoque_movimentacoes_quantidade_nao_zero CHECK (quantidade <> 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function preencherSlugsProdutos(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id, nome, slug FROM produtos ORDER BY id ASC");
    $produtos = $stmt->fetchAll();

    if ($produtos === []) {
        return;
    }

    $slugsUsados = [];
    $update = $pdo->prepare("UPDATE produtos SET slug = :slug WHERE id = :id");

    foreach ($produtos as $produto) {
        $slugAtual = trim((string) ($produto["slug"] ?? ""));

        if ($slugAtual !== "" && !isset($slugsUsados[$slugAtual])) {
            $slugsUsados[$slugAtual] = true;
            continue;
        }

        $slugBase = gerarSlugProduto((string) ($produto["nome"] ?? "")) ?: "produto";
        $slug = $slugBase;
        $contador = 2;

        while (isset($slugsUsados[$slug])) {
            $slug = "{$slugBase}-{$contador}";
            $contador += 1;
        }

        $update->execute([
            "slug" => $slug,
            "id" => (int) $produto["id"],
        ]);

        $slugsUsados[$slug] = true;
    }
}

function preencherReferenciasProdutos(PDO $pdo): void
{
    if (!colunaExiste($pdo, "produtos", "ref")) {
        return;
    }

    $stmt = $pdo->query("SELECT id, ref FROM produtos ORDER BY id ASC");
    $produtos = $stmt->fetchAll();
    $refsUsadas = [];
    $update = $pdo->prepare("UPDATE produtos SET ref = :ref WHERE id = :id");

    foreach ($produtos as $produto) {
        $refAtual = normalizarReferenciaProduto((string) ($produto["ref"] ?? ""));

        if ($refAtual !== null && !isset($refsUsadas[$refAtual])) {
            $refsUsadas[$refAtual] = true;

            if ($refAtual !== (string) ($produto["ref"] ?? "")) {
                $update->execute([
                    "ref" => $refAtual,
                    "id" => (int) $produto["id"],
                ]);
            }

            continue;
        }

        $refBase = $refAtual ?? gerarReferenciaProduto();
        $ref = $refBase;
        $contador = 2;

        while (isset($refsUsadas[$ref])) {
            $sufixo = "-" . $contador;
            $limiteBase = max(1, 40 - strlen($sufixo));
            $ref = substr($refBase, 0, $limiteBase) . $sufixo;
            $contador += 1;
        }

        $update->execute([
            "ref" => $ref,
            "id" => (int) $produto["id"],
        ]);

        $refsUsadas[$ref] = true;
    }
}

function preencherPesosProdutos(PDO $pdo): void
{
    if (!colunaExiste($pdo, "produtos", "peso") || !colunaExiste($pdo, "produtos", "peso_gramas")) {
        return;
    }

    $stmt = $pdo->query("SELECT id, peso, peso_gramas FROM produtos ORDER BY id ASC");
    $produtos = $stmt->fetchAll();
    $update = $pdo->prepare("UPDATE produtos SET peso_gramas = :peso_gramas WHERE id = :id");

    foreach ($produtos as $produto) {
        $pesoGramasAtual = $produto["peso_gramas"] !== null ? (int) $produto["peso_gramas"] : null;

        if ($pesoGramasAtual !== null && $pesoGramasAtual > 0) {
            continue;
        }

        $pesoGramas = extrairPesoGramas((string) ($produto["peso"] ?? ""));

        if ($pesoGramas === null) {
            continue;
        }

        $update->execute([
            "peso_gramas" => $pesoGramas,
            "id" => (int) $produto["id"],
        ]);
    }
}

function limparCarrinhosOrfaos(PDO $pdo): void
{
    if (!tabelaExiste($pdo, "usuarios")) {
        return;
    }

    $pdo->exec(
        "DELETE carrinho_itens
         FROM carrinho_itens
         LEFT JOIN usuarios ON usuarios.id = carrinho_itens.usuario_id
         WHERE usuarios.id IS NULL"
    );
}

function preencherProdutoIdNoCarrinho(PDO $pdo): void
{
    if (!colunaExiste($pdo, "carrinho_itens", "produto_id") || !tabelaExiste($pdo, "produtos")) {
        return;
    }

    $pdo->exec(
        "UPDATE carrinho_itens
         INNER JOIN produtos ON produtos.slug = carrinho_itens.produto_slug
         SET carrinho_itens.produto_id = produtos.id
         WHERE carrinho_itens.produto_id IS NULL
           AND carrinho_itens.produto_slug IS NOT NULL
           AND TRIM(carrinho_itens.produto_slug) <> ''"
    );
}
