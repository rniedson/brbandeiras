# Guia de Instalação no Servidor Remoto

Este guia mostra como instalar Apache, PostgreSQL e fazer o pull da aplicação no servidor remoto.

## Opção 1: Execução Automatizada (Recomendada)

### Passo 1: Instalar sshpass (se necessário)

No macOS:
```bash
brew install hudochenkov/sshpass/sshpass
```

No Linux:
```bash
sudo apt-get install sshpass  # Ubuntu/Debian
# ou
sudo yum install sshpass     # CentOS/RHEL
```

### Passo 2: Executar script automatizado

```bash
cd /Applications/AMPPS/www/brbandeiras
bash scripts/install/instalar_servidor_remoto.sh
```

---

## Opção 2: Execução Manual (Passo a Passo)

### Passo 1: Conectar ao servidor

```bash
ssh root@192.168.1.250
# Senha: Brbandeiras@21
```

### Passo 2: Copiar script para o servidor

No seu computador local, execute:
```bash
cd /Applications/AMPPS/www/brbandeiras
scp scripts/install/instalar_no_servidor.sh root@192.168.1.250:/tmp/
```

### Passo 3: Executar script no servidor

No servidor, execute:
```bash
chmod +x /tmp/instalar_no_servidor.sh
bash /tmp/instalar_no_servidor.sh
```

---

## Opção 3: Instalação Manual Completa

Se preferir fazer tudo manualmente, siga estes passos:

### 1. Conectar ao servidor

```bash
ssh root@192.168.1.250
# Senha: Brbandeiras@21
```

### 2. Detectar sistema operacional

```bash
cat /etc/os-release | grep -E '^ID='
```

### 3. Instalar Apache

**Ubuntu/Debian:**
```bash
apt-get update
apt-get install -y apache2
systemctl enable apache2
systemctl start apache2
```

**CentOS/RHEL/Fedora:**
```bash
yum update -y
yum install -y httpd
systemctl enable httpd
systemctl start httpd
```

### 4. Instalar PostgreSQL

**Ubuntu/Debian:**
```bash
apt-get install -y postgresql postgresql-contrib
systemctl enable postgresql
systemctl start postgresql
```

**CentOS/RHEL/Fedora:**
```bash
yum install -y postgresql-server postgresql-contrib
postgresql-setup --initdb
systemctl enable postgresql
systemctl start postgresql
```

### 5. Instalar PHP e extensões

**Ubuntu/Debian:**
```bash
apt-get install -y php php-cli php-fpm php-pgsql php-mbstring php-json php-xml php-curl php-zip libapache2-mod-php
```

**CentOS/RHEL/Fedora:**
```bash
yum install -y php php-cli php-fpm php-pgsql php-mbstring php-json php-xml php-curl php-zip
systemctl enable php-fpm
systemctl start php-fpm
```

### 6. Instalar Git e clonar aplicação

```bash
# Instalar Git (se necessário)
apt-get install -y git  # Ubuntu/Debian
# ou
yum install -y git      # CentOS/RHEL

# Criar diretório
mkdir -p /var/www/brbandeiras
cd /var/www/brbandeiras

# Clonar repositório
git clone github.com-sipom:rniedson/brbandeiras.git .

# OU se já existe, fazer pull
cd /var/www/brbandeiras
git pull origin main || git pull origin master
```

### 7. Configurar permissões

```bash
# Ubuntu/Debian
chown -R www-data:www-data /var/www/brbandeiras
chmod -R 755 /var/www/brbandeiras
chmod -R 775 /var/www/brbandeiras/storage
chmod -R 775 /var/www/brbandeiras/uploads

# CentOS/RHEL (use 'apache' em vez de 'www-data')
chown -R apache:apache /var/www/brbandeiras
chmod -R 755 /var/www/brbandeiras
chmod -R 775 /var/www/brbandeiras/storage
chmod -R 775 /var/www/brbandeiras/uploads
```

### 8. Configurar Virtual Host do Apache

**Ubuntu/Debian:**
```bash
cat > /etc/apache2/sites-available/brbandeiras.conf << 'EOF'
<VirtualHost *:80>
    ServerName brbandeiras.local
    DocumentRoot /var/www/brbandeiras/public
    
    <Directory /var/www/brbandeiras/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/brbandeiras_error.log
    CustomLog ${APACHE_LOG_DIR}/brbandeiras_access.log combined
</VirtualHost>
EOF

a2ensite brbandeiras.conf
a2enmod rewrite
systemctl restart apache2
```

**CentOS/RHEL/Fedora:**
```bash
cat > /etc/httpd/conf.d/brbandeiras.conf << 'EOF'
<VirtualHost *:80>
    ServerName brbandeiras.local
    DocumentRoot /var/www/brbandeiras/public
    
    <Directory /var/www/brbandeiras/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog /var/log/httpd/brbandeiras_error.log
    CustomLog /var/log/httpd/brbandeiras_access.log combined
</VirtualHost>
EOF

systemctl restart httpd
```

---

## Próximos Passos Após Instalação

### 1. Configurar banco de dados PostgreSQL

```bash
# Conectar como usuário postgres
sudo -u postgres psql

# No prompt do PostgreSQL, execute:
CREATE DATABASE brbandeiras;
CREATE USER seu_usuario WITH PASSWORD 'sua_senha';
GRANT ALL PRIVILEGES ON DATABASE brbandeiras TO seu_usuario;
\q
```

### 2. Configurar arquivo .env

```bash
cd /var/www/brbandeiras
cp .env.example .env
nano .env
```

Configure as variáveis:
```env
APP_ENV=production
DB_HOST=localhost
DB_PORT=5432
DB_NAME=brbandeiras
DB_USER=seu_usuario
DB_PASS=sua_senha
```

### 3. Executar scripts de criação de tabelas

```bash
cd /var/www/brbandeiras
php scripts/criar_tabelas_faltantes_otimizado.sql
# ou
php criar_tabelas_ordenado.php
```

### 4. Verificar instalação

```bash
# Verificar Apache
systemctl status apache2  # Ubuntu/Debian
systemctl status httpd    # CentOS/RHEL

# Verificar PostgreSQL
systemctl status postgresql

# Verificar PHP
php -v
php -m | grep pdo_pgsql

# Testar acesso
curl http://localhost/brbandeiras/public/
```

### 5. Acessar aplicação

Acesse no navegador:
```
http://192.168.1.250/brbandeiras/public/
```

---

## Troubleshooting

### Problema: Apache não inicia
```bash
# Verificar logs
tail -f /var/log/apache2/error.log  # Ubuntu/Debian
tail -f /var/log/httpd/error_log    # CentOS/RHEL

# Verificar configuração
apache2ctl configtest  # Ubuntu/Debian
httpd -t              # CentOS/RHEL
```

### Problema: PostgreSQL não conecta
```bash
# Verificar se está rodando
systemctl status postgresql

# Verificar conexão
sudo -u postgres psql -c "SELECT version();"
```

### Problema: Permissões negadas
```bash
# Verificar proprietário
ls -la /var/www/brbandeiras

# Corrigir permissões
chown -R www-data:www-data /var/www/brbandeiras  # Ubuntu/Debian
chown -R apache:apache /var/www/brbandeiras        # CentOS/RHEL
```

---

## Verificação Final

Execute este comando para verificar tudo:

```bash
echo "=== Apache ===" && systemctl is-active apache2 httpd 2>/dev/null
echo "=== PostgreSQL ===" && systemctl is-active postgresql
echo "=== PHP ===" && php -v | head -1
echo "=== Extensões ===" && php -m | grep -E 'pdo_pgsql|mbstring|json'
echo "=== Aplicação ===" && ls -la /var/www/brbandeiras/public/index.php
```
