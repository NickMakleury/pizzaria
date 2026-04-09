<?php
require "../config.php";
require "auth.php";
require "layout/header.php";

$mensagem = "";

/*
|--------------------------------------------------------------------------
| ATIVAR / DESATIVAR
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_id"])) {

    $id = (int) $_POST["toggle_id"];

    $pdo->prepare("
        UPDATE produtos 
        SET ativo = NOT ativo 
        WHERE id = :id AND pizzaria_id = :pizzaria_id
    ")->execute([
        ":id" => $id,
        ":pizzaria_id" => $pizzariaId
    ]);

    header("Location: produtos.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CARREGAR PRODUTO PARA EDIÇÃO
|--------------------------------------------------------------------------
*/
$produtoEdit = null;

if (isset($_GET["editar"])) {

    $id = (int) $_GET["editar"];

    $stmt = $pdo->prepare("
        SELECT * FROM produtos 
        WHERE id = :id AND pizzaria_id = :pizzaria_id
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $id,
        ":pizzaria_id" => $pizzariaId
    ]);

    $produtoEdit = $stmt->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| CADASTRAR / ATUALIZAR
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["toggle_id"])) {

    $id = (int) ($_POST["id"] ?? 0);
    $nome = trim($_POST["nome"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $preco = trim($_POST["preco"] ?? "");

    if ($nome == "" || $categoria == "" || $preco == "") {
        $mensagem = "Preencha todos os campos.";
    } elseif (!is_numeric($preco) || $preco <= 0) {
        $mensagem = "Informe um preço válido.";
    } else {

        try {

            if ($id > 0) {
                // UPDATE
                $pdo->prepare("
                    UPDATE produtos
                    SET nome = :nome,
                        categoria = :categoria,
                        preco = :preco
                    WHERE id = :id AND pizzaria_id = :pizzaria_id
                ")->execute([
                    ":nome" => $nome,
                    ":categoria" => $categoria,
                    ":preco" => $preco,
                    ":id" => $id,
                    ":pizzaria_id" => $pizzariaId
                ]);

                $mensagem = "Produto atualizado com sucesso!";

            } else {
                // INSERT
                $pdo->prepare("
                    INSERT INTO produtos (nome, categoria, preco, pizzaria_id)
                    VALUES (:nome, :categoria, :preco, :pizzaria_id)
                ")->execute([
                    ":nome" => $nome,
                    ":categoria" => $categoria,
                    ":preco" => $preco,
                    ":pizzaria_id" => $pizzariaId
                ]);

                $mensagem = "Produto cadastrado com sucesso!";
            }

        } catch (PDOException $e) {
            $mensagem = "Erro ao salvar produto.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| LISTAR PRODUTOS
|--------------------------------------------------------------------------
*/
$sqlProdutos = "
    SELECT * 
    FROM produtos 
    WHERE pizzaria_id = :pizzaria_id 
    ORDER BY nome ASC
";

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
    }

    .page-card button {
        padding: 10px 18px;
        border: none;
        background: #2d89ef;
        color: white;
        border-radius: 6px;
        cursor: pointer;
        height: 42px;
    }

    .mensagem {
        margin: 15px 0;
        padding: 12px;
        border-radius: 6px;
        background: #eef6ff;
        color: #1b4f72;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        padding: 12px;
        border-bottom: 1px solid #ddd;
    }

    th {
        background: #f1f1f1;
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

    .acoes {
        display: flex;
        gap: 6px;
    }
</style>

<div class="page-card">

    <h1>Cadastro de Produtos</h1>

    <?php if ($mensagem != ""): ?>
        <div class="mensagem"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="POST">

        <input type="hidden" name="id" value="<?= $produtoEdit["id"] ?? "" ?>">

        <div class="campo">
            <label>Nome</label>
            <input type="text" name="nome"
                value="<?= $produtoEdit["nome"] ?? "" ?>" required>
        </div>

        <div class="campo">
            <label>Categoria</label>
            <input type="text" name="categoria"
                value="<?= $produtoEdit["categoria"] ?? "" ?>" required>
        </div>

        <div class="campo">
            <label>Preço</label>
            <input type="number" name="preco"
                value="<?= $produtoEdit["preco"] ?? "" ?>"
                step="0.01" min="0.01" required>
        </div>

        <div class="campo">
            <button type="submit">
                <?= $produtoEdit ? "Atualizar" : "Cadastrar" ?>
            </button>
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
                <th>Ações</th>
            </tr>
        </thead>

        <tbody>
            <?php if (count($produtos) > 0): ?>
                <?php foreach ($produtos as $produto): ?>
                    <tr>
                        <td><?= $produto["id"] ?></td>

                        <td><?= htmlspecialchars($produto["nome"]) ?></td>

                        <td><?= htmlspecialchars($produto["categoria"]) ?></td>

                        <td class="preco">
                            R$ <?= number_format($produto["preco"], 2, ",", ".") ?>
                        </td>

                        <td>
                            <?php if ($produto["ativo"] == 1): ?>
                                <span class="status-ativo">Ativo</span>
                            <?php else: ?>
                                <span class="status-inativo">Inativo</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="acoes">
                                <a href="produtos.php?editar=<?= $produto["id"] ?>">
                                    Editar
                                </a>

                                <form method="POST">
                                    <input type="hidden" name="toggle_id" value="<?= $produto["id"] ?>">
                                    <button type="submit">
                                        <?= $produto["ativo"] == 1 ? "Desativar" : "Ativar" ?>
                                    </button>
                                </form>
                            </div>
                        </td>

                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">Nenhum produto cadastrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require "layout/footer.php"; ?>