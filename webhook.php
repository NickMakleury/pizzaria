<?php
require "config.php";
require "mp_config.php";

use MercadoPago\Client\Payment\PaymentClient;

/*
|--------------------------------------------------------------------------
| LOG SIMPLES
|--------------------------------------------------------------------------
*/
function registrarLogWebhook(string $mensagem): void
{
    $arquivo = __DIR__ . "/webhook_log.txt";
    $linha = "[" . date("Y-m-d H:i:s") . "] " . $mensagem . PHP_EOL;
    file_put_contents($arquivo, $linha, FILE_APPEND);
}

/*
|--------------------------------------------------------------------------
| RECEBER PAYLOAD
|--------------------------------------------------------------------------
*/
$payloadBruto = file_get_contents("php://input");
$data = json_decode($payloadBruto, true);

registrarLogWebhook("Payload recebido: " . $payloadBruto);

if (!isset($data["data"]["id"])) {
    http_response_code(200);
    registrarLogWebhook("Webhook sem payment id.");
    exit("Sem ID");
}

$paymentId = $data["data"]["id"];

try {
    $client = new PaymentClient();

    /*
    |--------------------------------------------------------------------------
    | BUSCAR PAGAMENTO NO MERCADO PAGO
    |--------------------------------------------------------------------------
    */
    $payment = $client->get($paymentId);

    $statusMp = $payment->status ?? "";
    $externalReference = $payment->external_reference ?? null;

    registrarLogWebhook("Payment ID {$paymentId} consultado. Status MP: {$statusMp}");

    /*
    |--------------------------------------------------------------------------
    | CONVERTER STATUS
    |--------------------------------------------------------------------------
    */
    $statusInterno = "pendente";

    if ($statusMp === "approved") {
        $statusInterno = "aprovado";
    } elseif (in_array($statusMp, ["rejected", "cancelled"])) {
        $statusInterno = "cancelado";
    }

    /*
    |--------------------------------------------------------------------------
    | BUSCAR PAGAMENTO NO BANCO
    |--------------------------------------------------------------------------
    */
    $sqlPagamento = "SELECT * FROM pagamentos WHERE payment_id = :payment_id LIMIT 1";
    $stmtPagamento = $pdo->prepare($sqlPagamento);
    $stmtPagamento->execute([":payment_id" => $paymentId]);
    $pagamento = $stmtPagamento->fetch(PDO::FETCH_ASSOC);

    if (!$pagamento) {
        registrarLogWebhook("Pagamento {$paymentId} não encontrado no banco.");
        http_response_code(200);
        exit("Pagamento não encontrado");
    }

    /*
    |--------------------------------------------------------------------------
    | BUSCAR COMANDA RELACIONADA
    |--------------------------------------------------------------------------
    */
    $sqlComanda = "SELECT * FROM comandas WHERE id = :comanda_id LIMIT 1";
    $stmtComanda = $pdo->prepare($sqlComanda);
    $stmtComanda->execute([":comanda_id" => $pagamento["comanda_id"]]);
    $comanda = $stmtComanda->fetch(PDO::FETCH_ASSOC);

    if (!$comanda) {
        registrarLogWebhook("Comanda {$pagamento["comanda_id"]} não encontrada.");
        http_response_code(200);
        exit("Comanda não encontrada");
    }

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | ATUALIZAR PAGAMENTO
    |--------------------------------------------------------------------------
    */
    $sqlUpdatePagamento = "
        UPDATE pagamentos
        SET status = :status, updated_at = NOW()
        WHERE id = :id
    ";
    $stmtUpdatePagamento = $pdo->prepare($sqlUpdatePagamento);
    $stmtUpdatePagamento->execute([
        ":status" => $statusInterno,
        ":id" => $pagamento["id"]
    ]);

    /*
    |--------------------------------------------------------------------------
    | SE APROVADO, MARCAR COMANDA E LIBERAR MESA
    |--------------------------------------------------------------------------
    */
    if ($statusInterno === "aprovado") {
        $sqlUpdateComanda = "
            UPDATE comandas
            SET status = 'paga'
            WHERE id = :id
        ";
        $stmtUpdateComanda = $pdo->prepare($sqlUpdateComanda);
        $stmtUpdateComanda->execute([
            ":id" => $comanda["id"]
        ]);

        $sqlUpdateMesa = "
            UPDATE mesas
            SET status = 'livre'
            WHERE id = :mesa_id
        ";
        $stmtUpdateMesa = $pdo->prepare($sqlUpdateMesa);
        $stmtUpdateMesa->execute([
            ":mesa_id" => $comanda["mesa_id"]
        ]);

        registrarLogWebhook("Pagamento {$paymentId} aprovado. Comanda {$comanda["id"]} paga e mesa {$comanda["mesa_id"]} liberada.");
    } else {
        registrarLogWebhook("Pagamento {$paymentId} atualizado para status interno: {$statusInterno}.");
    }

    $pdo->commit();

    http_response_code(200);
    echo "OK";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    registrarLogWebhook("Erro no webhook: " . $e->getMessage());

    http_response_code(500);
    echo "Erro: " . $e->getMessage();
}