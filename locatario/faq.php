<?php
/**
 * UC02 - Consultar FAQ (dúvidas frequentes).
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locatario');
$pdo = db();

$busca = trim((string) ($_GET['q'] ?? ''));
if ($busca !== '') {
    $stmt = $pdo->prepare("SELECT * FROM FAQ WHERE pergunta ILIKE :q OR resposta ILIKE :q OR categoria ILIKE :q ORDER BY categoria, id_faq");
    $stmt->execute(['q' => '%' . $busca . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM FAQ ORDER BY categoria, id_faq");
}
$faqs = $stmt->fetchAll();

$porCategoria = [];
foreach ($faqs as $f) {
    $cat = $f['categoria'] ?: 'Geral';
    $porCategoria[$cat][] = $f;
}

layout_top('Dúvidas Frequentes (FAQ)');
?>
<form method="get" action="/locatario/faq.php" style="margin-bottom:1.25rem">
    <input type="text" name="q" placeholder="Buscar por palavra-chave..." value="<?= h($busca) ?>">
</form>

<?php if (!$faqs): ?>
    <div class="card">
        <!-- 4a. Nenhum resultado encontrado -->
        <p class="empty">Nenhuma pergunta encontrada para essa busca. Se preferir, entre em contato com a nossa equipe de atendimento.</p>
    </div>
<?php else: ?>
    <?php foreach ($porCategoria as $cat => $itens): ?>
    <div class="card">
        <h3><?= h($cat) ?></h3>
        <?php foreach ($itens as $f): ?>
        <details class="faq-item" data-id="<?= (int) $f['id_faq'] ?>">
            <summary><?= h($f['pergunta']) ?></summary>
            <p><?= nl2br(h($f['resposta'])) ?></p>
        </details>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<script>
document.querySelectorAll('details.faq-item').forEach(function (d) {
    d.addEventListener('toggle', function () {
        if (d.open) {
            fetch('/faq_view.php?id=' + d.dataset.id, { method: 'POST' });
        }
    });
});
</script>
<?php layout_bottom(); ?>
