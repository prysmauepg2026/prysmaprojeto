<?php
/**
 * UC06 - Consultar repasses.
 * Fluxo: locador informa um período (data início/fim) -> sistema recupera os
 * repasses do período e calcula os valores líquidos.
 * 4a. Nenhum repasse no período -> mensagem específica.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locador');
$pdo = db();

// 2./3. Período de consulta informado pelo locador (padrão: mês atual)
$dataInicio = (string) ($_GET['inicio'] ?? date('Y-m-01'));
$dataFim = (string) ($_GET['fim'] ?? date('Y-m-t'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
    $dataInicio = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    $dataFim = date('Y-m-t');
}
if (strtotime($dataFim) < strtotime($dataInicio)) {
    [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
}

// 4. Recupera os repasses do período (por vencimento da cobrança) e calcula os valores líquidos.
$repasses = $pdo->prepare(
    "SELECT r.*, cb.dataVencimento, i.logradouro, i.numero, c.taxaAdministracao
     FROM Repasse r
     JOIN Cobranca cb ON cb.id_cobranca = r.id_cobranca
     JOIN Contrato c ON c.id_contrato = cb.id_contrato
     JOIN Imovel i ON i.id_imovel = c.id_imovel
     WHERE i.id_locador = :id AND cb.dataVencimento BETWEEN :ini AND :fim
     ORDER BY cb.dataVencimento DESC"
);
$repasses->execute(['id' => $user['perfil_id'], 'ini' => $dataInicio, 'fim' => $dataFim]);
$repasses = $repasses->fetchAll();

$totalBruto = array_sum(array_column($repasses, 'valorbruto'));
$totalLiquido = array_sum(array_column($repasses, 'valorliquido'));

layout_top('Repasses');
?>
<form method="get" action="/locador/repasses.php" class="card" style="display:flex; align-items:flex-end; gap:1rem; flex-wrap:wrap;">
    <div>
        <label for="inicio">Período - início</label>
        <input type="date" id="inicio" name="inicio" value="<?= h($dataInicio) ?>">
    </div>
    <div>
        <label for="fim">Período - fim</label>
        <input type="date" id="fim" name="fim" value="<?= h($dataFim) ?>">
    </div>
    <div><button type="submit" class="btn">Consultar</button></div>
</form>

<div class="grid grid-2">
    <div class="stat"><div class="num"><?= money($totalBruto) ?></div><div class="label">Total bruto no período</div></div>
    <div class="stat"><div class="num"><?= money($totalLiquido) ?></div><div class="label">Total líquido (após taxa de administração)</div></div>
</div>

<div class="card">
    <?php if (!$repasses): ?>
        <!-- 4a. Nenhum repasse no período -->
        <p class="empty">Nenhum repasse encontrado para o período informado (<?= fdate($dataInicio) ?> a <?= fdate($dataFim) ?>).</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Imóvel</th><th>Vencimento cobrança</th><th>Valor bruto</th><th>Taxa adm.</th><th>Valor líquido</th><th>Data do repasse</th></tr></thead>
        <tbody>
        <?php foreach ($repasses as $r): ?>
            <tr>
                <td><?= h($r['logradouro']) ?>, <?= h($r['numero']) ?></td>
                <td><?= fdate($r['datavencimento']) ?></td>
                <td><?= money($r['valorbruto']) ?></td>
                <td><?= h($r['taxaadministracao']) ?>%</td>
                <td><strong><?= money($r['valorliquido']) ?></strong></td>
                <td><?= $r['datarepasse'] ? fdate($r['datarepasse']) : '<span class="badge badge-warn">Aguardando</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php layout_bottom(); ?>
