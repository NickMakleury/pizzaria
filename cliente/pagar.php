<?php
require "../config.php";
require "../mp_config.php";

use MercadoPago\Client\Payment\PaymentClient;

$token = $_GET["token"] ?? "";

if ($token === "") {
    die("Token inválido.");
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
| BUSCAR COMANDA DA MESA
|--------------------------------------------------------------------------
*/
$sqlComanda = "
    SELECT * 
    FROM comandas 
    WHERE mesa_id = :mesa_id
      AND pizzaria_id = :pizzaria_id
      AND status IN ('aberta', 'paga')
    ORDER BY id DESC
    LIMIT 1
";
$stmtComanda = $pdo->prepare($sqlComanda);
$stmtComanda->execute([
    ":mesa_id" => $mesa["id"],
    ":pizzaria_id" => $pizzariaId
]);
$comanda = $stmtComanda->fetch(PDO::FETCH_ASSOC);

if (!$comanda) {
    die("Nenhuma comanda encontrada para esta mesa.");
}

/*
|--------------------------------------------------------------------------
| VALIDAR TOTAL DA COMANDA
|--------------------------------------------------------------------------
*/
if ((float) $comanda["total"] <= 0) {
    die("Essa comanda ainda não possui valor para pagamento.");
}

$mensagem = "";
$pagamento = null;

/*
|--------------------------------------------------------------------------
| BUSCAR PAGAMENTO MAIS RECENTE DA COMANDA
|--------------------------------------------------------------------------
*/
$sqlPagamentoExistente = "
    SELECT * 
    FROM pagamentos
    WHERE comanda_id = :comanda_id
    ORDER BY id DESC
    LIMIT 1
";
$stmtPagamentoExistente = $pdo->prepare($sqlPagamentoExistente);
$stmtPagamentoExistente->execute([
    ":comanda_id" => $comanda["id"]
]);
$pagamentoExistente = $stmtPagamentoExistente->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| SE NÃO EXISTE PAGAMENTO, CRIAR NOVO PIX
|--------------------------------------------------------------------------
*/
if ($pagamentoExistente) {
    $pagamento = $pagamentoExistente;
} else {
    $externalReference = "comanda_" . $comanda["id"] . "_mesa_" . $mesa["id"] . "_" . time();

    try {
        $client = new PaymentClient();

        $request = [
            "transaction_amount" => round((float) $comanda["total"], 2),
            "description" => "Pagamento da comanda #" . $comanda["id"],
            "payment_method_id" => "pix",
            "external_reference" => $externalReference,
            "notification_url" => "https://immiscible-katelynn-chorographically.ngrok-free.dev/pizzaria_qr/webhook.php",
            "payer" => [
                "email" => "britodealmeidan@gmail.com"
            ]
        ];

        $payment = $client->create($request);

        $paymentId = $payment->id ?? null;
        $status = $payment->status ?? "pending";
        $qrCode = $payment->point_of_interaction->transaction_data->qr_code ?? null;
        $qrCodeBase64 = $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null;

        $statusInterno = "pendente";
        if ($status === "approved") {
            $statusInterno = "aprovado";
        } elseif (in_array($status, ["rejected", "cancelled"])) {
            $statusInterno = "cancelado";
        }

        $sqlInsertPagamento = "
            INSERT INTO pagamentos
            (
                comanda_id,
                gateway,
                payment_id,
                external_reference,
                metodo,
                valor,
                status,
                qr_code,
                qr_code_base64
            )
            VALUES
            (
                :comanda_id,
                'mercado_pago',
                :payment_id,
                :external_reference,
                'pix',
                :valor,
                :status,
                :qr_code,
                :qr_code_base64
            )
        ";

        $stmtInsertPagamento = $pdo->prepare($sqlInsertPagamento);
        $stmtInsertPagamento->execute([
            ":comanda_id" => $comanda["id"],
            ":payment_id" => $paymentId,
            ":external_reference" => $externalReference,
            ":valor" => $comanda["total"],
            ":status" => $statusInterno,
            ":qr_code" => $qrCode,
            ":qr_code_base64" => $qrCodeBase64
        ]);

        $pagamentoIdInterno = $pdo->lastInsertId();

        $sqlBuscaPagamento = "SELECT * FROM pagamentos WHERE id = :id LIMIT 1";
        $stmtBuscaPagamento = $pdo->prepare($sqlBuscaPagamento);
        $stmtBuscaPagamento->execute([":id" => $pagamentoIdInterno]);
        $pagamento = $stmtBuscaPagamento->fetch(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        echo "<pre>";
        var_dump($e);
        echo "</pre>";
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| ATUALIZAÇÃO MANUAL (SIMULAÇÃO DE DESENVOLVIMENTO)
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["verificar_pagamento"]) && $pagamento) {
    try {
        $pdo->beginTransaction();

        $sqlUpdate = "UPDATE pagamentos SET status = 'aprovado' WHERE id = :id";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([":id" => $pagamento["id"]]);

        $sqlComandaUpdate = "
            UPDATE comandas 
            SET status = 'paga' 
            WHERE id = :id
              AND pizzaria_id = :pizzaria_id
        ";
        $stmtComandaUpdate = $pdo->prepare($sqlComandaUpdate);
        $stmtComandaUpdate->execute([
            ":id" => $comanda["id"],
            ":pizzaria_id" => $pizzariaId
        ]);

        $sqlMesaUpdate = "
            UPDATE mesas 
            SET status = 'livre' 
            WHERE id = :id
              AND pizzaria_id = :pizzaria_id
        ";
        $stmtMesaUpdate = $pdo->prepare($sqlMesaUpdate);
        $stmtMesaUpdate->execute([
            ":id" => $mesa["id"],
            ":pizzaria_id" => $pizzariaId
        ]);

        $pdo->commit();

        header("Location: pagar.php?token=" . urlencode($token));
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao atualizar pagamento.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Pix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #111;
            color: #fff;
            margin: 0;
        }

        .container {
            max-width: 600px;
            margin: auto;
            padding: 20px;
        }

        .card {
            background: #1c1c1c;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .total {
            font-size: 26px;
            font-weight: bold;
            color: #00ff88;
            text-align: center;
        }

        .qr-img {
            display: block;
            margin: 20px auto;
            width: 260px;
            max-width: 100%;
            background: #fff;
            padding: 10px;
            border-radius: 10px;
        }

        .codigo {
            width: 100%;
            min-height: 120px;
            box-sizing: border-box;
            padding: 12px;
            border-radius: 10px;
            border: none;
            resize: vertical;
        }

        .status {
            text-align: center;
            font-weight: bold;
            color: #ffc107;
        }

        .link-voltar {
            display: inline-block;
            margin-top: 12px;
            color: white;
        }

        button {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            background: #00c853;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 15px;
        }

        button:hover {
            background: #00a844;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Pagamento via Pix</h1>

        <?php if ($mensagem): ?>
            <div class="card"><?= htmlspecialchars($mensagem) ?></div>
        <?php elseif ($pagamento): ?>
            <div class="card">
                <p>Mesa <?= htmlspecialchars($mesa["numero"]) ?></p>
                <p>Comanda #<?= htmlspecialchars($comanda["id"]) ?></p>
                <div class="total">R$ <?= number_format($comanda["total"], 2, ",", ".") ?></div>
            </div>

            <div class="card">
                <p class="status">
                    Status atual:
                    <?php if ($pagamento["status"] === "aprovado"): ?>
                        <span style="color: #00ff88;">Pagamento aprovado</span>
                    <?php elseif ($pagamento["status"] === "cancelado"): ?>
                        <span style="color: #ff5252;">Pagamento cancelado</span>
                    <?php else: ?>
                        <span style="color: #ffc107;">Aguardando pagamento</span>
                    <?php endif; ?>
                </p>

                <?php if (!empty($pagamento["qr_code_base64"])): ?>
                    <img
                        class="qr-img"
                        src="data:image/png;base64,<?= htmlspecialchars($pagamento["qr_code_base64"]) ?>"
                        alt="QR Code Pix">
                <?php endif; ?>

                <p>Copia e cola Pix:</p>
                <textarea class="codigo" readonly><?= htmlspecialchars($pagamento["qr_code"] ?? "") ?></textarea>

                <?php if ($pagamento["status"] !== "aprovado"): ?>
                    <form method="POST">
                        <button type="submit" name="verificar_pagamento">
                            Atualizar pagamento
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <a class="link-voltar" href="mesa.php?token=<?= urlencode($token) ?>">Voltar para a mesa</a>
        <?php endif; ?>
    </div>
</body>
</html>