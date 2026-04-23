<?php
require "../config.php";
require "auth.php";
require "layout/header.php";

/*
|--------------------------------------------------------------------------
| FILTRO DE FATURAMENTO
|--------------------------------------------------------------------------
*/
$filtroFaturamento = $_GET["faturamento"] ?? "mensal";
$tituloFaturamento = "Faturamento mensal";

$sqlFaturamentoPeriodo = "";
$paramsFaturamentoPeriodo = [":pizzaria_id" => $pizzariaId];

if ($filtroFaturamento === "semanal") {
    $tituloFaturamento = "Faturamento semanal";

    $sqlFaturamentoPeriodo = "
        SELECT COALESCE(SUM(total), 0) AS total_periodo
        FROM comandas
        WHERE pizzaria_id = :pizzaria_id
          AND status IN ('fechada', 'paga')
          AND YEARWEEK(updated_at, 1) = YEARWEEK(CURDATE(), 1)
    ";
} elseif ($filtroFaturamento === "anual") {
    $tituloFaturamento = "Faturamento anual";

    $sqlFaturamentoPeriodo = "
        SELECT COALESCE(SUM(total), 0) AS total_periodo
        FROM comandas
        WHERE pizzaria_id = :pizzaria_id
          AND status IN ('fechada', 'paga')
          AND YEAR(updated_at) = YEAR(CURDATE())
    ";
} else {
    $filtroFaturamento = "mensal";
    $tituloFaturamento = "Faturamento mensal";

    $sqlFaturamentoPeriodo = "
        SELECT COALESCE(SUM(total), 0) AS total_periodo
        FROM comandas
        WHERE pizzaria_id = :pizzaria_id
          AND status IN ('fechada', 'paga')
          AND MONTH(updated_at) = MONTH(CURDATE())
          AND YEAR(updated_at) = YEAR(CURDATE())
    ";
}

$stmtFaturamentoPeriodo = $pdo->prepare($sqlFaturamentoPeriodo);
$stmtFaturamentoPeriodo->execute($paramsFaturamentoPeriodo);
$faturamentoPeriodo = $stmtFaturamentoPeriodo->fetch(PDO::FETCH_ASSOC);
$valorFaturamentoPeriodo = (float) ($faturamentoPeriodo["total_periodo"] ?? 0);

/*
|--------------------------------------------------------------------------
| CONTADORES DE MESAS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status = 'livre' THEN 1 ELSE 0 END) AS livres,
        SUM(CASE WHEN status = 'ocupada' THEN 1 ELSE 0 END) AS ocupadas
    FROM mesas
    WHERE pizzaria_id = :pizzaria_id
");
$stmt->execute([":pizzaria_id" => $pizzariaId]);
$mesas = $stmt->fetch(PDO::FETCH_ASSOC);

$totalLivres = $mesas["livres"] ?? 0;
$totalOcupadas = $mesas["ocupadas"] ?? 0;

/*
|--------------------------------------------------------------------------
| COMANDAS ABERTAS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM comandas
    WHERE pizzaria_id = :pizzaria_id
      AND status = 'aberta'
");
$stmt->execute([":pizzaria_id" => $pizzariaId]);
$totalComandasAbertas = $stmt->fetchColumn() ?? 0;

/*
|--------------------------------------------------------------------------
| FATURAMENTO TOTAL
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0)
    FROM comandas
    WHERE pizzaria_id = :pizzaria_id
      AND status IN ('fechada', 'paga')
");
$stmt->execute([":pizzaria_id" => $pizzariaId]);
$faturamentoTotal = (float) ($stmt->fetchColumn() ?? 0);

/*
|--------------------------------------------------------------------------
| FATURAMENTO DE HOJE
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0)
    FROM comandas
    WHERE pizzaria_id = :pizzaria_id
      AND status IN ('fechada', 'paga')
      AND DATE(updated_at) = CURDATE()
");
$stmt->execute([":pizzaria_id" => $pizzariaId]);
$faturamentoHoje = (float) ($stmt->fetchColumn() ?? 0);

/*
|--------------------------------------------------------------------------
| ÚLTIMAS COMANDAS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.status,
        c.total,
        c.updated_at,
        m.numero AS numero_mesa
    FROM comandas c
    INNER JOIN mesas m ON c.mesa_id = m.id
    WHERE c.pizzaria_id = :pizzaria_id
    ORDER BY c.updated_at DESC
    LIMIT 5
");
$stmt->execute([":pizzaria_id" => $pizzariaId]);
$ultimasComandas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .page-card {
        margin-bottom: 20px;
        background: #fff;
        padding: 28px;
        border-radius: 18px;
        box-shadow: 0 0 18px rgba(0, 0, 0, 0.06);
        color: #222;
    }

    .page-card h1 {
        margin: 0 0 6px;
        font-size: 38px;
    }

    .page-card p {
        margin: 0;
        font-size: 18px;
        color: #444;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }

    .dashboard-card {
        background: #fff;
        border-radius: 14px;
        padding: 20px;
        min-width: 235px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.08);
        color: #222;
    }

    .dashboard-card h3 {
        margin: 0 0 10px;
        font-size: 16px;
        color: #555;
    }

    .dashboard-card .valor {
        font-size: 28px;
        font-weight: bold;
        color: #111;
    }

    .dashboard-card .valor.money {
        color: #1a7f37;
    }

    .dashboard-section {
        background: #fff;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.08);
        color: #222;
    }

    .dashboard-section h2 {
        margin-top: 0;
        margin-bottom: 20px;
    }

    .dashboard-section table {
        width: 100%;
        border-collapse: collapse;
    }

    .dashboard-section th,
    .dashboard-section td {
        padding: 12px;
        border-bottom: 1px solid #ddd;
        text-align: left;
    }

    .dashboard-section th {
        background: #f1f1f1;
    }

    .status-aberta {
        color: orange;
        font-weight: bold;
    }

    .status-fechada {
        color: #6c757d;
        font-weight: bold;
    }

    .status-paga {
        color: green;
        font-weight: bold;
    }

    .vazio {
        padding: 18px;
        border: 1px dashed #ccc;
        border-radius: 10px;
        background: #fafafa;
    }

    .card-topo {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .card-topo h3 {
        margin: 0;
        font-size: 16px;
        color: #555;
    }

    .filtro-faturamento select {
        padding: 8px 10px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 14px;
        background: #fff;
        cursor: pointer;
    }

    @media (max-width: 768px) {
        .card-topo {
            flex-direction: column;
            align-items: flex-start;
        }

        .filtro-faturamento select {
            width: 100%;
        }

        .dashboard-section table {
            font-size: 14px;
        }
    }
</style>

<div class="page-card">
    <h1>Dashboard</h1>
    <p>Visão geral da pizzaria.</p>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h3>Mesas livres</h3>
        <div class="valor"><?= $totalLivres ?></div>
    </div>

    <div class="dashboard-card">
        <h3>Mesas ocupadas</h3>
        <div class="valor"><?= $totalOcupadas ?></div>
    </div>

    <div class="dashboard-card">
        <h3>Comandas abertas</h3>
        <div class="valor"><?= $totalComandasAbertas ?></div>
    </div>

    <div class="dashboard-card">
        <h3>Faturamento total</h3>
        <div class="valor money">R$ <?= number_format($faturamentoTotal, 2, ",", ".") ?></div>
    </div>

    <div class="dashboard-card">
        <h3>Faturamento de hoje</h3>
        <div class="valor money">R$ <?= number_format($faturamentoHoje, 2, ",", ".") ?></div>
    </div>

    <div class="dashboard-card">
        <div class="card-topo">
            <h3><?= htmlspecialchars($tituloFaturamento) ?></h3>

            <form method="GET" class="filtro-faturamento">
                <select name="faturamento" onchange="this.form.submit()">
                    <option value="semanal" <?= $filtroFaturamento === "semanal" ? "selected" : "" ?>>Semanal</option>
                    <option value="mensal" <?= $filtroFaturamento === "mensal" ? "selected" : "" ?>>Mensal</option>
                    <option value="anual" <?= $filtroFaturamento === "anual" ? "selected" : "" ?>>Anual</option>
                </select>
            </form>
        </div>

        <div class="valor money">R$ <?= number_format($valorFaturamentoPeriodo, 2, ",", ".") ?></div>
    </div>
</div>

<div class="dashboard-section">
    <h2>Últimas comandas</h2>

    <?php if (count($ultimasComandas) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Mesa</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Atualizada em</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ultimasComandas as $comanda): ?>
                    <?php
                    $classeStatus = "";
                    if ($comanda["status"] === "aberta") $classeStatus = "status-aberta";
                    elseif ($comanda["status"] === "fechada") $classeStatus = "status-fechada";
                    elseif ($comanda["status"] === "paga") $classeStatus = "status-paga";
                    ?>
                    <tr>
                        <td><?= $comanda["id"] ?></td>
                        <td>Mesa <?= htmlspecialchars($comanda["numero_mesa"]) ?></td>
                        <td><span class="<?= $classeStatus ?>"><?= htmlspecialchars($comanda["status"]) ?></span></td>
                        <td>R$ <?= number_format($comanda["total"], 2, ",", ".") ?></td>
                        <td><?= date("d/m/Y H:i", strtotime($comanda["updated_at"])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="vazio">Nenhuma comanda registrada ainda.</div>
    <?php endif; ?>
</div>

<?php require "layout/footer.php"; ?>