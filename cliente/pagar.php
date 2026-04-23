<?php
require "../config.php";
require "../mp_config.php";

use MercadoPago\Client\Payment\PaymentClient;

function converterStatusMercadoPago(?string $statusMp): string
{
    if ($statusMp === "approved") {
        return "aprovado";
    }

    if (in_array($statusMp, ["rejected", "cancelled"])) {
        return "cancelado";
    }

    return "pendente";
}

/*
|--------------------------------------------------------------------------
| MODO DESENVOLVIMENTO PIX
|--------------------------------------------------------------------------
| true  = não chama Mercado Pago, cria pagamento local para teste
| false = usa integração real
*/
$modoDesenvolvimentoPix = true;

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
| CONSULTAR STATUS NA API (SÓ NO MODO REAL)
|--------------------------------------------------------------------------
*/
if (!$modoDesenvolvimentoPix && $pagamentoExistente && !empty($pagamentoExistente["payment_id"])) {
    try {
        $client = new PaymentClient();
        $paymentMp = $client->get($pagamentoExistente["payment_id"]);

        $statusMp = $paymentMp->status ?? null;
        $statusInterno = converterStatusMercadoPago($statusMp);

        if (
            $statusInterno !== $pagamentoExistente["status"]
            && !($pagamentoExistente["status"] === "aprovado" && $statusInterno !== "aprovado")
        ) {
            $pdo->beginTransaction();

            $sqlAtualizaPagamento = "
                UPDATE pagamentos
                SET status = :status, updated_at = NOW()
                WHERE id = :id
            ";
            $stmtAtualizaPagamento = $pdo->prepare($sqlAtualizaPagamento);
            $stmtAtualizaPagamento->execute([
                ":status" => $statusInterno,
                ":id" => $pagamentoExistente["id"]
            ]);

            if ($statusInterno === "aprovado") {
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
            }

            $pdo->commit();

            $stmtPagamentoExistente->execute([
                ":comanda_id" => $comanda["id"]
            ]);
            $pagamentoExistente = $stmtPagamentoExistente->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        // No modo real, se falhar a consulta, segue com o status salvo no banco.
    }
}

/*
|--------------------------------------------------------------------------
| SE NÃO EXISTE PAGAMENTO, CRIAR NOVO
|--------------------------------------------------------------------------
*/
if ($pagamentoExistente && $pagamentoExistente["status"] !== "cancelado") {
    $pagamento = $pagamentoExistente;
} else {
    $externalReference = "comanda_" . $comanda["id"] . "_mesa_" . $mesa["id"] . "_" . time();

    try {
        if ($modoDesenvolvimentoPix) {
            /*
            |--------------------------------------------------------------------------
            | CRIAR PAGAMENTO LOCAL DE TESTE
            |--------------------------------------------------------------------------
            */
            $qrCodeFake = "PIX-TESTE-COMANDA-" . $comanda["id"] . "-MESA-" . $mesa["id"] . "-VALOR-" . number_format((float) $comanda["total"], 2, ".", "");
            $qrCodeBase64Fake = null;

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
                    'pendente',
                    :qr_code,
                    :qr_code_base64
                )
            ";

            $stmtInsertPagamento = $pdo->prepare($sqlInsertPagamento);
            $stmtInsertPagamento->execute([
                ":comanda_id" => $comanda["id"],
                ":payment_id" => "teste_" . time(),
                ":external_reference" => $externalReference,
                ":valor" => $comanda["total"],
                ":qr_code" => $qrCodeFake,
                ":qr_code_base64" => $qrCodeBase64Fake
            ]);

            $pagamentoIdInterno = $pdo->lastInsertId();

            $sqlBuscaPagamento = "SELECT * FROM pagamentos WHERE id = :id LIMIT 1";
            $stmtBuscaPagamento = $pdo->prepare($sqlBuscaPagamento);
            $stmtBuscaPagamento->execute([":id" => $pagamentoIdInterno]);
            $pagamento = $stmtBuscaPagamento->fetch(PDO::FETCH_ASSOC);
        } else {
            /*
            |--------------------------------------------------------------------------
            | CRIAÇÃO REAL VIA MERCADO PAGO
            |--------------------------------------------------------------------------
            */
            $request = [
                "transaction_amount" => round((float) $comanda["total"], 2),
                "description" => "Pagamento da comanda #" . $comanda["id"],
                "payment_method_id" => "pix",
                "external_reference" => $externalReference,
                "notification_url" => "https://immiscible-katelynn-chorographically.ngrok-free.dev/pizzaria_qr/webhook.php",
                "payer" => [
                    "email" => "testuser7885632883123701561@testuser.com",
                    "identification" => [
                        "type" => "CPF",
                        "number" => "12345678909"
                    ]
                ]
            ];

            $accessToken = "TEST-2347149998456921-021510-6444a9902d0d627742a79d7379dfa886-2040942626";

            $ch = curl_init("https://api.mercadopago.com/v1/payments");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . $accessToken,
                    "Content-Type: application/json",
                    "X-Idempotency-Key: " . uniqid("pix_", true)
                ],
                CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_UNICODE)
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || $curlError) {
                throw new Exception("Erro cURL ao criar pagamento: " . $curlError);
            }

            $payment = json_decode($response, true);

            if (!is_array($payment)) {
                throw new Exception("Resposta inválida da API do Mercado Pago: " . $response);
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new Exception("Erro ao criar pagamento. HTTP {$httpCode}: " . json_encode($payment, JSON_UNESCAPED_UNICODE));
            }

            $paymentId = $payment["id"] ?? null;
            $statusMp = $payment["status"] ?? "pending";
            $statusInterno = converterStatusMercadoPago($statusMp);

            $qrCode = $payment["point_of_interaction"]["transaction_data"]["qr_code"] ?? null;
            $qrCodeBase64 = $payment["point_of_interaction"]["transaction_data"]["qr_code_base64"] ?? null;

            if (!$paymentId) {
                throw new Exception("Pagamento criado sem ID.");
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
        }
    } catch (Throwable $e) {
        echo "<pre>";
        echo "Erro ao criar pagamento.\n\n";
        var_dump($e->getMessage());
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

        $sqlUpdate = "
            UPDATE pagamentos
            SET status = 'aprovado', updated_at = NOW()
            WHERE id = :id
        ";
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

        .sucesso-pagamento {
            text-align: center;
        }

        .icone-sucesso {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #00c853;
            color: #fff;
            font-size: 42px;
            line-height: 72px;
            margin: 0 auto 18px;
            font-weight: bold;
        }

        .valor-sucesso {
            font-size: 30px;
            font-weight: bold;
            color: #00ff88;
            margin: 14px 0;
        }

        .botao-sucesso {
            display: inline-block;
            margin-top: 16px;
            padding: 14px 20px;
            background: #00c853;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .botao-sucesso:hover {
            background: #00a844;
        }

        .aviso-dev {
            text-align: center;
            background: #2b2413;
            color: #ffe9a6;
            border: 1px solid #5f4d14;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Pagamento via Pix</h1>

        <?php if ($mensagem): ?>
            <div class="card"><?= htmlspecialchars($mensagem) ?></div>
        <?php elseif ($pagamento): ?>

            <?php if ($pagamento["status"] === "aprovado"): ?>
                <div class="card sucesso-pagamento">
                    <div class="icone-sucesso">✓</div>
                    <h2>Pagamento confirmado</h2>
                    <p>Mesa <?= htmlspecialchars($mesa["numero"]) ?></p>
                    <p>Comanda #<?= htmlspecialchars($comanda["id"]) ?></p>
                    <p class="valor-sucesso">R$ <?= number_format($comanda["total"], 2, ",", ".") ?></p>
                    <p>Obrigado pelo seu pedido. Sua mesa foi liberada com sucesso.</p>

                    <a class="botao-sucesso" href="mesa.php?token=<?= urlencode($token) ?>">
                        Voltar para a mesa
                    </a>
                </div>
            <?php else: ?>
                <div class="card">
                    <p>Mesa <?= htmlspecialchars($mesa["numero"]) ?></p>
                    <p>Comanda #<?= htmlspecialchars($comanda["id"]) ?></p>
                    <div class="total">R$ <?= number_format($comanda["total"], 2, ",", ".") ?></div>
                </div>

                <?php if ($modoDesenvolvimentoPix): ?>
                    <div class="card aviso-dev">
                        <p><strong>Modo desenvolvimento ativo</strong></p>
                        <p>Este pagamento está sendo simulado localmente para testes do sistema.</p>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <p class="status">
                        Status atual:
                        <?php if ($pagamento["status"] === "cancelado"): ?>
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
                    <?php else: ?>
                        <div class="card aviso-dev">
                            <p><strong>QR Code de teste local</strong></p>
                            <p>Use o botão abaixo para simular a confirmação do pagamento.</p>
                        </div>
                    <?php endif; ?>

                    <p>Copia e cola Pix:</p>
                    <textarea class="codigo" readonly><?= htmlspecialchars($pagamento["qr_code"] ?? "") ?></textarea>

                    <form method="POST">
                        <button type="submit" name="verificar_pagamento">
                            Atualizar pagamento
                        </button>
                    </form>
                </div>

                <a class="link-voltar" href="mesa.php?token=<?= urlencode($token) ?>">Voltar para a mesa</a>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <script>
        let pausadoPagamento = false;

        document.addEventListener("visibilitychange", function () {
            pausadoPagamento = document.hidden;
        });

        setInterval(function () {
            if (!pausadoPagamento) {
                window.location.reload();
            }
        }, 8000);
    </script>
</body>
</html>