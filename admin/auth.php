<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$pizzariaId = $_SESSION["pizzaria_id"];
$usuarioNome = $_SESSION["usuario_nome"];
$usuarioTipo = $_SESSION["usuario_tipo"];