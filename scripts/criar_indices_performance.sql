-- Script de criação de índices para performance
-- Execute este script no banco de dados PostgreSQL para melhorar performance de queries frequentes
-- 
-- Data: 2025-01-25
-- Versão: 1.0.0

-- ============================================================================
-- ÍNDICES PARA TABELA PEDIDOS
-- ============================================================================

-- Índice composto para queries de dashboard (status + data de atualização)
CREATE INDEX IF NOT EXISTS idx_pedidos_status_updated_at 
ON pedidos(status, updated_at DESC);

-- Índice para buscar pedidos por vendedor e status
CREATE INDEX IF NOT EXISTS idx_pedidos_vendedor_status 
ON pedidos(vendedor_id, status);

-- Índice para buscar pedidos por cliente
CREATE INDEX IF NOT EXISTS idx_pedidos_cliente_id 
ON pedidos(cliente_id);

-- Índice para pedidos urgentes
CREATE INDEX IF NOT EXISTS idx_pedidos_urgente 
ON pedidos(urgente) WHERE urgente = true;

-- Índice para busca por número de pedido
CREATE INDEX IF NOT EXISTS idx_pedidos_numero 
ON pedidos(numero);

-- ============================================================================
-- ÍNDICES PARA TABELA PEDIDO_ITENS
-- ============================================================================

-- Índice para buscar itens por pedido (muito usado em JOINs)
CREATE INDEX IF NOT EXISTS idx_pedido_itens_pedido_id 
ON pedido_itens(pedido_id);

-- Índice para buscar itens por produto
CREATE INDEX IF NOT EXISTS idx_pedido_itens_produto_id 
ON pedido_itens(produto_id);

-- ============================================================================
-- ÍNDICES PARA TABELA ARTE_VERSOES
-- ============================================================================

-- Índice para buscar versões por pedido
CREATE INDEX IF NOT EXISTS idx_arte_versoes_pedido_id 
ON arte_versoes(pedido_id);

-- Índice composto para buscar versão específica
CREATE INDEX IF NOT EXISTS idx_arte_versoes_pedido_versao 
ON arte_versoes(pedido_id, versao DESC);

-- Índice para versões aprovadas
CREATE INDEX IF NOT EXISTS idx_arte_versoes_aprovada 
ON arte_versoes(pedido_id, aprovada) WHERE aprovada = true;

-- ============================================================================
-- ÍNDICES PARA TABELA PEDIDO_ARQUIVOS
-- ============================================================================

-- Índice para buscar arquivos por pedido
CREATE INDEX IF NOT EXISTS idx_pedido_arquivos_pedido_id 
ON pedido_arquivos(pedido_id);

-- Índice para buscar por data de criação
CREATE INDEX IF NOT EXISTS idx_pedido_arquivos_created_at 
ON pedido_arquivos(created_at DESC);

-- ============================================================================
-- ÍNDICES PARA TABELA CLIENTES
-- ============================================================================

-- Índice para busca por CPF/CNPJ (muito usado)
CREATE INDEX IF NOT EXISTS idx_clientes_cpf_cnpj 
ON clientes(cpf_cnpj);

-- Índice para busca por nome (usado em filtros)
CREATE INDEX IF NOT EXISTS idx_clientes_nome 
ON clientes(nome);

-- Índice para busca por cidade e estado
CREATE INDEX IF NOT EXISTS idx_clientes_cidade_estado 
ON clientes(cidade, estado);

-- ============================================================================
-- ÍNDICES PARA TABELA LOGS_SISTEMA
-- ============================================================================

-- Índice para buscar logs por data (muito usado em relatórios)
CREATE INDEX IF NOT EXISTS idx_logs_sistema_created_at 
ON logs_sistema(created_at DESC);

-- Índice para buscar logs por usuário
CREATE INDEX IF NOT EXISTS idx_logs_sistema_usuario_id 
ON logs_sistema(usuario_id);

-- Índice composto para buscar logs por usuário e data
CREATE INDEX IF NOT EXISTS idx_logs_sistema_usuario_created 
ON logs_sistema(usuario_id, created_at DESC);

-- Índice para buscar logs por ação
CREATE INDEX IF NOT EXISTS idx_logs_sistema_acao 
ON logs_sistema(acao);

-- ============================================================================
-- ÍNDICES PARA TABELA PRODUCAO_STATUS
-- ============================================================================

-- Índice para buscar histórico por pedido
CREATE INDEX IF NOT EXISTS idx_producao_status_pedido_id 
ON producao_status(pedido_id);

-- Índice para buscar por data
CREATE INDEX IF NOT EXISTS idx_producao_status_created_at 
ON producao_status(created_at DESC);

-- ============================================================================
-- ÍNDICES PARA TABELA USUARIOS
-- ============================================================================

-- Índice para busca por email (usado em login)
CREATE INDEX IF NOT EXISTS idx_usuarios_email 
ON usuarios(email);

-- Índice para busca por perfil
CREATE INDEX IF NOT EXISTS idx_usuarios_perfil 
ON usuarios(perfil);

-- ============================================================================
-- VERIFICAÇÃO DE ÍNDICES CRIADOS
-- ============================================================================

-- Query para verificar índices criados:
-- SELECT 
--     schemaname,
--     tablename,
--     indexname,
--     indexdef
-- FROM pg_indexes
-- WHERE schemaname = 'public'
-- AND indexname LIKE 'idx_%'
-- ORDER BY tablename, indexname;
