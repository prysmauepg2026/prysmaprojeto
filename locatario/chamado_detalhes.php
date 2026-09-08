<?php
/**
 * UC05 - Acompanhar meus chamados (detalhe).
 * Exibe os dados completos de um chamado específico do locatário logado:
 * imóvel, descrição, prioridade, prestador designado (quando houver) e
 * histórico de datas (abertura, última atualização, fechamento).
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locatario');
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT ch.*, i.logradouro, i.numero, i.bairro,
            p.nome AS prestador_nome, p.telefone AS prestador_telefone,
            p.email AS prestador_email, p.especialidade AS prestador_especialidade,
            ch.funcionarioAtendente AS funcionario_nome
     FROM ChamadoManutencao ch
     JOIN Imovel i ON i.id_imovel = ch.id_imovel
     LEFT JOIN Prestador p ON p.id_prestador = ch.id_prestador
     WHERE ch.id_chamado = :id AND ch.id_locatario = :lt"
);
$stmt->execute(['id' => $id, 'lt' => $user['perfil_id']]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('error', 'Chamado não encontrado para o seu usuário.');
    redirect('/locatario/chamados.php');
}

// Linha do tempo do chamado, montada a partir dos campos de data disponíveis
// (o sistema não mantém uma tabela de histórico separada; as datas de
// abertura/atualização/fechamento já cobrem o que o UC05 pede).
$timeline = [];
$timeline[] = ['label' => 'Chamado aberto', 'data' => $chamado['dataabertura']];
if ($chamado['id_prestador']) {
    $timeline[] = ['label' => 'Prestador designado: ' . $chamado['prestador_nome'], 'data' => $chamado['dataatualizacao'] ?: $chamado['dataabertura']];
}
if ($chamado['dataatualizacao'] && $chamado['dataatualizacao'] !== $chamado['dataabertura']) {
    $timeline[] = ['label' => 'Última atualização de status (' . str_replace('_', ' ', $chamado['status']) . ')', 'data' => $chamado['dataatualizacao']];
}
if ($chamado['datafechamento']) {
    $timeline[] = ['label' => 'Chamado encerrado', 'data' => $chamado['datafechamento']];
}
usort($timeline, fn ($a, $b) => strtotime((string) $a['data']) <=> strtotime((string) $b['data']));

layout_top('Chamado #' . (int) $chamado['id_chamado']);
?>
<p class="breadcrumb"><a href="/locatario/chamados.php">&larr; voltar para meus chamados</a></p>

<div class="card">
    <h2><?= h($chamado['titulo']) ?> <?= status_badge($chamado['status']) ?></h2>
    <table>
        <tr><th>Protocolo</th><td>#<?= (int) $chamado['id_chamado'] ?></td></tr>
        <tr><th>Imóvel</th><td><?= h($chamado['logradouro']) ?>, <?= h($chamado['numero']) ?> - <?= h($chamado['bairro']) ?></td></tr>
        <tr><th>Prioridade</th><td><span class="tag-priority-<?= strtolower(h($chamado['prioridade'])) ?>"><?= h($chamado['prioridade']) ?></span></td></tr>
        <tr><th>Descrição</th><td><?= $chamado['descricao'] ? nl2br(h($chamado['descricao'])) : '<span class="empty">Sem descrição adicional.</span>' ?></td></tr>
        <tr><th>Aberto em</th><td><?= fdatetime($chamado['dataabertura']) ?></td></tr>
        <tr><th>Última atualização</th><td><?= $chamado['dataatualizacao'] ? fdatetime($chamado['dataatualizacao']) : '—' ?></td></tr>
        <?php if ($chamado['datafechamento']): ?>
        <tr><th>Encerrado em</th><td><?= fdatetime($chamado['datafechamento']) ?></td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <h2>Prestador responsável</h2>
    <?php if (!$chamado['id_prestador']): ?>
        <p class="empty">Nenhum prestador designado até o momento. Assim que a administração atribuir um
        prestador para este chamado, os dados aparecerão aqui.</p>
    <?php else: ?>
        <table>
            <tr><th>Empresa</th><td><?= h($chamado['prestador_nome']) ?></td></tr>
            <tr><th>Funcionário que atendeu</th><td><?= h($chamado['funcionario_nome'] ?: 'Ainda não informado') ?></td></tr>
            <tr><th>Especialidade</th><td><?= h($chamado['prestador_especialidade'] ?: '—') ?></td></tr>
            <tr><th>Telefone</th><td><?= h($chamado['prestador_telefone'] ?: '—') ?></td></tr>
            <tr><th>E-mail</th><td><?= h($chamado['prestador_email'] ?: '—') ?></td></tr>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Histórico</h2>
    <ul class="timeline">
        <?php foreach ($timeline as $t): ?>
            <li><strong><?= fdatetime($t['data']) ?></strong> — <?= h($t['label']) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php layout_bottom(); ?>
