# Configuração do Sistema - BR Bandeiras

Este documento descreve todas as configurações do sistema BR Bandeiras.

## Arquivo .env

O arquivo `.env` na raiz do projeto contém todas as configurações do ambiente.

### Estrutura Básica

```env
# Ambiente
APP_ENV=development

# Banco de Dados PostgreSQL
DATABASE_URL=postgresql://usuario:senha@host:5432/brbandeiras?schema=public
DB_SCHEMA=public
DB_NAME=brbandeiras
DB_HOST=91.99.5.234
DB_PORT=5432
DB_USER=postgres
DB_PASS=sua-senha
```

### Variáveis de Ambiente

#### APP_ENV

Define o ambiente da aplicação:
- `development` - Modo desenvolvimento (erros exibidos)
- `production` - Modo produção (erros ocultos)

#### DATABASE_URL

URL completa de conexão com PostgreSQL. Formato:

```
postgresql://usuario:senha@host:porta/banco?schema=public
```

#### Variáveis Individuais de Banco

Se `DATABASE_URL` não estiver definido, o sistema usa variáveis individuais:

- `DB_HOST` - Host do banco de dados
- `DB_PORT` - Porta (padrão: 5432)
- `DB_NAME` - Nome do banco
- `DB_USER` - Usuário
- `DB_PASS` - Senha
- `DB_SCHEMA` - Schema (padrão: public)

## Configuração PHP

### Arquivo: app/config.php

Este arquivo carrega as configurações do `.env` e estabelece a conexão com o banco.

**Características:**

- Carrega `.env` automaticamente
- Suporta `DATABASE_URL` e variáveis individuais
- Configura tratamento de erros baseado em `APP_ENV`
- Estabelece conexão PDO com PostgreSQL
- Define constantes globais do sistema

### Constantes Definidas

O sistema define as seguintes constantes (em `app/config.php` e `app/functions.php`):

```php
UPLOAD_PATH      // Caminho para uploads (padrão: ../uploads/)
SISTEMA_EMAIL    // Email do sistema
BASE_URL         // URL base da aplicação
```

**Nota**: Todas as constantes usam `if (!defined())` para evitar redefinição (compatível com PHP 9).

## Configuração Apache

### Arquivo: public/.htaccess

```apache
# Tentar usar PHP do Homebrew se disponível
<IfModule mod_actions.c>
    Action application/x-httpd-php /opt/homebrew/bin/php-cgi
</IfModule>
AddHandler application/x-httpd-php .php
```

### Arquivo: httpd.conf (AMPPS)

Configuração para usar PHP-FPM do Homebrew:

```apache
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so

<FilesMatch \.php$>
    SetHandler "proxy:fcgi://127.0.0.1:9000"
</FilesMatch>
```

Veja detalhes em [Guias > Apache](guias/apache.md).

## Configuração PHP-FPM

### Arquivo: /opt/homebrew/etc/php/8.5/php-fpm.d/www.conf

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

## Configuração de Sessões

As sessões são iniciadas automaticamente em `app/config.php`:

```php
session_start();
```

### Configurações de Sessão

Podem ser configuradas no `php.ini` ou via `ini_set()`:

```php
ini_set('session.cookie_lifetime', 0); // Sessão expira ao fechar navegador
ini_set('session.gc_maxlifetime', 3600); // 1 hora
```

## Configuração de Uploads

### Diretório de Uploads

Padrão: `uploads/` na raiz do projeto

Estrutura:
```
uploads/
├── pedidos/        # Arquivos de pedidos
├── catalogo/       # Imagens do catálogo
└── background/     # Imagens de fundo
```

### Configurações PHP para Upload

No `php.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20
```

### Função de Upload

A função `uploadArquivo()` em `app/functions.php` gerencia uploads:

```php
$resultado = uploadArquivo(
    $_FILES['arquivo'],
    'pasta/destino',
    ['jpg', 'png', 'pdf'], // tipos permitidos
    5242880 // tamanho máximo (5MB)
);
```

## Configuração de Logs

### Diretório de Logs

Padrão: `storage/logs/`

### Logging

O sistema usa `registrarLog()` para logs:

```php
registrarLog('acao', 'detalhes');
```

Logs são escritos em arquivo ou via `error_log()`.

## Configuração de Cache

### Diretório de Cache

Padrão: `storage/cache/`

### Permissões

```bash
chmod -R 755 storage/cache/
```

## Configuração de Segurança

### Arquivo .env

⚠️ **IMPORTANTE**: Nunca commite o arquivo `.env` no Git!

Certifique-se de que está no `.gitignore`:

```gitignore
.env
.env.local
.env.*.local
```

### Proteção de Diretórios

O arquivo `uploads/.htaccess` protege arquivos sensíveis:

```apache
Options -Indexes
```

## Configuração de Desenvolvimento vs Produção

### Development

```env
APP_ENV=development
```

- Erros exibidos na tela
- Logs detalhados
- Debug habilitado

### Production

```env
APP_ENV=production
```

- Erros ocultos
- Logs apenas em arquivo
- Debug desabilitado

## Variáveis de Ambiente Adicionais

Você pode adicionar variáveis customizadas no `.env`:

```env
# Email
SMTP_HOST=smtp.exemplo.com
SMTP_PORT=587
SMTP_USER=usuario@exemplo.com
SMTP_PASS=senha

# API Keys
API_KEY=chave-secreta

# Outras configurações
TIMEZONE=America/Sao_Paulo
LOCALE=pt_BR
```

## Validação de Configuração

### Script de Validação

Execute para validar configuração:

```bash
php -r "require_once 'app/config.php'; echo 'Configuração OK!';"
```

### Checklist

- [ ] Arquivo `.env` existe e está configurado
- [ ] `DATABASE_URL` ou variáveis de banco configuradas
- [ ] Conexão com banco funcionando
- [ ] Permissões de diretórios corretas
- [ ] PHP-FPM rodando (se aplicável)
- [ ] Apache configurado corretamente

## Troubleshooting

### Erro: ".env não encontrado"

Verifique se o arquivo existe na raiz do projeto:
```bash
ls -la .env
```

### Erro: "Conexão com banco falhou"

1. Verifique credenciais no `.env`
2. Teste conexão manual:
   ```bash
   psql -h host -U usuario -d banco
   ```
3. Verifique se `pdo_pgsql` está disponível:
   ```bash
   php -m | grep pdo_pgsql
   ```

### Erro: "Constante já definida"

Todas as constantes devem usar `if (!defined())`. Veja [Troubleshooting > Correções Aplicadas](troubleshooting/correcoes-aplicadas.md).

## Próximos Passos

Após configurar:

1. Leia [Arquitetura](ARQUITETURA.md) para entender a estrutura
2. Consulte [Desenvolvimento](desenvolvimento/) para guias de desenvolvimento
3. Veja [Troubleshooting](troubleshooting/) se encontrar problemas
