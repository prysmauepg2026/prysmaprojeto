-- ============================================================
-- PRYSMA -- Migração incremental 02
-- Aplica sobre um banco já criado com prysma_schema.sql anterior.
-- Rode com: psql -U postgres -d prysma -f migration_02.sql
-- ============================================================

-- Novas colunas em Boleto, para armazenar o retorno do Sistema Bancário
-- (Mercado Pago sandbox) ao gerar a 2ª via (UC03).
ALTER TABLE Boleto ADD COLUMN IF NOT EXISTS idExterno   VARCHAR(50);
ALTER TABLE Boleto ADD COLUMN IF NOT EXISTS urlPdf      VARCHAR(500);
ALTER TABLE Boleto ADD COLUMN IF NOT EXISTS dataGeracao TIMESTAMP;

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

DROP TRIGGER IF EXISTS trg_chamado_prioridade_alta ON ChamadoManutencao;
CREATE TRIGGER trg_chamado_prioridade_alta
AFTER INSERT ON ChamadoManutencao
FOR EACH ROW EXECUTE FUNCTION fn_chamado_prioridade_alta();

-- Garante que o usuário da aplicação continua com acesso aos objetos novos.
DO $$
DECLARE r RECORD;
BEGIN
  FOR r IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
    EXECUTE format('GRANT ALL ON public.%I TO prysma_app', r.tablename);
  END LOOP;
  FOR r IN SELECT sequencename FROM pg_sequences WHERE schemaname = 'public' LOOP
    EXECUTE format('GRANT ALL ON public.%I TO prysma_app', r.sequencename);
  END LOOP;
END $$;
