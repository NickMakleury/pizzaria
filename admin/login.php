<?php
session_start();
require "../config.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $senha = trim($_POST["senha"] ?? "");

    if ($email === "" || $senha === "") {
        $mensagem = "Preencha e-mail e senha.";
    } else {
        $sql = "SELECT * FROM usuarios WHERE email = :email AND ativo = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":email" => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senha, $usuario["senha"])) {
            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["usuario_nome"] = $usuario["nome"];
            $_SESSION["usuario_tipo"] = $usuario["tipo"];
            $_SESSION["pizzaria_id"] = $usuario["pizzaria_id"];

            header("Location: mesas.php");
            exit;
        } else {
            $mensagem = "E-mail ou senha inválidos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login do Sistema</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #111;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .box {
            width: 100%;
            max-width: 400px;
            background: #1c1c1c;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 0 20px rgba(0,0,0,0.25);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-bottom: 16px;
            border: none;
            border-radius: 8px;
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #00c853;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #00a844;
        }

        .mensagem {
            background: #3a1f1f;
            color: #ffb3b3;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>Login do Sistema</h1>

        <?php if ($mensagem !== ""): ?>
            <div class="mensagem"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="email">E-mail</label>
            <input type="email" name="email" id="email" required>

            <label for="senha">Senha</label>
            <input type="password" name="senha" id="senha" required>

            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>