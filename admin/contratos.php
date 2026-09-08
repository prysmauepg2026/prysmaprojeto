<?php
/**
 * Gerenciar contratos (CRUD básico) - apoio à administração geral do sistema.
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

    if ($acao === 'criar_contrato') {
        $idLocatario = (int) ($_POST['id_locatario'] ?? 0);
        $idImovel = (int) ($_POST['id_imovel'] ?? 0);
        $dataInicio = (string) ($_POST['datainicio'] ?? '');
        $dataFim = (string) ($_POST['datafim'] ?? '');
        $valorAluguel = (string) ($_POST['valoraluguel'] ?? '');
        $valorCaucao = (string) ($_POST['valorcaucao'] ?? '');
        $taxa = (string) ($_POST['taxaadministracao'] ?? '');

        if ($idLocatario <= 0 || $idImovel <= 0 || $dataInicio === '' || $dataFim === ''
            || !is_numeric($valorAluguel) || (float) $valorAluguel <= 0
            || !is_numeric($taxa) || (float) $taxa < 0 || (float) $taxa > 100
            || strtotime($dataFim) <= strtotime($dataInicio)) {
            $erro = 'Verifique os dados do contrato (datas, valores e taxa entre 0 e 100).';
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO Contrato (id_locatario, id_imovel, dataInicio, dataFim, valorAluguel, valorCaucao, taxaAdministracao, status)
                 VALUES (:lt, :im, :di, :df, :val, :cau, :taxa, 'ATIVO')"
            );
            $ins->execute([
                'lt' => $idLocatario, 'im' => $idImovel, 'di' => $dataInicio, 'df' => $dataFim,
                'val' => (float) $valorAluguel, 'cau' => $valorCaucao !== '' ? (float) $valorCaucao : null,
                'taxa' => (float) $taxa,
            ]);
            flash('success', 'Contrato criado com sucesso. O imóvel foi marcado como alugado.');
            redirect('/admin/contratos.php');
        }
    } elseif ($acao === 'mudar_status') {
        $id = (int) ($_POST['id_contrato'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (in_array($status, ['ATIVO', 'ENCERRADO', 'RESCINDIDO'], true)) {
            $upd = $pdo->prepare('UPDATE Contrato SET status = :st WHERE id_contrato = :id');
            $upd->execute(['st' => $status, 'id' => $id]);
            flash('success', 'Contrato #' . $id . ' atualizado.');
        }
        redirect('/admin/contratos.php');
    }
}

$contratos = $pdo->query(
    "SELECT c.*, i.logradouro, i.numero, u.nome AS locatario_nome
     FROM Contrato c
     JOIN Imovel i ON i.id_imovel = c.id_imovel
     JOIN Locatario lt ON lt.id_locatario = c.id_locatario
     JOIN Usuario u ON u.id_usuario = lt.id_usuario
     ORDER BY c.status = 'ATIVO' DESC, c.dataInicio DESC"
)->fetchAll();

$locatarios = $pdo->query(
    "SELECT lt.id_locatario, u.nome FROM Locatario lt JOIN Usuario u ON u.id_usuario = lt.id_usuario ORDER BY u.nome"
)->fetchAll();
$imoveisDisponiveis = $pdo->query(
    "SELECT id_imovel, logradouro, numero FROM Imovel WHERE status = 'DISPONIVEL' ORDER BY logradouro"
)->fetchAll();

layout_top('Gerenciar Contratos');
?>
<?php if ($erro): ?><div class="alert alert-error"><?= h($erro) ?></div><?php endif; ?>

<div class="card">
    <h2>Novo contrato</h2>
    <?php if (!$imoveisDisponiveis): ?>
        <p class="empty">Não há imóveis disponíveis no momento para novo contrato.</p>
    <?php else: ?>
    <form method="post" action="/admin/contratos.php" class="grid grid-2">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="acao" value="criar_contrato">
        <div>
            <label>Locatário</label>
            <select name="id_locatario" required>
                <?php foreach ($locatarios as $l): ?><option value="<?= (int) $l['id_locatario'] ?>"><?= h($l['nome']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Imóvel (disponível)</label>
            <select name="id_imovel" required>
                <?php foreach ($imoveisDisponiveis as $i): ?><option value="<?= (int) $i['id_imovel'] ?>"><?= h($i['logradouro']) ?>, <?= h($i['numero']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label>Data de início</label><input type="date" name="datainicio" required></div>
        <div><label>Data de fim</label><input type="date" name="datafim" required></div>
        <div><label>Valor do aluguel (R$)</label><input type="number" step="0.01" min="0.01" name="valoraluguel" required></div>
        <div><label>Valor da caução (R$)</label><input type="number" step="0.01" min="0" name="valorcaucao"></div>
        <div><label>Taxa de administração (%)</label><input type="number" step="0.01" min="0" max="100" name="taxaadministracao" required></div>
        <div style="grid-column: 1 / -1"><button type="submit" class="btn">Criar contrato</button></div>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Contratos</h2>
    <?php if (!$contratos): ?>
        <p class="empty">Nenhum contrato cadastrado.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Imóvel</th><th>Locatário</th><th>Início</th><th>Fim</th><th>Aluguel</th><th>Taxa</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($contratos as $c): ?>
            <tr>
                <td><?= h($c['logradouro']) ?>, <?= h($c['numero']) ?></td>
                <td><?= h($c['locatario_nome']) ?></td>
                <td><?= fdate($c['datainicio']) ?></td>
                <td><?= fdate($c['datafim']) ?></td>
                <td><?= money($c['valoraluguel']) ?></td>
                <td><?= h($c['taxaadministracao']) ?>%</td>
                <td>
                    <form method="post" action="/admin/contratos.php" class="actions">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="mudar_status">
                        <input type="hidden" name="id_contrato" value="<?= (int) $c['id_contrato'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach (['ATIVO', 'ENCERRADO', 'RESCINDIDO'] as $s): ?>
                                <option value="<?= h($s) ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option>
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
