<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

require_login('admin');
$pdo = db();

$stats = [
    'usuarios' => (int) $pdo->query('SELECT COUNT(*) FROM Usuario')->fetchColumn(),
    'imoveis' => (int) $pdo->query('SELECT COUNT(*) FROM Imovel')->fetchColumn(),
    'contratos_ativos' => (int) $pdo->query("SELECT COUNT(*) FROM Contrato WHERE status = 'ATIVO'")->fetchColumn(),
    'chamados_abertos' => (int) $pdo->query("SELECT COUNT(*) FROM ChamadoManutencao WHERE status NOT IN ('CONCLUIDO','CANCELADO')")->fetchColumn(),
    'cobrancas_atrasadas' => (int) $pdo->query("SELECT COUNT(*) FROM Cobranca WHERE status = 'ATRASADO'")->fetchColumn(),
];

$chamadosRecentes = $pdo->query(
    "SELECT ch.*, i.logradouro, i.numero, u.nome AS locatario_nome
     FROM ChamadoManutencao ch
     JOIN Imovel i ON i.id_imovel = ch.id_imovel
     JOIN Locatario lt ON lt.id_locatario = ch.id_locatario
     JOIN Usuario u ON u.id_usuario = lt.id_usuario
     ORDER BY ch.dataAbertura DESC LIMIT 8"
)->fetchAll();

layout_top('Painel Administrativo');
?>
<div class="grid grid-3">
    <div class="stat"><div class="num"><?= $stats['usuarios'] ?></div><div class="label">Usuários</div></div>
    <div class="stat"><div class="num"><?= $stats['imoveis'] ?></div><div class="label">Imóveis</div></div>
    <div class="stat"><div class="num"><?= $stats['contratos_ativos'] ?></div><div class="label">Contratos ativos</div></div>
</div>
<div class="grid grid-2" style="margin-top:1rem">
    <div class="stat"><div class="num"><?= $stats['chamados_abertos'] ?></div><div class="label">Chamados em aberto</div></div>
    <div class="stat"><div class="num"><?= $stats['cobrancas_atrasadas'] ?></div><div class="label">Cobranças atrasadas</div></div>
</div>

<div class="card">
    <h2>Chamados recentes</h2>
    <?php if (!$chamadosRecentes): ?>
        <p class="empty">Nenhum chamado registrado.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>#</th><th>Título</th><th>Imóvel</th><th>Locatário</th><th>Prioridade</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($chamadosRecentes as $c): ?>
            <tr>
                <td>#<?= (int) $c['id_chamado'] ?></td>
                <td><?= h($c['titulo']) ?></td>
                <td><?= h($c['logradouro']) ?>, <?= h($c['numero']) ?></td>
                <td><?= h($c['locatario_nome']) ?></td>
                <td><span class="tag-priority-<?= strtolower(h($c['prioridade'])) ?>"><?= h($c['prioridade']) ?></span></td>
                <td><?= status_badge($c['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p><a href="/admin/chamados.php">Gerenciar todos os chamados &rarr;</a></p>
    <?php endif; ?>
</div>
<?php layout_bottom(); ?>
