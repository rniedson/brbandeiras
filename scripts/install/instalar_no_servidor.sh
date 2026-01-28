#!/bin/bash

# Script para executar DIRETAMENTE no servidor remoto
# BR Bandeiras - Instalação completa de Apache, PostgreSQL e aplicação
# 
# Uso no servidor: bash instalar_no_servidor.sh

set -e  # Parar em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configurações
REPO_URL="https://github.com/rniedson/brbandeiras.git"
INSTALL_DIR="/var/www/brbandeiras"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Instalação BR Bandeiras${NC}"
echo -e "${GREEN}========================================${NC}\n"

# Detectar sistema operacional
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
else
    echo -e "${RED}✗ Não foi possível detectar o sistema operacional${NC}"
    exit 1
fi

echo -e "${YELLOW}[1/6] Sistema detectado: $OS${NC}\n"

echo -e "${YELLOW}[2/6] Instalando Apache...${NC}"
if [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y apache2
    systemctl enable apache2
    systemctl start apache2
    APACHE_USER="www-data"
elif [[ "$OS" == "centos" ]] || [[ "$OS" == "rhel" ]] || [[ "$OS" == "fedora" ]]; then
    yum update -y -q
    yum install -y httpd
    systemctl enable httpd
    systemctl start httpd
    APACHE_USER="apache"
else
    echo -e "${RED}✗ Sistema operacional não suportado: $OS${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Apache instalado e iniciado${NC}\n"

echo -e "${YELLOW}[3/6] Instalando PostgreSQL...${NC}"
if [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y postgresql postgresql-contrib
    systemctl enable postgresql
    systemctl start postgresql
elif [[ "$OS" == "centos" ]] || [[ "$OS" == "rhel" ]] || [[ "$OS" == "fedora" ]]; then
    yum install -y postgresql-server postgresql-contrib
    postgresql-setup --initdb
    systemctl enable postgresql
    systemctl start postgresql
fi
echo -e "${GREEN}✓ PostgreSQL instalado e iniciado${NC}\n"

echo -e "${YELLOW}[4/6] Instalando PHP e extensões...${NC}"
if [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y php php-cli php-fpm php-pgsql php-mbstring php-json php-xml php-curl php-zip libapache2-mod-php
elif [[ "$OS" == "centos" ]] || [[ "$OS" == "rhel" ]] || [[ "$OS" == "fedora" ]]; then
    yum install -y php php-cli php-fpm php-pgsql php-mbstring php-json php-xml php-curl php-zip
    systemctl enable php-fpm
    systemctl start php-fpm
fi
echo -e "${GREEN}✓ PHP e extensões instaladas${NC}\n"

echo -e "${YELLOW}[5/6] Configurando Git e fazendo pull da aplicação...${NC}"
command -v git >/dev/null 2>&1 || (apt-get install -y git || yum install -y git)
mkdir -p $INSTALL_DIR
cd $INSTALL_DIR
if [ -d .git ]; then
    echo "Diretório já existe, fazendo pull..."
    git pull origin main || git pull origin master
else
    echo "Clonando repositório..."
    git clone $REPO_URL .
fi
echo -e "${GREEN}✓ Aplicação clonada/atualizada${NC}\n"

echo -e "${YELLOW}[6/6] Configurando permissões e Apache...${NC}"
chown -R $APACHE_USER:$APACHE_USER $INSTALL_DIR
chmod -R 755 $INSTALL_DIR
chmod -R 775 $INSTALL_DIR/storage 2>/dev/null || true
chmod -R 775 $INSTALL_DIR/uploads 2>/dev/null || true

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

if [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
    echo "$VHOST_CONFIG" > /etc/apache2/sites-available/brbandeiras.conf
    a2ensite brbandeiras.conf
    a2enmod rewrite
    systemctl restart apache2
elif [[ "$OS" == "centos" ]] || [[ "$OS" == "rhel" ]] || [[ "$OS" == "fedora" ]]; then
    echo "$VHOST_CONFIG" > /etc/httpd/conf.d/brbandeiras.conf
    systemctl restart httpd
fi

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
echo -e "4. Acesse: http://$(hostname -I | awk '{print $1}')/brbandeiras/public/\n"
