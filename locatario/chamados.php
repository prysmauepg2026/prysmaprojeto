<?php
/**
 * UC04 - Abrir chamado de manutenção.
 * UC05 - Acompanhar meus chamados.
 * Fluxo UC04: locatário escolhe imóvel, título, descrição e prioridade ->
 * sistema cria chamado com status ABERTO.
 * Fluxo UC05: lista chamados do locatário com status e histórico.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locatario');
$pdo = db();

$erro = null;

// UC04 - abertura de chamado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'abrir_chamado') {
    csrf_check();
    $idImovel = (int) ($_POST['id_imovel'] ?? 0);
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $descricao = trim((string) ($_POST['descricao'] ?? ''));
    $prioridade = (string) ($_POST['prioridade'] ?? 'NORMAL');

    // valida que o imóvel pertence a um contrato ativo do locatário
    $chk = $pdo->prepare(
        "SELECT 1 FROM Contrato WHERE id_locatario = :lt AND id_imovel = :im AND status = 'ATIVO'"
    );
    $chk->execute(['lt' => $user['perfil_id'], 'im' => $idImovel]);

    if ($titulo === '') {
        $erro = 'Informe um título para o chamado.';
    } elseif (!in_array($prioridade, ['BAIXA', 'NORMAL', 'ALTA'], true)) {
        $erro = 'Prioridade inválida.';
    } elseif (!$chk->fetch()) {
        // 4a (UC04) - Locatário sem vínculo com o imóvel
        $erro = 'Nenhum imóvel vinculado ao seu contrato';
    } else {
        // 5. Registra o chamado com status ABERTO e gera um número de protocolo
        // (o próprio id_chamado, sequencial, serve como número de protocolo).
        // 5a. Prioridade ALTA -> status do imóvel passa para EM_MANUTENCAO
        // (aplicado via trigger trg_chamado_prioridade_alta no banco).
        $ins = $pdo->prepare(
            "INSERT INTO ChamadoManutencao (id_locatario, id_imovel, titulo, descricao, prioridade, status)
             VALUES (:lt, :im, :tit, :desc, :prio, 'ABERTO') RETURNING id_chamado"
        );
        $ins->execute([
            'lt' => $user['perfil_id'], 'im' => $idImovel, 'tit' => $titulo,
            'desc' => $descricao !== '' ? $descricao : null, 'prio' => $prioridade,
        ]);
        $protocolo = (int) $ins->fetchColumn();
        // 6. Exibe o protocolo ao locatário
        flash('success', 'Chamado aberto com sucesso. Protocolo: #' . $protocolo . '.');
        redirect('/locatario/chamados.php');
    }
}

$imoveis = $pdo->prepare(
    "SELECT i.id_imovel, i.logradouro, i.numero, i.bairro
     FROM Contrato c JOIN Imovel i ON i.id_imovel = c.id_imovel
     WHERE c.id_locatario = :id AND c.status = 'ATIVO'"
);
$imoveis->execute(['id' => $user['perfil_id']]);
$imoveis = $imoveis->fetchAll();

$chamados = $pdo->prepare(
    "SELECT ch.*, i.logradouro, i.numero, p.nome AS prestador_nome, ch.funcionarioAtendente AS funcionario_nome
     FROM ChamadoManutencao ch
     JOIN Imovel i ON i.id_imovel = ch.id_imovel
     LEFT JOIN Prestador p ON p.id_prestador = ch.id_prestador
     WHERE ch.id_locatario = :id
     ORDER BY ch.dataAbertura DESC"
);
$chamados->execute(['id' => $user['perfil_id']]);
$chamados = $chamados->fetchAll();

$mostrarForm = isset($_GET['novo']) || $erro;

layout_top('Meus Chamados de Manutenção');
?>
<p class="subtitle">Abra novos chamados e acompanhe o status dos existentes.</p>

<?php if ($erro): ?><div class="alert alert-error"><?= h($erro) ?></div><?php endif; ?>

<div class="card">
    <h2>Novo chamado <?php if (!$mostrarForm): ?><a class="btn btn-sm btn-secondary" style="margin-left:0.5rem" href="/locatario/chamados.php?novo=1">+ abrir</a><?php endif; ?></h2>
    <?php if ($mostrarForm): ?>
        <?php if (!$imoveis): ?>
            <p class="empty">Nenhum imóvel vinculado ao seu contrato.</p>
        <?php else: ?>
        <form method="post" action="/locatario/chamados.php">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="acao" value="abrir_chamado">
            <label for="id_imovel">Imóvel</label>
            <select id="id_imovel" name="id_imovel" required>
                <?php foreach ($imoveis as $im): ?>
                    <option value="<?= (int) $im['id_imovel'] ?>"><?= h($im['logradouro']) ?>, <?= h($im['numero']) ?> - <?= h($im['bairro']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="titulo">Título</label>
            <input type="text" id="titulo" name="titulo" maxlength="200" required>
            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao" placeholder="Descreva o problema com detalhes"></textarea>
            <label for="prioridade">Prioridade</label>
            <select id="prioridade" name="prioridade">
                <option value="BAIXA">Baixa</option>
                <option value="NORMAL" selected>Normal</option>
                <option value="ALTA">Alta</option>
            </select>
            <button type="submit" class="btn">Abrir chamado</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Histórico de chamados</h2>
    <?php if (!$chamados): ?>
        <p class="empty">Nenhum chamado registrado ainda.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>#</th><th>Título</th><th>Imóvel</th><th>Prioridade</th><th>Prestador</th><th>Funcionário</th><th>Aberto em</th><th>Atualizado</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($chamados as $c): ?>
            <tr>
                <td>#<?= (int) $c['id_chamado'] ?></td>
                <td><?= h($c['titulo']) ?></td>
                <td><?= h($c['logradouro']) ?>, <?= h($c['numero']) ?></td>
                <td><span class="tag-priority-<?= strtolower(h($c['prioridade'])) ?>"><?= h($c['prioridade']) ?></span></td>
                <td><?= h($c['prestador_nome'] ?: '—') ?></td>
                <td><?= h($c['funcionario_nome'] ?: '—') ?></td>
                <td><?= fdatetime($c['dataabertura']) ?></td>
                <td><?= fdatetime($c['dataatualizacao']) ?></td>
                <td><?= status_badge($c['status']) ?></td>
                <td><a class="btn btn-sm btn-secondary" href="/locatario/chamado_detalhes.php?id=<?= (int) $c['id_chamado'] ?>">Ver detalhes</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php layout_bottom(); ?>
