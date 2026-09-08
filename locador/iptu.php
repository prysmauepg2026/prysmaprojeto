<?php
/**
 * Consulta de IPTU e parcelas por imóvel (complementa a proposta do projeto,
 * que menciona "visualização de taxas e impostos" entre os recursos de
 * autoatendimento do locador). Somente leitura.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locador');
$pdo = db();

$iptus = $pdo->prepare(
    "SELECT t.*, i.logradouro, i.numero, i.bairro
     FROM IPTU t
     JOIN Imovel i ON i.id_imovel = t.id_imovel
     WHERE i.id_locador = :id
     ORDER BY t.ano DESC, i.logradouro"
);
$iptus->execute(['id' => $user['perfil_id']]);
$iptus = $iptus->fetchAll();

$parcelasPorIptu = [];
if ($iptus) {
    $ids = array_column($iptus, 'id_iptu');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM IptuParcela WHERE id_iptu IN ($in) ORDER BY numeroParcela");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $p) {
        $parcelasPorIptu[(int) $p['id_iptu']][] = $p;
    }
}

layout_top('IPTU dos Imóveis');
?>
<p class="subtitle">Consulta de IPTU e parcelas por imóvel.</p>

<?php if (!$iptus): ?>
    <div class="card"><p class="empty">Nenhum lançamento de IPTU registrado para seus imóveis.</p></div>
<?php else: ?>
    <?php foreach ($iptus as $t): ?>
    <div class="card">
        <h3><?= h($t['logradouro']) ?>, <?= h($t['numero']) ?> - <?= h($t['bairro']) ?> &middot; IPTU <?= (int) $t['ano'] ?></h3>
        <p>Valor total: <strong><?= money($t['valortotal']) ?></strong> em <?= (int) $t['quantidadeparcelas'] ?> parcela(s).</p>
        <?php $parcelas = $parcelasPorIptu[(int) $t['id_iptu']] ?? []; ?>
        <?php if (!$parcelas): ?>
            <p class="empty">Nenhuma parcela cadastrada.</p>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($parcelas as $p): ?>
                <tr>
                    <td><?= (int) $p['numeroparcela'] ?>/<?= (int) $t['quantidadeparcelas'] ?></td>
                    <td><?= fdate($p['datavencimento']) ?></td>
                    <td><?= money($p['valor']) ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php layout_bottom(); ?>
