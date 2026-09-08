<?php
/**
 * UC08 - Gerenciar chamados de manutenção.
 * Fluxo: admin visualiza chamados abertos -> atribui prestador -> altera status
 * até conclusão.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

require_login('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar_chamado') {
    csrf_check();
    $id = (int) ($_POST['id_chamado'] ?? 0);
    $nomeEmpresa = trim((string) ($_POST['empresa'] ?? ''));
    $status = (string) ($_POST['status'] ?? '');
    $justificativa = trim((string) ($_POST['justificativa'] ?? ''));
    $funcionario = trim((string) ($_POST['funcionarioatendente'] ?? ''));
    $validStatus = ['ABERTO', 'EM_ANDAMENTO', 'AGUARDANDO_LOCATARIO', 'CONCLUIDO', 'CANCELADO'];

    // A empresa responsável é digitada como texto livre: se já existir um
    // Prestador com esse nome ele é reaproveitado, senão é cadastrado na
    // hora; campo vazio remove o prestador do chamado.
    $idPrestador = null;
    if ($nomeEmpresa !== '') {
        $busca = $pdo->prepare('SELECT id_prestador FROM Prestador WHERE LOWER(nome) = LOWER(:nome)');
        $busca->execute(['nome' => $nomeEmpresa]);
        $existente = $busca->fetchColumn();
        if ($existente) {
            $idPrestador = (int) $existente;
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO Prestador (nome, documento, ativo) VALUES (:nome, 'A DEFINIR', TRUE) RETURNING id_prestador"
            );
            $ins->execute(['nome' => $nomeEmpresa]);
            $idPrestador = (int) $ins->fetchColumn();
        }
    }

    if (!in_array($status, $validStatus, true)) {
        flash('error', 'Status inválido.');
    } elseif ($status === 'CANCELADO' && $justificativa === '') {
        // Fluxo alternativo 4b do UC08: cancelamento exige justificativa.
        flash('error', 'Para cancelar o chamado é obrigatório informar uma justificativa.');
    } else {
        $dataFechamento = in_array($status, ['CONCLUIDO', 'CANCELADO'], true) ? date('Y-m-d H:i:s') : null;
        if ($status === 'CANCELADO') {
            // Não há coluna dedicada para justificativa no dicionário de dados;
            // ela é anexada à descrição do chamado, preservando o histórico.
            $descAtual = $pdo->prepare('SELECT descricao FROM ChamadoManutencao WHERE id_chamado = :id');
            $descAtual->execute(['id' => $id]);
            $descOriginal = (string) $descAtual->fetchColumn();
            $novaDescricao = $descOriginal
                . "\n\n[Cancelado em " . date('d/m/Y H:i') . "] Justificativa: " . $justificativa;
            $upd = $pdo->prepare(
                "UPDATE ChamadoManutencao SET id_prestador = :pr, status = :st, dataFechamento = :df, descricao = :desc, funcionarioAtendente = :func WHERE id_chamado = :id"
            );
            $upd->execute(['pr' => $idPrestador, 'st' => $status, 'df' => $dataFechamento, 'desc' => $novaDescricao, 'func' => $funcionario !== '' ? $funcionario : null, 'id' => $id]);
        } else {
            $upd = $pdo->prepare(
                "UPDATE ChamadoManutencao SET id_prestador = :pr, status = :st, dataFechamento = :df, funcionarioAtendente = :func WHERE id_chamado = :id"
            );
            $upd->execute(['pr' => $idPrestador, 'st' => $status, 'df' => $dataFechamento, 'func' => $funcionario !== '' ? $funcionario : null, 'id' => $id]);
        }
        flash('success', 'Chamado #' . $id . ' atualizado.');
    }
    redirect('/admin/chamados.php' . (isset($_GET['status']) ? '?status=' . urlencode((string) $_GET['status']) : ''));
}

$filtroStatus = (string) ($_GET['status'] ?? '');
$sql = "SELECT ch.*, i.logradouro, i.numero, u.nome AS locatario_nome, p.nome AS prestador_nome
        FROM ChamadoManutencao ch
        JOIN Imovel i ON i.id_imovel = ch.id_imovel
        JOIN Locatario lt ON lt.id_locatario = ch.id_locatario
        JOIN Usuario u ON u.id_usuario = lt.id_usuario
        LEFT JOIN Prestador p ON p.id_prestador = ch.id_prestador";
$params = [];
if ($filtroStatus !== '') {
    $sql .= " WHERE ch.status = :st";
    $params['st'] = $filtroStatus;
}
$sql .= " ORDER BY CASE ch.prioridade WHEN 'ALTA' THEN 0 WHEN 'NORMAL' THEN 1 ELSE 2 END, ch.dataAbertura DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$chamados = $stmt->fetchAll();

layout_top('Gerenciar Chamados de Manutenção');
?>
<form method="get" action="/admin/chamados.php" style="margin-bottom:1rem">
    <label>Filtrar por status</label>
    <div class="filtro-radio">
        <label class="filtro-opcao">
            <input type="radio" name="status" value="" onchange="this.form.submit()" <?= $filtroStatus === '' ? 'checked' : '' ?>> Todos
        </label>
        <?php foreach (['ABERTO', 'EM_ANDAMENTO', 'AGUARDANDO_LOCATARIO', 'CONCLUIDO', 'CANCELADO'] as $s): ?>
            <label class="filtro-opcao">
                <input type="radio" name="status" value="<?= h($s) ?>" onchange="this.form.submit()" <?= $filtroStatus === $s ? 'checked' : '' ?>> <?= h(str_replace('_', ' ', $s)) ?>
            </label>
        <?php endforeach; ?>
    </div>
</form>

<div class="card">
    <?php if (!$chamados): ?>
        <p class="empty">Nenhum chamado encontrado.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>#</th><th>Título</th><th>Imóvel</th><th>Locatário</th><th>Prioridade</th><th>Aberto em</th><th>Prestador / Status / Funcionário</th></tr></thead>
        <tbody>
        <?php foreach ($chamados as $c): ?>
            <tr>
                <td>#<?= (int) $c['id_chamado'] ?></td>
                <td><?= h($c['titulo']) ?><?php if ($c['descricao']): ?><br><small style="color:var(--muted)"><?= h(strim_utf8($c['descricao'], 80)) ?></small><?php endif; ?></td>
                <td><?= h($c['logradouro']) ?>, <?= h($c['numero']) ?></td>
                <td><?= h($c['locatario_nome']) ?></td>
                <td><span class="tag-priority-<?= strtolower(h($c['prioridade'])) ?>"><?= h($c['prioridade']) ?></span></td>
                <td><?= fdatetime($c['dataabertura']) ?></td>
                <td>
                    <form method="post" action="/admin/chamados.php<?= $filtroStatus !== '' ? '?status=' . h($filtroStatus) : '' ?>" class="actions">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="atualizar_chamado">
                        <input type="hidden" name="id_chamado" value="<?= (int) $c['id_chamado'] ?>">
                        <input type="text" name="empresa" value="<?= h((string) ($c['prestador_nome'] ?? '')) ?>" placeholder="Empresa responsável" style="width:auto">
                        <select name="status" onchange="var j=this.closest('form').querySelector('.campo-justificativa'); j.style.display = (this.value === 'CANCELADO') ? 'inline-block' : 'none';">
                            <?php foreach (['ABERTO', 'EM_ANDAMENTO', 'AGUARDANDO_LOCATARIO', 'CONCLUIDO', 'CANCELADO'] as $s): ?>
                                <option value="<?= h($s) ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="justificativa" class="campo-justificativa" placeholder="Justificativa do cancelamento" style="display:<?= $c['status'] === 'CANCELADO' ? 'inline-block' : 'none' ?>">
                        <input type="text" name="funcionarioatendente" value="<?= h((string) ($c['funcionarioatendente'] ?? '')) ?>" placeholder="Funcionário que atendeu" style="width:auto">
                        <button type="submit" class="btn btn-sm">Salvar</button>
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
