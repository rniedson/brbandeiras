-- ============================================================================
-- SCRIPT SQL - CRIAR TABELAS DE ARTE-FINALISTA
-- ============================================================================
-- Versão: 1.0
-- Data: 2026-02-02
-- 
-- Este script cria as tabelas necessárias para o fluxo de arte-finalista:
-- - pedido_arte: Controla atribuição de pedidos para arte-finalistas
-- - arte_versoes: Armazena versões de artes criadas para cada pedido
-- ============================================================================
-- ============================================
-- 1. PEDIDO_ARTE (Atribuição de pedidos)
-- ============================================
-- Controla qual arte-finalista está trabalhando em qual pedido
CREATE TABLE IF NOT EXISTS pedido_arte (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL UNIQUE REFERENCES pedidos(id) ON DELETE CASCADE,
    arte_finalista_id INTEGER REFERENCES usuarios(id) ON DELETE
    SET NULL,
        created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_pedido_arte_pedido ON pedido_arte(pedido_id);
CREATE INDEX IF NOT EXISTS idx_pedido_arte_arte_finalista ON pedido_arte(arte_finalista_id)
WHERE arte_finalista_id IS NOT NULL;
COMMENT ON TABLE pedido_arte IS 'Controle de atribuição de pedidos para arte-finalistas';
COMMENT ON COLUMN pedido_arte.pedido_id IS 'ID do pedido (único - um pedido só pode ter um responsável)';
COMMENT ON COLUMN pedido_arte.arte_finalista_id IS 'ID do arte-finalista responsável pelo pedido';
-- ============================================
-- 2. ARTE_VERSOES (Versões de artes)
-- ============================================
-- Armazena todas as versões de arte criadas para cada pedido
CREATE TABLE IF NOT EXISTS arte_versoes (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    arte_finalista_id INTEGER REFERENCES usuarios(id) ON DELETE
    SET NULL,
        versao INTEGER NOT NULL DEFAULT 1,
        arquivo_caminho VARCHAR(500) NOT NULL,
        arquivo_nome VARCHAR(255) NOT NULL,
        arquivo_tamanho BIGINT,
        aprovada BOOLEAN DEFAULT FALSE,
        reprovada BOOLEAN DEFAULT FALSE,
        comentario_arte TEXT,
        comentario_cliente TEXT,
        data_aprovacao TIMESTAMP WITHOUT TIME ZONE,
        created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_arte_versoes_pedido ON arte_versoes(pedido_id);
CREATE INDEX IF NOT EXISTS idx_arte_versoes_arte_finalista ON arte_versoes(arte_finalista_id)
WHERE arte_finalista_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_arte_versoes_versao ON arte_versoes(pedido_id, versao DESC);
CREATE INDEX IF NOT EXISTS idx_arte_versoes_aprovada ON arte_versoes(aprovada)
WHERE aprovada = TRUE;
CREATE INDEX IF NOT EXISTS idx_arte_versoes_created ON arte_versoes(created_at DESC);
-- Constraint para evitar versões duplicadas no mesmo pedido
CREATE UNIQUE INDEX IF NOT EXISTS idx_arte_versoes_pedido_versao ON arte_versoes(pedido_id, versao);
COMMENT ON TABLE arte_versoes IS 'Versões de artes criadas para cada pedido';
COMMENT ON COLUMN arte_versoes.versao IS 'Número da versão (incrementa a cada nova arte)';
COMMENT ON COLUMN arte_versoes.arquivo_caminho IS 'Caminho relativo do arquivo de arte';
COMMENT ON COLUMN arte_versoes.aprovada IS 'Se a arte foi aprovada pelo cliente';
COMMENT ON COLUMN arte_versoes.reprovada IS 'Se a arte foi reprovada/necessita ajustes';
COMMENT ON COLUMN arte_versoes.comentario_arte IS 'Comentário do arte-finalista';
COMMENT ON COLUMN arte_versoes.comentario_cliente IS 'Feedback do cliente sobre a arte';
-- ============================================
-- TRIGGERS PARA UPDATED_AT
-- ============================================
-- Usar função existente se já criada, senão criar
CREATE OR REPLACE FUNCTION update_updated_at_column() RETURNS TRIGGER AS $$ BEGIN NEW.updated_at = CURRENT_TIMESTAMP;
RETURN NEW;
END;
$$ LANGUAGE plpgsql;
-- Trigger para pedido_arte
DROP TRIGGER IF EXISTS update_pedido_arte_updated_at ON pedido_arte;
CREATE TRIGGER update_pedido_arte_updated_at BEFORE
UPDATE ON pedido_arte FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
-- Trigger para arte_versoes
DROP TRIGGER IF EXISTS update_arte_versoes_updated_at ON arte_versoes;
CREATE TRIGGER update_arte_versoes_updated_at BEFORE
UPDATE ON arte_versoes FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
-- ============================================
-- FIM DO SCRIPT
-- ============================================
-- Tabelas criadas:
--   1. pedido_arte - Atribuição de pedidos
--   2. arte_versoes - Histórico de versões de arte
--
-- Execute este script no PostgreSQL usando:
-- psql -U seu_usuario -d seu_banco -f criar_tabelas_arte.sql
-- ============================================