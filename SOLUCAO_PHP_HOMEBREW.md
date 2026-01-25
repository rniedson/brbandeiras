# Solução: Usar PHP do Homebrew no AMPPS

## Problema
O AMPPS usa PHP x86_64 que não tem a extensão `pdo_pgsql` compilada. O PHP do Homebrew (arm64) já tem essa extensão.

## Solução Rápida: Configurar AMPPS para usar PHP do Homebrew

### Opção 1: Modificar configuração do Apache no AMPPS

1. Abra o painel do AMPPS
2. Vá em **Apache** > **Config** > **httpd.conf**
3. Procure por `LoadModule php_module` ou `PHPIniDir`
4. Altere para apontar para o PHP do Homebrew:

```apache
LoadModule php_module /opt/homebrew/lib/httpd/modules/libphp.so
PHPIniDir /opt/homebrew/etc/php/8.3/php.ini
```

**OU** se o AMPPS usar PHP-FPM:

```apache
ProxyPassMatch ^/(.*\.php)$ fcgi://127.0.0.1:9000/Applications/AMPPS/www/$1
```

E configure o PHP-FPM do Homebrew para escutar na porta 9000.

### Opção 2: Criar symlink (mais simples)

```bash
# Fazer backup do PHP original do AMPPS
sudo mv /Applications/AMPPS/apps/php82/bin/php /Applications/AMPPS/apps/php82/bin/php.original

# Criar symlink para PHP do Homebrew
sudo ln -s /opt/homebrew/bin/php /Applications/AMPPS/apps/php82/bin/php
```

### Opção 3: Usar wrapper script

Crie um script wrapper que redireciona para o PHP do Homebrew:

```bash
cat > /Applications/AMPPS/apps/php82/bin/php << 'SCRIPT'
#!/bin/bash
exec /opt/homebrew/bin/php "$@"
SCRIPT
chmod +x /Applications/AMPPS/apps/php82/bin/php
```

## Teste

Após configurar, teste:

```bash
/Applications/AMPPS/apps/php82/bin/php -m | grep pdo_pgsql
```

Deve mostrar: `pdo_pgsql`

## Alternativa: Adaptar código para MySQL

Se preferir não modificar o AMPPS, podemos adaptar o código para usar MySQL que já está disponível no AMPPS.
