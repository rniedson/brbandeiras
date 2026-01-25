# Guia de Configuração Banco de Dados PostgreSQL

Este guia documenta a configuração do PostgreSQL para o projeto BR Bandeiras.

## Conexão Remota PostgreSQL Configurada

### O que foi feito:

1. ✅ **Suporte a DATABASE_URL**: O código agora lê `DATABASE_URL` diretamente do arquivo `.env`
2. ✅ **Conectividade testada**: Servidor remoto `91.99.5.234:5432` está acessível
3. ✅ **Conexão funcionando**: Testada e confirmada com PostgreSQL 18.1
4. ✅ **Otimizações para remoto**: Timeout configurado, conexão não-persistente

## Configuração Atual

O arquivo `.env` está configurado com:

```env
DATABASE_URL=postgresql://postgres:philips13@91.99.5.234:5432/brbandeiras?schema=public
DB_SCHEMA=public
DB_NAME=brbandeiras
DB_HOST=91.99.5.234
DB_PORT=5432
DB_USER=postgres
DB_PASS=philips13
```

## Estratégia de Conexão

O código agora usa **duas estratégias**:

1. **Primária**: Usa `DATABASE_URL` se disponível (mais confiável para remoto)
2. **Fallback**: Usa variáveis individuais (`DB_HOST`, `DB_PORT`, etc.) se `DATABASE_URL` não estiver definido

## Opções de Conexão para Remoto

- ✅ `PDO::ATTR_TIMEOUT => 10` - Timeout de 10 segundos
- ✅ `PDO::ATTR_PERSISTENT => false` - Não usar conexão persistente (melhor para remoto)
- ✅ `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` - Tratamento de erros

## Teste de Conexão

Execute para testar:

```bash
/opt/homebrew/bin/php test_conexao_remota.php
```

Ou acesse no navegador após reiniciar o Apache:

```
http://localhost/brbandeiras/public/
```

## Status da Conexão

✅ **Conectividade**: OK  
✅ **Autenticação**: OK  
✅ **Banco de dados**: `brbandeiras` encontrado  
✅ **Versão PostgreSQL**: 18.1 (Ubuntu)  
✅ **Driver PHP**: `pdo_pgsql` disponível  

## Estrutura do Banco de Dados

### Tabela pedido_arte

A tabela `pedido_arte` foi criada para relacionar pedidos com arte-finalistas:

```sql
CREATE TABLE pedido_arte (
    id INTEGER PRIMARY KEY,
    pedido_id INTEGER NOT NULL UNIQUE REFERENCES pedidos(id) ON DELETE CASCADE,
    arte_finalista_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Índices Criados**:
- `idx_pedido_arte_pedido_id` - Para performance em JOINs
- `idx_pedido_arte_arte_finalista_id` - Para buscas por arte-finalista

## Troubleshooting

### Erro de Conexão

Se houver erro de conexão:

1. Verifique se o servidor está acessível:
   ```bash
   ping 91.99.5.234
   ```

2. Verifique se a porta está aberta:
   ```bash
   telnet 91.99.5.234 5432
   ```

3. Verifique credenciais no arquivo `.env`

4. Verifique se o driver `pdo_pgsql` está disponível:
   ```bash
   /opt/homebrew/bin/php -m | grep pdo_pgsql
   ```

### Erro "relation does not exist"

Se aparecer erro de tabela não encontrada:

1. Verifique se a tabela existe no banco:
   ```sql
   SELECT table_name FROM information_schema.tables 
   WHERE table_schema = 'public';
   ```

2. Execute o script de criação se necessário:
   ```bash
   php scripts/database/criar_tabela_pedido_arte.php
   ```

### Timeout de Conexão

Se houver timeout:

1. Aumente o timeout no código (não recomendado para produção)
2. Verifique latência da rede: `ping 91.99.5.234`
3. Considere usar conexão local se possível

## Segurança

⚠️ **IMPORTANTE**: O arquivo `.env` contém credenciais sensíveis. Nunca commite este arquivo no Git!

Certifique-se de que `.env` está no `.gitignore`:

```gitignore
.env
.env.local
.env.*.local
```

## Backup e Restore

### Backup

```bash
pg_dump -h 91.99.5.234 -U postgres -d brbandeiras > backup.sql
```

### Restore

```bash
psql -h 91.99.5.234 -U postgres -d brbandeiras < backup.sql
```
