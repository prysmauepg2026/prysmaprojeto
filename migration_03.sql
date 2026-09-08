-- ============================================================
-- PRYSMA -- Migração incremental 03
-- Aplica sobre um banco já criado com prysma_schema.sql + migration_02.sql
-- Rode com: psql -U postgres -d prysma -f migration_03.sql
-- ============================================================

-- Ajuste solicitado pela professora: além da empresa (Prestador)
-- responsável pelo chamado, registrar o nome do funcionário da empresa
-- que efetivamente atendeu o chamado (UC08).
ALTER TABLE ChamadoManutencao ADD COLUMN IF NOT EXISTS funcionarioAtendente VARCHAR(150);

-- Garante que o usuário da aplicação continua com acesso aos objetos.
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
