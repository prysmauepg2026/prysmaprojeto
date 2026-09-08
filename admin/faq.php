<?php
/**
 * Gerenciar FAQ (CRUD) - apoio ao UC02 (base de dúvidas frequentes).
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('admin');
$pdo = db();
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'criar_faq') {
        $pergunta = trim((string) ($_POST['pergunta'] ?? ''));
        $resposta = trim((string) ($_POST['resposta'] ?? ''));
        $categoria = trim((string) ($_POST['categoria'] ?? ''));
        if ($pergunta === '' || $resposta === '') {
            $erro = 'Preencha pergunta e resposta.';
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO FAQ (id_admin, pergunta, resposta, categoria) VALUES (:adm, :p, :r, :c)"
            );
            $ins->execute([
                'adm' => $user['perfil_id'], 'p' => $pergunta, 'r' => $resposta,
                'c' => $categoria !== '' ? $categoria : null,
            ]);
            flash('success', 'Pergunta adicionada à FAQ.');
            redirect('/admin/faq.php');
        }
    } elseif ($acao === 'excluir_faq') {
        $id = (int) ($_POST['id_faq'] ?? 0);
        $pdo->prepare('DELETE FROM FAQ WHERE id_faq = :id')->execute(['id' => $id]);
        flash('success', 'Pergunta removida.');
        redirect('/admin/faq.php');
    }
}

$faqs = $pdo->query('SELECT * FROM FAQ ORDER BY categoria, id_faq')->fetchAll();

layout_top('Gerenciar FAQ');
?>
<?php if ($erro): ?><div class="alert alert-error"><?= h($erro) ?></div><?php endif; ?>

<div class="card">
    <h2>Nova pergunta</h2>
    <form method="post" action="/admin/faq.php">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="acao" value="criar_faq">
        <label>Categoria</label>
        <input type="text" name="categoria" placeholder="Ex.: Contratos, Manutenção, Pagamentos">
        <label>Pergunta</label>
        <input type="text" name="pergunta" required>
        <label>Resposta</label>
        <textarea name="resposta" required></textarea>
        <button type="submit" class="btn">Adicionar</button>
    </form>
</div>

<div class="card">
    <h2>Perguntas cadastradas</h2>
    <?php if (!$faqs): ?>
        <p class="empty">Nenhuma pergunta cadastrada.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Categoria</th><th>Pergunta</th><th>Visualizações</th><th>Atualizado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($faqs as $f): ?>
            <tr>
                <td><?= h($f['categoria'] ?: '—') ?></td>
                <td><?= h($f['pergunta']) ?></td>
                <td><?= (int) $f['visualizacoes'] ?></td>
                <td><?= fdatetime($f['dataatualizacao']) ?></td>
                <td>
                    <form method="post" action="/admin/faq.php" onsubmit="return confirm('Remover esta pergunta?');">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="excluir_faq">
                        <input type="hidden" name="id_faq" value="<?= (int) $f['id_faq'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
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
