# Guia de Configuração Apache

Este guia consolida todas as informações sobre configuração do Apache para o projeto BR Bandeiras.

## Configuração Apache com PHP do Homebrew

### Problema
O Apache do AMPPS está usando o módulo PHP do AMPPS (8.2.29) que não tem `pdo_pgsql`. Precisamos fazer o Apache usar o PHP do Homebrew (8.5.2).

### Solução: Usar PHP-FPM do Homebrew

#### Passo 1: Configurar PHP-FPM do Homebrew

1. Edite o arquivo de configuração do PHP-FPM:
```bash
nano /opt/homebrew/etc/php/8.5/php-fpm.d/www.conf
```

2. Procure por `listen =` e certifique-se que está:
```
listen = 127.0.0.1:9000
```

3. Inicie o PHP-FPM:
```bash
brew services start php
# OU
/opt/homebrew/sbin/php-fpm -D
```

#### Passo 2: Modificar httpd.conf do AMPPS

1. **Abra o painel do AMPPS**
2. Vá em **Apache** > **Config** > **httpd.conf**
3. **Localize** estas linhas (por volta da linha 158-159):
   ```apache
   LoadModule php_module /Applications/AMPPS/apps/php82/lib/libphp8.so
   PHPIniDir "/Applications/AMPPS/apps/php82/etc"
   ```
4. **COMENTE** essas linhas (adicione `#` no início):
   ```apache
   #LoadModule php_module /Applications/AMPPS/apps/php82/lib/libphp8.so
   #PHPIniDir "/Applications/AMPPS/apps/php82/etc"
   ```
5. **ADICIONE** estas linhas logo após (certifique-se que os módulos proxy estão habilitados):
   ```apache
   LoadModule proxy_module modules/mod_proxy.so
   LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
   
   <FilesMatch \.php$>
       SetHandler "proxy:fcgi://127.0.0.1:9000"
   </FilesMatch>
   ```
6. **Salve** o arquivo
7. **Reinicie** o Apache no painel do AMPPS

#### Passo 3: Verificar

```bash
# Verificar se PHP-FPM está rodando
lsof -i :9000

# Testar no navegador
# Acesse: http://localhost/brbandeiras/public/
```

## Configuração via .htaccess

O arquivo `public/.htaccess` está configurado para usar PHP do Homebrew:

```apache
# Tentar usar PHP do Homebrew se disponível
<IfModule mod_actions.c>
    Action application/x-httpd-php /opt/homebrew/bin/php-cgi
</IfModule>
AddHandler application/x-httpd-php .php
```

## Comandos para Configuração Manual

Se preferir configurar manualmente via Terminal:

### 1. Backup do PHP original
```bash
sudo mv /Applications/AMPPS/apps/php82/bin/php /Applications/AMPPS/apps/php82/bin/php.original
```

### 2. Criar symlink para PHP do Homebrew
```bash
sudo ln -sf /opt/homebrew/bin/php /Applications/AMPPS/apps/php82/bin/php
```

### 3. Comentar extensões problemáticas no php.ini
```bash
sudo sed -i.bak 's/^extension=pdo_pgsql.so/;extension=pdo_pgsql.so/' /Applications/AMPPS/apps/php82/etc/php.ini
sudo sed -i.bak 's/^extension=pgsql.so/;extension=pgsql.so/' /Applications/AMPPS/apps/php82/etc/php.ini
```

### 4. Testar se funcionou
```bash
/Applications/AMPPS/apps/php82/bin/php -m | grep pdo_pgsql
# Deve mostrar: pdo_pgsql
```

### 5. Testar conexão
```bash
/Applications/AMPPS/apps/php82/bin/php -r "require_once '/Applications/AMPPS/www/brbandeiras/app/config.php'; echo '✅ Conexão OK!\n';"
```

### 6. REINICIAR APACHE NO PAINEL DO AMPPS
- Abra o painel do AMPPS
- Clique em "Stop" no Apache
- Aguarde 3 segundos
- Clique em "Start" no Apache

### 7. Testar no navegador
Acesse: http://localhost/brbandeiras/public/

## Status da Configuração

✅ **PHP-FPM do Homebrew**: Iniciado e rodando na porta 9000  
✅ **httpd.conf modificado**: 
   - Comentado módulo PHP do AMPPS
   - Habilitado módulos proxy e proxy_fcgi
   - Configurado FilesMatch para usar PHP-FPM

## Troubleshooting

### Apache não inicia
- Verifique se os módulos `proxy` e `proxy_fcgi` estão habilitados
- Verifique se PHP-FPM está rodando: `lsof -i :9000`
- Verifique logs do Apache: `/Applications/AMPPS/apps/apache/logs/error_log`

### PHP não processa arquivos .php
- Verifique se `mod_actions` está habilitado no Apache
- Verifique se o caminho do PHP-CGI está correto: `/opt/homebrew/bin/php-cgi`
- Teste manualmente: `/opt/homebrew/bin/php-cgi -v`

### Erro 500 Internal Server Error
- Verifique permissões dos arquivos PHP
- Verifique logs do PHP-FPM: `/opt/homebrew/var/log/php-fpm.log`
- Verifique se `.env` existe e está configurado corretamente
