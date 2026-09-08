<?php

/**
 * UC01 - Autenticar-se.
 * Fluxo principal: usuário informa email/senha -> sistema valida credenciais
 * -> valida se está ativo -> identifica o perfil -> redireciona ao painel.
 * Fluxos alternativos: 4a credenciais inválidas; 4b usuário inativo.
 */

declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect(base_url_for_role(current_user()['perfil']));
}

$erro = null;
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $emailValue = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($emailValue === '' || $senha === '') {
        $erro = 'Informe email e senha.';
    } else {
        $stmt = db()->prepare('SELECT * FROM Usuario WHERE email = :email');
        $stmt->execute(['email' => $emailValue]);
        $usuario = $stmt->fetch();

        // 4a. Credenciais inválidas
        if (!$usuario || !password_verify($senha, $usuario['senhahash'])) {
            $erro = 'E-mail ou senha inválidos';
        } elseif (!$usuario['ativo']) {
            // 4b. Usuário inativo
            $erro = 'Este usuário está inativo. Procure a administração.';
        } else {
            // Identifica o perfil (Locador, Locatário ou Administrador)
            $perfil = null;
            $perfilId = null;

            $l = db()->prepare('SELECT id_locador FROM Locador WHERE id_usuario = :id');
            $l->execute(['id' => $usuario['id_usuario']]);
            if ($row = $l->fetch()) {
                $perfil = 'locador';
                $perfilId = (int) $row['id_locador'];
            }

            if (!$perfil) {
                $t = db()->prepare('SELECT id_locatario FROM Locatario WHERE id_usuario = :id');
                $t->execute(['id' => $usuario['id_usuario']]);
                if ($row = $t->fetch()) {
                    $perfil = 'locatario';
                    $perfilId = (int) $row['id_locatario'];
                }
            }

            if (!$perfil) {
                $a = db()->prepare('SELECT id_admin FROM ADMIN WHERE id_usuario = :id');
                $a->execute(['id' => $usuario['id_usuario']]);
                if ($row = $a->fetch()) {
                    $perfil = 'admin';
                    $perfilId = (int) $row['id_admin'];
                }
            }

            if (!$perfil) {
                $erro = 'Usuário sem perfil associado. Procure a administração.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id_usuario' => (int) $usuario['id_usuario'],
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email'],
                    'perfil' => $perfil,
                    'perfil_id' => $perfilId,
                ];
                flash('success', 'Bem-vindo(a), ' . $usuario['nome'] . '!');
                redirect(base_url_for_role($perfil));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar · PRYSMA</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<main class="container login-wrap">
    <div class="login-logo">PRYSMA</div>
    <div class="card">
        <h2>Entrar</h2>
        <?php if ($erro): ?>
            <div class="alert alert-error"><?= h($erro) ?></div>
        <?php endif; ?>
        <?php foreach (take_flashes() as $f): ?>
            <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['message']) ?></div>
        <?php endforeach; ?>
        <form method="post" action="/login.php" novalidate>
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= h($emailValue) ?>" required autofocus>
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" required>
            <button type="submit" class="btn">Entrar</button>
        </form>
        <div class="demo-creds">
            <strong>Contas de demonstração</strong> (senha: <code>123456</code>)<br>
            Locador: joao.locador@prysma.com<br>
            Locatário: maria.locataria@prysma.com<br>
            Administrador: admin@prysma.com
        </div>
    </div>
</main>
</body>
</html>
