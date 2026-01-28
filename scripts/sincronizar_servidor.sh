#!/bin/bash

# Script para sincronizar arquivos locais com o servidor remoto
# Preserva arquivos importantes como .env, logs, etc.

set -e

# Cores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Configurações
SERVER_IP="192.168.1.250"
SERVER_USER="root"
SERVER_PASS="Brbandeiras@21"
SERVER_DIR="/var/www/brbandeiras"
LOCAL_DIR="/Applications/AMPPS/www/brbandeiras"

echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     Sincronização com Servidor - BR Bandeiras           ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Verificar se sshpass está instalado
if ! command -v sshpass &> /dev/null; then
    echo -e "${YELLOW}⚠️  sshpass não encontrado. Instalando...${NC}"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        brew install hudochenkov/sshpass/sshpass
    fi
fi

echo -e "${YELLOW}[1/4] Fazendo backup de arquivos importantes no servidor...${NC}"
sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP" \
    "cd $SERVER_DIR && \
    cp -p .env /tmp/.env.backup_\$(date +%Y%m%d_%H%M%S) 2>/dev/null || true && \
    echo '✓ Backup do .env criado'"

echo -e "${YELLOW}[2/4] Criando pacote dos arquivos locais...${NC}"
cd "$LOCAL_DIR"
tar --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.env' \
    --exclude='storage/logs' \
    --exclude='storage/cache' \
    --exclude='uploads' \
    --exclude='.DS_Store' \
    --exclude='_notes' \
    --exclude='*.log' \
    -czf /tmp/brbandeiras_sync.tar.gz . 2>/dev/null

TAR_SIZE=$(du -h /tmp/brbandeiras_sync.tar.gz | cut -f1)
echo "✓ Pacote criado: $TAR_SIZE"

echo -e "${YELLOW}[3/4] Transferindo arquivos para o servidor...${NC}"
sshpass -p "$SERVER_PASS" scp -o StrictHostKeyChecking=no \
    /tmp/brbandeiras_sync.tar.gz \
    "$SERVER_USER@$SERVER_IP:/tmp/"

echo -e "${YELLOW}[4/4] Extraindo arquivos no servidor...${NC}"
sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP" \
    "cd $SERVER_DIR && \
    tar -xzf /tmp/brbandeiras_sync.tar.gz --overwrite --exclude='.env' && \
    cp /tmp/.env.backup_* .env 2>/dev/null || echo '⚠️  .env não restaurado (pode não existir backup)' && \
    chown -R www-data:www-data . && \
    chmod -R 755 . && \
    chmod -R 775 storage uploads 2>/dev/null || true && \
    rm -f /tmp/brbandeiras_sync.tar.gz && \
    echo '✓ Arquivos extraídos e permissões configuradas'"

# Limpar arquivo local
rm -f /tmp/brbandeiras_sync.tar.gz

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              Sincronização Concluída!                    ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo "Arquivos sincronizados com sucesso!"
echo "O arquivo .env foi preservado no servidor."
echo ""
