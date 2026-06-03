<?php
declare(strict_types=1);

require_once __DIR__ . "/../app/seguranca.php";

iniciarSessaoSegura();

header("Content-Type: application/json; charset=UTF-8");

$usuarioAutenticado = isset($_SESSION["usuario_id"]);
$papelUsuario = (string) ($_SESSION["usuario_papel"] ?? "cliente");
$usuarioAdmin = $usuarioAutenticado && $papelUsuario === "admin";

echo json_encode([
    "autenticado" => $usuarioAutenticado,
    "admin" => $usuarioAdmin,
    "papel" => $usuarioAutenticado ? $papelUsuario : null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
