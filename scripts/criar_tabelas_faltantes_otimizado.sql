-- ============================================================================
-- SCRIPT SQL OTIMIZADO - CRIAR TABELAS FALTANTES
-- ============================================================================
-- Versão: 2.0 - Revisada e Otimizada
-- Data: 2026-01-24
-- 
-- Este script cria todas as tabelas faltantes necessárias para o sistema,
-- seguindo os padrões do banco de dados existente e incluindo otimizações.
-- ============================================================================

-- ============================================================================
-- 1. FORNECEDORES
-- ============================================================================
-- Armazena informações de fornecedores da empresa
CREATE TABLE IF NOT EXISTS fornecedores (
    id SERIAL PRIMARY KEY,
    nome CHARACTER VARYING(200) NOT NULL,
    nome_fantasia CHARACTER VARYING(200),
    cpf_cnpj CHARACTER VARYING(20) UNIQUE,
    telefone CHARACTER VARYING(20),
    celular CHARACTER VARYING(20),
    email CHARACTER VARYING(100) UNIQUE,
    whatsapp CHARACTER VARYING(20),
    cep CHARACTER VARYING(8),
    endereco TEXT,
    numero CHARACTER VARYING(10),
    complemento CHARACTER VARYING(100),
    bairro CHARACTER VARYING(100),
    cidade CHARACTER VARYING(100),
    estado CHARACTER(2),
    contato_principal CHARACTER VARYING(255),
    site CHARACTER VARYING(255),
    observacoes TEXT,
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_fornecedores_nome ON fornecedores(nome);
CREATE INDEX IF NOT EXISTS idx_fornecedores_cpf_cnpj ON fornecedores(cpf_cnpj) WHERE cpf_cnpj IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_fornecedores_email ON fornecedores(email) WHERE email IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_fornecedores_cidade_estado ON fornecedores(cidade, estado) WHERE cidade IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_fornecedores_ativo ON fornecedores(ativo) WHERE ativo = true;

-- Comentários nas colunas
COMMENT ON TABLE fornecedores IS 'Cadastro de fornecedores da empresa';
COMMENT ON COLUMN fornecedores.cpf_cnpj IS 'CPF ou CNPJ do fornecedor (sem formatação)';
COMMENT ON COLUMN fornecedores.ativo IS 'Indica se o fornecedor está ativo no sistema';

-- ============================================================================
-- 2. COTAÇÕES
-- ============================================================================
-- Armazena cotações de preços de fornecedores
CREATE TABLE IF NOT EXISTS cotacoes (
    id SERIAL PRIMARY KEY,
    numero CHARACTER VARYING(50) UNIQUE NOT NULL,
    fornecedor_id INTEGER REFERENCES fornecedores(id) ON DELETE SET NULL,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    status CHARACTER VARYING(20) DEFAULT 'pendente' 
        CHECK (status IN ('pendente', 'aprovada', 'rejeitada', 'cancelada')),
    valor_total NUMERIC(10,2) DEFAULT 0,
    observacoes TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_cotacoes_fornecedor ON cotacoes(fornecedor_id) WHERE fornecedor_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_cotacoes_usuario ON cotacoes(usuario_id) WHERE usuario_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_cotacoes_status ON cotacoes(status);
CREATE INDEX IF NOT EXISTS idx_cotacoes_numero ON cotacoes(numero);
CREATE INDEX IF NOT EXISTS idx_cotacoes_created ON cotacoes(created_at DESC);
-- Índice composto para consultas comuns
CREATE INDEX IF NOT EXISTS idx_cotacoes_fornecedor_status ON cotacoes(fornecedor_id, status) WHERE fornecedor_id IS NOT NULL;

COMMENT ON TABLE cotacoes IS 'Cotações de preços solicitadas aos fornecedores';
COMMENT ON COLUMN cotacoes.numero IS 'Número único da cotação (ex: COT-2024-001)';

-- ============================================================================
-- 3. COTAÇÃO ITENS
-- ============================================================================
-- Itens de cada cotação
CREATE TABLE IF NOT EXISTS cotacao_itens (
    id SERIAL PRIMARY KEY,
    cotacao_id INTEGER NOT NULL REFERENCES cotacoes(id) ON DELETE CASCADE,
    produto_id INTEGER REFERENCES produtos_catalogo(id) ON DELETE SET NULL,
    descricao CHARACTER VARYING(255) NOT NULL,
    quantidade NUMERIC(10,2) NOT NULL CHECK (quantidade > 0),
    valor_unitario NUMERIC(10,2) NOT NULL CHECK (valor_unitario >= 0),
    valor_total NUMERIC(10,2) NOT NULL CHECK (valor_total >= 0),
    observacoes TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_cotacao_itens_cotacao ON cotacao_itens(cotacao_id);
CREATE INDEX IF NOT EXISTS idx_cotacao_itens_produto ON cotacao_itens(produto_id) WHERE produto_id IS NOT NULL;

-- Constraint para garantir cálculo correto
ALTER TABLE cotacao_itens 
    ADD CONSTRAINT chk_cotacao_itens_valor_total 
    CHECK (valor_total = quantidade * valor_unitario);

COMMENT ON TABLE cotacao_itens IS 'Itens de cada cotação';
COMMENT ON COLUMN cotacao_itens.valor_total IS 'Calculado automaticamente: quantidade * valor_unitario';

-- ============================================================================
-- 4. CONTAS A RECEBER
-- ============================================================================
-- Contas a receber de clientes
CREATE TABLE IF NOT EXISTS contas_receber (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
    pedido_id INTEGER REFERENCES pedidos(id) ON DELETE SET NULL,
    descricao CHARACTER VARYING(255) NOT NULL,
    numero_documento CHARACTER VARYING(100),
    valor NUMERIC(10,2) NOT NULL CHECK (valor > 0),
    valor_pago NUMERIC(10,2) DEFAULT 0 CHECK (valor_pago >= 0),
    vencimento DATE NOT NULL,
    status CHARACTER VARYING(20) DEFAULT 'aberto' 
        CHECK (status IN ('aberto', 'pago', 'cancelado')),
    observacoes TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_contas_receber_cliente ON contas_receber(cliente_id) WHERE cliente_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_contas_receber_pedido ON contas_receber(pedido_id) WHERE pedido_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_contas_receber_status ON contas_receber(status);
CREATE INDEX IF NOT EXISTS idx_contas_receber_vencimento ON contas_receber(vencimento);
-- Índice composto para consultas de vencimento por status
CREATE INDEX IF NOT EXISTS idx_contas_receber_status_vencimento ON contas_receber(status, vencimento) WHERE status = 'aberto';
-- Índice para contas vencidas
CREATE INDEX IF NOT EXISTS idx_contas_receber_vencidas ON contas_receber(vencimento) 
    WHERE status = 'aberto' AND vencimento < CURRENT_DATE;

-- Constraint para garantir que valor_pago não exceda valor
ALTER TABLE contas_receber 
    ADD CONSTRAINT chk_contas_receber_valor_pago 
    CHECK (valor_pago <= valor);

COMMENT ON TABLE contas_receber IS 'Contas a receber de clientes';
COMMENT ON COLUMN contas_receber.valor_pago IS 'Valor já pago (não pode exceder valor total)';

-- ============================================================================
-- 5. CONTAS A PAGAR
-- ============================================================================
-- Contas a pagar para fornecedores
CREATE TABLE IF NOT EXISTS contas_pagar (
    id SERIAL PRIMARY KEY,
    fornecedor_id INTEGER REFERENCES fornecedores(id) ON DELETE SET NULL,
    descricao CHARACTER VARYING(255) NOT NULL,
    numero_documento CHARACTER VARYING(100),
    valor NUMERIC(10,2) NOT NULL CHECK (valor > 0),
    valor_pago NUMERIC(10,2) DEFAULT 0 CHECK (valor_pago >= 0),
    vencimento DATE NOT NULL,
    status CHARACTER VARYING(20) DEFAULT 'aberto' 
        CHECK (status IN ('aberto', 'pago', 'cancelado')),
    observacoes TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_contas_pagar_fornecedor ON contas_pagar(fornecedor_id) WHERE fornecedor_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_contas_pagar_status ON contas_pagar(status);
CREATE INDEX IF NOT EXISTS idx_contas_pagar_vencimento ON contas_pagar(vencimento);
-- Índice composto para consultas de vencimento por status
CREATE INDEX IF NOT EXISTS idx_contas_pagar_status_vencimento ON contas_pagar(status, vencimento) WHERE status = 'aberto';
-- Índice para contas vencidas
CREATE INDEX IF NOT EXISTS idx_contas_pagar_vencidas ON contas_pagar(vencimento) 
    WHERE status = 'aberto' AND vencimento < CURRENT_DATE;

-- Constraint para garantir que valor_pago não exceda valor
ALTER TABLE contas_pagar 
    ADD CONSTRAINT chk_contas_pagar_valor_pago 
    CHECK (valor_pago <= valor);

COMMENT ON TABLE contas_pagar IS 'Contas a pagar para fornecedores';
COMMENT ON COLUMN contas_pagar.valor_pago IS 'Valor já pago (não pode exceder valor total)';

-- ============================================================================
-- 6. COMISSÕES
-- ============================================================================
-- Comissões de vendedores por pedidos
CREATE TABLE IF NOT EXISTS comissoes (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    vendedor_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    valor_pedido NUMERIC(10,2) NOT NULL CHECK (valor_pedido >= 0),
    taxa_comissao NUMERIC(5,2) NOT NULL CHECK (taxa_comissao >= 0 AND taxa_comissao <= 100),
    valor_comissao NUMERIC(10,2) NOT NULL CHECK (valor_comissao >= 0),
    status_pagamento CHARACTER VARYING(20) DEFAULT 'pendente' 
        CHECK (status_pagamento IN ('pendente', 'pago', 'cancelado')),
    data_pagamento DATE,
    observacoes TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_comissoes_pedido ON comissoes(pedido_id);
CREATE INDEX IF NOT EXISTS idx_comissoes_vendedor ON comissoes(vendedor_id) WHERE vendedor_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_comissoes_status ON comissoes(status_pagamento);
CREATE INDEX IF NOT EXISTS idx_comissoes_data_pagamento ON comissoes(data_pagamento) WHERE data_pagamento IS NOT NULL;
-- Índice composto para relatórios por vendedor e status
CREATE INDEX IF NOT EXISTS idx_comissoes_vendedor_status ON comissoes(vendedor_id, status_pagamento) WHERE vendedor_id IS NOT NULL;
-- Índice único para evitar duplicatas
CREATE UNIQUE INDEX IF NOT EXISTS idx_comissoes_pedido_unique ON comissoes(pedido_id);

-- Constraint para garantir cálculo correto da comissão
ALTER TABLE comissoes 
    ADD CONSTRAINT chk_comissoes_valor_calculado 
    CHECK (ABS(valor_comissao - (valor_pedido * taxa_comissao / 100)) < 0.01);

COMMENT ON TABLE comissoes IS 'Comissões de vendedores calculadas sobre pedidos';
COMMENT ON COLUMN comissoes.taxa_comissao IS 'Taxa de comissão em percentual (0-100)';
COMMENT ON COLUMN comissoes.valor_comissao IS 'Valor calculado: valor_pedido * taxa_comissao / 100';

-- ============================================================================
-- 7. METAS DE VENDAS
-- ============================================================================
-- Metas de vendas por vendedor e período
CREATE TABLE IF NOT EXISTS metas_vendas (
    id SERIAL PRIMARY KEY,
    vendedor_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    periodo_tipo CHARACTER VARYING(20) NOT NULL 
        CHECK (periodo_tipo IN ('mes', 'trimestre', 'ano')),
    periodo_referencia CHARACTER VARYING(20) NOT NULL,
    valor_meta NUMERIC(10,2) NOT NULL CHECK (valor_meta > 0),
    status CHARACTER VARYING(20) DEFAULT 'ativa' 
        CHECK (status IN ('ativa', 'concluida', 'cancelada')),
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índice único para evitar metas duplicadas ativas (usando partial index)
CREATE UNIQUE INDEX IF NOT EXISTS idx_metas_vendas_unique ON metas_vendas(vendedor_id, periodo_tipo, periodo_referencia) 
    WHERE status = 'ativa';

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_metas_vendas_vendedor ON metas_vendas(vendedor_id) WHERE vendedor_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_metas_vendas_periodo ON metas_vendas(periodo_tipo, periodo_referencia);
CREATE INDEX IF NOT EXISTS idx_metas_vendas_status ON metas_vendas(status);
-- Índice composto para consultas por vendedor e status
CREATE INDEX IF NOT EXISTS idx_metas_vendas_vendedor_status ON metas_vendas(vendedor_id, status) WHERE vendedor_id IS NOT NULL;

COMMENT ON TABLE metas_vendas IS 'Metas de vendas definidas para vendedores';
COMMENT ON COLUMN metas_vendas.periodo_referencia IS 'Formato: YYYY-MM para mês, YYYY-QN para trimestre, YYYY para ano';
COMMENT ON COLUMN metas_vendas.status IS 'ativa: meta em andamento, concluida: meta atingida, cancelada: meta cancelada';

-- ============================================================================
-- 8. EMPRESA
-- ============================================================================
-- Dados cadastrais da empresa
CREATE TABLE IF NOT EXISTS empresa (
    id INTEGER PRIMARY KEY DEFAULT 1,
    nome CHARACTER VARYING(200) NOT NULL,
    nome_fantasia CHARACTER VARYING(200),
    cnpj CHARACTER VARYING(20),
    inscricao_estadual CHARACTER VARYING(30),
    telefone CHARACTER VARYING(20),
    celular CHARACTER VARYING(20),
    email CHARACTER VARYING(100),
    site CHARACTER VARYING(255),
    cep CHARACTER VARYING(8),
    endereco TEXT,
    numero CHARACTER VARYING(10),
    complemento CHARACTER VARYING(100),
    bairro CHARACTER VARYING(100),
    cidade CHARACTER VARYING(100),
    estado CHARACTER(2),
    observacoes TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Inserir registro inicial se não existir
INSERT INTO empresa (id, nome) VALUES (1, 'BR Bandeiras') 
ON CONFLICT (id) DO NOTHING;

COMMENT ON TABLE empresa IS 'Dados cadastrais da empresa (apenas um registro)';
COMMENT ON COLUMN empresa.id IS 'Sempre 1 - apenas um registro de empresa';

-- ============================================================================
-- 9. DOCUMENTOS EMPRESA
-- ============================================================================
-- Documentos da empresa armazenados no sistema
CREATE TABLE IF NOT EXISTS documentos_empresa (
    id SERIAL PRIMARY KEY,
    nome CHARACTER VARYING(255) NOT NULL,
    categoria CHARACTER VARYING(50) DEFAULT 'geral',
    descricao TEXT,
    arquivo_nome CHARACTER VARYING(255) NOT NULL,
    arquivo_caminho CHARACTER VARYING(500) NOT NULL,
    tamanho BIGINT NOT NULL CHECK (tamanho > 0),
    tipo CHARACTER VARYING(10) NOT NULL,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_documentos_empresa_categoria ON documentos_empresa(categoria);
CREATE INDEX IF NOT EXISTS idx_documentos_empresa_usuario ON documentos_empresa(usuario_id) WHERE usuario_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_documentos_empresa_created ON documentos_empresa(created_at DESC);
-- Índice composto para consultas por categoria e data
CREATE INDEX IF NOT EXISTS idx_documentos_empresa_categoria_created ON documentos_empresa(categoria, created_at DESC);

COMMENT ON TABLE documentos_empresa IS 'Documentos da empresa armazenados no sistema';
COMMENT ON COLUMN documentos_empresa.tamanho IS 'Tamanho do arquivo em bytes';
COMMENT ON COLUMN documentos_empresa.tipo IS 'Extensão do arquivo (pdf, doc, jpg, etc)';

-- ============================================================================
-- OTIMIZAÇÕES ADICIONAIS
-- ============================================================================

-- Função para atualizar updated_at automaticamente
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Triggers para atualizar updated_at automaticamente
CREATE TRIGGER update_fornecedores_updated_at 
    BEFORE UPDATE ON fornecedores 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_cotacoes_updated_at 
    BEFORE UPDATE ON cotacoes 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_contas_receber_updated_at 
    BEFORE UPDATE ON contas_receber 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_contas_pagar_updated_at 
    BEFORE UPDATE ON contas_pagar 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_comissoes_updated_at 
    BEFORE UPDATE ON comissoes 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_metas_vendas_updated_at 
    BEFORE UPDATE ON metas_vendas 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_empresa_updated_at 
    BEFORE UPDATE ON empresa 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- RESUMO
-- ============================================================================
-- Total de tabelas criadas: 9
-- Total de índices criados: ~35
-- Total de triggers criados: 7
-- 
-- Tabelas:
--   1. fornecedores
--   2. cotacoes
--   3. cotacao_itens
--   4. contas_receber
--   5. contas_pagar
--   6. comissoes
--   7. metas_vendas
--   8. empresa
--   9. documentos_empresa
--
-- Otimizações implementadas:
--   - Índices parciais (WHERE) para melhor performance
--   - Índices compostos para consultas comuns
--   - Constraints CHECK para validação de dados
--   - Triggers para atualização automática de updated_at
--   - Comentários em tabelas e colunas importantes
--   - Tipos de dados alinhados com padrão do banco existente
-- ============================================================================
