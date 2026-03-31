<?php
require "../config.php";
require "auth.php";
require "layout/header.php";

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST["nome"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $preco = trim($_POST["preco"] ?? "");

    if ($nome == "" || $categoria == "" || $preco == "") {
        $mensagem = "Preencha todos os campos.";
    } elseif (!is_numeric($preco) || $preco <= 0) {
        $mensagem = "Informe um preço válido.";
    } else {
        $sql = "INSERT INTO produtos (nome, categoria, preco, pizzaria_id) VALUES (:nome, :categoria, :preco, :pizzaria_id)";
        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute([
                ":nome" => $nome,
                ":categoria" => $categoria,
                ":preco" => $preco,
                ":pizzaria_id" => $pizzariaId
            ]);
            $mensagem = "Produto cadastrado com sucesso!";
        } catch (PDOException $e) {
            $mensagem = "Erro ao cadastrar produto.";
        }
    }
}

$sqlProdutos = "SELECT * FROM produtos WHERE pizzaria_id = :pizzaria_id ORDER BY nome ASC";
$stmtProdutos = $pdo->prepare($sqlProdutos);
$stmtProdutos->execute([":pizzaria_id" => $pizzariaId]);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .page-card {
        max-width: 950px;
        margin: auto;
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.08);
        color: #222;
    }

    .page-card h1,
    .page-card h2 {
        margin-bottom: 20px;
    }

    .page-card form {
        margin-bottom: 30px;
        display: grid;
        grid-template-columns: 1fr 1fr 180px auto;
        gap: 12px;
        align-items: end;
    }

    .campo label {
        display: block;
        margin-bottom: 6px;
        font-weight: bold;
    }

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
    }

    .page-card th {
        background: #f1f1f1;
        color: #222;
    }

    .preco {
        font-weight: bold;
        color: #1a7f37;
    }

    .status-ativo {
        color: green;
        font-weight: bold;
    }

    .status-inativo {
        color: red;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        .page-card form {
            grid-template-columns: 1fr;
        }

        .page-card button {
            width: 100%;
        }

        .page-card table {
            font-size: 14px;
        }
    }
</style>

<div class="page-card">
    <h1>Cadastro de Produtos</h1>

    <?php if ($mensagem != ""): ?>
        <div class="mensagem"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="campo">
            <label for="nome">Nome do produto</label>
            <input type="text" name="nome" id="nome" required>
        </div>

        <div class="campo">
            <label for="categoria">Categoria</label>
            <input type="text" name="categoria" id="categoria" required>
        </div>

        <div class="campo">
            <label for="preco">Preço</label>
            <input type="number" name="preco" id="preco" step="0.01" min="0.01" required>
        </div>

        <div class="campo">
            <button type="submit">Cadastrar</button>
        </div>
    </form>

    <h2>Produtos cadastrados</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Preço</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($produtos) > 0): ?>
                <?php foreach ($produtos as $produto): ?>
                    <tr>
                        <td><?= $produto["id"] ?></td>
                        <td><?= htmlspecialchars($produto["nome"]) ?></td>
                        <td><?= htmlspecialchars($produto["categoria"]) ?></td>
                        <td class="preco">R$ <?= number_format($produto["preco"], 2, ",", ".") ?></td>
                        <td>
                            <?php if ($produto["ativo"] == 1): ?>
                                <span class="status-ativo">Ativo</span>
                            <?php else: ?>
                                <span class="status-inativo">Inativo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">Nenhum produto cadastrado ainda.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require "layout/footer.php"; ?>