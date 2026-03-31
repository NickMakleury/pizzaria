<?php
require "../config.php";
require "auth.php";
require "layout/header.php";

$sqlMesas = "SELECT * FROM mesas WHERE pizzaria_id = :pizzaria_id ORDER BY numero ASC";
$stmtMesas = $pdo->prepare($sqlMesas);
$stmtMesas->execute([":pizzaria_id" => $pizzariaId]);
$mesas = $stmtMesas->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = "http://localhost/pizzaria_qr/cliente/mesa.php?token=";
?>

<style>
    .page-card {
        max-width: 1200px;
        margin: auto;
        color: #222;
    }

    .page-card h1 {
        margin-bottom: 25px;
    }

    .grid-qrcodes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 20px;
    }

    .qr-card {
        background: white;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 0 10px rgba(0,0,0,0.08);
        text-align: center;
    }

    .qr-card h2 {
        margin-top: 0;
        margin-bottom: 10px;
        color: #222;
    }

    .status {
        margin-bottom: 14px;
        font-weight: bold;
    }

    .livre {
        color: green;
    }

    .ocupada {
        color: orange;
    }

    .aguardando_pagamento {
        color: red;
    }

    .paga {
        color: blue;
    }

    .qr {
        margin: 15px 0;
    }

    .qr img {
        width: 180px;
        height: 180px;
        object-fit: contain;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 10px;
        background: #fff;
    }

    .link-box {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 10px;
        font-size: 12px;
        color: #444;
        word-break: break-all;
        text-align: left;
        margin-top: 10px;
        margin-bottom: 15px;
    }

    .acoes a {
        display: inline-block;
        text-decoration: none;
        background: #2d89ef;
        color: white;
        padding: 10px 14px;
        border-radius: 8px;
        margin: 4px;
        font-size: 14px;
    }

    .acoes a:hover {
        background: #1b5fad;
    }

    .sem-mesas {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 0 10px rgba(0,0,0,0.08);
        color: #222;
    }
</style>

<div class="page-card">
    <h1>QR Codes das Mesas</h1>

    <?php if (count($mesas) > 0): ?>
        <div class="grid-qrcodes">
            <?php foreach ($mesas as $mesa): ?>
                <?php
                    $urlMesa = $baseUrl . urlencode($mesa["token"]);
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($urlMesa);
                ?>
                <div class="qr-card">
                    <h2>Mesa <?= htmlspecialchars($mesa["numero"]) ?></h2>

                    <div class="status <?= htmlspecialchars($mesa["status"]) ?>">
                        Status: <?= htmlspecialchars($mesa["status"]) ?>
                    </div>

                    <div class="qr">
                        <img src="<?= $qrUrl ?>" alt="QR Code da Mesa <?= htmlspecialchars($mesa["numero"]) ?>">
                    </div>

                    <div class="link-box">
                        <?= htmlspecialchars($urlMesa) ?>
                    </div>

                    <div class="acoes">
                        <a href="<?= $urlMesa ?>" target="_blank">Abrir página da mesa</a>
                        <a href="<?= $qrUrl ?>" target="_blank">Abrir QR Code</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="sem-mesas">
            Nenhuma mesa cadastrada ainda.
        </div>
    <?php endif; ?>
</div>

<?php require "layout/footer.php"; ?>