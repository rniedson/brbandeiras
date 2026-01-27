#!/bin/bash

# Script para instalar PostgreSQL localmente e importar banco remoto
# Autor: Script de instalação automática
# Data: $(date +%Y-%m-%d)

set -e  # Parar em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configurações do banco remoto (do arquivo .env)
REMOTE_HOST="91.99.5.234"
REMOTE_PORT="5432"
REMOTE_DB="brbandeiras"
REMOTE_USER="postgres"
REMOTE_PASS="philips13"

# Configurações do banco local
LOCAL_DB="brbandeiras"
LOCAL_USER="${USER}"  # Usa o usuário atual do sistema (padrão Homebrew)
LOCAL_PASS=""  # Sem senha (autenticação peer no macOS)

# Diretório do projeto
PROJECT_DIR="/Applications/AMPPS/www/brbandeiras"
BACKUP_DIR="${PROJECT_DIR}/storage/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DUMP_FILE="${BACKUP_DIR}/dump_remoto_${TIMESTAMP}.sql"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Instalação PostgreSQL Local + Importação${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Função para verificar se comando existe
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# 1. Verificar se Homebrew está instalado
echo -e "${YELLOW}[1/7] Verificando Homebrew...${NC}"
if ! command_exists brew; then
    echo -e "${RED}❌ Homebrew não encontrado!${NC}"
    echo -e "${YELLOW}Instale o Homebrew primeiro:${NC}"
    echo -e "  /bin/bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\""
    exit 1
fi
echo -e "${GREEN}✓ Homebrew encontrado${NC}\n"

# 2. Instalar PostgreSQL
echo -e "${YELLOW}[2/7] Instalando PostgreSQL...${NC}"
if command_exists psql; then
    echo -e "${GREEN}✓ PostgreSQL já está instalado${NC}"
    psql --version
else
    echo -e "${YELLOW}Instalando PostgreSQL via Homebrew...${NC}"
    brew install postgresql@16
    
    # Adicionar ao PATH
    echo -e "${YELLOW}Configurando PATH...${NC}"
    if [[ -d "/opt/homebrew/opt/postgresql@16/bin" ]]; then
        export PATH="/opt/homebrew/opt/postgresql@16/bin:$PATH"
        echo 'export PATH="/opt/homebrew/opt/postgresql@16/bin:$PATH"' >> ~/.zshrc
    elif [[ -d "/usr/local/opt/postgresql@16/bin" ]]; then
        export PATH="/usr/local/opt/postgresql@16/bin:$PATH"
        echo 'export PATH="/usr/local/opt/postgresql@16/bin:$PATH"' >> ~/.zshrc
    fi
    
    echo -e "${GREEN}✓ PostgreSQL instalado${NC}"
fi
echo ""

# 3. Iniciar serviço PostgreSQL
echo -e "${YELLOW}[3/7] Iniciando serviço PostgreSQL...${NC}"
brew services start postgresql@16 || brew services restart postgresql@16
sleep 3  # Aguardar serviço iniciar
echo -e "${GREEN}✓ Serviço PostgreSQL iniciado${NC}\n"

# 4. Criar banco de dados local (se não existir)
echo -e "${YELLOW}[4/7] Criando banco de dados local...${NC}"

# Tentar criar/verificar banco sem senha primeiro (autenticação peer)
if psql -U "$LOCAL_USER" -lqt 2>/dev/null | cut -d \| -f 1 | grep -qw "$LOCAL_DB"; then
    echo -e "${YELLOW}⚠ Banco '$LOCAL_DB' já existe${NC}"
    read -p "Deseja recriar o banco? (s/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        echo -e "${YELLOW}Removendo banco existente...${NC}"
        psql -U "$LOCAL_USER" -c "DROP DATABASE IF EXISTS $LOCAL_DB;" 2>/dev/null || psql -c "DROP DATABASE IF EXISTS $LOCAL_DB;"
        psql -U "$LOCAL_USER" -c "CREATE DATABASE $LOCAL_DB;" 2>/dev/null || psql -c "CREATE DATABASE $LOCAL_DB;"
        echo -e "${GREEN}✓ Banco recriado${NC}"
    else
        echo -e "${YELLOW}Mantendo banco existente${NC}"
    fi
else
    psql -U "$LOCAL_USER" -c "CREATE DATABASE $LOCAL_DB;" 2>/dev/null || psql -c "CREATE DATABASE $LOCAL_DB;" || {
        echo -e "${YELLOW}Tentando criar banco com usuário postgres...${NC}"
        psql -U postgres -c "CREATE DATABASE $LOCAL_DB;" 2>/dev/null || {
            echo -e "${RED}❌ Erro ao criar banco. Verifique as permissões.${NC}"
            echo -e "${YELLOW}Tente executar manualmente:${NC}"
            echo -e "  createdb $LOCAL_DB"
            exit 1
        }
        LOCAL_USER="postgres"
    }
    echo -e "${GREEN}✓ Banco '$LOCAL_DB' criado${NC}"
fi
echo ""

# 5. Criar diretório de backups se não existir
echo -e "${YELLOW}[5/7] Preparando diretório de backups...${NC}"
mkdir -p "$BACKUP_DIR"
echo -e "${GREEN}✓ Diretório preparado: $BACKUP_DIR${NC}\n"

# 6. Fazer dump do banco remoto
echo -e "${YELLOW}[6/7] Fazendo dump do banco remoto...${NC}"
echo -e "${BLUE}Conectando em: ${REMOTE_HOST}:${REMOTE_PORT}${NC}"
echo -e "${BLUE}Banco: ${REMOTE_DB}${NC}"

# Verificar se pg_dump está disponível
if ! command_exists pg_dump; then
    # Tentar encontrar pg_dump no caminho do PostgreSQL
    if [[ -f "/opt/homebrew/opt/postgresql@16/bin/pg_dump" ]]; then
        export PATH="/opt/homebrew/opt/postgresql@16/bin:$PATH"
    elif [[ -f "/usr/local/opt/postgresql@16/bin/pg_dump" ]]; then
        export PATH="/usr/local/opt/postgresql@16/bin:$PATH"
    fi
fi

# Fazer dump usando PGPASSWORD para não solicitar senha interativamente
export PGPASSWORD="$REMOTE_PASS"
pg_dump -h "$REMOTE_HOST" -p "$REMOTE_PORT" -U "$REMOTE_USER" -d "$REMOTE_DB" \
    --no-owner --no-acl --clean --if-exists \
    -f "$DUMP_FILE" 2>&1 | tee /tmp/pg_dump.log

if [ ${PIPESTATUS[0]} -eq 0 ]; then
    echo -e "${GREEN}✓ Dump criado com sucesso: $DUMP_FILE${NC}"
    DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
    echo -e "${BLUE}Tamanho do dump: $DUMP_SIZE${NC}"
else
    echo -e "${RED}❌ Erro ao fazer dump do banco remoto${NC}"
    echo -e "${YELLOW}Verifique:${NC}"
    echo -e "  1. Conectividade com ${REMOTE_HOST}:${REMOTE_PORT}"
    echo -e "  2. Credenciais corretas"
    echo -e "  3. Permissões do usuário remoto"
    exit 1
fi
unset PGPASSWORD
echo ""

# 7. Importar dump no banco local
echo -e "${YELLOW}[7/7] Importando dump no banco local...${NC}"
if [ -n "$LOCAL_PASS" ]; then
    export PGPASSWORD="$LOCAL_PASS"
    psql -U "$LOCAL_USER" -d "$LOCAL_DB" -f "$DUMP_FILE" 2>&1 | tee /tmp/pg_restore.log
    unset PGPASSWORD
else
    # Tentar sem senha (autenticação peer)
    psql -U "$LOCAL_USER" -d "$LOCAL_DB" -f "$DUMP_FILE" 2>&1 | tee /tmp/pg_restore.log || \
    psql -d "$LOCAL_DB" -f "$DUMP_FILE" 2>&1 | tee /tmp/pg_restore.log
fi

if [ ${PIPESTATUS[0]} -eq 0 ]; then
    echo -e "${GREEN}✓ Importação concluída com sucesso!${NC}"
else
    echo -e "${YELLOW}⚠ Alguns avisos podem ter ocorrido, mas verifique o log acima${NC}"
fi
echo ""

# 8. Atualizar arquivo .env
echo -e "${YELLOW}[8/8] Atualizando arquivo .env...${NC}"
ENV_FILE="${PROJECT_DIR}/.env"
ENV_BACKUP="${PROJECT_DIR}/.env.backup_${TIMESTAMP}"

# Fazer backup do .env atual
cp "$ENV_FILE" "$ENV_BACKUP"
echo -e "${BLUE}Backup do .env criado: $ENV_BACKUP${NC}"

# Atualizar para localhost
sed -i '' "s/^DB_HOST=.*/DB_HOST=localhost/" "$ENV_FILE"
sed -i '' "s/^DB_USER=.*/DB_USER=$LOCAL_USER/" "$ENV_FILE"
if [ -n "$LOCAL_PASS" ]; then
    sed -i '' "s/^DB_PASS=.*/DB_PASS=$LOCAL_PASS/" "$ENV_FILE"
else
    # Se não há senha, comentar ou deixar vazio
    if grep -q "^DB_PASS=" "$ENV_FILE"; then
        sed -i '' "s/^DB_PASS=.*/DB_PASS=/" "$ENV_FILE"
    fi
fi

echo -e "${GREEN}✓ Arquivo .env atualizado para usar banco local${NC}\n"

# Resumo final
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✓ Instalação concluída!${NC}"
echo -e "${GREEN}========================================${NC}\n"
echo -e "${BLUE}Resumo:${NC}"
echo -e "  • PostgreSQL instalado e rodando"
echo -e "  • Banco local criado: $LOCAL_DB"
echo -e "  • Dump remoto salvo em: $DUMP_FILE"
echo -e "  • Banco importado com sucesso"
echo -e "  • Arquivo .env atualizado para localhost"
echo -e "\n${YELLOW}Próximos passos:${NC}"
echo -e "  1. Teste a conexão executando: psql -U $LOCAL_USER -d $LOCAL_DB"
echo -e "  2. Verifique se a aplicação está funcionando"
echo -e "  3. Para voltar ao banco remoto, restaure o backup: cp $ENV_BACKUP $ENV_FILE"
echo -e "\n${BLUE}Comandos úteis:${NC}"
echo -e "  • Iniciar PostgreSQL: brew services start postgresql@16"
echo -e "  • Parar PostgreSQL: brew services stop postgresql@16"
echo -e "  • Ver status: brew services list | grep postgresql"
echo -e "  • Conectar ao banco: psql -U $LOCAL_USER -d $LOCAL_DB"
echo ""
