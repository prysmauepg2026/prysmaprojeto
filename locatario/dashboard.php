<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locatario');
$pdo = db();

$loc = $pdo->prepare('SELECT * FROM Locatario WHERE id_locatario = :id');
$loc->execute(['id' => $user['perfil_id']]);
$locatario = $loc->fetch();

$contratos = $pdo->prepare(
    "SELECT c.*, i.logradouro, i.numero, i.bairro, ci.nome AS cidade, ci.uf
     FROM Contrato c
     JOIN Imovel i ON i.id_imovel = c.id_imovel
     JOIN Cidade ci ON ci.id_cidade = i.id_cidade
     WHERE c.id_locatario = :id
     ORDER BY c.status = 'ATIVO' DESC, c.dataInicio DESC"
);
$contratos->execute(['id' => $user['perfil_id']]);
$contratos = $contratos->fetchAll();

$pendentes = $pdo->prepare(
    "SELECT COUNT(*) FROM Cobranca cb JOIN Contrato c ON c.id_contrato = cb.id_contrato
     WHERE c.id_locatario = :id AND cb.status IN ('PENDENTE','ATRASADO')"
);
$pendentes->execute(['id' => $user['perfil_id']]);
$numPendentes = (int) $pendentes->fetchColumn();

$chamadosAbertos = $pdo->prepare(
    "SELECT COUNT(*) FROM ChamadoManutencao WHERE id_locatario = :id AND status NOT IN ('CONCLUIDO','CANCELADO')"
);
$chamadosAbertos->execute(['id' => $user['perfil_id']]);
$numChamados = (int) $chamadosAbertos->fetchColumn();

layout_top('Painel do Locatário');
?>
<div class="grid grid-3">
    <div class="stat"><div class="num"><?= count($contratos) ?></div><div class="label">Contratos</div></div>
    <div class="stat"><div class="num"><?= $numPendentes ?></div><div class="label">Cobranças pendentes/atrasadas</div></div>
    <div class="stat"><div class="num"><?= $numChamados ?></div><div class="label">Chamados em aberto</div></div>
</div>

<div class="card">
    <h2>Meus contratos</h2>
    <?php if (!$contratos): ?>
        <p class="empty">Nenhum contrato encontrado.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Imóvel</th><th>Cidade</th><th>Início</th><th>Fim</th><th>Aluguel</th><th>Caução</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($contratos as $c): ?>
            <tr>
                <td><?= h($c['logradouro']) ?>, <?= h($c['numero']) ?> - <?= h($c['bairro']) ?></td>
                <td><?= h($c['cidade']) ?>/<?= h($c['uf']) ?></td>
                <td><?= fdate($c['datainicio']) ?></td>
                <td><?= fdate($c['datafim']) ?></td>
                <td><?= money($c['valoraluguel']) ?></td>
                <td><?= $c['valorcaucao'] !== null ? money($c['valorcaucao']) : '—' ?></td>
                <td><?= status_badge($c['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-2">
    <div class="card">
        <h3>Ações rápidas</h3>
        <p><a href="/locatario/boletos.php">Emitir 2ª via de boleto</a></p>
        <p><a href="/locatario/chamados.php?novo=1">Abrir novo chamado de manutenção</a></p>
        <p><a href="/locatario/chamados.php">Acompanhar meus chamados</a></p>
        <p><a href="/locatario/faq.php">Consultar dúvidas frequentes (FAQ)</a></p>
    </div>
</div>
<?php layout_bottom(); ?>
