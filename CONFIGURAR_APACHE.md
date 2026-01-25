# 🔧 Configurar Apache para usar PHP do Homebrew

## Problema
O Apache está usando o módulo PHP do AMPPS (8.2.29) que não tem `pdo_pgsql`. Precisamos fazer o Apache usar o PHP do Homebrew (8.5.2).

## Solução: Usar PHP-FPM do Homebrew

### Passo 1: Configurar PHP-FPM do Homebrew

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

### Passo 2: Modificar httpd.conf do AMPPS

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

### Passo 3: Verificar

```bash
# Verificar se PHP-FPM está rodando
lsof -i :9000

# Testar no navegador
# Acesse: http://localhost/brbandeiras/public/
```

## Alternativa: Script Automático

Execute este script (vai pedir sua senha):

```bash
cd /Applications/AMPPS/www/brbandeiras
bash CONFIGURAR_APACHE_AUTO.sh
```
