<?php
if (!isset($_SESSION)) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| PROTEÇÃO DE ROTA
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DADOS DO USUÁRIO
|--------------------------------------------------------------------------
*/
$usuarioNome = $_SESSION["usuario_nome"] ?? "Usuário";
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sistema Pizzaria</title>

    <style>
        /* RESET BÁSICO */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /*
|--------------------------------------------------------------------------
| LAYOUT PRINCIPAL
|--------------------------------------------------------------------------
*/
        body {
            font-family: Arial, sans-serif;
            display: flex;
            background: #f4f4f4;
        }

        /*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/
        .sidebar {
            width: 240px;
            height: 100vh;
            background: #111;
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px;
        }

        .sidebar h2 {
            margin-bottom: 25px;
            font-size: 20px;
        }

        /* links do menu */
        .sidebar a {
            display: block;
            color: #ccc;
            text-decoration: none;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .sidebar a:hover {
            background: #222;
            color: #fff;
        }

        /*
|--------------------------------------------------------------------------
| ÁREA PRINCIPAL
|--------------------------------------------------------------------------
*/
        .main {
            margin-left: 240px;
            width: 100%;
        }

        /*
|--------------------------------------------------------------------------
| TOPO
|--------------------------------------------------------------------------
*/
        .topbar {
            background: #fff;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
        }

        .topbar strong {
            color: #333;
        }

        /*
|--------------------------------------------------------------------------
| CONTEÚDO
|--------------------------------------------------------------------------
*/
        .content {
            padding: 25px;
        }

        /*
|--------------------------------------------------------------------------
| CARD PADRÃO
|--------------------------------------------------------------------------
*/
        .page-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>

    <!-- MENU LATERAL -->
    <div class="sidebar">
        <h2>Pizzaria</h2>

        <a href="mesas.php">Mesas</a>
        <a href="comandas.php">Comandas</a>
        <a href="produtos.php">Produtos</a>
        <a href="historico_comandas.php">Histórico</a>

        <hr style="margin:15px 0; border:1px solid #222;">

        <a href="logout.php">Sair</a>
    </div>

    <!-- ÁREA PRINCIPAL -->
    <div class="main">

        <!-- TOPO -->
        <div class="topbar">
            <div>Painel Administrativo</div>
            <div>Olá, <strong><?= htmlspecialchars($usuarioNome) ?></strong></div>
        </div>

        <!-- CONTEÚDO -->
        <div class="content">