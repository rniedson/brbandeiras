<?php
/**
 * Script para criar a tabela pedido_arte no PostgreSQL
 * Execute este arquivo uma vez para criar a tabela
 */

require_once __DIR__ . '/app/config.php';

try {
    echo "<h1>Criando tabela pedido_arte</h1>\n";
    
    // Verificar se a tabela já existe
    $stmt = $pdo->query("
        SELECT EXISTS (
            SELECT 1 FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = 'pedido_arte'
        ) as existe
    ");
    $existe = $stmt->fetch()['existe'];
    
    if ($existe) {
        echo "<p style='color: orange;'>⚠️ Tabela pedido_arte já existe.</p>\n";
        echo "<p>Verificando estrutura...</p>\n";
        
        // Verificar colunas
        $stmt = $pdo->query("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = 'pedido_arte'
            ORDER BY ordinal_position
        ");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        print_r($colunas);
        echo "</pre>";
        
    } else {
        echo "<p>Criando tabela pedido_arte...</p>\n";
        
        // Criar sequência
        $pdo->exec("
            CREATE SEQUENCE IF NOT EXISTS pedido_arte_id_seq
            AS integer
            START WITH 1
            INCREMENT BY 1
            NO MINVALUE
            NO MAXVALUE
            CACHE 1
        ");
        
        // Criar tabela
        $pdo->exec("
            CREATE TABLE pedido_arte (
                id INTEGER NOT NULL DEFAULT nextval('pedido_arte_id_seq'::regclass),
                pedido_id INTEGER NOT NULL,
                arte_finalista_id INTEGER,
                created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pedido_arte_pkey PRIMARY KEY (id),
                CONSTRAINT pedido_arte_pedido_id_fkey FOREIGN KEY (pedido_id) 
                    REFERENCES pedidos(id) ON DELETE CASCADE,
                CONSTRAINT pedido_arte_arte_finalista_id_fkey FOREIGN KEY (arte_finalista_id) 
                    REFERENCES usuarios(id) ON DELETE SET NULL,
                CONSTRAINT pedido_arte_pedido_id_key UNIQUE (pedido_id)
            )
        ");
        
        // Fazer a sequência pertencer à coluna id
        $pdo->exec("
            ALTER SEQUENCE pedido_arte_id_seq OWNED BY pedido_arte.id
        ");
        
        // Criar índice para melhor performance
        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_pedido_arte_pedido_id ON pedido_arte(pedido_id)
        ");
        
        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_pedido_arte_arte_finalista_id ON pedido_arte(arte_finalista_id)
        ");
        
        echo "<p style='color: green;'>✅ Tabela pedido_arte criada com sucesso!</p>\n";
        
        // Verificar estrutura criada
        $stmt = $pdo->query("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = 'pedido_arte'
            ORDER BY ordinal_position
        ");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Estrutura da tabela:</h2>\n";
        echo "<pre>";
        print_r($colunas);
        echo "</pre>";
    }
    
    echo "<hr>\n";
    echo "<p><a href='javascript:history.back()'>← Voltar</a></p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}
