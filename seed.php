<?php
/**
 * Script de dados de demonstração para o PRYSMA.
 * Executar via linha de comando: php seed.php
 * Popula usuários (locador, locatário, admin), imóveis, contrato,
 * cobranças (paga, pendente, atrasada), boletos, repasse, chamados e FAQ.
 * Seguro para rodar mais de uma vez: limpa os dados de demonstração antes de inserir.
 */
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$pdo = db();
$pdo->beginTransaction();

try {
    // Limpa dados de demonstração anteriores (ordem respeita FKs)
    $pdo->exec('DELETE FROM Documento');
    $pdo->exec('DELETE FROM IptuParcela');
    $pdo->exec('DELETE FROM IPTU');
    $pdo->exec('DELETE FROM Repasse');
    $pdo->exec('DELETE FROM Boleto');
    $pdo->exec('DELETE FROM Cobranca');
    $pdo->exec('DELETE FROM ChamadoManutencao');
    $pdo->exec('DELETE FROM Contrato');
    $pdo->exec('DELETE FROM Imovel');
    $pdo->exec('DELETE FROM Prestador');
    $pdo->exec('DELETE FROM FAQ');
    $pdo->exec('DELETE FROM ADMIN');
    $pdo->exec('DELETE FROM Locatario');
    $pdo->exec('DELETE FROM Locador');
    $pdo->exec('DELETE FROM Usuario');
    $pdo->exec('DELETE FROM Cidade');
    foreach (['usuario_id_usuario_seq', 'cidade_id_cidade_seq', 'locador_id_locador_seq', 'locatario_id_locatario_seq',
              'admin_id_admin_seq', 'imovel_id_imovel_seq', 'prestador_id_prestador_seq', 'contrato_id_contrato_seq',
              'chamadomanutencao_id_chamado_seq', 'cobranca_id_cobranca_seq', 'boleto_id_boleto_seq',
              'repasse_id_repasse_seq', 'faq_id_faq_seq', 'iptu_id_iptu_seq', 'iptuparcela_id_parcela_seq'] as $seq) {
        $pdo->exec("ALTER SEQUENCE IF EXISTS $seq RESTART WITH 1");
    }

    $senhaHash = password_hash('123456', PASSWORD_DEFAULT);

    // ---- Cidades ----
    $pdo->exec("INSERT INTO Cidade (nome, uf) VALUES
        ('Ponta Grossa', 'PR'), ('Curitiba', 'PR'), ('Castro', 'PR')");
    [$cidPontaGrossa, $cidCuritiba, $cidCastro] = [1, 2, 3];

    // ---- Usuários + perfis ----
    $insUsuario = $pdo->prepare('INSERT INTO Usuario (nome, email, senhaHash, ativo) VALUES (:n, :e, :h, :a) RETURNING id_usuario');

    $insUsuario->execute(['n' => 'João Pedro Locador', 'e' => 'joao.locador@prysma.com', 'h' => $senhaHash, 'a' => 't']);
    $uLocador1 = (int) $insUsuario->fetchColumn();
    $insUsuario->execute(['n' => 'Beatriz Andrade', 'e' => 'beatriz.locadora@prysma.com', 'h' => $senhaHash, 'a' => 't']);
    $uLocador2 = (int) $insUsuario->fetchColumn();

    $insUsuario->execute(['n' => 'Maria Locatária', 'e' => 'maria.locataria@prysma.com', 'h' => $senhaHash, 'a' => 't']);
    $uLocatario1 = (int) $insUsuario->fetchColumn();
    $insUsuario->execute(['n' => 'Carlos Souza', 'e' => 'carlos.locatario@prysma.com', 'h' => $senhaHash, 'a' => 't']);
    $uLocatario2 = (int) $insUsuario->fetchColumn();
    $insUsuario->execute(['n' => 'Fernanda Lima', 'e' => 'fernanda.inativa@prysma.com', 'h' => $senhaHash, 'a' => 'f']);
    $uLocatarioInativo = (int) $insUsuario->fetchColumn();

    $insUsuario->execute(['n' => 'Administrador PRYSMA', 'e' => 'admin@prysma.com', 'h' => $senhaHash, 'a' => 't']);
    $uAdmin = (int) $insUsuario->fetchColumn();

    $insLocador = $pdo->prepare('INSERT INTO Locador (id_usuario, cpfCnpj, tipoPessoa, dadosBancarios) VALUES (:u, :doc, :tp, :db) RETURNING id_locador');
    $insLocador->execute(['u' => $uLocador1, 'doc' => '123.456.789-00', 'tp' => 'PF', 'db' => 'Banco 001 - Ag 1234 - CC 56789-0']);
    $locador1 = (int) $insLocador->fetchColumn();
    $insLocador->execute(['u' => $uLocador2, 'doc' => '12.345.678/0001-90', 'tp' => 'PJ', 'db' => 'Banco 341 - Ag 4321 - CC 98765-4']);
    $locador2 = (int) $insLocador->fetchColumn();

    $insLocatario = $pdo->prepare('INSERT INTO Locatario (id_usuario, cpf, telefone) VALUES (:u, :cpf, :tel) RETURNING id_locatario');
    $insLocatario->execute(['u' => $uLocatario1, 'cpf' => '98765432100', 'tel' => '(42) 99911-2233']);
    $locatario1 = (int) $insLocatario->fetchColumn();
    $insLocatario->execute(['u' => $uLocatario2, 'cpf' => '11122233344', 'tel' => '(42) 98822-1144']);
    $locatario2 = (int) $insLocatario->fetchColumn();
    $insLocatario->execute(['u' => $uLocatarioInativo, 'cpf' => '55566677788', 'tel' => '(42) 97711-0055']);
    $locatarioInativo = (int) $insLocatario->fetchColumn();

    $insAdmin = $pdo->prepare('INSERT INTO ADMIN (id_usuario, matriculaFuncional, cargo) VALUES (:u, :mat, :cargo) RETURNING id_admin');
    $insAdmin->execute(['u' => $uAdmin, 'mat' => 'ADM-0001', 'cargo' => 'Administrador do Sistema']);
    $admin1 = (int) $insAdmin->fetchColumn();

    // ---- Prestadores ----
    $insPrestador = $pdo->prepare('INSERT INTO Prestador (nome, documento, especialidade, telefone, email, ativo) VALUES (:n, :doc, :esp, :tel, :em, TRUE) RETURNING id_prestador');
    $insPrestador->execute(['n' => 'Reparos Rápidos Ltda', 'doc' => '11.222.333/0001-44', 'esp' => 'Elétrica', 'tel' => '(42) 3222-1000', 'em' => 'contato@reparosrapidos.com']);
    $prest1 = (int) $insPrestador->fetchColumn();
    $insPrestador->execute(['n' => 'Hidráulica Central', 'doc' => '22.333.444/0001-55', 'esp' => 'Hidráulica', 'tel' => '(42) 3222-2000', 'em' => 'contato@hidraulicacentral.com']);
    $prest2 = (int) $insPrestador->fetchColumn();

    // ---- Imóveis ----
    $insImovel = $pdo->prepare(
        "INSERT INTO Imovel (id_locador, id_cidade, matricula, tipo, valorAluguel, numero, cep, status, logradouro, bairro, metragem)
         VALUES (:loc, :cid, :mat, :tipo, :val, :num, :cep, :st, :log, :bai, :met) RETURNING id_imovel"
    );
    $insImovel->execute(['loc' => $locador1, 'cid' => $cidPontaGrossa, 'mat' => 'MAT-1001', 'tipo' => 'Apartamento', 'val' => 1450.00, 'num' => '220', 'cep' => '84010-000', 'st' => 'DISPONIVEL', 'log' => 'Rua Balduíno Taques', 'bai' => 'Centro', 'met' => 68.5]);
    $imovel1 = (int) $insImovel->fetchColumn();
    $insImovel->execute(['loc' => $locador1, 'cid' => $cidPontaGrossa, 'mat' => 'MAT-1002', 'tipo' => 'Casa', 'val' => 2100.00, 'num' => '85', 'cep' => '84030-500', 'st' => 'DISPONIVEL', 'log' => 'Av. Visconde de Taunay', 'bai' => 'Jardim Carvalho', 'met' => 120.0]);
    $imovel2 = (int) $insImovel->fetchColumn();
    $insImovel->execute(['loc' => $locador2, 'cid' => $cidCuritiba, 'mat' => 'MAT-2001', 'tipo' => 'Sala comercial', 'val' => 1800.00, 'num' => '1500', 'cep' => '80230-010', 'st' => 'DISPONIVEL', 'log' => 'Rua XV de Novembro', 'bai' => 'Centro', 'met' => 45.0]);
    $imovel3 = (int) $insImovel->fetchColumn();
    $insImovel->execute(['loc' => $locador2, 'cid' => $cidCastro, 'mat' => 'MAT-2002', 'tipo' => 'Apartamento', 'val' => 1100.00, 'num' => '40', 'cep' => '84165-000', 'st' => 'EM_MANUTENCAO', 'log' => 'Rua Barão do Rio Branco', 'bai' => 'Centro', 'met' => 55.0]);
    $imovel4 = (int) $insImovel->fetchColumn();

    // ---- Contratos ----
    $insContrato = $pdo->prepare(
        "INSERT INTO Contrato (id_locatario, id_imovel, dataInicio, dataFim, valorAluguel, valorCaucao, taxaAdministracao, status)
         VALUES (:lt, :im, :di, :df, :val, :cau, :taxa, :st) RETURNING id_contrato"
    );
    $insContrato->execute(['lt' => $locatario1, 'im' => $imovel1, 'di' => '2025-08-01', 'df' => '2027-07-31', 'val' => 1450.00, 'cau' => 2900.00, 'taxa' => 8.50, 'st' => 'ATIVO']);
    $contrato1 = (int) $insContrato->fetchColumn();
    $insContrato->execute(['lt' => $locatario2, 'im' => $imovel3, 'di' => '2026-01-15', 'df' => '2028-01-14', 'val' => 1800.00, 'cau' => 3600.00, 'taxa' => 10.00, 'st' => 'ATIVO']);
    $contrato2 = (int) $insContrato->fetchColumn();
    $insContrato->execute(['lt' => $locatario1, 'im' => $imovel2, 'di' => '2023-03-01', 'df' => '2025-02-28', 'val' => 2000.00, 'cau' => 4000.00, 'taxa' => 8.00, 'st' => 'ENCERRADO']);
    $contratoAntigo = (int) $insContrato->fetchColumn();

    // ---- Cobranças + Boletos + Repasses ----
    $insCobranca = $pdo->prepare(
        "INSERT INTO Cobranca (id_contrato, valor, dataVencimento, status, dataPagamento) VALUES (:c, :v, :dv, :st, :dp) RETURNING id_cobranca"
    );
    $insBoleto = $pdo->prepare(
        "INSERT INTO Boleto (id_cobranca, linhaDigitavel, codigoBarras, valorMulta, valorJuros) VALUES (:cb, :ld, :cbar, :mu, :ju)"
    );
    $insRepasse = $pdo->prepare(
        "INSERT INTO Repasse (id_cobranca, valorBruto, dataRepasse) VALUES (:cb, :vb, :dr)"
    );

    // Contrato 1: cobrança paga (mês anterior) + repasse já efetuado
    $insCobranca->execute(['c' => $contrato1, 'v' => 1450.00, 'dv' => date('Y-m-d', strtotime('first day of last month')), 'st' => 'PAGO', 'dp' => date('Y-m-d', strtotime('first day of last month +2 days'))]);
    $cobPagaMesPassado = (int) $insCobranca->fetchColumn();
    $insBoleto->execute(['cb' => $cobPagaMesPassado, 'ld' => '34191.79001 01043.510047 91020.150008 1 98760000145000', 'cbar' => '34199987600001450001790010104351004791020', 'mu' => 0, 'ju' => 0]);
    $insRepasse->execute(['cb' => $cobPagaMesPassado, 'vb' => 1450.00, 'dr' => date('Y-m-d', strtotime('first day of last month +5 days'))]);

    // Contrato 1: cobrança pendente (mês atual)
    $insCobranca->execute(['c' => $contrato1, 'v' => 1450.00, 'dv' => date('Y-m-d', strtotime('first day of this month +9 days')), 'st' => 'PENDENTE', 'dp' => null]);
    $cobPendente = (int) $insCobranca->fetchColumn();
    $insBoleto->execute(['cb' => $cobPendente, 'ld' => '34191.79001 01043.510047 91020.150008 1 98760000145000', 'cbar' => '34199987600001450001790010104351004791020', 'mu' => 0, 'ju' => 0]);

    // Contrato 2: cobrança atrasada (mês passado, vencida e não paga)
    $insCobranca->execute(['c' => $contrato2, 'v' => 1800.00, 'dv' => date('Y-m-d', strtotime('first day of last month +9 days')), 'st' => 'PENDENTE', 'dp' => null]);
    $cobAtrasada = (int) $insCobranca->fetchColumn(); // trigger marca como ATRASADO automaticamente
    $insBoleto->execute(['cb' => $cobAtrasada, 'ld' => '10491.79001 02043.610047 92020.150008 1 98760000180000', 'cbar' => '10499987600001800001790010204361004792020', 'mu' => 36.00, 'ju' => 12.60]);

    // Contrato 2: cobrança paga do mês atual + repasse
    $insCobranca->execute(['c' => $contrato2, 'v' => 1800.00, 'dv' => date('Y-m-d', strtotime('first day of this month +4 days')), 'st' => 'PAGO', 'dp' => date('Y-m-d', strtotime('first day of this month +3 days'))]);
    $cobPagaAtual = (int) $insCobranca->fetchColumn();
    $insBoleto->execute(['cb' => $cobPagaAtual, 'ld' => '10491.79001 02043.610047 92020.150008 1 98760000180000', 'cbar' => '10499987600001800001790010204361004792020', 'mu' => 0, 'ju' => 0]);
    $insRepasse->execute(['cb' => $cobPagaAtual, 'vb' => 1800.00, 'dr' => date('Y-m-d', strtotime('first day of this month +6 days'))]);

    // ---- Chamados de manutenção ----
    $insChamado = $pdo->prepare(
        "INSERT INTO ChamadoManutencao (id_locatario, id_imovel, id_prestador, titulo, descricao, prioridade, status)
         VALUES (:lt, :im, :pr, :tit, :desc, :prio, :st)"
    );
    // Chamados com prestador/status já avançado recebem dataAbertura e
    // dataAtualizacao explícitas no INSERT (a trigger trg_chamado_atualizar_data
    // só dispara em UPDATE, e o DEFAULT de dataAbertura é NOW()), para a tela
    // de detalhes (UC05) mostrar um histórico coerente (abertura sempre antes
    // da última atualização/fechamento).
    $insChamadoAvancado = $pdo->prepare(
        "INSERT INTO ChamadoManutencao (id_locatario, id_imovel, id_prestador, titulo, descricao, prioridade, status, dataAbertura, dataAtualizacao, dataFechamento)
         VALUES (:lt, :im, :pr, :tit, :desc, :prio, :st, :datAbe, :datAtu, :datFec)"
    );
    $insChamado->execute(['lt' => $locatario1, 'im' => $imovel1, 'pr' => null, 'tit' => 'Vazamento no banheiro', 'desc' => 'Há um vazamento embaixo da pia do banheiro social.', 'prio' => 'ALTA', 'st' => 'ABERTO']);
    $insChamadoAvancado->execute([
        'lt' => $locatario2, 'im' => $imovel3, 'pr' => $prest1, 'tit' => 'Tomada sem energia',
        'desc' => 'A tomada da sala parou de funcionar.', 'prio' => 'NORMAL', 'st' => 'EM_ANDAMENTO',
        'datAbe' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'datAtu' => date('Y-m-d H:i:s', strtotime('-1 day')), 'datFec' => null,
    ]);
    $insChamadoAvancado->execute([
        'lt' => $locatario1, 'im' => $imovel1, 'pr' => $prest2, 'tit' => 'Troca de registro da caixa d\'água',
        'desc' => 'Registro antigo, difícil de fechar.', 'prio' => 'BAIXA', 'st' => 'CONCLUIDO',
        'datAbe' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'datAtu' => date('Y-m-d H:i:s', strtotime('-2 days')), 'datFec' => date('Y-m-d H:i:s', strtotime('-2 days')),
    ]);

    // ---- FAQ ----
    $insFaq = $pdo->prepare('INSERT INTO FAQ (id_admin, pergunta, resposta, categoria) VALUES (:a, :p, :r, :c)');
    $insFaq->execute(['a' => $admin1, 'p' => 'Como emito a 2ª via do boleto de aluguel?', 'r' => 'Acesse seu painel de locatário, clique em "Boletos" e selecione "Ver 2ª via" na cobrança desejada.', 'c' => 'Pagamentos']);
    $insFaq->execute(['a' => $admin1, 'p' => 'Como abro um chamado de manutenção?', 'r' => 'No painel do locatário, acesse "Meus Chamados" e clique em "abrir". Escolha o imóvel, descreva o problema e defina a prioridade.', 'c' => 'Manutenção']);
    $insFaq->execute(['a' => $admin1, 'p' => 'Como funciona a taxa de administração?', 'r' => 'A taxa de administração é definida em cada contrato e é descontada automaticamente do valor bruto de cada repasse ao locador.', 'c' => 'Contratos']);
    $insFaq->execute(['a' => $admin1, 'p' => 'Onde consulto o informe de rendimento anual?', 'r' => 'No painel do locador, acesse "Informe de Rendimento", escolha o ano e visualize (ou imprima) o resumo de repasses recebidos.', 'c' => 'Pagamentos']);

    // ---- IPTU (visualização de taxas e impostos) ----
    $insIptu = $pdo->prepare(
        'INSERT INTO IPTU (id_imovel, ano, valorTotal, quantidadeParcelas) VALUES (:im, :ano, :val, :qtd) RETURNING id_iptu'
    );
    $insIptu->execute(['im' => $imovel1, 'ano' => 2026, 'val' => 1200.00, 'qtd' => 10]);
    $iptu1 = (int) $insIptu->fetchColumn();

    $insParcela = $pdo->prepare(
        'INSERT INTO IptuParcela (id_iptu, numeroParcela, valor, dataVencimento, status) VALUES (:ip, :num, :val, :dv, :st)'
    );
    $insParcela->execute(['ip' => $iptu1, 'num' => 1, 'val' => 120.00, 'dv' => '2026-01-10', 'st' => 'PAGO']);
    $insParcela->execute(['ip' => $iptu1, 'num' => 2, 'val' => 120.00, 'dv' => '2026-02-10', 'st' => 'PAGO']);
    $insParcela->execute(['ip' => $iptu1, 'num' => 3, 'val' => 120.00, 'dv' => date('Y-m-d', strtotime('first day of this month +9 days')), 'st' => 'PENDENTE']);

    $pdo->commit();

    echo "Dados de demonstração inseridos com sucesso.\n\n";
    echo "Contas de teste (senha para todas: 123456):\n";
    echo "  Locador ativo com imóveis: joao.locador@prysma.com\n";
    echo "  Locatário ativo com contratos/chamados/cobrancas: maria.locataria@prysma.com\n";
    echo "  Locatário 2 (cobrança atrasada): carlos.locatario@prysma.com\n";
    echo "  Usuário inativo (testa fluxo alternativo de login): fernanda.inativa@prysma.com\n";
    echo "  Administrador: admin@prysma.com\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Erro ao popular dados: ' . $e->getMessage() . "\n");
    exit(1);
}
