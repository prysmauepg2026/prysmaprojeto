<?php
/**
 * Gerenciar imóveis (CRUD básico) - apoio ao UC08 / administração geral.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

require_login('admin');
$pdo = db();
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'criar_imovel') {
        $idLocador = (int) ($_POST['id_locador'] ?? 0);
        $idCidade = (int) ($_POST['id_cidade'] ?? 0);
        $matricula = trim((string) ($_POST['matricula'] ?? ''));
        $tipo = trim((string) ($_POST['tipo'] ?? ''));
        $valorAluguel = (string) ($_POST['valoraluguel'] ?? '');
        $numero = trim((string) ($_POST['numero'] ?? ''));
        $cep = trim((string) ($_POST['cep'] ?? ''));
        $logradouro = trim((string) ($_POST['logradouro'] ?? ''));
        $bairro = trim((string) ($_POST['bairro'] ?? ''));
        $metragem = (string) ($_POST['metragem'] ?? '');

        if ($idLocador <= 0 || $idCidade <= 0 || $matricula === '' || $tipo === '' || $logradouro === '' || $bairro === '' || $cep === '' || !is_numeric($valorAluguel) || (float) $valorAluguel <= 0) {
            $erro = 'Preencha todos os campos obrigatórios corretamente.';
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO Imovel (id_locador, id_cidade, matricula, tipo, valorAluguel, numero, cep, logradouro, bairro, metragem, status)
                 VALUES (:loc, :cid, :mat, :tipo, :val, :num, :cep, :log, :bai, :met, 'DISPONIVEL')"
            );
            $ins->execute([
                'loc' => $idLocador, 'cid' => $idCidade, 'mat' => $matricula, 'tipo' => $tipo,
                'val' => (float) $valorAluguel, 'num' => $numero !== '' ? $numero : null, 'cep' => $cep,
                'log' => $logradouro, 'bai' => $bairro, 'met' => $metragem !== '' ? (float) $metragem : null,
            ]);
            flash('success', 'Imóvel cadastrado com sucesso.');
            redirect('/admin/imoveis.php');
        }
    } elseif ($acao === 'mudar_status') {
        $id = (int) ($_POST['id_imovel'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (in_array($status, ['DISPONIVEL', 'ALUGADO', 'EM_MANUTENCAO', 'INATIVO'], true)) {
            $upd = $pdo->prepare('UPDATE Imovel SET status = :st WHERE id_imovel = :id');
            $upd->execute(['st' => $status, 'id' => $id]);
            flash('success', 'Status do imóvel #' . $id . ' atualizado.');
        }
        redirect('/admin/imoveis.php');
    }
}

$imoveis = $pdo->query(
    "SELECT i.*, ci.nome AS cidade, ci.uf, u.nome AS locador_nome
     FROM Imovel i JOIN Cidade ci ON ci.id_cidade = i.id_cidade
     JOIN Locador l ON l.id_locador = i.id_locador
     JOIN Usuario u ON u.id_usuario = l.id_usuario
     ORDER BY i.status, i.logradouro"
)->fetchAll();

$locadores = $pdo->query(
    "SELECT l.id_locador, u.nome FROM Locador l JOIN Usuario u ON u.id_usuario = l.id_usuario ORDER BY u.nome"
)->fetchAll();
$cidades = $pdo->query('SELECT * FROM Cidade ORDER BY nome')->fetchAll();

layout_top('Gerenciar Imóveis');
?>
<?php if ($erro): ?><div class="alert alert-error"><?= h($erro) ?></div><?php endif; ?>

<div class="card">
    <h2>Novo imóvel</h2>
    <form method="post" action="/admin/imoveis.php" class="grid grid-2">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="acao" value="criar_imovel">
        <div>
            <label>Locador</label>
            <select name="id_locador" required>
                <?php foreach ($locadores as $l): ?><option value="<?= (int) $l['id_locador'] ?>"><?= h($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Cidade</label>
            <select name="id_cidade" required>
                <?php foreach ($cidades as $c): ?><option value="<?= (int) $c['id_cidade'] ?>"><?= h($c['nome']) ?>/<?= h($c['uf']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label>Matrícula</label><input type="text" name="matricula" required></div>
        <div><label>Tipo</label><input type="text" name="tipo" placeholder="Apartamento, Casa, Sala comercial..." required></div>
        <div><label>Logradouro</label><input type="text" name="logradouro" required></div>
        <div><label>Número</label><input type="text" name="numero"></div>
        <div><label>Bairro</label><input type="text" name="bairro" required></div>
        <div><label>CEP</label><input type="text" name="cep" placeholder="00000-000" required></div>
        <div><label>Valor do aluguel (R$)</label><input type="number" step="0.01" min="0.01" name="valoraluguel" required></div>
        <div><label>Metragem (m²)</label><input type="number" step="0.01" min="0" name="metragem"></div>
        <div style="grid-column: 1 / -1"><button type="submit" class="btn">Cadastrar imóvel</button></div>
    </form>
</div>

<div class="card">
    <h2>Imóveis cadastrados</h2>
    <?php if (!$imoveis): ?>
        <p class="empty">Nenhum imóvel cadastrado.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Endereço</th><th>Cidade</th><th>Locador</th><th>Tipo</th><th>Aluguel</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($imoveis as $i): ?>
            <tr>
                <td><?= h($i['logradouro']) ?>, <?= h($i['numero']) ?> - <?= h($i['bairro']) ?></td>
                <td><?= h($i['cidade']) ?>/<?= h($i['uf']) ?></td>
                <td><?= h($i['locador_nome']) ?></td>
                <td><?= h($i['tipo']) ?></td>
                <td><?= money($i['valoraluguel']) ?></td>
                <td>
                    <form method="post" action="/admin/imoveis.php" class="actions">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="mudar_status">
                        <input type="hidden" name="id_imovel" value="<?= (int) $i['id_imovel'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach (['DISPONIVEL', 'ALUGADO', 'EM_MANUTENCAO', 'INATIVO'] as $s): ?>
                                <option value="<?= h($s) ?>" <?= $i['status'] === $s ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php layout_bottom(); ?>
