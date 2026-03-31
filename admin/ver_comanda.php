<?php
require "../config.php";
require "auth.php";
require "layout/header.php";

$comanda_id = (int) ($_GET["comanda_id"] ?? 0);

if ($comanda_id <= 0) {
    die("Comanda inválida.");
}

/*
|--------------------------------------------------------------------------
| BUSCAR DADOS DA COMANDA
|--------------------------------------------------------------------------
*/
$sqlComanda = "
    SELECT
        comandas.*,
        mesas.numero AS numero_mesa
    FROM comandas
    INNER JOIN mesas ON comandas.mesa_id = mesas.id
    WHERE comandas.id = :comanda_id AND comandas.pizzaria_id = :pizzaria_id
    LIMIT 1
";
$stmtComanda = $pdo->prepare($sqlComanda);
$stmtComanda->execute([
    ":comanda_id" => $comanda_id,
    ":pizzaria_id" => $pizzariaId
]);
$comanda = $stmtComanda->fetch(PDO::FETCH_ASSOC);

if (!$comanda) {
    die("Comanda não encontrada.");
}

/*
|--------------------------------------------------------------------------
| BUSCAR ITENS DA COMANDA
|--------------------------------------------------------------------------
*/
$sqlItens = "
    SELECT
        comanda_itens.*,
        produtos.nome AS nome_produto,
        produtos.categoria
    FROM comanda_itens
    INNER JOIN produtos ON comanda_itens.produto_id = produtos.id
    WHERE comanda_itens.comanda_id = :comanda_id
    ORDER BY comanda_itens.created_at DESC, comanda_itens.id DESC
";
$stmtItens = $pdo->prepare($sqlItens);
$stmtItens->execute([":comanda_id" => $comanda_id]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Comanda</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.08);
        }

        h1,
        h2 {
            margin-bottom: 20px;
        }

        .topo {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .card-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 16px;
            min-width: 220px;
        }

        .card-info strong {
            display: block;
            margin-bottom: 8px;
        }

        .valor {
            font-size: 24px;
            font-weight: bold;
            color: #1a7f37;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f1f1f1;
        }

        .preco {
            font-weight: bold;
            color: #1a7f37;
        }

        .voltar {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            background: #6c757d;
            color: #fff;
            padding: 10px 14px;
            border-radius: 6px;
        }

        .voltar:hover {
            background: #565e64;
        }

        .vazio {
            padding: 20px;
            background: #fafafa;
            border: 1px dashed #ccc;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            table {
                font-size: 14px;
            }

            .topo {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Detalhes da Comanda</h1>

        <div class="topo">
            <div class="card-info">
                <strong>Comanda</strong>
                #<?= $comanda["id"] ?>
            </div>

            <div class="card-info">
                <strong>Mesa</strong>
                Mesa <?= htmlspecialchars($comanda["numero_mesa"]) ?>
            </div>

            <div class="card-info">
                <strong>Status</strong>
                <?= htmlspecialchars($comanda["status"]) ?>
            </div>

            <div class="card-info">
                <strong>Total</strong>
                <span class="valor">R$ <?= number_format($comanda["total"], 2, ",", ".") ?></span>
            </div>
        </div>

        <h2>Itens da comanda</h2>

        <?php if (count($itens) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Quantidade</th>
                        <th>Preço unitário</th>
                        <th>Subtotal</th>
                        <th>Lançado em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item["nome_produto"]) ?></td>
                            <td><?= htmlspecialchars($item["categoria"]) ?></td>
                            <td><?= $item["quantidade"] ?></td>
                            <td class="preco">R$ <?= number_format($item["preco_unitario"], 2, ",", ".") ?></td>
                            <td class="preco">R$ <?= number_format($item["subtotal"], 2, ",", ".") ?></td>
                            <td><?= date("d/m/Y H:i", strtotime($item["created_at"])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="vazio">Nenhum item encontrado nesta comanda.</div>
        <?php endif; ?>

        <a class="voltar" href="historico_comandas.php">Voltar para o histórico</a>
    </div>
</body>

</html>