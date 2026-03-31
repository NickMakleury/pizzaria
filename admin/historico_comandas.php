<?php
require "../config.php";
require "auth.php";
require "layout/header.php";

/*
|--------------------------------------------------------------------------
| LISTAR COMANDAS FECHADAS
|--------------------------------------------------------------------------
*/
$sqlHistorico = "
    SELECT
        comandas.id,
        comandas.status,
        comandas.total,
        comandas.created_at,
        comandas.updated_at,
        mesas.numero AS numero_mesa
    FROM comandas
    INNER JOIN mesas ON comandas.mesa_id = mesas.id
    WHERE comandas.status IN ('fechada', 'paga')
      AND comandas.pizzaria_id = :pizzaria_id
    ORDER BY comandas.updated_at DESC, comandas.id DESC
";
$stmtHistorico = $pdo->prepare($sqlHistorico);
$stmtHistorico->execute([":pizzaria_id" => $pizzariaId]);
$comandas = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TOTAL GERAL DAS COMANDAS FECHADAS
|--------------------------------------------------------------------------
*/
$sqlTotal = "
    SELECT SUM(total) AS total_geral 
    FROM comandas 
    WHERE status IN ('fechada', 'paga') 
      AND pizzaria_id = :pizzaria_id
";
$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute([":pizzaria_id" => $pizzariaId]);
$totalGeral = $stmtTotal->fetch(PDO::FETCH_ASSOC);
$valorTotalGeral = $totalGeral["total_geral"] ?? 0;
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

    .resumo {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 25px;
    }

    .card-resumo {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 16px;
        min-width: 220px;
        color: #222;
    }

    .card-resumo strong {
        display: block;
        margin-bottom: 8px;
    }

    .valor {
        font-size: 24px;
        font-weight: bold;
        color: #1a7f37;
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
        vertical-align: middle;
        color: #222;
    }

    .page-card th {
        background: #f1f1f1;
    }

    .total {
        font-weight: bold;
        color: #1a7f37;
    }

    .acao-link {
        display: inline-block;
        text-decoration: none;
        background: #2d89ef;
        color: #fff;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 14px;
    }

    .acao-link:hover {
        background: #1b5fad;
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
        .page-card table {
            font-size: 14px;
        }

        .resumo {
            flex-direction: column;
        }
    }
</style>

<div class="page-card">
    <h1>Histórico de Comandas</h1>

    <div class="resumo">
        <div class="card-resumo">
            <strong>Total de comandas fechadas</strong>
            <?= count($comandas) ?>
        </div>

        <div class="card-resumo">
            <strong>Valor total acumulado</strong>
            <span class="valor">R$ <?= number_format($valorTotalGeral, 2, ",", ".") ?></span>
        </div>
    </div>

    <h2>Comandas encerradas</h2>

    <?php if (count($comandas) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID da Comanda</th>
                    <th>Mesa</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Aberta em</th>
                    <th>Fechada em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comandas as $comanda): ?>
                    <tr>
                        <td><?= $comanda["id"] ?></td>
                        <td>Mesa <?= htmlspecialchars($comanda["numero_mesa"]) ?></td>
                        <td>
                            <?php if ($comanda["status"] === "paga"): ?>
                                <span style="color: green; font-weight: bold;">paga</span>
                            <?php else: ?>
                                <span style="color: #6c757d; font-weight: bold;">fechada</span>
                            <?php endif; ?>
                        </td>
                        <td class="total">R$ <?= number_format($comanda["total"], 2, ",", ".") ?></td>
                        <td><?= date("d/m/Y H:i", strtotime($comanda["created_at"])) ?></td>
                        <td><?= date("d/m/Y H:i", strtotime($comanda["updated_at"])) ?></td>
                        <td>
                            <a class="acao-link" href="ver_comanda.php?comanda_id=<?= $comanda["id"] ?>">
                                Ver itens
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="vazio">Nenhuma comanda fechada ainda.</div>
    <?php endif; ?>

    <a class="voltar" href="comandas.php">Voltar para comandas</a>
</div>

<?php require "layout/footer.php"; ?>