<?php
require "../config.php";
require "auth.php";
require "layout/header.php";

$mensagem = "";

/*
|--------------------------------------------------------------------------
| ABRIR COMANDA
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["abrir_comanda"])) {
    $mesa_id = (int) ($_POST["mesa_id"] ?? 0);

    if ($mesa_id <= 0) {
        $mensagem = "Selecione uma mesa válida.";
    } else {
        $sqlVerifica = "
            SELECT id 
            FROM comandas 
            WHERE mesa_id = :mesa_id 
              AND pizzaria_id = :pizzaria_id
              AND status = 'aberta' 
            LIMIT 1
        ";
        $stmtVerifica = $pdo->prepare($sqlVerifica);
        $stmtVerifica->execute([
            ":mesa_id" => $mesa_id,
            ":pizzaria_id" => $pizzariaId
        ]);
        $comandaExistente = $stmtVerifica->fetch(PDO::FETCH_ASSOC);

        if ($comandaExistente) {
            $mensagem = "Essa mesa já possui uma comanda aberta.";
        } else {
            try {
                $pdo->beginTransaction();

                $sqlInsert = "
                    INSERT INTO comandas (mesa_id, pizzaria_id, status, total) 
                    VALUES (:mesa_id, :pizzaria_id, 'aberta', 0.00)
                ";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    ":mesa_id" => $mesa_id,
                    ":pizzaria_id" => $pizzariaId
                ]);

                $sqlMesa = "
                    UPDATE mesas 
                    SET status = 'ocupada' 
                    WHERE id = :mesa_id 
                      AND pizzaria_id = :pizzaria_id
                ";
                $stmtMesa = $pdo->prepare($sqlMesa);
                $stmtMesa->execute([
                    ":mesa_id" => $mesa_id,
                    ":pizzaria_id" => $pizzariaId
                ]);

                $pdo->commit();
                $mensagem = "Comanda aberta com sucesso!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = "Erro ao abrir a comanda.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FECHAR COMANDA
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["fechar_comanda"])) {
    $comanda_id = (int) ($_POST["comanda_id"] ?? 0);

    if ($comanda_id <= 0) {
        $mensagem = "Comanda inválida.";
    } else {
        $sqlBusca = "
            SELECT * 
            FROM comandas 
            WHERE id = :comanda_id 
              AND pizzaria_id = :pizzaria_id
              AND status = 'aberta' 
            LIMIT 1
        ";
        $stmtBusca = $pdo->prepare($sqlBusca);
        $stmtBusca->execute([
            ":comanda_id" => $comanda_id,
            ":pizzaria_id" => $pizzariaId
        ]);
        $comanda = $stmtBusca->fetch(PDO::FETCH_ASSOC);

        if (!$comanda) {
            $mensagem = "Comanda não encontrada ou já foi encerrada.";
        } else {
            try {
                $pdo->beginTransaction();

                $sqlFecha = "
                    UPDATE comandas 
                    SET status = 'fechada' 
                    WHERE id = :comanda_id
                      AND pizzaria_id = :pizzaria_id
                ";
                $stmtFecha = $pdo->prepare($sqlFecha);
                $stmtFecha->execute([
                    ":comanda_id" => $comanda_id,
                    ":pizzaria_id" => $pizzariaId
                ]);

                $sqlMesaLivre = "
                    UPDATE mesas 
                    SET status = 'livre' 
                    WHERE id = :mesa_id 
                      AND pizzaria_id = :pizzaria_id
                ";
                $stmtMesaLivre = $pdo->prepare($sqlMesaLivre);
                $stmtMesaLivre->execute([
                    ":mesa_id" => $comanda["mesa_id"],
                    ":pizzaria_id" => $pizzariaId
                ]);

                $pdo->commit();
                $mensagem = "Comanda fechada e mesa liberada com sucesso!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = "Erro ao fechar a comanda.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| BUSCAR MESAS LIVRES
|--------------------------------------------------------------------------
*/
$sqlMesasLivres = "
    SELECT * 
    FROM mesas 
    WHERE status = 'livre' 
      AND pizzaria_id = :pizzaria_id 
    ORDER BY numero ASC
";
$stmtMesasLivres = $pdo->prepare($sqlMesasLivres);
$stmtMesasLivres->execute([":pizzaria_id" => $pizzariaId]);
$mesasLivres = $stmtMesasLivres->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| LISTAR COMANDAS ABERTAS
|--------------------------------------------------------------------------
*/
$sqlComandas = "
    SELECT 
        comandas.id,
        comandas.mesa_id,
        comandas.status,
        comandas.total,
        comandas.created_at,
        mesas.numero AS numero_mesa
    FROM comandas
    INNER JOIN mesas ON comandas.mesa_id = mesas.id
    WHERE comandas.status = 'aberta' 
      AND comandas.pizzaria_id = :pizzaria_id
    ORDER BY comandas.created_at DESC
";
$stmtComandas = $pdo->prepare($sqlComandas);
$stmtComandas->execute([":pizzaria_id" => $pizzariaId]);
$comandas = $stmtComandas->fetchAll(PDO::FETCH_ASSOC);
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

    .form-abertura {
        margin-bottom: 30px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: end;
    }

    .campo {
        display: flex;
        flex-direction: column;
    }

    .campo label {
        margin-bottom: 6px;
        font-weight: bold;
        color: #222;
    }

    .page-card select,
    .page-card button {
        padding: 10px;
        font-size: 15px;
    }

    .page-card button {
        border: none;
        color: white;
        border-radius: 6px;
        cursor: pointer;
        padding: 10px 18px;
    }

    .btn-abrir {
        background: #2d89ef;
    }

    .btn-abrir:hover {
        background: #1b5fad;
    }

    .btn-fechar {
        background: #dc3545;
    }

    .btn-fechar:hover {
        background: #b02a37;
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
        vertical-align: middle;
        color: #222;
    }

    .page-card th {
        background: #f1f1f1;
    }

    .status-aberta {
        color: orange;
        font-weight: bold;
    }

    .total {
        font-weight: bold;
        color: #1a7f37;
    }

    .vazio {
        padding: 20px;
        background: #fafafa;
        border: 1px dashed #ccc;
        border-radius: 8px;
        color: #222;
    }

    .acao-link {
        display: inline-block;
        text-decoration: none;
        background: #198754;
        color: #fff;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 14px;
        margin-right: 6px;
    }

    .acao-link:hover {
        background: #146c43;
    }

    .acoes {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .form-fechar {
        margin: 0;
    }

    @media (max-width: 768px) {
        .form-abertura {
            flex-direction: column;
            align-items: stretch;
        }

        .page-card select,
        .page-card button {
            width: 100%;
        }

        .page-card table {
            font-size: 14px;
        }

        .acoes {
            flex-direction: column;
        }

        .acao-link {
            margin-right: 0;
            text-align: center;
        }
    }
</style>

<div class="page-card">
    <h1>Abertura de Comandas</h1>

    <?php if ($mensagem != ""): ?>
        <div class="mensagem"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="POST" class="form-abertura">
        <div class="campo">
            <label for="mesa_id">Mesa disponível</label>
            <select name="mesa_id" id="mesa_id" required>
                <option value="">Selecione uma mesa</option>
                <?php foreach ($mesasLivres as $mesa): ?>
                    <option value="<?= $mesa["id"] ?>">
                        Mesa <?= htmlspecialchars($mesa["numero"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <button class="btn-abrir" type="submit" name="abrir_comanda">Abrir Comanda</button>
        </div>
    </form>

    <h2>Comandas abertas</h2>

    <?php if (count($comandas) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID da Comanda</th>
                    <th>Mesa</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Data de abertura</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comandas as $comanda): ?>
                    <tr>
                        <td><?= $comanda["id"] ?></td>
                        <td>Mesa <?= htmlspecialchars($comanda["numero_mesa"]) ?></td>
                        <td><span class="status-aberta"><?= htmlspecialchars($comanda["status"]) ?></span></td>
                        <td class="total">R$ <?= number_format($comanda["total"], 2, ",", ".") ?></td>
                        <td><?= date("d/m/Y H:i", strtotime($comanda["created_at"])) ?></td>
                        <td>
                            <div class="acoes">
                                <a class="acao-link" href="itens_comanda.php?comanda_id=<?= $comanda["id"] ?>">
                                    Lançar itens
                                </a>

                                <form class="form-fechar" method="POST" onsubmit="return confirm('Deseja realmente fechar esta comanda e liberar a mesa?');">
                                    <input type="hidden" name="comanda_id" value="<?= $comanda["id"] ?>">
                                    <button class="btn-fechar" type="submit" name="fechar_comanda">
                                        Fechar comanda
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="vazio">Nenhuma comanda aberta no momento.</div>
    <?php endif; ?>
</div>

<?php require "layout/footer.php"; ?>