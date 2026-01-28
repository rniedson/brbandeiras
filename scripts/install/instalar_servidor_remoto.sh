#!/bin/bash

# Script de Instalação no Servidor Remoto
# BR Bandeiras - Instalação completa de Apache, PostgreSQL e aplicação
# 
# Uso: bash scripts/install/instalar_servidor_remoto.sh

set -e  # Parar em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configurações
SERVER_IP="192.168.1.250"
SERVER_USER="root"
SERVER_PASS="Brbandeiras@21"
REPO_URL="https://github.com/rniedson/brbandeiras.git"
INSTALL_DIR="/var/www/brbandeiras"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Instalação BR Bandeiras - Servidor Remoto${NC}"
echo -e "${GREEN}========================================${NC}\n"

# Verificar se sshpass está instalado (necessário para senha automática)
if ! command -v sshpass &> /dev/null; then
    echo -e "${YELLOW}sshpass não encontrado. Instalando...${NC}"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        brew install hudochenkov/sshpass/sshpass
    elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
        sudo apt-get update && sudo apt-get install -y sshpass
    fi
fi

# Função para executar comandos no servidor remoto
run_remote() {
    sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP" "$@"
}

# Função para copiar arquivos para o servidor
copy_to_remote() {
    sshpass -p "$SERVER_PASS" scp -o StrictHostKeyChecking=no "$1" "$SERVER_USER@$SERVER_IP:$2"
}

echo -e "${YELLOW}[1/6] Verificando sistema operacional...${NC}"
OS_INFO=$(run_remote "cat /etc/os-release | grep -E '^ID=' | cut -d'=' -f2 | tr -d '\"'")
echo -e "${GREEN}✓ Sistema detectado: $OS_INFO${NC}\n"

echo -e "${YELLOW}[2/6] Instalando Apache...${NC}"
if [[ "$OS_INFO" == "ubuntu" ]] || [[ "$OS_INFO" == "debian" ]]; then
    run_remote "apt-get update -qq"
    run_remote "DEBIAN_FRONTEND=noninteractive apt-get install -y apache2"
    run_remote "systemctl enable apache2"
    run_remote "systemctl start apache2"
    APACHE_USER="www-data"
elif [[ "$OS_INFO" == "centos" ]] || [[ "$OS_INFO" == "rhel" ]] || [[ "$OS_INFO" == "fedora" ]]; then
    run_remote "yum update -y -q"
    run_remote "yum install -y httpd"
    run_remote "systemctl enable httpd"
    run_remote "systemctl start httpd"
    APACHE_USER="apache"
else
    echo -e "${RED}✗ Sistema operacional não suportado: $OS_INFO${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Apache instalado e iniciado${NC}\n"

echo -e "${YELLOW}[3/6] Instalando PostgreSQL...${NC}"
if [[ "$OS_INFO" == "ubuntu" ]] || [[ "$OS_INFO" == "debian" ]]; then
    run_remote "DEBIAN_FRONTEND=noninteractive apt-get install -y postgresql postgresql-contrib"
    run_remote "systemctl enable postgresql"
    run_remote "systemctl start postgresql"
elif [[ "$OS_INFO" == "centos" ]] || [[ "$OS_INFO" == "rhel" ]] || [[ "$OS_INFO" == "fedora" ]]; then
    run_remote "yum install -y postgresql-server postgresql-contrib"
    run_remote "postgresql-setup --initdb"
    run_remote "systemctl enable postgresql"
    run_remote "systemctl start postgresql"
fi
echo -e "${GREEN}✓ PostgreSQL instalado e iniciado${NC}\n"

echo -e "${YELLOW}[4/6] Instalando PHP e extensões...${NC}"
if [[ "$OS_INFO" == "ubuntu" ]] || [[ "$OS_INFO" == "debian" ]]; then
    run_remote "DEBIAN_FRONTEND=noninteractive apt-get install -y php php-cli php-fpm php-pgsql php-mbstring php-json php-xml php-curl php-zip libapache2-mod-php"
elif [[ "$OS_INFO" == "centos" ]] || [[ "$OS_INFO" == "rhel" ]] || [[ "$OS_INFO" == "fedora" ]]; then
    run_remote "yum install -y php php-cli php-fpm php-pgsql php-mbstring php-json php-xml php-curl php-zip"
    run_remote "systemctl enable php-fpm"
    run_remote "systemctl start php-fpm"
fi
echo -e "${GREEN}✓ PHP e extensões instaladas${NC}\n"

echo -e "${YELLOW}[5/6] Transferindo aplicação para o servidor...${NC}"
run_remote "mkdir -p $INSTALL_DIR"

# Transferir arquivos usando tar + ssh
echo -e "${YELLOW}Empacotando e transferindo arquivos...${NC}"
LOCAL_DIR="/Applications/AMPPS/www/brbandeiras"

# Criar arquivo tar temporário excluindo arquivos desnecessários
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
    -czf /tmp/brbandeiras.tar.gz .

# Transferir arquivo tar
sshpass -p "$SERVER_PASS" scp -o StrictHostKeyChecking=no /tmp/brbandeiras.tar.gz "$SERVER_USER@$SERVER_IP:/tmp/"

# Extrair no servidor
run_remote "cd $INSTALL_DIR && tar -xzf /tmp/brbandeiras.tar.gz && rm /tmp/brbandeiras.tar.gz"

# Limpar arquivo local
rm -f /tmp/brbandeiras.tar.gz

# Inicializar Git no servidor se necessário
run_remote "cd $INSTALL_DIR && if [ ! -d .git ]; then git init && git remote add origin $REPO_URL 2>/dev/null || true; fi"
echo -e "${GREEN}✓ Aplicação transferida${NC}\n"

echo -e "${YELLOW}[6/6] Configurando permissões e Apache...${NC}"
run_remote "chown -R $APACHE_USER:$APACHE_USER $INSTALL_DIR"
run_remote "chmod -R 755 $INSTALL_DIR"
run_remote "chmod -R 775 $INSTALL_DIR/storage 2>/dev/null || true"
run_remote "chmod -R 775 $INSTALL_DIR/uploads 2>/dev/null || true"

# Criar virtual host do Apache
VHOST_CONFIG="<VirtualHost *:80>
    ServerName brbandeiras.local
    DocumentRoot $INSTALL_DIR/public
    
    <Directory $INSTALL_DIR/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/brbandeiras_error.log
    CustomLog \${APACHE_LOG_DIR}/brbandeiras_access.log combined
</VirtualHost>"

run_remote "echo '$VHOST_CONFIG' > /etc/apache2/sites-available/brbandeiras.conf 2>/dev/null || echo '$VHOST_CONFIG' > /etc/httpd/conf.d/brbandeiras.conf"
run_remote "a2ensite brbandeiras.conf 2>/dev/null || true"
run_remote "a2enmod rewrite 2>/dev/null || true"
run_remote "systemctl restart apache2 2>/dev/null || systemctl restart httpd"

echo -e "${GREEN}✓ Permissões e Apache configurados${NC}\n"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Instalação concluída com sucesso!${NC}"
echo -e "${GREEN}========================================${NC}\n"

echo -e "${YELLOW}Próximos passos:${NC}"
echo -e "1. Configure o arquivo .env em $INSTALL_DIR"
echo -e "2. Crie o banco de dados PostgreSQL:"
echo -e "   sudo -u postgres psql -c \"CREATE DATABASE brbandeiras;\""
echo -e "   sudo -u postgres psql -c \"CREATE USER seu_usuario WITH PASSWORD 'sua_senha';\""
echo -e "   sudo -u postgres psql -c \"GRANT ALL PRIVILEGES ON DATABASE brbandeiras TO seu_usuario;\""
echo -e "3. Execute os scripts de criação de tabelas"
echo -e "4. Acesse: http://$SERVER_IP/brbandeiras/public/\n"
