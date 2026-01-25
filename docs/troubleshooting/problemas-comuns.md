# Problemas Comuns e Soluções

Este documento consolida problemas frequentes e suas soluções para o projeto BR Bandeiras.

## Problema Resolvido: Symlink PHP

### Status

✅ **Symlink criado**: `/Applications/AMPPS/apps/php82/bin/php` → `/opt/homebrew/bin/php`  
✅ **PHP do Homebrew**: Versão 8.5.2 com `pdo_pgsql`  
✅ **Conexão PostgreSQL**: Testada e funcionando  
✅ **Configuração**: Completa

### Ação Necessária

**REINICIE O APACHE NO PAINEL DO AMPPS!**

1. Abra o painel do AMPPS
2. Clique em **"Stop"** no Apache
3. Aguarde 3-5 segundos
4. Clique em **"Start"** no Apache

### Teste Final

Após reiniciar o Apache, acesse:

```
http://localhost/brbandeiras/public/
```

O sistema deve funcionar normalmente agora!

### Verificação

Se ainda aparecer erro, execute no Terminal:

```bash
# Verificar se symlink está correto
ls -la /Applications/AMPPS/apps/php82/bin/php

# Deve mostrar: php -> /opt/homebrew/bin/php

# Verificar extensão
/Applications/AMPPS/apps/php82/bin/php -m | grep pdo_pgsql

# Deve mostrar: pdo_pgsql

# Testar conexão
/Applications/AMPPS/apps/php82/bin/php -r "require_once '/Applications/AMPPS/www/brbandeiras/app/config.php'; echo 'OK';"
```

## Solução Rápida - SEM SUDO

### Problema
O AMPPS está usando PHP x86_64 que não tem `pdo_pgsql`. Precisamos usar o PHP do Homebrew.

### Solução: Modificar httpd.conf do Apache

Como não podemos modificar o binário PHP sem sudo, vamos modificar o Apache para usar PHP-FPM do Homebrew:

#### Passo 1: Verificar PHP-FPM do Homebrew

```bash
# Verificar se PHP-FPM está instalado
/opt/homebrew/bin/php-fpm -v

# Se não estiver, instalar:
brew install php
```

#### Passo 2: Configurar PHP-FPM do Homebrew

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

#### Passo 3: Iniciar PHP-FPM

```bash
# Iniciar PHP-FPM do Homebrew
brew services start php
# OU
/opt/homebrew/bin/php-fpm -D
```

#### Passo 4: Modificar httpd.conf do AMPPS

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

#### Passo 5: Testar

```bash
# Verificar se PHP-FPM está rodando
lsof -i :9000

# Testar
curl http://localhost/brbandeiras/public/test_pdo_pgsql.php
```

## Resumo da Solução - PDO PostgreSQL no AMPPS

### Status Atual

✅ **PHP do Homebrew**: Funcionando com `pdo_pgsql`  
✅ **Conexão PostgreSQL**: Testada e funcionando  
❌ **AMPPS**: Ainda usando PHP x86_64 sem `pdo_pgsql`

### Teste Realizado

```bash
/opt/homebrew/bin/php -r "require_once '/Applications/AMPPS/www/brbandeiras/app/config.php'; echo '✅ Conexão OK!';"
```

**Resultado**: ✅ Conexão estabelecida com sucesso!

### Solução: Configurar AMPPS para usar PHP do Homebrew

#### Opção 1: Via Terminal (Requer sudo)

Execute estes comandos no Terminal:

```bash
# 1. Backup do PHP original
sudo mv /Applications/AMPPS/apps/php82/bin/php /Applications/AMPPS/apps/php82/bin/php.original

# 2. Criar symlink
sudo ln -sf /opt/homebrew/bin/php /Applications/AMPPS/apps/php82/bin/php

# 3. Comentar extensões problemáticas
sudo sed -i.bak 's/^extension=pdo_pgsql.so/;extension=pdo_pgsql.so/' /Applications/AMPPS/apps/php82/etc/php.ini
sudo sed -i.bak 's/^extension=pgsql.so/;extension=pgsql.so/' /Applications/AMPPS/apps/php82/etc/php.ini

# 4. Testar
/Applications/AMPPS/apps/php82/bin/php -m | grep pdo_pgsql
# Deve mostrar: pdo_pgsql

# 5. Reiniciar Apache no painel do AMPPS
```

#### Opção 2: Modificar Apache diretamente

Se a Opção 1 não funcionar, você pode modificar a configuração do Apache no AMPPS para usar o PHP do Homebrew diretamente.

1. Abra o painel do AMPPS
2. Vá em **Apache** > **Config** > **httpd.conf**
3. Procure por linhas relacionadas a PHP
4. Modifique para apontar para `/opt/homebrew/bin/php`

### Verificação

Após configurar, teste:

```bash
# Verificar extensão
/Applications/AMPPS/apps/php82/bin/php -m | grep pdo_pgsql

# Testar conexão
/Applications/AMPPS/apps/php82/bin/php -r "require_once '/Applications/AMPPS/www/brbandeiras/app/config.php'; echo 'OK';"
```

Se ambos funcionarem, está tudo configurado! 🎉

## Erros Comuns

### Erro: "Constant already defined"

**Problema**: Warnings sobre constantes duplicadas (PHP 9)

**Solução**: Verifique se todas as constantes usam `if (!defined())` antes de definir.

Veja: [docs/troubleshooting/correcoes-aplicadas.md](correcoes-aplicadas.md)

### Erro: "relation does not exist"

**Problema**: Tabela não encontrada no banco de dados

**Solução**: Execute o script de criação da tabela:

```bash
php scripts/database/criar_tabela_pedido_arte.php
```

### Erro: "pdo_pgsql not found"

**Problema**: Extensão PostgreSQL não disponível

**Solução**: Configure o Apache para usar PHP do Homebrew (veja seções acima)

### Erro 500 Internal Server Error

**Possíveis causas**:
1. PHP não está processando arquivos .php
2. Erro de sintaxe no código
3. Permissões incorretas
4. Arquivo .env não encontrado

**Solução**:
1. Verifique logs do Apache: `/Applications/AMPPS/apps/apache/logs/error_log`
2. Verifique logs do PHP: `/opt/homebrew/var/log/php-fpm.log`
3. Verifique se `.env` existe e está configurado
4. Verifique permissões dos arquivos

## Próximos Passos Após Resolver Problemas

1. Execute os comandos da solução escolhida acima
2. Reinicie o Apache no painel do AMPPS
3. Acesse: `http://localhost/brbandeiras/public/`
4. O sistema deve funcionar normalmente!
