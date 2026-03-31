<?php
require "../config.php";

$token = $_GET["token"] ?? "";

if ($token == "") {
    die("Mesa inválida.");
}

/*
|--------------------------------------------------------------------------
| BUSCAR MESA PELO TOKEN
|--------------------------------------------------------------------------
*/
$sqlMesa = "SELECT * FROM mesas WHERE token = :token LIMIT 1";
$stmtMesa = $pdo->prepare($sqlMesa);
$stmtMesa->execute([":token" => $token]);
$mesa = $stmtMesa->fetch(PDO::FETCH_ASSOC);

if (!$mesa) {
    die("Mesa não encontrada.");
}

$pizzariaId = $mesa["pizzaria_id"];

/*
|--------------------------------------------------------------------------
| BUSCAR COMANDA ABERTA
|--------------------------------------------------------------------------
*/
$sqlComanda = "
    SELECT * 
    FROM comandas 
    WHERE mesa_id = :mesa_id 
      AND pizzaria_id = :pizzaria_id
      AND status = 'aberta' 
    LIMIT 1
";
$stmtComanda = $pdo->prepare($sqlComanda);
$stmtComanda->execute([
    ":mesa_id" => $mesa["id"],
    ":pizzaria_id" => $pizzariaId
]);
$comanda = $stmtComanda->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| BUSCAR ITENS DA COMANDA
|--------------------------------------------------------------------------
*/
$itens = [];

if ($comanda) {
    $sqlItens = "
        SELECT 
            comanda_itens.*,
            produtos.nome,
            produtos.categoria
        FROM comanda_itens
        INNER JOIN produtos ON comanda_itens.produto_id = produtos.id
        WHERE comanda_itens.comanda_id = :comanda_id
        ORDER BY comanda_itens.created_at DESC
    ";

    $stmtItens = $pdo->prepare($sqlItens);
    $stmtItens->execute([":comanda_id" => $comanda["id"]]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesa <?= htmlspecialchars($mesa["numero"]) ?></title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #111;
            color: #fff;
            margin: 0;
        }

        .container {
            padding: 20px;
            max-width: 600px;
            margin: auto;
        }

        .topo {
            text-align: center;
            margin-bottom: 20px;
        }

        .topo h1 {
            margin: 0;
        }

        .card {
            background: #1c1c1c;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            gap: 12px;
        }

        .categoria {
            font-size: 12px;
            color: #aaa;
        }

        .total {
            font-size: 22px;
            font-weight: bold;
            color: #00ff88;
            text-align: right;
        }

        .vazio {
            text-align: center;
            color: #aaa;
            margin-top: 30px;
        }

        .botao {
            display: block;
            width: 100%;
            text-align: center;
            padding: 14px;
            background: #00c853;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            margin-top: 20px;
            font-weight: bold;
            box-sizing: border-box;
        }

        .botao:hover {
            background: #00a844;
        }
    </style>
</head>
<body>

    <div class="container">

        <div class="topo">
            <h1>🍕 Mesa <?= htmlspecialchars($mesa["numero"]) ?></h1>
            <p>Status: <?= htmlspecialchars($mesa["status"]) ?></p>
        </div>

        <?php if (!$comanda): ?>
            <div class="vazio">
                Nenhuma comanda aberta para esta mesa.
            </div>
        <?php else: ?>

            <div class="card">
                <h3>Seu consumo</h3>

                <?php if (count($itens) > 0): ?>
                    <?php foreach ($itens as $item): ?>
                        <div class="item">
                            <div>
                                <?= (int) $item["quantidade"] ?>x <?= htmlspecialchars($item["nome"]) ?>
                                <div class="categoria"><?= htmlspecialchars($item["categoria"]) ?></div>
                            </div>
                            <div>
                                R$ <?= number_format($item["subtotal"], 2, ",", ".") ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Nenhum item ainda.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="total">
                    Total: R$ <?= number_format($comanda["total"], 2, ",", ".") ?>
                </div>
            </div>

            <a href="pagar.php?token=<?= urlencode($token) ?>" class="botao">
                Pagar agora
            </a>

        <?php endif; ?>

    </div>

</body>
</html>