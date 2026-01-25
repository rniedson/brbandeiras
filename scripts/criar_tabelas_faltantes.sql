-- ============================================================================
-- SCRIPT SQL - CRIAR TABELAS FALTANTES
-- ============================================================================
-- Versão: 1.0
-- Data: 2026-01-24
-- 
-- Execute este script no PostgreSQL para criar todas as tabelas necessárias.
-- Para versão otimizada, use: criar_tabelas_faltantes_otimizado.sql
-- ============================================================================

-- ============================================
-- 1. FORNECEDORES
-- ============================================
CREATE TABLE IF NOT EXISTS fornecedores (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    cpf_cnpj VARCHAR(18) UNIQUE,
    telefone VARCHAR(20),
    celular VARCHAR(20),
    email VARCHAR(255) UNIQUE,
    whatsapp VARCHAR(20),
    cep VARCHAR(10),
    endereco VARCHAR(255),
    numero VARCHAR(20),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    contato_principal VARCHAR(255),
    site VARCHAR(255),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_fornecedores_nome ON fornecedores(nome);
CREATE INDEX idx_fornecedores_cpf_cnpj ON fornecedores(cpf_cnpj);
CREATE INDEX idx_fornecedores_email ON fornecedores(email);

-- ============================================
-- 2. COTAÇÕES
-- ============================================
CREATE TABLE IF NOT EXISTS cotacoes (
    id SERIAL PRIMARY KEY,
    numero VARCHAR(50) UNIQUE NOT NULL,
    fornecedor_id INTEGER REFERENCES fornecedores(id) ON DELETE SET NULL,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'aprovada', 'rejeitada', 'cancelada')),
    valor_total DECIMAL(10,2) DEFAULT 0,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_cotacoes_fornecedor ON cotacoes(fornecedor_id);
CREATE INDEX idx_cotacoes_usuario ON cotacoes(usuario_id);
CREATE INDEX idx_cotacoes_status ON cotacoes(status);
CREATE INDEX idx_cotacoes_numero ON cotacoes(numero);

-- ============================================
-- 3. COTAÇÃO ITENS
-- ============================================
CREATE TABLE IF NOT EXISTS cotacao_itens (
    id SERIAL PRIMARY KEY,
    cotacao_id INTEGER REFERENCES cotacoes(id) ON DELETE CASCADE,
    produto_id INTEGER REFERENCES produtos_catalogo(id) ON DELETE SET NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    valor_unitario DECIMAL(10,2) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_cotacao_itens_cotacao ON cotacao_itens(cotacao_id);
CREATE INDEX idx_cotacao_itens_produto ON cotacao_itens(produto_id);

-- ============================================
-- 4. CONTAS A RECEBER
-- ============================================
CREATE TABLE IF NOT EXISTS contas_receber (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
    pedido_id INTEGER REFERENCES pedidos(id) ON DELETE SET NULL,
    descricao VARCHAR(255) NOT NULL,
    numero_documento VARCHAR(100),
    valor DECIMAL(10,2) NOT NULL,
    valor_pago DECIMAL(10,2) DEFAULT 0,
    vencimento DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'aberto' CHECK (status IN ('aberto', 'pago', 'cancelado')),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_contas_receber_cliente ON contas_receber(cliente_id);
CREATE INDEX idx_contas_receber_pedido ON contas_receber(pedido_id);
CREATE INDEX idx_contas_receber_status ON contas_receber(status);
CREATE INDEX idx_contas_receber_vencimento ON contas_receber(vencimento);

-- ============================================
-- 5. CONTAS A PAGAR
-- ============================================
CREATE TABLE IF NOT EXISTS contas_pagar (
    id SERIAL PRIMARY KEY,
    fornecedor_id INTEGER REFERENCES fornecedores(id) ON DELETE SET NULL,
    descricao VARCHAR(255) NOT NULL,
    numero_documento VARCHAR(100),
    valor DECIMAL(10,2) NOT NULL,
    valor_pago DECIMAL(10,2) DEFAULT 0,
    vencimento DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'aberto' CHECK (status IN ('aberto', 'pago', 'cancelado')),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_contas_pagar_fornecedor ON contas_pagar(fornecedor_id);
CREATE INDEX idx_contas_pagar_status ON contas_pagar(status);
CREATE INDEX idx_contas_pagar_vencimento ON contas_pagar(vencimento);

-- ============================================
-- 6. COMISSÕES
-- ============================================
CREATE TABLE IF NOT EXISTS comissoes (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER REFERENCES pedidos(id) ON DELETE CASCADE,
    vendedor_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    valor_pedido DECIMAL(10,2) NOT NULL,
    taxa_comissao DECIMAL(5,2) NOT NULL,
    valor_comissao DECIMAL(10,2) NOT NULL,
    status_pagamento VARCHAR(20) DEFAULT 'pendente' CHECK (status_pagamento IN ('pendente', 'pago', 'cancelado')),
    data_pagamento DATE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_comissoes_pedido ON comissoes(pedido_id);
CREATE INDEX idx_comissoes_vendedor ON comissoes(vendedor_id);
CREATE INDEX idx_comissoes_status ON comissoes(status_pagamento);

-- ============================================
-- 7. METAS DE VENDAS
-- ============================================
CREATE TABLE IF NOT EXISTS metas_vendas (
    id SERIAL PRIMARY KEY,
    vendedor_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    periodo_tipo VARCHAR(20) NOT NULL CHECK (periodo_tipo IN ('mes', 'trimestre', 'ano')),
    periodo_referencia VARCHAR(20) NOT NULL,
    valor_meta DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'ativa' CHECK (status IN ('ativa', 'concluida', 'cancelada')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índice único para evitar metas duplicadas ativas
CREATE UNIQUE INDEX idx_metas_vendas_unique ON metas_vendas(vendedor_id, periodo_tipo, periodo_referencia) 
WHERE status = 'ativa';

CREATE INDEX idx_metas_vendas_vendedor ON metas_vendas(vendedor_id);
CREATE INDEX idx_metas_vendas_periodo ON metas_vendas(periodo_tipo, periodo_referencia);
CREATE INDEX idx_metas_vendas_status ON metas_vendas(status);

-- ============================================
-- 8. EMPRESA
-- ============================================
CREATE TABLE IF NOT EXISTS empresa (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    cnpj VARCHAR(18),
    inscricao_estadual VARCHAR(50),
    telefone VARCHAR(20),
    celular VARCHAR(20),
    email VARCHAR(255),
    site VARCHAR(255),
    cep VARCHAR(10),
    endereco VARCHAR(255),
    numero VARCHAR(20),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserir registro inicial
INSERT INTO empresa (id, nome) VALUES (1, 'BR Bandeiras') 
ON CONFLICT (id) DO NOTHING;

-- ============================================
-- 9. DOCUMENTOS EMPRESA
-- ============================================
CREATE TABLE IF NOT EXISTS documentos_empresa (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    categoria VARCHAR(50) DEFAULT 'geral',
    descricao TEXT,
    arquivo_nome VARCHAR(255) NOT NULL,
    arquivo_caminho VARCHAR(500) NOT NULL,
    tamanho BIGINT NOT NULL,
    tipo VARCHAR(10) NOT NULL,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_documentos_empresa_categoria ON documentos_empresa(categoria);
CREATE INDEX idx_documentos_empresa_usuario ON documentos_empresa(usuario_id);
CREATE INDEX idx_documentos_empresa_created ON documentos_empresa(created_at);

-- ============================================
-- FIM DO SCRIPT
-- ============================================
-- Total de tabelas criadas: 9
-- Execute este script no PostgreSQL usando:
-- psql -U seu_usuario -d seu_banco -f criar_tabelas_faltantes.sql
-- ou via pgAdmin ou outra ferramenta de gerenciamento
