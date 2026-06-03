<?php
declare(strict_types=1);

require_once __DIR__ . "/conexao.php";

header("Content-Type: text/plain; charset=UTF-8");

if (!bancoDeDadosDisponivel($pdo)) {
    echo "ERRO: não foi possível conectar ao banco.\n";
    echo $databaseConnectionError !== "" ? $databaseConnectionError . "\n" : "";
    exit(1);
}

echo "OK: conexão com o banco realizada com sucesso.\n";

try {
    $stmt = $pdo->query("SELECT DATABASE() AS banco_atual");
    $resultado = $stmt->fetch();
    echo "Banco atual: " . ($resultado["banco_atual"] ?? "desconhecido") . "\n";

    $tabelasObrigatorias = ["usuarios", "recuperacoes_senha", "produtos", "carrinho_itens", "enderecos", "pedidos", "pedido_itens", "estoque_movimentacoes"];
    $tabelasAusentes = listarTabelasAusentes($pdo, $tabelasObrigatorias);

    if ($tabelasAusentes === []) {
        echo "Schema principal: OK.\n";
    } else {
        echo "Schema principal: incompleto.\n";
        echo "Tabelas ausentes: " . implode(", ", $tabelasAusentes) . "\n";
        echo "Execute: C:\\xampp\\php\\php.exe database\\migrate.php\n";
    }
} catch (PDOException $exception) {
    echo "Conectou, mas não foi possível consultar o banco atual.\n";
}
