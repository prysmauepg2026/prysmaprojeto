<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}
$_SESSION = [];
session_destroy();
session_start();
flash('info', 'Sessão encerrada.');
redirect('/login.php');
