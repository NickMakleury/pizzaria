<?php
require "../config.php";
require "auth.php";
require "layout/header.php";

$mensagem = "";
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
    WHERE comandas.id = :comanda_id 
      AND comandas.pizzaria_id = :pizzaria_id 
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
| BLOQUEAR COMANDA ENCERRADA
|--------------------------------------------------------------------------
*/
if ($comanda["status"] !== "aberta") {
    die("Esta comanda não pode mais receber itens.");
}

/*
|--------------------------------------------------------------------------
| ADICIONAR ITEM
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["adicionar_item"])) {
    $produto_id = (int) ($_POST["produto_id"] ?? 0);
    $quantidade = (int) ($_POST["quantidade"] ?? 0);

    if ($produto_id <= 0 || $quantidade <= 0) {
        $mensagem = "Selecione um produto e informe uma quantidade válida.";
    } else {
        $sqlProduto = "
            SELECT * 
            FROM produtos 
            WHERE id = :produto_id 
              AND ativo = 1 
              AND pizzaria_id = :pizzaria_id
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
            $preco_unitario = (float) $produto["preco"];
            $subtotal = $preco_unitario * $quantidade;

            try {
                $pdo->beginTransaction();

                $sqlInsert = "
                    INSERT INTO comanda_itens 
                    (comanda_id, produto_id, quantidade, preco_unitario, subtotal)
                    VALUES
                    (:comanda_id, :produto_id, :quantidade, :preco_unitario, :subtotal)
                ";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    ":comanda_id" => $comanda_id,
                    ":produto_id" => $produto_id,
                    ":quantidade" => $quantidade,
                    ":preco_unitario" => $preco_unitario,
                    ":subtotal" => $subtotal
                ]);

                $sqlTotal = "
                    SELECT COALESCE(SUM(subtotal), 0) AS total 
                    FROM comanda_itens 
                    WHERE comanda_id = :comanda_id
                ";
                $stmtTotal = $pdo->prepare($sqlTotal);
                $stmtTotal->execute([":comanda_id" => $comanda_id]);
                $resultadoTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);
                $novoTotal = (float) ($resultadoTotal["total"] ?? 0);

                $sqlUpdateComanda = "
                    UPDATE comandas 
                    SET total = :total 
                    WHERE id = :comanda_id
                      AND pizzaria_id = :pizzaria_id
                ";
                $stmtUpdateComanda = $pdo->prepare($sqlUpdateComanda);
                $stmtUpdateComanda->execute([
                    ":total" => $novoTotal,
                    ":comanda_id" => $comanda_id,
                    ":pizzaria_id" => $pizzariaId
                ]);

                $pdo->commit();

                header("Location: itens_comanda.php?comanda_id=" . $comanda_id . "&sucesso=1");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = "Erro ao lançar item.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| MENSAGEM DE SUCESSO VIA GET
|--------------------------------------------------------------------------
*/
if (isset($_GET["sucesso"])) {
    $mensagem = "Item lançado com sucesso!";
}

/*
|--------------------------------------------------------------------------
| BUSCAR PRODUTOS ATIVOS
|--------------------------------------------------------------------------
*/
$sqlProdutos = "
    SELECT * 
    FROM produtos 
    WHERE ativo = 1 
      AND pizzaria_id = :pizzaria_id 
    ORDER BY nome ASC
";
$stmtProdutos = $pdo->prepare($sqlProdutos);
$stmtProdutos->execute([":pizzaria_id" => $pizzariaId]);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

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

/*
|--------------------------------------------------------------------------
| RECARREGAR TOTAL ATUAL DA COMANDA
|--------------------------------------------------------------------------
*/
$sqlComandaAtual = "
    SELECT total 
    FROM comandas 
    WHERE id = :comanda_id 
      AND pizzaria_id = :pizzaria_id
    LIMIT 1
";
$stmtComandaAtual = $pdo->prepare($sqlComandaAtual);
$stmtComandaAtual->execute([
    ":comanda_id" => $comanda_id,
    ":pizzaria_id" => $pizzariaId
]);
$comandaAtual = $stmtComandaAtual->fetch(PDO::FETCH_ASSOC);
$totalComanda = (float) ($comandaAtual["total"] ?? 0);
?>

<style>
    .page-card {
        max-width: 1100px;
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

    .topo {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 25px;
    }

    .card-info {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 16px;
        min-width: 220px;
        color: #222;
    }

    .card-info strong {
        display: block;
        margin-bottom: 8px;
    }

    .total-geral {
        font-size: 24px;
        color: #1a7f37;
        font-weight: bold;
    }

    .form-itens {
        margin-bottom: 30px;
        display: grid;
        grid-template-columns: 1fr 180px auto;
        gap: 12px;
        align-items: end;
    }

    .campo label {
        display: block;
        margin-bottom: 6px;
        font-weight: bold;
        color: #222;
    }

    .campo select,
    .campo input {
        width: 100%;
        padding: 10px;
        font-size: 15px;
        box-sizing: border-box;
    }

    .page-card button {
        padding: 10px 18px;
        font-size: 16px;
        border: none;
        background: #2d89ef;
        color: white;
        border-radius: 6px;
        cursor: pointer;
        height: 42px;
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
        color: #222;
    }

    @media (max-width: 768px) {
        .form-itens {
            grid-template-columns: 1fr;
        }

        .page-card button {
            width: 100%;
        }

        .page-card table {
            font-size: 14px;
        }

        .topo {
            flex-direction: column;
        }
    }
</style>

<div class="page-card">
    <h1>Itens da Comanda</h1>

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
            <strong>Total atual</strong>
            <span class="total-geral">R$ <?= number_format($totalComanda, 2, ",", ".") ?></span>
        </div>
    </div>

    <?php if ($mensagem != ""): ?>
        <div class="mensagem"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="POST" class="form-itens">
        <div class="campo">
            <label for="produto_id">Produto</label>
            <select name="produto_id" id="produto_id" required>
                <option value="">Selecione um produto</option>
                <?php foreach ($produtos as $produto): ?>
                    <option value="<?= $produto["id"] ?>">
                        <?= htmlspecialchars($produto["nome"]) ?> — <?= htmlspecialchars($produto["categoria"]) ?> — R$ <?= number_format($produto["preco"], 2, ",", ".") ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="quantidade">Quantidade</label>
            <input type="number" name="quantidade" id="quantidade" min="1" required>
        </div>

        <div class="campo">
            <button type="submit" name="adicionar_item">Adicionar Item</button>
        </div>
    </form>

    <h2>Itens lançados</h2>

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
        <div class="vazio">Nenhum item lançado nesta comanda ainda.</div>
    <?php endif; ?>

    <a class="voltar" href="comandas.php">Voltar para comandas</a>
</div>

<?php require "layout/footer.php"; ?>