<?php
require "../config.php";
require "auth.php";
require "layout/header.php";

function gerarToken(int $tamanho = 32): string
{
    return bin2hex(random_bytes($tamanho / 2));
}

$mensagem = "";

/*
|--------------------------------------------------------------------------
| CADASTRAR MESA
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $numero = (int) ($_POST["numero"] ?? 0);

    if ($numero <= 0) {
        $mensagem = "Informe um número de mesa válido.";
    } else {
        $token = gerarToken();

        try {
            $sql = "
                INSERT INTO mesas (numero, token, pizzaria_id)
                VALUES (:numero, :token, :pizzaria_id)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":numero" => $numero,
                ":token" => $token,
                ":pizzaria_id" => $pizzariaId
            ]);

            $mensagem = "Mesa cadastrada com sucesso!";
        } catch (PDOException $e) {
            $mensagem = "Erro ao cadastrar mesa. Verifique se esse número já existe.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| LISTAR MESAS
|--------------------------------------------------------------------------
*/
$sqlMesas = "
    SELECT * 
    FROM mesas 
    WHERE pizzaria_id = :pizzaria_id 
    ORDER BY numero ASC
";
$stmtMesas = $pdo->prepare($sqlMesas);
$stmtMesas->execute([":pizzaria_id" => $pizzariaId]);
$mesas = $stmtMesas->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .page-card {
        max-width: 900px;
        margin: auto;
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.08);
        color: #222;
    }

    .page-card h1,
    .page-card h2 {
        margin-bottom: 20px;
    }

    .form-mesa {
        margin-bottom: 30px;
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .form-mesa label {
        font-weight: bold;
        color: #222;
    }

    .form-mesa input {
        padding: 10px;
        width: 200px;
        font-size: 16px;
    }

    .page-card button {
        padding: 10px 18px;
        font-size: 16px;
        border: none;
        background: #2d89ef;
        color: white;
        border-radius: 6px;
        cursor: pointer;
    }

    .page-card button:hover {
        background: #1b5fad;
    }

    .mensagem {
        margin: 15px 0;
        padding: 12px;
        border-radius: 6px;
        background: #eef6ff;
        color: #1b4f72;
    }

    .page-card table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }

    .page-card th,
    .page-card td {
        padding: 12px;
        border-bottom: 1px solid #ddd;
        text-align: left;
        color: #222;
    }

    .page-card th {
        background: #f1f1f1;
    }

    .status-livre {
        color: green;
        font-weight: bold;
    }

    .status-ocupada {
        color: orange;
        font-weight: bold;
    }

    .status-paga {
        color: blue;
        font-weight: bold;
    }

    .status-aguardando {
        color: red;
        font-weight: bold;
    }

    .token {
        font-size: 12px;
        color: #666;
        word-break: break-all;
    }

    @media (max-width: 768px) {
        .form-mesa {
            flex-direction: column;
            align-items: stretch;
        }

        .form-mesa input,
        .page-card button {
            width: 100%;
        }
    }
</style>

<div class="page-card">
    <h1>Cadastro de Mesas</h1>

    <?php if ($mensagem !== ""): ?>
        <div class="mensagem"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="POST" class="form-mesa">
        <label for="numero">Número da mesa</label>
        <input type="number" name="numero" id="numero" min="1" required>
        <button type="submit">Cadastrar Mesa</button>
    </form>

    <h2>Mesas cadastradas</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Número</th>
                <th>Status</th>
                <th>Token</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($mesas) > 0): ?>
                <?php foreach ($mesas as $mesa): ?>
                    <?php
                    $status = $mesa["status"];
                    $classe = "";

                    if ($status === "livre") {
                        $classe = "status-livre";
                    } elseif ($status === "ocupada") {
                        $classe = "status-ocupada";
                    } elseif ($status === "paga") {
                        $classe = "status-paga";
                    } elseif ($status === "aguardando_pagamento") {
                        $classe = "status-aguardando";
                    }
                    ?>
                    <tr>
                        <td><?= $mesa["id"] ?></td>
                        <td>Mesa <?= htmlspecialchars($mesa["numero"]) ?></td>
                        <td>
                            <span class="<?= $classe ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                        </td>
                        <td class="token"><?= htmlspecialchars($mesa["token"]) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">Nenhuma mesa cadastrada ainda.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require "layout/footer.php"; ?>