<?php
/**
 * UC03 - Emitir 2ª via de boleto.
 * Ator secundário: Sistema Bancário (implementado via Boleto Cloud sandbox,
 * ver includes/boletocloud.php). O PDF retornado pela API é salvo localmente
 * e servido pela própria aplicação (locatario/boleto_pdf.php), sem depender
 * de um link externo.
 *
 * Fluxo principal:
 * 1. Locatário acessa a tela de segunda via de boleto.
 * 2. Sistema lista as cobranças em aberto vinculadas ao contrato.
 * 3. Locatário seleciona a cobrança e solicita a segunda via.
 * 4. Sistema requisita ao Sistema Bancário a geração do boleto.
 * 5. Sistema Bancário retorna código de barras e linha digitável.
 * 6. Sistema atualiza o boleto e disponibiliza o arquivo para download.
 *
 * Fluxos alternativos:
 * 2a. Não há cobrança em aberto -> mensagem e encerra o fluxo.
 * 4a. Falha na comunicação com o Sistema Bancário -> mensagem de
 *     indisponibilidade temporária, orienta nova tentativa.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/boletocloud.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locatario');
$pdo = db();

/** Busca os dados completos (locatário + imóvel + cidade) de uma cobrança do usuário logado. */
function boleto_buscar_cobranca(PDO $pdo, int $idLocatario, int $idCobranca): ?array
{
    $stmt = $pdo->prepare(
        "SELECT cb.*, c.id_contrato,
                i.logradouro, i.numero, i.bairro, i.cep,
                ci.nome AS cidade, ci.uf,
                u.nome AS locatario_nome, u.email AS locatario_email, lt.cpf AS locatario_cpf,
                b.id_boleto, b.linhadigitavel, b.codigobarras, b.valormulta, b.valorjuros,
                b.idexterno, b.urlpdf, b.datageracao
         FROM Cobranca cb
         JOIN Contrato c ON c.id_contrato = cb.id_contrato
         JOIN Imovel i ON i.id_imovel = c.id_imovel
         JOIN Cidade ci ON ci.id_cidade = i.id_cidade
         JOIN Locatario lt ON lt.id_locatario = c.id_locatario
         JOIN Usuario u ON u.id_usuario = lt.id_usuario
         LEFT JOIN Boleto b ON b.id_cobranca = cb.id_cobranca
         WHERE cb.id_cobranca = :cob AND c.id_locatario = :lt"
    );
    $stmt->execute(['cob' => $idCobranca, 'lt' => $idLocatario]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$erro = null;

// 3./4./5./6. Locatário solicita a 2ª via -> aciona o Sistema Bancário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'solicitar_2via') {
    csrf_check();
    $idCobranca = (int) ($_POST['id_cobranca'] ?? 0);
    $cobranca = boleto_buscar_cobranca($pdo, $user['perfil_id'], $idCobranca);

    if (!$cobranca || !in_array($cobranca['status'], ['PENDENTE', 'ATRASADO'], true)) {
        flash('error', 'Cobrança não encontrada ou não está mais em aberto.');
        redirect('/locatario/boletos.php');
    }

    // Já foi gerado antes: a "2ª via" é só reexibir o boleto já emitido,
    // sem acionar o Sistema Bancário de novo.
    if (!empty($cobranca['urlpdf'])) {
        redirect('/locatario/boletos.php?ver=' . $idCobranca);
    }

    try {
        $resultado = bc_gerar_boleto([
            'id_cobranca' => $idCobranca,
            'valor' => (float) $cobranca['valor'],
            'vencimento' => (string) $cobranca['datavencimento'],
            'descricao' => 'Aluguel - Cobrança #' . $idCobranca . ' - PRYSMA',
            'nome' => $cobranca['locatario_nome'],
            'email' => $cobranca['locatario_email'],
            'cpf' => $cobranca['locatario_cpf'],
            'cep' => $cobranca['cep'],
            'logradouro' => $cobranca['logradouro'],
            'numero' => $cobranca['numero'],
            'bairro' => $cobranca['bairro'],
            'cidade' => $cobranca['cidade'],
            'uf' => $cobranca['uf'],
        ]);

        if ($cobranca['id_boleto']) {
            $upd = $pdo->prepare(
                "UPDATE Boleto SET linhaDigitavel = :ld, codigoBarras = :cb, idExterno = :ext, urlPdf = :url, dataGeracao = NOW()
                 WHERE id_cobranca = :cobr"
            );
            $upd->execute([
                'ld' => $resultado['linha_digitavel'], 'cb' => $resultado['codigo_barras'],
                'ext' => $resultado['id_externo'], 'url' => $resultado['url_pdf'], 'cobr' => $idCobranca,
            ]);
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO Boleto (id_cobranca, linhaDigitavel, codigoBarras, idExterno, urlPdf, dataGeracao)
                 VALUES (:cobr, :ld, :cb, :ext, :url, NOW())"
            );
            $ins->execute([
                'cobr' => $idCobranca, 'ld' => $resultado['linha_digitavel'], 'cb' => $resultado['codigo_barras'],
                'ext' => $resultado['id_externo'], 'url' => $resultado['url_pdf'],
            ]);
        }

        flash('success', '2ª via gerada com sucesso pelo Sistema Bancário.');
        redirect('/locatario/boletos.php?ver=' . $idCobranca);
    } catch (SistemaBancarioIndisponivelException $e) {
        // 4a. Falha na comunicação com o Sistema Bancário
        error_log('[UC03] ' . $e->getMessage());
        flash('error', 'O Sistema Bancário está indisponível no momento. Tente novamente em instantes.');
        redirect('/locatario/boletos.php');
    }
}

// 2. Lista as cobranças em aberto vinculadas ao contrato do locatário
$abertas = $pdo->prepare(
    "SELECT cb.*, i.logradouro, i.numero, b.id_boleto, b.urlpdf
     FROM Cobranca cb
     JOIN Contrato c ON c.id_contrato = cb.id_contrato
     JOIN Imovel i ON i.id_imovel = c.id_imovel
     LEFT JOIN Boleto b ON b.id_cobranca = cb.id_cobranca
     WHERE c.id_locatario = :id AND cb.status IN ('PENDENTE', 'ATRASADO')
     ORDER BY cb.dataVencimento ASC"
);
$abertas->execute(['id' => $user['perfil_id']]);
$abertas = $abertas->fetchAll();

// Histórico (inclui pagas/canceladas) só para referência do locatário.
$historico = $pdo->prepare(
    "SELECT cb.*, i.logradouro, i.numero, b.id_boleto
     FROM Cobranca cb
     JOIN Contrato c ON c.id_contrato = cb.id_contrato
     JOIN Imovel i ON i.id_imovel = c.id_imovel
     LEFT JOIN Boleto b ON b.id_cobranca = cb.id_cobranca
     WHERE c.id_locatario = :id AND cb.status NOT IN ('PENDENTE', 'ATRASADO')
     ORDER BY cb.dataVencimento DESC"
);
$historico->execute(['id' => $user['perfil_id']]);
$historico = $historico->fetchAll();

$verId = isset($_GET['ver']) ? (int) $_GET['ver'] : null;
$boletoSelecionado = $verId ? boleto_buscar_cobranca($pdo, $user['perfil_id'], $verId) : null;
if ($verId && !$boletoSelecionado) {
    flash('error', 'Cobrança não encontrada para este usuário.');
}

layout_top('Meus Boletos');
?>
<p class="subtitle">Emissão de 2ª via de boleto para suas cobranças (via Sistema Bancário).</p>

<?php if ($boletoSelecionado): ?>
<div class="card">
    <h2>2ª via - Boleto</h2>
    <p class="breadcrumb"><a href="/locatario/boletos.php">&larr; voltar à lista</a></p>
    <table>
        <tr><th>Imóvel</th><td><?= h($boletoSelecionado['logradouro']) ?>, <?= h($boletoSelecionado['numero']) ?></td></tr>
        <tr><th>Vencimento</th><td><?= fdate($boletoSelecionado['datavencimento']) ?></td></tr>
        <tr><th>Valor original</th><td><?= money($boletoSelecionado['valor']) ?></td></tr>
        <?php if ($boletoSelecionado['status'] === 'ATRASADO'): ?>
        <tr><th>Multa</th><td><?= money($boletoSelecionado['valormulta']) ?></td></tr>
        <tr><th>Juros</th><td><?= money($boletoSelecionado['valorjuros']) ?></td></tr>
        <tr><th>Valor atualizado</th><td><strong><?= money((float) $boletoSelecionado['valor'] + (float) $boletoSelecionado['valormulta'] + (float) $boletoSelecionado['valorjuros']) ?></strong></td></tr>
        <?php endif; ?>
                <tr><th>Status</th><td><?= status_badge($boletoSelecionado['status']) ?></td></tr>
        <?php if ($boletoSelecionado['linhadigitavel']): ?>
        <tr><th>Linha digitável</th><td><code><?= h($boletoSelecionado['linhadigitavel']) ?></code></td></tr>
        <?php endif; ?>
        <?php if ($boletoSelecionado['codigobarras']): ?>
        <tr><th>Código de barras</th><td><code><?= h($boletoSelecionado['codigobarras']) ?></code></td></tr>
        <?php endif; ?>
        <tr><th>Gerado em</th><td><?= $boletoSelecionado['datageracao'] ? fdatetime($boletoSelecionado['datageracao']) : '—' ?></td></tr>
    </table>
    <?php if (!empty($boletoSelecionado['urlpdf'])): ?>
        <a class="btn" href="<?= h($boletoSelecionado['urlpdf']) ?>" target="_blank" rel="noopener">Baixar PDF do boleto</a>
        <?php if (!$boletoSelecionado['linhadigitavel']): ?>
        <p class="hint">A linha digitável e o código de barras completos estão impressos no PDF do boleto
        (abaixo). Exibi-los também aqui na tela depende de um plano pago da Boleto Cloud; o PDF já traz
        essa informação normalmente, igual a um boleto real.</p>
        <?php endif; ?>
    <?php elseif (in_array($boletoSelecionado['status'], ['PENDENTE', 'ATRASADO'], true)): ?>
        <div class="alert alert-info">Esta cobrança ainda não tem 2ª via gerada. Volte à lista e clique em "Solicitar 2ª via".</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Cobranças em aberto</h2>
    <?php if (!$abertas): ?>
        <!-- 2a. Não há cobrança em aberto -->
        <p class="empty">Nenhuma cobrança em aberto no momento.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Vencimento</th><th>Imóvel</th><th>Valor</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($abertas as $c): ?>
            <tr>
                <td><?= fdate($c['datavencimento']) ?></td>
                <td><?= h($c['logradouro']) ?>, <?= h($c['numero']) ?></td>
                <td><?= money($c['valor']) ?></td>
                <td><?= status_badge($c['status']) ?></td>
                <td>
                    <?php if (!empty($c['urlpdf'])): ?>
                        <a class="btn btn-sm btn-secondary" href="/locatario/boletos.php?ver=<?= (int) $c['id_cobranca'] ?>">Ver 2ª via</a>
                    <?php else: ?>
                        <form method="post" action="/locatario/boletos.php" style="display:inline">
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="acao" value="solicitar_2via">
                            <input type="hidden" name="id_cobranca" value="<?= (int) $c['id_cobranca'] ?>">
                            <button type="submit" class="btn btn-sm">Solicitar 2ª via</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($historico): ?>
<div class="card">
    <h2>Histórico</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Vencimento</th><th>Imóvel</th><th>Valor</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($historico as $c): ?>
            <tr>
                <td><?= fdate($c['datavencimento']) ?></td>
                <td><?= h($c['logradouro']) ?>, <?= h($c['numero']) ?></td>
                <td><?= money($c['valor']) ?></td>
                <td><?= status_badge($c['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
<?php layout_bottom(); ?>
