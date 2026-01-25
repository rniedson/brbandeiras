# 🚀 Solução Rápida - SEM SUDO

## Problema
O AMPPS está usando PHP x86_64 que não tem `pdo_pgsql`. Precisamos usar o PHP do Homebrew.

## Solução: Modificar httpd.conf do Apache

Como não podemos modificar o binário PHP sem sudo, vamos modificar o Apache para usar PHP-FPM do Homebrew:

### Passo 1: Verificar PHP-FPM do Homebrew

```bash
# Verificar se PHP-FPM está instalado
/opt/homebrew/bin/php-fpm -v

# Se não estiver, instalar:
brew install php
```

### Passo 2: Configurar PHP-FPM do Homebrew

Crie o arquivo de configuração do PHP-FPM:

```bash
# Copiar configuração padrão
cp /opt/homebrew/etc/php/8.5/php-fpm.d/www.conf.default /opt/homebrew/etc/php/8.5/php-fpm.d/www.conf

# Editar para escutar na porta 9000
nano /opt/homebrew/etc/php/8.5/php-fpm.d/www.conf
```

Procure por `listen =` e altere para:
```
listen = 127.0.0.1:9000
```

### Passo 3: Iniciar PHP-FPM

```bash
# Iniciar PHP-FPM do Homebrew
brew services start php
# OU
/opt/homebrew/bin/php-fpm -D
```

### Passo 4: Modificar httpd.conf do AMPPS

1. Abra o painel do AMPPS
2. Vá em **Apache** > **Config** > **httpd.conf**
3. Procure por estas linhas:
   ```
   LoadModule php_module /Applications/AMPPS/apps/php82/lib/libphp8.so
   PHPIniDir "/Applications/AMPPS/apps/php82/etc"
   ```
4. **Comente** essas linhas (adicione # no início)
5. **Adicione** estas linhas:
   ```apache
   LoadModule proxy_module modules/mod_proxy.so
   LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
   
   <FilesMatch \.php$>
       SetHandler "proxy:fcgi://127.0.0.1:9000"
   </FilesMatch>
   ```
6. Salve o arquivo
7. Reinicie o Apache no painel do AMPPS

### Passo 5: Testar

```bash
# Verificar se PHP-FPM está rodando
lsof -i :9000

# Testar
curl http://localhost/brbandeiras/public/test_pdo_pgsql.php
```

## Alternativa Mais Simples: Usar wrapper via .htaccess

Crie um arquivo `.htaccess` na pasta `public/`:

```apache
# Forçar uso do PHP do Homebrew via CGI
Action application/x-httpd-php /opt/homebrew/bin/php-cgi
AddHandler application/x-httpd-php .php
```

Mas isso pode não funcionar se o Apache não tiver mod_actions habilitado.

## Solução Definitiva: Executar comandos sudo

A solução mais simples ainda é executar os comandos com sudo:

```bash
sudo mv /Applications/AMPPS/apps/php82/bin/php /Applications/AMPPS/apps/php82/bin/php.original
sudo ln -sf /opt/homebrew/bin/php /Applications/AMPPS/apps/php82/bin/php
sudo sed -i.bak 's/^extension=pdo_pgsql.so/;extension=pdo_pgsql.so/' /Applications/AMPPS/apps/php82/etc/php.ini
```

Depois reinicie o Apache.
