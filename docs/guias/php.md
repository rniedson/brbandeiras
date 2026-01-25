# Guia de Configuração PHP

Este guia consolida informações sobre configuração do PHP para o projeto BR Bandeiras.

## Problema

O AMPPS usa PHP x86_64 que não tem a extensão `pdo_pgsql` compilada. O PHP do Homebrew (arm64) já tem essa extensão.

## Solução: Usar PHP do Homebrew no AMPPS

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

## Verificação de Extensões

Para verificar todas as extensões disponíveis:

```bash
/opt/homebrew/bin/php -m
```

Extensões essenciais que devem estar disponíveis:
- `pdo`
- `pdo_pgsql`
- `pgsql`
- `mbstring`
- `json`
- `session`
- `openssl`

## Configuração PHP-FPM

Se usar PHP-FPM, configure o arquivo `/opt/homebrew/etc/php/8.5/php-fpm.d/www.conf`:

```ini
listen = 127.0.0.1:9000
user = _www
group = _www
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
```

## Iniciar PHP-FPM

```bash
# Via Homebrew services
brew services start php

# Ou manualmente
/opt/homebrew/sbin/php-fpm -D
```

## Verificar Status

```bash
# Verificar se PHP-FPM está rodando
lsof -i :9000

# Verificar versão do PHP
/opt/homebrew/bin/php -v

# Verificar extensões carregadas
/opt/homebrew/bin/php -m
```

## Troubleshooting

### Extensão não encontrada
- Verifique se a extensão está instalada: `brew list php`
- Verifique se está habilitada no `php.ini`: `/opt/homebrew/etc/php/8.5/php.ini`
- Reinicie PHP-FPM após alterações: `brew services restart php`

### PHP-FPM não inicia
- Verifique logs: `/opt/homebrew/var/log/php-fpm.log`
- Verifique se a porta 9000 está livre: `lsof -i :9000`
- Verifique permissões do arquivo de configuração

### Conflito entre PHP do AMPPS e Homebrew
- Use apenas uma versão por vez
- Se usar Homebrew, desabilite PHP do AMPPS no `httpd.conf`
- Verifique qual PHP está sendo usado: `which php`
