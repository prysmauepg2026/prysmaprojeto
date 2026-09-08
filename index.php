<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

redirect(is_logged_in() ? base_url_for_role(current_user()['perfil']) : '/login.php');
