<?php
/**
 * UC07 - Emitir informe de rendimento.
 * Fluxo: locador escolhe o ano -> sistema soma os repasses líquidos recebidos
 * no período, agrupados por imóvel, para fins de declaração de renda.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/layout.php';

$user = require_login('locador');
$pdo = db();

$anoAtual = (int) date('Y');
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : $anoAtual;

$anosDisponiveis = $pdo->prepare(
    "SELECT DISTINCT EXTRACT(YEAR FROM r.dataRepasse)::int AS ano
     FROM Repasse r JOIN Cobranca cb ON cb.id_cobranca = r.id_cobranca
     JOIN Contrato c ON c.id_contrato = cb.id_contrato
     JOIN Imovel i ON i.id_imovel = c.id_imovel
     WHERE i.id_locador = :id AND r.dataRepasse IS NOT NULL
     ORDER BY ano DESC"
);
$anosDisponiveis->execute(['id' => $user['perfil_id']]);
$anosDisponiveis = array_column($anosDisponiveis->fetchAll(), 'ano');
if (!$anosDisponiveis) {
    $anosDisponiveis = [$anoAtual];
}

$linhas = $pdo->prepare(
    "SELECT i.logradouro, i.numero, i.bairro, ci.nome AS cidade, ci.uf,
            COUNT(r.id_repasse) AS qtd_repasses,
            COALESCE(SUM(r.valorBruto), 0) AS total_bruto,
            COALESCE(SUM(r.valorBruto - r.valorLiquido), 0) AS total_taxa,
            COALESCE(SUM(r.valorLiquido), 0) AS total_liquido
     FROM Imovel i
     JOIN Cidade ci ON ci.id_cidade = i.id_cidade
     JOIN Contrato c ON c.id_imovel = i.id_imovel
     JOIN Cobranca cb ON cb.id_contrato = c.id_contrato
     JOIN Repasse r ON r.id_cobranca = cb.id_cobranca AND r.dataRepasse IS NOT NULL
        AND EXTRACT(YEAR FROM r.dataRepasse) = :ano
     WHERE i.id_locador = :id
     GROUP BY i.id_imovel, i.logradouro, i.numero, i.bairro, ci.nome, ci.uf
     HAVING COUNT(r.id_repasse) > 0
     ORDER BY i.logradouro"
);
$linhas->execute(['ano' => $ano, 'id' => $user['perfil_id']]);
$linhas = $linhas->fetchAll();

$totalGeral = array_sum(array_column($linhas, 'total_liquido'));

$loc = $pdo->prepare(
    "SELECT u.nome, u.email, l.cpfCnpj FROM Locador l JOIN Usuario u ON u.id_usuario = l.id_usuario WHERE l.id_locador = :id"
);
$loc->execute(['id' => $user['perfil_id']]);
$locador = $loc->fetch();

layout_top('Informe de Rendimento');
?>
<form method="get" action="/locador/informe.php" class="card" style="display:flex; align-items:flex-end; gap:1rem;">
    <div>
        <label for="ano">Ano-calendário</label>
        <select id="ano" name="ano" onchange="this.form.submit()">
            <?php foreach ($anosDisponiveis as $a): ?>
                <option value="<?= (int) $a ?>" <?= $a === $ano ? 'selected' : '' ?>><?= (int) $a ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card" id="informe-imprimivel">
    <h2>Informe de Rendimentos - Ano <?= (int) $ano ?></h2>
    <p><strong>Locador:</strong> <?= h($locador['nome'] ?? '') ?> &middot; <?= h($locador['cpfcnpj'] ?? '') ?><br>
       <strong>Email:</strong> <?= h($locador['email'] ?? '') ?></p>

    <?php if (!$linhas): ?>
        <p class="empty">Nenhum repasse registrado para o ano selecionado.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Imóvel</th><th>Cidade</th><th>Repasses</th><th>Total bruto</th><th>Total taxa adm.</th><th>Total líquido recebido</th></tr></thead>
        <tbody>
        <?php foreach ($linhas as $l): ?>
            <tr>
                <td><?= h($l['logradouro']) ?>, <?= h($l['numero']) ?> - <?= h($l['bairro']) ?></td>
                <td><?= h($l['cidade']) ?>/<?= h($l['uf']) ?></td>
                <td><?= (int) $l['qtd_repasses'] ?></td>
                <td><?= money($l['total_bruto']) ?></td>
                <td><?= money($l['total_taxa']) ?></td>
                <td><strong><?= money($l['total_liquido']) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><th colspan="5">Total geral recebido no ano</th><th><?= money($totalGeral) ?></th></tr>
        </tfoot>
    </table>
    </div>
    <button class="btn btn-secondary" onclick="window.print()" type="button">Imprimir / salvar em PDF</button>
    <?php endif; ?>
</div>
<?php layout_bottom(); ?>
