<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

require_login();
$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = db()->prepare('UPDATE FAQ SET visualizacoes = visualizacoes + 1 WHERE id_faq = :id');
    $stmt->execute(['id' => $id]);
}
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
