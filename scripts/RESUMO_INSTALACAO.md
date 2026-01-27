# ✅ Instalação PostgreSQL Local - Concluída!

## 📋 Resumo da Instalação

### ✅ O que foi feito:

1. **PostgreSQL 18 instalado** via Homebrew
   - Versão: 18.1 (compatível com servidor remoto)
   - Localização: `/opt/homebrew/opt/postgresql@18`

2. **Serviço PostgreSQL iniciado**
   - Rodando em background via `brew services`
   - Porta padrão: 5432

3. **Banco de dados local criado**
   - Nome: `brbandeiras`
   - Usuário: `robsonniedson` (seu usuário do macOS)

4. **Dump do banco remoto realizado**
   - Arquivo: `storage/backups/dump_remoto_20260125_201137.sql`
   - Tamanho: ~1.0MB
   - Origem: 91.99.5.234:5432

5. **Banco importado com sucesso**
   - Todas as tabelas importadas
   - Dados preservados

6. **Arquivo .env atualizado**
   - Backup criado: `.env.backup_remoto_TIMESTAMP`
   - Configurado para usar `localhost`

## 🔧 Configuração Atual

### Arquivo `.env`:
```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=brbandeiras
DB_USER=robsonniedson
DB_PASS=
```

### PATH configurado:
O PostgreSQL foi adicionado ao seu `~/.zshrc`:
```bash
export PATH="/opt/homebrew/opt/postgresql@18/bin:$PATH"
```

**Nota:** Execute `source ~/.zshrc` ou abra um novo terminal para usar os comandos `psql` e `pg_dump` diretamente.

## 🚀 Comandos Úteis

### Gerenciar PostgreSQL
```bash
# Ver status
brew services list | grep postgresql

# Iniciar
brew services start postgresql@18

# Parar
brew services stop postgresql@18

# Reiniciar
brew services restart postgresql@18
```

### Conectar ao banco
```bash
psql -d brbandeiras
```

### Comandos SQL úteis
```sql
-- Listar tabelas
\dt

-- Ver estrutura de uma tabela
\d nome_tabela

-- Contar registros
SELECT COUNT(*) FROM nome_tabela;

-- Sair
\q
```

## 🔄 Voltar para o banco remoto

Se precisar voltar a usar o banco remoto:

1. Restaure o backup do `.env`:
```bash
cp .env.backup_remoto_TIMESTAMP .env
```

Ou edite manualmente:
```env
DB_HOST=91.99.5.234
DB_PORT=5432
DB_NAME=brbandeiras
DB_USER=postgres
DB_PASS=philips13
```

## 📝 Próximos Passos

1. ✅ Teste a aplicação para garantir que está conectando ao banco local
2. ✅ Verifique se todas as funcionalidades estão funcionando
3. ✅ Considere criar backups regulares do banco local

## ⚠️ Observações

- O PostgreSQL está configurado para iniciar automaticamente ao fazer login
- O banco local usa autenticação peer (sem senha) - padrão do Homebrew no macOS
- O dump remoto está salvo em `storage/backups/` para referência futura

## 🆘 Troubleshooting

### Erro: "psql: command not found"
```bash
export PATH="/opt/homebrew/opt/postgresql@18/bin:$PATH"
source ~/.zshrc
```

### Erro: "could not connect to server"
```bash
brew services start postgresql@18
```

### Verificar se PostgreSQL está rodando
```bash
brew services list | grep postgresql
```

---

**Data da instalação:** 25 de Janeiro de 2026  
**Versão PostgreSQL:** 18.1  
**Status:** ✅ Concluído com sucesso
