<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locador');
$pdo = db();

$imoveis = $pdo->prepare(
    "SELECT i.*, ci.nome AS cidade, ci.uf FROM Imovel i JOIN Cidade ci ON ci.id_cidade = i.id_cidade
     WHERE i.id_locador = :id ORDER BY i.status, i.logradouro"
);
$imoveis->execute(['id' => $user['perfil_id']]);
$imoveis = $imoveis->fetchAll();

$repasseMes = $pdo->prepare(
    "SELECT COALESCE(SUM(r.valorLiquido), 0) AS total
     FROM Repasse r JOIN Cobranca cb ON cb.id_cobranca = r.id_cobranca
     JOIN Contrato c ON c.id_contrato = cb.id_contrato
     JOIN Imovel i ON i.id_imovel = c.id_imovel
     WHERE i.id_locador = :id AND date_trunc('month', r.dataRepasse) = date_trunc('month', CURRENT_DATE)"
);
$repasseMes->execute(['id' => $user['perfil_id']]);
$totalRepasseMes = (float) $repasseMes->fetchColumn();

$alugados = count(array_filter($imoveis, fn ($i) => $i['status'] === 'ALUGADO'));
$disponiveis = count(array_filter($imoveis, fn ($i) => $i['status'] === 'DISPONIVEL'));

layout_top('Painel do Locador');
?>
<div class="grid grid-3">
    <div class="stat"><div class="num"><?= count($imoveis) ?></div><div class="label">Imóveis cadastrados</div></div>
    <div class="stat"><div class="num"><?= $alugados ?></div><div class="label">Alugados</div></div>
    <div class="stat"><div class="num"><?= money($totalRepasseMes) ?></div><div class="label">Repasses recebidos este mês</div></div>
</div>

<div class="card">
    <h2>Meus imóveis</h2>
    <?php if (!$imoveis): ?>
        <p class="empty">Nenhum imóvel cadastrado.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Endereço</th><th>Cidade</th><th>Tipo</th><th>Aluguel</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($imoveis, 0, 6) as $i): ?>
            <tr>
                <td><?= h($i['logradouro']) ?>, <?= h($i['numero']) ?></td>
                <td><?= h($i['cidade']) ?>/<?= h($i['uf']) ?></td>
                <td><?= h($i['tipo']) ?></td>
                <td><?= money($i['valoraluguel']) ?></td>
                <td><?= status_badge($i['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p><a href="/locador/imoveis.php">Ver todos &rarr;</a></p>
    <?php endif; ?>
</div>

<div class="grid grid-2">
    <div class="card">
        <h3>Ações rápidas</h3>
        <p><a href="/locador/repasses.php">Consultar repasses</a></p>
        <p><a href="/locador/informe.php">Emitir informe de rendimento</a></p>
        <p><a href="/locador/faq.php">Consultar dúvidas frequentes (FAQ)</a></p>
    </div>
</div>
<?php layout_bottom(); ?>
