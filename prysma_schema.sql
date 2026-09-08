-- ============================================================
-- PRYSMA -- Script do Banco de Dados (PostgreSQL 16)
-- Inclui estrutura + regras de negocio diretamente no banco
-- (CHECK, UNIQUE, FOREIGN KEY e triggers)
-- ============================================================

CREATE DATABASE prysma;
-- Depois de rodar a linha acima, conecte ao banco criado antes
-- de continuar (no psql: \c prysma)

-- ============================================================
-- 1. ESTRUTURA + REGRAS DE NEGOCIO (CHECK / UNIQUE / FK)
-- ============================================================

CREATE TABLE Usuario (
    id_usuario      SERIAL          PRIMARY KEY,
    nome            VARCHAR(150)    NOT NULL,
    email           VARCHAR(255)    NOT NULL UNIQUE,
    senhaHash       TEXT            NOT NULL,
    dataCadastro    TIMESTAMP       NOT NULL DEFAULT NOW(),
    ativo           BOOLEAN         NOT NULL DEFAULT TRUE
);

CREATE TABLE Cidade (
    id_cidade       SERIAL          PRIMARY KEY,
    nome            VARCHAR(100)    NOT NULL,
    uf              CHAR(2)         NOT NULL
);

CREATE TABLE Locador (
    id_locador      SERIAL          PRIMARY KEY,
    id_usuario      INTEGER         NOT NULL UNIQUE,
    cpfCnpj         VARCHAR(18)     NOT NULL,
    tipoPessoa      CHAR(2)         NOT NULL,
    dadosBancarios  VARCHAR(255),
    FOREIGN KEY (id_usuario) REFERENCES Usuario (id_usuario),
    CONSTRAINT chk_locador_tipopessoa CHECK (tipoPessoa IN ('PF','PJ'))
);

CREATE TABLE Locatario (
    id_locatario    SERIAL          PRIMARY KEY,
    id_usuario      INTEGER         NOT NULL UNIQUE,
    cpf             CHAR(11)        NOT NULL,
    telefone        VARCHAR(20),
    FOREIGN KEY (id_usuario) REFERENCES Usuario (id_usuario)
);

CREATE TABLE ADMIN (
    id_admin            SERIAL          PRIMARY KEY,
    id_usuario          INTEGER         NOT NULL UNIQUE,
    matriculaFuncional  VARCHAR(30)     NOT NULL,
    cargo               VARCHAR(100),
    FOREIGN KEY (id_usuario) REFERENCES Usuario (id_usuario)
);

CREATE TABLE Imovel (
    id_imovel       SERIAL          PRIMARY KEY,
    id_locador      INTEGER         NOT NULL,
    id_cidade       INTEGER         NOT NULL,
    matricula       VARCHAR(50)     NOT NULL,
    tipo            VARCHAR(50)     NOT NULL,
    valorAluguel    DECIMAL(10,2)   NOT NULL,
    numero          VARCHAR(10),
    cep             CHAR(9)         NOT NULL,
    status          VARCHAR(30)     NOT NULL DEFAULT 'DISPONIVEL',
    logradouro      VARCHAR(150)    NOT NULL,
    bairro          VARCHAR(100)    NOT NULL,
    metragem        DECIMAL(8,2),
    FOREIGN KEY (id_locador) REFERENCES Locador (id_locador),
    FOREIGN KEY (id_cidade) REFERENCES Cidade (id_cidade),
    CONSTRAINT chk_imovel_status   CHECK (status IN ('DISPONIVEL','ALUGADO','EM_MANUTENCAO','INATIVO')),
    CONSTRAINT chk_imovel_aluguel  CHECK (valorAluguel > 0),
    CONSTRAINT chk_imovel_metragem CHECK (metragem IS NULL OR metragem > 0)
);

CREATE TABLE Prestador (
    id_prestador    SERIAL          PRIMARY KEY,
    nome            VARCHAR(150)    NOT NULL,
    documento       VARCHAR(18)     NOT NULL,
    especialidade   VARCHAR(100),
    telefone        VARCHAR(20),
    email           VARCHAR(255),
    ativo           BOOLEAN         NOT NULL DEFAULT TRUE
);

CREATE TABLE Contrato (
    id_contrato                 SERIAL          PRIMARY KEY,
    id_locatario                INTEGER         NOT NULL,
    id_imovel                   INTEGER         NOT NULL,
    dataInicio                  DATE            NOT NULL,
    dataFim                     DATE            NOT NULL,
    valorAluguel                DECIMAL(10,2)   NOT NULL,
    valorCaucao                 DECIMAL(10,2),
    taxaAdministracao           DECIMAL(5,2)    NOT NULL,   -- taxa da imobiliária vigente neste contrato; ex.: 8.50 = 8,5%
    status                      VARCHAR(30)     NOT NULL DEFAULT 'ATIVO',
    FOREIGN KEY (id_locatario) REFERENCES Locatario (id_locatario),
    FOREIGN KEY (id_imovel) REFERENCES Imovel (id_imovel),
    CONSTRAINT chk_contrato_status  CHECK (status IN ('ATIVO','ENCERRADO','RESCINDIDO')),
    CONSTRAINT chk_contrato_datas   CHECK (dataFim > dataInicio),
    CONSTRAINT chk_contrato_aluguel CHECK (valorAluguel > 0),
    CONSTRAINT chk_contrato_taxa    CHECK (taxaAdministracao >= 0 AND taxaAdministracao <= 100)
);

CREATE TABLE ChamadoManutencao (
    id_chamado      SERIAL          PRIMARY KEY,
    id_locatario    INTEGER         NOT NULL,
    id_imovel       INTEGER         NOT NULL,
    id_prestador    INTEGER,
    titulo          VARCHAR(200)    NOT NULL,
    descricao       TEXT,
    prioridade      VARCHAR(20)     NOT NULL DEFAULT 'NORMAL',
    dataAbertura    TIMESTAMP       NOT NULL DEFAULT NOW(),
    dataFechamento  TIMESTAMP,
    status          VARCHAR(30)     NOT NULL DEFAULT 'ABERTO',
    dataAtualizacao TIMESTAMP,
    funcionarioAtendente VARCHAR(150),
    FOREIGN KEY (id_locatario) REFERENCES Locatario (id_locatario),
    FOREIGN KEY (id_imovel) REFERENCES Imovel (id_imovel),
    FOREIGN KEY (id_prestador) REFERENCES Prestador (id_prestador),
    CONSTRAINT chk_chamado_status     CHECK (status IN ('ABERTO','EM_ANDAMENTO','AGUARDANDO_LOCATARIO','CONCLUIDO','CANCELADO')),
    CONSTRAINT chk_chamado_prioridade CHECK (prioridade IN ('BAIXA','NORMAL','ALTA'))
);

CREATE TABLE Cobranca (
    id_cobranca     SERIAL          PRIMARY KEY,
    id_contrato     INTEGER         NOT NULL,
    valor           DECIMAL(10,2)   NOT NULL,
    dataVencimento  DATE            NOT NULL,
    status          VARCHAR(30)     NOT NULL DEFAULT 'PENDENTE',
    dataPagamento   DATE,
    FOREIGN KEY (id_contrato) REFERENCES Contrato (id_contrato),
    CONSTRAINT chk_cobranca_status    CHECK (status IN ('PENDENTE','PAGO','ATRASADO','CANCELADO')),
    CONSTRAINT chk_cobranca_valor     CHECK (valor > 0),
    -- Regra: se PAGO, precisa ter data de pagamento
    CONSTRAINT chk_cobranca_pagamento CHECK (status <> 'PAGO' OR dataPagamento IS NOT NULL)
);

CREATE TABLE Boleto (
    id_boleto       SERIAL          PRIMARY KEY,
    id_cobranca     INTEGER         NOT NULL UNIQUE,
    linhaDigitavel  VARCHAR(60),
    codigoBarras    VARCHAR(50),
    valorMulta      DECIMAL(10,2)   NOT NULL DEFAULT 0,
    valorJuros      DECIMAL(10,2)   NOT NULL DEFAULT 0,
    idExterno       VARCHAR(50),        -- id do pagamento no Sistema Bancario (Mercado Pago sandbox)
    urlPdf          VARCHAR(500),       -- link para download do PDF do boleto, retornado pelo Sistema Bancario
    dataGeracao     TIMESTAMP,          -- quando o boleto foi solicitado ao Sistema Bancario
    FOREIGN KEY (id_cobranca) REFERENCES Cobranca (id_cobranca),
    CONSTRAINT chk_boleto_multa CHECK (valorMulta >= 0),
    CONSTRAINT chk_boleto_juros CHECK (valorJuros >= 0)
);

-- Os dados bancários de destino do repasse são sempre os cadastrados em
-- Locador.dadosBancarios (cadastrados uma única vez, com direito a edição
-- pelo próprio locador); por isso não são duplicados aqui.
-- A taxa de administração também não é gravada aqui: ela é lida do
-- Contrato vigente (taxaAdministracao) no momento do cálculo,
-- via trigger, pois pode variar de contrato para contrato.
CREATE TABLE Repasse (
    id_repasse              SERIAL          PRIMARY KEY,
    id_cobranca             INTEGER         NOT NULL UNIQUE,
    valorBruto              DECIMAL(10,2)   NOT NULL,
    valorLiquido            DECIMAL(10,2),
    dataRepasse             DATE,
    FOREIGN KEY (id_cobranca) REFERENCES Cobranca (id_cobranca),
    CONSTRAINT chk_repasse_bruto CHECK (valorBruto > 0)
);

CREATE TABLE Documento (
    id_documento        SERIAL          PRIMARY KEY,
    id_contrato         INTEGER,
    id_chamado          INTEGER,
    tipo                VARCHAR(50)     NOT NULL,
    caminhoArquivo      VARCHAR(500)    NOT NULL,
    dataDigitalizacao   TIMESTAMP       NOT NULL DEFAULT NOW(),
    FOREIGN KEY (id_contrato) REFERENCES Contrato (id_contrato),
    FOREIGN KEY (id_chamado) REFERENCES ChamadoManutencao (id_chamado),
    -- Regra: documento pertence a um contrato OU a um chamado (exclusivo)
    CONSTRAINT chk_documento_vinculo
        CHECK ( (id_contrato IS NOT NULL AND id_chamado IS NULL)
             OR (id_contrato IS NULL AND id_chamado IS NOT NULL) )
);

CREATE TABLE FAQ (
    id_FAQ          SERIAL          PRIMARY KEY,
    id_admin        INTEGER,
    pergunta        TEXT            NOT NULL,
    resposta        TEXT            NOT NULL,
    categoria       VARCHAR(100),
    dataAtualizacao TIMESTAMP       NOT NULL DEFAULT NOW(),
    visualizacoes   INTEGER         NOT NULL DEFAULT 0,
    FOREIGN KEY (id_admin) REFERENCES ADMIN (id_admin),
    CONSTRAINT chk_faq_visualizacoes CHECK (visualizacoes >= 0)
);

CREATE TABLE IPTU (
    id_iptu             SERIAL          PRIMARY KEY,
    id_imovel           INTEGER         NOT NULL,
    ano                 INTEGER         NOT NULL,
    valorTotal          DECIMAL(10,2)   NOT NULL,
    quantidadeParcelas  INTEGER         NOT NULL,
    FOREIGN KEY (id_imovel) REFERENCES Imovel (id_imovel),
    CONSTRAINT chk_iptu_valor    CHECK (valorTotal > 0),
    CONSTRAINT chk_iptu_parcelas CHECK (quantidadeParcelas > 0)
);

CREATE TABLE IptuParcela (
    id_parcela      SERIAL          PRIMARY KEY,
    id_iptu         INTEGER         NOT NULL,
    numeroParcela   INTEGER         NOT NULL,
    valor           DECIMAL(10,2)   NOT NULL,
    dataVencimento  DATE            NOT NULL,
    status          VARCHAR(30)     NOT NULL DEFAULT 'PENDENTE',
    FOREIGN KEY (id_iptu) REFERENCES IPTU (id_iptu),
    CONSTRAINT chk_parcela_valor  CHECK (valor > 0),
    CONSTRAINT chk_parcela_status CHECK (status IN ('PENDENTE','PAGO','ATRASADO'))
);

-- ============================================================
-- 2. REGRAS DE NEGOCIO ATIVAS (TRIGGERS)
-- ============================================================

-- 2.1 Atualiza automaticamente dataAtualizacao do chamado a cada alteracao
CREATE OR REPLACE FUNCTION fn_chamado_atualizar_data()
RETURNS TRIGGER AS $$
BEGIN
    NEW.dataAtualizacao := NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_chamado_atualizar_data
BEFORE UPDATE ON ChamadoManutencao
FOR EACH ROW EXECUTE FUNCTION fn_chamado_atualizar_data();

-- 2.2 Ao ativar um contrato, o imovel passa para ALUGADO
--     Ao encerrar/rescindir, o imovel volta a ficar DISPONIVEL
CREATE OR REPLACE FUNCTION fn_contrato_ocupar_imovel()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status = 'ATIVO' THEN
        UPDATE Imovel SET status = 'ALUGADO' WHERE id_imovel = NEW.id_imovel;
    ELSIF NEW.status IN ('ENCERRADO','RESCINDIDO') THEN
        UPDATE Imovel SET status = 'DISPONIVEL' WHERE id_imovel = NEW.id_imovel;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_contrato_ocupar_imovel
AFTER INSERT OR UPDATE OF status ON Contrato
FOR EACH ROW EXECUTE FUNCTION fn_contrato_ocupar_imovel();

-- 2.3 Marca cobrancas vencidas e nao pagas como ATRASADO
CREATE OR REPLACE FUNCTION fn_cobranca_marcar_atraso()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status = 'PENDENTE' AND NEW.dataVencimento < CURRENT_DATE THEN
        NEW.status := 'ATRASADO';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_cobranca_marcar_atraso
BEFORE INSERT OR UPDATE ON Cobranca
FOR EACH ROW EXECUTE FUNCTION fn_cobranca_marcar_atraso();

-- 2.4 Calcula valorLiquido do repasse a partir da taxa vigente no Contrato
--     (a taxa nao e fixa: cada contrato pode ter seu proprio percentual)
CREATE OR REPLACE FUNCTION fn_repasse_calcular_liquido()
RETURNS TRIGGER AS $$
DECLARE
    v_taxa DECIMAL(5,2);
BEGIN
    SELECT ct.taxaAdministracao INTO v_taxa
    FROM Cobranca cb
    JOIN Contrato ct ON ct.id_contrato = cb.id_contrato
    WHERE cb.id_cobranca = NEW.id_cobranca;

    NEW.valorLiquido := ROUND(NEW.valorBruto - (NEW.valorBruto * v_taxa / 100), 2);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_repasse_calcular_liquido
BEFORE INSERT OR UPDATE OF valorBruto, id_cobranca ON Repasse
FOR EACH ROW EXECUTE FUNCTION fn_repasse_calcular_liquido();

-- 2.5 Chamado de manutencao com prioridade ALTA coloca o imovel em
--     EM_MANUTENCAO automaticamente (UC04, fluxo alternativo 5a)
CREATE OR REPLACE FUNCTION fn_chamado_prioridade_alta()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.prioridade = 'ALTA' THEN
        UPDATE Imovel SET status = 'EM_MANUTENCAO' WHERE id_imovel = NEW.id_imovel;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_chamado_prioridade_alta
AFTER INSERT ON ChamadoManutencao
FOR EACH ROW EXECUTE FUNCTION fn_chamado_prioridade_alta();
