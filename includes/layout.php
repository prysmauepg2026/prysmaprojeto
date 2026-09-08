<?php
/**
 * PRYSMA - Layout compartilhado (topo e rodapé).
 * Uso: definir $pageTitle antes de incluir; chamar layout_top() e layout_bottom().
 */

declare(strict_types=1);

function layout_top(string $pageTitle): void
{
    $user = current_user();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?> · PRYSMA</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= $user ? h(base_url_for_role($user['perfil'])) : '/login.php' ?>">PRYSMA</a>
        <?php if ($user): ?>
        <nav class="nav">
            <?php foreach (nav_links($user['perfil']) as $label => $href): ?>
                <a href="<?= h($href) ?>" class="<?= (strtok($_SERVER['REQUEST_URI'] ?? '', '?') === $href) ? 'active' : '' ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="user-box">
            <span class="user-name"><?= h($user['nome']) ?> <small>(<?= h(ucfirst($user['perfil'])) ?>)</small></span>
            <form method="post" action="/logout.php" style="display:inline">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <button type="submit" class="btn-link">Sair</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</header>
<main class="container">
    <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['message']) ?></div>
    <?php endforeach; ?>
    <h1 class="page-title"><?= h($pageTitle) ?></h1>
<?php
}

function nav_links(string $role): array
{
    return match ($role) {
        'locatario' => [
            'Painel' => '/locatario/dashboard.php',
            'Meus Chamados' => '/locatario/chamados.php',
            'Boletos' => '/locatario/boletos.php',
            'FAQ' => '/locatario/faq.php',
        ],
        'locador' => [
            'Painel' => '/locador/dashboard.php',
            'Meus Imóveis' => '/locador/imoveis.php',
            'Repasses' => '/locador/repasses.php',
            'Informe de Rendimento' => '/locador/informe.php',
            'IPTU' => '/locador/iptu.php',
            'FAQ' => '/locador/faq.php',
        ],
        'admin' => [
            'Painel' => '/admin/dashboard.php',
            'Chamados' => '/admin/chamados.php',
            'Cobranças' => '/admin/cobrancas.php',
            'Contratos' => '/admin/contratos.php',
            'Imóveis' => '/admin/imoveis.php',
            'FAQ' => '/admin/faq.php',
        ],
        default => [],
    };
}

function layout_bottom(): void
{
    ?>
</main>
<footer class="footer">
    <div class="container">PRYSMA &middot; Plataforma de Gestão Imobiliária &middot; Projeto acadêmico UEPG</div>
</footer>
</body>
</html>
<?php
}

/** Badge de status (cor conforme valor). */
function status_badge(string $status): string
{
    $map = [
        'ATIVO' => 'ok', 'DISPONIVEL' => 'ok', 'PAGO' => 'ok', 'CONCLUIDO' => 'ok',
        'PENDENTE' => 'warn', 'ABERTO' => 'warn', 'EM_ANDAMENTO' => 'warn', 'AGUARDANDO_LOCATARIO' => 'warn', 'ALUGADO' => 'info',
        'ATRASADO' => 'danger', 'CANCELADO' => 'danger', 'RESCINDIDO' => 'danger', 'ENCERRADO' => 'muted', 'EM_MANUTENCAO' => 'warn', 'INATIVO' => 'muted',
    ];
    $cls = $map[$status] ?? 'muted';
    return '<span class="badge badge-' . $cls . '">' . h(str_replace('_', ' ', $status)) . '</span>';
}
