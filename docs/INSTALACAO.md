# Guia de Instalação - BR Bandeiras

Este guia fornece instruções completas para instalar e configurar o sistema BR Bandeiras.

## Pré-requisitos

- macOS (testado no macOS Sonoma 14.x)
- Homebrew instalado
- AMPPS instalado (ou Apache independente)
- Acesso ao servidor PostgreSQL

## Instalação Passo a Passo

### 1. Clonar/Obter o Projeto

```bash
cd /Applications/AMPPS/www
git clone <repository-url> brbandeiras
cd brbandeiras
```

### 2. Instalar PHP do Homebrew

```bash
brew install php
```

Verifique a instalação:

```bash
/opt/homebrew/bin/php -v
/opt/homebrew/bin/php -m | grep pdo_pgsql
```

### 3. Configurar Apache

Siga o guia completo em [Guias > Apache](guias/apache.md).

**Resumo rápido:**

1. Configure PHP-FPM do Homebrew para escutar na porta 9000
2. Modifique `httpd.conf` do AMPPS para usar PHP-FPM
3. Reinicie o Apache

### 4. Configurar Banco de Dados

Siga o guia completo em [Guias > Banco de Dados](guias/banco-dados.md).

**Resumo rápido:**

1. Crie arquivo `.env` na raiz do projeto:

```env
APP_ENV=development
DATABASE_URL=postgresql://usuario:senha@host:5432/brbandeiras?schema=public
DB_SCHEMA=public
DB_NAME=brbandeiras
DB_HOST=seu-host
DB_PORT=5432
DB_USER=seu-usuario
DB_PASS=sua-senha
```

2. Execute scripts de criação de tabelas se necessário:

```bash
php scripts/database/criar_tabela_pedido_arte.php
```

### 5. Configurar Permissões

```bash
# Dar permissões para uploads
chmod -R 755 uploads/
chmod -R 755 storage/

# Dar permissões para logs
chmod -R 755 storage/logs/
```

### 6. Testar Instalação

```bash
# Testar conexão com banco
php tests/test_conexao_remota.php

# Testar extensões PHP
php tests/test_pdo_pgsql.php

# Acessar no navegador
# http://localhost/brbandeiras/public/
```

## Instalação via Scripts Automáticos

Scripts de instalação estão disponíveis em `scripts/install/`:

```bash
# Instalar Apache
bash scripts/install/instalar_apache.sh

# Instalar PDO PostgreSQL
bash scripts/install/instalar_pdo_pgsql.sh
```

⚠️ **Nota**: Alguns scripts podem requerer permissões sudo.

## Instalação Manual (Sem Sudo)

Se não tiver acesso sudo, siga estas instruções:

### Passo 1: Configurar PHP-FPM

```bash
# Copiar configuração padrão
cp /opt/homebrew/etc/php/8.5/php-fpm.d/www.conf.default /opt/homebrew/etc/php/8.5/php-fpm.d/www.conf

# Editar para escutar na porta 9000
nano /opt/homebrew/etc/php/8.5/php-fpm.d/www.conf
# Procure por: listen = 127.0.0.1:9000
```

### Passo 2: Iniciar PHP-FPM

```bash
brew services start php
# OU
/opt/homebrew/bin/php-fpm -D
```

### Passo 3: Modificar Apache via Painel AMPPS

1. Abra o painel do AMPPS
2. Vá em **Apache** > **Config** > **httpd.conf**
3. Comente módulo PHP do AMPPS
4. Adicione configuração para PHP-FPM
5. Reinicie Apache

Veja detalhes completos em [Guias > Apache](guias/apache.md).

## Verificação Pós-Instalação

### Checklist

- [ ] PHP do Homebrew instalado e funcionando
- [ ] Extensão `pdo_pgsql` disponível
- [ ] Apache configurado para usar PHP do Homebrew
- [ ] Arquivo `.env` criado e configurado
- [ ] Conexão com banco de dados funcionando
- [ ] Tabelas do banco criadas
- [ ] Permissões de diretórios configuradas
- [ ] Sistema acessível via navegador

### Comandos de Verificação

```bash
# Verificar PHP
/opt/homebrew/bin/php -v
/opt/homebrew/bin/php -m | grep pdo_pgsql

# Verificar PHP-FPM
lsof -i :9000

# Verificar conexão
php tests/test_conexao_remota.php

# Verificar Apache
curl http://localhost/brbandeiras/public/ping.php
```

## Troubleshooting

Se encontrar problemas durante a instalação:

1. Consulte [Troubleshooting > Problemas Comuns](troubleshooting/problemas-comuns.md)
2. Verifique logs:
   - Apache: `/Applications/AMPPS/apps/apache/logs/error_log`
   - PHP-FPM: `/opt/homebrew/var/log/php-fpm.log`
   - Aplicação: `storage/logs/`

## Próximos Passos

Após instalação bem-sucedida:

1. Leia [Configuração](CONFIGURACAO.md) para configurar o sistema
2. Consulte [Arquitetura](ARQUITETURA.md) para entender a estrutura
3. Veja [Desenvolvimento > Fase 1](desenvolvimento/fase1-implementacao.md) para entender a arquitetura MVC

## Suporte

Para problemas específicos:

- **Apache**: [Guias > Apache](guias/apache.md)
- **PHP**: [Guias > PHP](guias/php.md)
- **Banco de Dados**: [Guias > Banco de Dados](guias/banco-dados.md)
- **Problemas Gerais**: [Troubleshooting](troubleshooting/problemas-comuns.md)
