# Guia de Instalação PostgreSQL Local

Este guia explica como instalar PostgreSQL localmente e importar o banco de dados remoto.

## 📋 Pré-requisitos

- macOS (já está no seu sistema)
- Homebrew instalado (se não tiver, será solicitado durante a instalação)
- Acesso ao banco de dados remoto (91.99.5.234)

## 🚀 Instalação Automática

Execute o script de instalação:

```bash
cd /Applications/AMPPS/www/brbandeiras
./scripts/instalar_postgres_local.sh
```

O script irá:
1. ✅ Verificar/instalar Homebrew
2. ✅ Instalar PostgreSQL 16 via Homebrew
3. ✅ Iniciar o serviço PostgreSQL
4. ✅ Criar o banco de dados local `brbandeiras`
5. ✅ Fazer dump do banco remoto
6. ✅ Importar o dump no banco local
7. ✅ Atualizar o arquivo `.env` para usar localhost

## 📝 Instalação Manual (se preferir)

### 1. Instalar PostgreSQL

```bash
brew install postgresql@16
```

### 2. Iniciar o serviço

```bash
brew services start postgresql@16
```

### 3. Criar banco de dados

```bash
createdb brbandeiras
```

### 4. Fazer dump do banco remoto

```bash
cd /Applications/AMPPS/www/brbandeiras
mkdir -p storage/backups

export PGPASSWORD="philips13"
pg_dump -h 91.99.5.234 -p 5432 -U postgres -d brbandeiras \
    --no-owner --no-acl --clean --if-exists \
    -f storage/backups/dump_remoto.sql
unset PGPASSWORD
```

### 5. Importar no banco local

```bash
psql -d brbandeiras -f storage/backups/dump_remoto.sql
```

### 6. Atualizar .env

Edite o arquivo `.env` e altere:

```env
DB_HOST=localhost
DB_USER=seu_usuario  # Geralmente seu nome de usuário do macOS
DB_PASS=             # Deixe vazio se usar autenticação peer
```

## 🔧 Comandos Úteis

### Gerenciar serviço PostgreSQL

```bash
# Iniciar
brew services start postgresql@16

# Parar
brew services stop postgresql@16

# Reiniciar
brew services restart postgresql@16

# Ver status
brew services list | grep postgresql
```

### Conectar ao banco

```bash
# Conectar ao banco local
psql -d brbandeiras

# Ou com usuário específico
psql -U seu_usuario -d brbandeiras
```

### Comandos SQL úteis

```sql
-- Listar bancos de dados
\l

-- Conectar a um banco
\c brbandeiras

-- Listar tabelas
\dt

-- Ver estrutura de uma tabela
\d nome_tabela

-- Sair
\q
```

## 🔄 Voltar para o banco remoto

Se precisar voltar a usar o banco remoto:

1. O script cria um backup do `.env` antes de modificar
2. Restaure o backup:

```bash
cp .env.backup_TIMESTAMP .env
```

Ou edite manualmente o `.env`:

```env
DB_HOST=91.99.5.234
DB_PORT=5432
DB_NAME=brbandeiras
DB_USER=postgres
DB_PASS=philips13
```

## ⚠️ Troubleshooting

### Erro: "psql: command not found"

Adicione PostgreSQL ao PATH:

```bash
# Para Homebrew Intel
echo 'export PATH="/usr/local/opt/postgresql@16/bin:$PATH"' >> ~/.zshrc

# Para Homebrew Apple Silicon
echo 'export PATH="/opt/homebrew/opt/postgresql@16/bin:$PATH"' >> ~/.zshrc

source ~/.zshrc
```

### Erro: "could not connect to server"

Verifique se o PostgreSQL está rodando:

```bash
brew services list | grep postgresql
```

Se não estiver rodando:

```bash
brew services start postgresql@16
```

### Erro de permissão ao criar banco

No macOS com Homebrew, geralmente você usa seu próprio usuário. Tente:

```bash
createdb brbandeiras
```

Se não funcionar, crie com usuário postgres:

```bash
createuser -s postgres  # Criar superusuário postgres
createdb -U postgres brbandeiras
```

### Erro ao fazer dump remoto

Verifique:
1. Conectividade com o servidor remoto: `ping 91.99.5.234`
2. Porta 5432 está acessível: `nc -zv 91.99.5.234 5432`
3. Credenciais corretas no script

## 📚 Documentação Adicional

- [Documentação PostgreSQL](https://www.postgresql.org/docs/)
- [Homebrew PostgreSQL](https://formulae.brew.sh/formula/postgresql@16)
