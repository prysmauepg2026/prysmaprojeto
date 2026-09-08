<?php
/**
 * UC07 (parte) - Listar imóveis/contratos do locador.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locador');
$pdo = db();

$imoveis = $pdo->prepare(
    "SELECT i.*, ci.nome AS cidade, ci.uf,
        (SELECT COUNT(*) FROM Contrato c WHERE c.id_imovel = i.id_imovel AND c.status = 'ATIVO') AS tem_contrato_ativo
     FROM Imovel i JOIN Cidade ci ON ci.id_cidade = i.id_cidade
     WHERE i.id_locador = :id ORDER BY i.logradouro"
);
$imoveis->execute(['id' => $user['perfil_id']]);
$imoveis = $imoveis->fetchAll();

$idDetalhe = isset($_GET['ver']) ? (int) $_GET['ver'] : null;
$contratos = [];
if ($idDetalhe) {
    $stmt = $pdo->prepare(
        "SELECT c.*, u.nome AS locatario_nome, u.email AS locatario_email
         FROM Contrato c JOIN Locatario lt ON lt.id_locatario = c.id_locatario
         JOIN Usuario u ON u.id_usuario = lt.id_usuario
         WHERE c.id_imovel = :im AND c.id_imovel IN (SELECT id_imovel FROM Imovel WHERE id_locador = :loc)
         ORDER BY c.dataInicio DESC"
    );
    $stmt->execute(['im' => $idDetalhe, 'loc' => $user['perfil_id']]);
    $contratos = $stmt->fetchAll();
}

layout_top('Meus Imóveis');
?>
<div class="card">
    <?php if (!$imoveis): ?>
        <p class="empty">Nenhum imóvel cadastrado.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Endereço</th><th>Cidade</th><th>Tipo</th><th>Metragem</th><th>Aluguel</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($imoveis as $i): ?>
            <tr>
                <td><?= h($i['logradouro']) ?>, <?= h($i['numero']) ?> - <?= h($i['bairro']) ?></td>
                <td><?= h($i['cidade']) ?>/<?= h($i['uf']) ?></td>
                <td><?= h($i['tipo']) ?></td>
                <td><?= $i['metragem'] ? h($i['metragem']) . ' m²' : '—' ?></td>
                <td><?= money($i['valoraluguel']) ?></td>
                <td><?= status_badge($i['status']) ?></td>
                <td><a class="btn btn-sm btn-secondary" href="/locador/imoveis.php?ver=<?= (int) $i['id_imovel'] ?>">Ver contratos</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($idDetalhe): ?>
<div class="card">
    <h2>Contratos do imóvel #<?= $idDetalhe ?></h2>
    <?php if (!$contratos): ?>
        <p class="empty">Nenhum contrato encontrado para este imóvel.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Locatário</th><th>Início</th><th>Fim</th><th>Aluguel</th><th>Taxa adm.</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($contratos as $c): ?>
            <tr>
                <td><?= h($c['locatario_nome']) ?> (<?= h($c['locatario_email']) ?>)</td>
                <td><?= fdate($c['datainicio']) ?></td>
                <td><?= fdate($c['datafim']) ?></td>
                <td><?= money($c['valoraluguel']) ?></td>
                <td><?= h($c['taxaadministracao']) ?>%</td>
                <td><?= status_badge($c['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php layout_bottom(); ?>
