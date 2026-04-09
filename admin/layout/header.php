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
$usuarioTipo = $_SESSION["usuario_tipo"] ?? "perfil";
$paginaAtual = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Pizzaria</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            display: flex;
            background: #f4f4f4;
        }

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

        .sidebar a {
            display: block;
            color: #ccc;
            text-decoration: none;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 8px;
            transition: 0.2s;
        }

        .sidebar a:hover {
            background: #222;
            color: #fff;
        }

        .sidebar a.ativo {
            background: #2d89ef;
            color: #fff;
            font-weight: bold;
        }

        .main {
            margin-left: 240px;
            width: 100%;
        }

        .topbar {
            background: #fff;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar strong {
            color: #333;
        }

        .topbar small {
            color: #666;
            margin-left: 6px;
        }

        .content {
            padding: 25px;
        }

        .page-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <h2>Pizzaria</h2>

        <a href="index.php" class="<?= $paginaAtual === 'index.php' ? 'ativo' : '' ?>">Dashboard</a>
        <a href="mesas.php" class="<?= $paginaAtual === 'mesas.php' ? 'ativo' : '' ?>">Mesas</a>
        <a href="comandas.php" class="<?= $paginaAtual === 'comandas.php' ? 'ativo' : '' ?>">Comandas</a>
        <a href="produtos.php" class="<?= $paginaAtual === 'produtos.php' ? 'ativo' : '' ?>">Produtos</a>
        <a href="historico_comandas.php" class="<?= $paginaAtual === 'historico_comandas.php' ? 'ativo' : '' ?>">Histórico</a>

        <hr style="margin:15px 0; border:1px solid #222;">

        <a href="logout.php">Sair</a>
    </div>

    <div class="main">

        <div class="topbar">
            <div>Painel Administrativo</div>
            <div>
                Olá, <strong><?= htmlspecialchars($usuarioNome) ?></strong>
                <small>(<?= htmlspecialchars($usuarioTipo) ?>)</small>
            </div>
        </div>

        <div class="content">