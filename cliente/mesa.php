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
$mensagem = "";

/*
|--------------------------------------------------------------------------
| BUSCAR COMANDA MAIS RECENTE DA MESA
|--------------------------------------------------------------------------
*/
$sqlComanda = "
    SELECT * 
    FROM comandas 
    WHERE mesa_id = :mesa_id 
      AND pizzaria_id = :pizzaria_id
      AND status = 'aberta'
    ORDER BY id DESC
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
| ADICIONAR ITEM PELO CLIENTE
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["adicionar_item"])) {
    $produto_id = (int) ($_POST["produto_id"] ?? 0);
    $quantidade = (int) ($_POST["quantidade"] ?? 0);

    if (!$comanda) {
        $mensagem = "Não há comanda aberta para esta mesa.";
    } elseif ($produto_id <= 0 || $quantidade <= 0) {
        $mensagem = "Selecione um produto e informe uma quantidade válida.";
    } else {
        $sqlProduto = "
            SELECT * 
            FROM produtos
            WHERE id = :produto_id
              AND pizzaria_id = :pizzaria_id
              AND ativo = 1
            LIMIT 1
        ";
        $stmtProduto = $pdo->prepare($sqlProduto);
        $stmtProduto->execute([
            ":produto_id" => $produto_id,
            ":pizzaria_id" => $pizzariaId
        ]);
        $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);

        if (!$produto) {
            $mensagem = "Produto inválido.";
        } else {
            $precoUnitario = (float) $produto["preco"];
            $subtotal = $precoUnitario * $quantidade;

            try {
                $pdo->beginTransaction();

                $sqlInsertItem = "
                    INSERT INTO comanda_itens
                    (comanda_id, produto_id, quantidade, preco_unitario, subtotal)
                    VALUES
                    (:comanda_id, :produto_id, :quantidade, :preco_unitario, :subtotal)
                ";
                $stmtInsertItem = $pdo->prepare($sqlInsertItem);
                $stmtInsertItem->execute([
                    ":comanda_id" => $comanda["id"],
                    ":produto_id" => $produto_id,
                    ":quantidade" => $quantidade,
                    ":preco_unitario" => $precoUnitario,
                    ":subtotal" => $subtotal
                ]);

                $sqlTotal = "
                    SELECT COALESCE(SUM(subtotal), 0) AS total
                    FROM comanda_itens
                    WHERE comanda_id = :comanda_id
                ";
                $stmtTotal = $pdo->prepare($sqlTotal);
                $stmtTotal->execute([
                    ":comanda_id" => $comanda["id"]
                ]);
                $novoTotal = (float) ($stmtTotal->fetch(PDO::FETCH_ASSOC)["total"] ?? 0);

                $sqlUpdateComanda = "
                    UPDATE comandas
                    SET total = :total
                    WHERE id = :comanda_id
                      AND pizzaria_id = :pizzaria_id
                ";
                $stmtUpdateComanda = $pdo->prepare($sqlUpdateComanda);
                $stmtUpdateComanda->execute([
                    ":total" => $novoTotal,
                    ":comanda_id" => $comanda["id"],
                    ":pizzaria_id" => $pizzariaId
                ]);

                $pdo->commit();

                header("Location: mesa.php?token=" . urlencode($token) . "&sucesso=1");
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $mensagem = "Erro ao adicionar item.";
            }
        }
    }
}

if (isset($_GET["sucesso"])) {
    $mensagem = "Item adicionado com sucesso!";
}

/*
|--------------------------------------------------------------------------
| RECARREGAR COMANDA
|--------------------------------------------------------------------------
*/
if ($comanda) {
    $stmtComanda->execute([
        ":mesa_id" => $mesa["id"],
        ":pizzaria_id" => $pizzariaId
    ]);
    $comanda = $stmtComanda->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| BUSCAR PRODUTOS ATIVOS
|--------------------------------------------------------------------------
*/
$sqlProdutos = "
    SELECT * 
    FROM produtos
    WHERE pizzaria_id = :pizzaria_id
      AND ativo = 1
    ORDER BY categoria ASC, nome ASC
";
$stmtProdutos = $pdo->prepare($sqlProdutos);
$stmtProdutos->execute([":pizzaria_id" => $pizzariaId]);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| AGRUPAR PRODUTOS POR CATEGORIA
|--------------------------------------------------------------------------
*/
$produtosPorCategoria = [];

foreach ($produtos as $produto) {
    $categoria = trim($produto["categoria"] ?? "");
    if ($categoria === "") {
        $categoria = "Outros";
    }

    $produtosPorCategoria[$categoria][] = $produto;
}

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
            max-width: 700px;
            margin: auto;
        }

        .topo {
            text-align: center;
            margin-bottom: 20px;
        }

        .topo h1 {
            margin: 0;
        }

        .mensagem {
            background: #1d3b2a;
            color: #c8ffd8;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
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

        .grid-produtos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .produto-card {
            background: #222;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            border: 1px solid #2d2d2d;
        }

        .produto-card .nome {
            font-weight: bold;
            margin-bottom: 6px;
            min-height: 38px;
        }

        .produto-card .preco {
            color: #00ff88;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .produto-card button {
            width: 100%;
            padding: 8px;
            border: none;
            border-radius: 6px;
            background: #2d89ef;
            color: white;
            cursor: pointer;
            font-weight: bold;
        }

        .produto-card button:hover {
            background: #1b5fad;
        }

        .titulo-categoria {
            margin-top: 18px;
            margin-bottom: 8px;
            color: #fff;
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .grid-produtos {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 520px) {
            .grid-produtos {
                grid-template-columns: 1fr;
            }
        }

        /* barra de valor total */
        .barra-total-fixa {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background: #111;
            border-top: 1px solid #2a2a2a;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            z-index: 999;
            box-shadow: 0 -6px 20px rgba(0, 0, 0, 0.35);
        }

        .barra-total-info {
            display: flex;
            flex-direction: column;
        }

        .barra-total-label {
            font-size: 12px;
            color: #aaa;
        }

        .barra-total-valor {
            font-size: 22px;
            font-weight: bold;
            color: #00ff88;
        }

        .botao-fixo {
            display: inline-block;
            text-align: center;
            padding: 12px 18px;
            background: #00c853;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            white-space: nowrap;
        }

        .botao-fixo:hover {
            background: #00a844;
        }

        /* espaço para o conteúdo não ficar escondido atrás da barra */
        .container {
            padding-bottom: 100px;
        }

        @media (max-width: 520px) {
            .barra-total-fixa {
                flex-direction: column;
                align-items: stretch;
            }

            .botao-fixo {
                width: 100%;
                box-sizing: border-box;
            }

            .barra-total-info {
                align-items: center;
            }
        }

        /* menu das categorias */

        .categoria-toggle {
            background: #181818;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            margin-top: 12px;
            overflow: hidden;
        }

        .categoria-toggle summary {
            list-style: none;
            cursor: pointer;
            padding: 14px 16px;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
        }

        .categoria-toggle summary::-webkit-details-marker {
            display: none;
        }

        .categoria-toggle summary::after {
            content: "+";
            font-size: 22px;
            color: #00ff88;
            font-weight: bold;
        }

        .categoria-toggle[open] summary::after {
            content: "−";
        }

        .qtd-itens {
            font-size: 12px;
            color: #aaa;
            font-weight: normal;
            margin-left: 10px;
        }

        .categoria-toggle .grid-produtos {
            padding: 0 14px 14px;
            margin-top: 0;
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="topo">
            <h1>Mesa <?= htmlspecialchars($mesa["numero"]) ?></h1>
            <p>Status: <?= htmlspecialchars($mesa["status"]) ?></p>
        </div>

        <?php if ($mensagem !== ""): ?>
            <div class="mensagem"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <?php if (!$comanda): ?>
            <div class="vazio">
                Nenhuma comanda aberta para esta mesa.
            </div>
        <?php else: ?>

            <div class="card">
                <h3>Cardápio</h3>

                <?php foreach ($produtosPorCategoria as $categoria => $listaProdutos): ?>
                    <details class="categoria-toggle">
                        <summary>
                            <?= htmlspecialchars($categoria) ?>
                            <span class="qtd-itens"><?= count($listaProdutos) ?> itens</span>
                        </summary>

                        <div class="grid-produtos">
                            <?php foreach ($listaProdutos as $produto): ?>
                                <form method="POST" class="produto-card">
                                    <div class="nome"><?= htmlspecialchars($produto["nome"]) ?></div>
                                    <div class="preco">R$ <?= number_format($produto["preco"], 2, ",", ".") ?></div>

                                    <input type="hidden" name="produto_id" value="<?= $produto["id"] ?>">
                                    <input type="hidden" name="quantidade" value="1">

                                    <button type="submit" name="adicionar_item">Adicionar</button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

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

            <div class="barra-total-fixa">
                <div class="barra-total-info">
                    <span class="barra-total-label">Total atual</span>
                    <span class="barra-total-valor">R$ <?= number_format((float) $comanda["total"], 2, ",", ".") ?></span>
                </div>

                <a href="pagar.php?token=<?= urlencode($token) ?>" class="botao-fixo">
                    Pagar agora
                </a>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>