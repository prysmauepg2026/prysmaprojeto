<?php
/**
 * Gerenciar cobranças (CT-09 do Plano de Testes: marcar cobrança como paga).
 * O administrador registra o pagamento de uma cobrança, definindo status
 * PAGO e a data de pagamento (satisfaz a constraint chk_cobranca_pagamento).
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

require_login('admin');
$pdo = db();
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'marcar_paga') {
    csrf_check();
    $id = (int) ($_POST['id_cobranca'] ?? 0);
    $dataPagamento = (string) ($_POST['datapagamento'] ?? date('Y-m-d'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPagamento)) {
        $erro = 'Data de pagamento inválida.';
    } else {
        $upd = $pdo->prepare(
            "UPDATE Cobranca SET status = 'PAGO', dataPagamento = :dp WHERE id_cobranca = :id AND status <> 'PAGO'"
        );
        $upd->execute(['dp' => $dataPagamento, 'id' => $id]);
        flash('success', 'Cobrança #' . $id . ' marcada como paga em ' . fdate($dataPagamento) . '.');
        redirect('/admin/cobrancas.php' . (isset($_GET['status']) ? '?status=' . urlencode((string) $_GET['status']) : ''));
    }
}

$filtroStatus = (string) ($_GET['status'] ?? 'PENDENTE');
$sql = "SELECT cb.*, i.logradouro, i.numero, u.nome AS locatario_nome
        FROM Cobranca cb
        JOIN Contrato c ON c.id_contrato = cb.id_contrato
        JOIN Imovel i ON i.id_imovel = c.id_imovel
        JOIN Locatario lt ON lt.id_locatario = c.id_locatario
        JOIN Usuario u ON u.id_usuario = lt.id_usuario";
$params = [];
if ($filtroStatus !== '') {
    $sql .= " WHERE cb.status = :st";
    $params['st'] = $filtroStatus;
}
$sql .= " ORDER BY cb.dataVencimento ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cobrancas = $stmt->fetchAll();

layout_top('Gerenciar Cobranças');
?>
<?php if ($erro): ?><div class="alert alert-error"><?= h($erro) ?></div><?php endif; ?>

<form method="get" action="/admin/cobrancas.php" style="margin-bottom:1rem">
    <label>Filtrar por status</label>
    <div class="filtro-radio">
        <label class="filtro-opcao">
            <input type="radio" name="status" value="" onchange="this.form.submit()" <?= $filtroStatus === '' ? 'checked' : '' ?>> Todos
        </label>
        <?php foreach (['PENDENTE', 'ATRASADO', 'PAGO', 'CANCELADO'] as $s): ?>
            <label class="filtro-opcao">
                <input type="radio" name="status" value="<?= h($s) ?>" onchange="this.form.submit()" <?= $filtroStatus === $s ? 'checked' : '' ?>> <?= h($s) ?>
            </label>
        <?php endforeach; ?>
    </div>
</form>

<div class="card">
    <?php if (!$cobrancas): ?>
        <p class="empty">Nenhuma cobrança encontrada para esse filtro.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>#</th><th>Imóvel</th><th>Locatário</th><th>Vencimento</th><th>Valor</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cobrancas as $c): ?>
            <tr>
                <td>#<?= (int) $c['id_cobranca'] ?></td>
                <td><?= h($c['logradouro']) ?>, <?= h($c['numero']) ?></td>
                <td><?= h($c['locatario_nome']) ?></td>
                <td><?= fdate($c['datavencimento']) ?></td>
                <td><?= money($c['valor']) ?></td>
                <td><?= status_badge($c['status']) ?><?= $c['status'] === 'PAGO' ? ' em ' . fdate($c['datapagamento']) : '' ?></td>
                <td>
                    <?php if ($c['status'] !== 'PAGO'): ?>
                    <form method="post" action="/admin/cobrancas.php<?= $filtroStatus !== '' ? '?status=' . h($filtroStatus) : '' ?>" class="actions">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="marcar_paga">
                        <input type="hidden" name="id_cobranca" value="<?= (int) $c['id_cobranca'] ?>">
                        <input type="date" name="datapagamento" value="<?= h(date('Y-m-d')) ?>" style="width:auto">
                        <button type="submit" class="btn btn-sm">Marcar como paga</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php layout_bottom(); ?>
