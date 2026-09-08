<?php
/**
 * Serve o PDF do boleto (gerado pela Boleto Cloud e salvo em
 * includes/../storage/boletos/) para o locatário dono da cobrança.
 * Evita expor os arquivos diretamente por URL pública sem checar login/dono.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/boletocloud.php';

$user = require_login('locatario');
$pdo = db();

$idCobranca = (int) ($_GET['cobranca'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT b.id_boleto
     FROM Boleto b
     JOIN Cobranca cb ON cb.id_cobranca = b.id_cobranca
     JOIN Contrato c ON c.id_contrato = cb.id_contrato
     WHERE b.id_cobranca = :cob AND c.id_locatario = :lt"
);
$stmt->execute(['cob' => $idCobranca, 'lt' => $user['perfil_id']]);

if (!$stmt->fetch()) {
    http_response_code(404);
    exit('Boleto não encontrado.');
}

$caminho = BC_STORAGE_DIR . '/cobranca-' . $idCobranca . '.pdf';
if (!is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo do boleto não encontrado. Solicite a 2ª via novamente.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="boleto-cobranca-' . $idCobranca . '.pdf"');
header('Content-Length: ' . filesize($caminho));
readfile($caminho);
