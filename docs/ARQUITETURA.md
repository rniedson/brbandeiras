# Arquitetura do Sistema - BR Bandeiras

Este documento descreve a arquitetura, estrutura e padrões do sistema BR Bandeiras.

## Visão Geral

O BR Bandeiras é um sistema de gestão completo para produção de bandeiras, organizado em módulos funcionais e seguindo padrões MVC (Model-View-Controller).

## Estrutura de Diretórios

```
brbandeiras/
├── app/                    # Backend/Core
│   ├── Core/              # Classes core (Database, BaseModel, etc.)
│   ├── Models/            # Modelos de dados
│   ├── config.php        # Configuração principal
│   ├── auth.php          # Autenticação
│   └── functions.php     # Funções auxiliares
│
├── public/                # Frontend/Public
│   ├── pedidos/          # Módulo Pedidos
│   ├── clientes/          # Módulo Clientes
│   ├── produtos/         # Módulo Produtos
│   ├── orcamentos/       # Módulo Orçamentos
│   ├── producao/         # Módulo Produção
│   ├── arte/             # Módulo Arte
│   ├── estoque/          # Módulo Estoque
│   ├── usuarios/         # Módulo Usuários
│   ├── dashboard/        # Dashboards
│   ├── relatorios/       # Relatórios
│   ├── calendario/       # Calendário
│   └── utils/            # Utilitários
│
├── views/                 # Templates/Views
│   ├── layouts/          # Layouts principais
│   ├── components/       # Componentes reutilizáveis
│   └── partials/         # Partials específicos
│
├── docs/                  # Documentação
├── scripts/               # Scripts utilitários
├── tests/                 # Testes
└── storage/               # Arquivos gerados
```

## Arquitetura MVC

O sistema está em processo de migração para arquitetura MVC completa.

### Fase Atual: Híbrida

- **Models**: Implementados (Fase 1)
- **Views**: Organizadas em templates
- **Controllers**: Em migração (arquivos PHP em `public/`)

### Estrutura MVC Implementada

#### Models (app/Models/)

```php
// BaseModel - CRUD genérico com proteção SQL injection
abstract class BaseModel {
    protected $db;
    protected $table;
    // Whitelist de tabelas permitidas
    // Validação de nomes de tabelas/colunas
    // Métodos: find(), findAll(), create(), update(), delete()
}

// Model específico com cache
class Pedido extends BaseModel {
    // Métodos específicos de domínio
    public function gerarNumero();
    public function comCliente($id); // Com cache
    public function atualizarStatus($id, $status);
    public function clearCache($id); // Limpar cache após updates
}
```

#### Views (views/)

- **Layouts**: Estrutura base (`_header.php`, `_footer.php`)
- **Components**: Componentes reutilizáveis (`_widget_proximas_entregas.php`)
- **Partials**: Partials específicos (`_arte_timeline.php`)

#### Controllers (public/)

Atualmente, arquivos PHP em `public/` funcionam como controllers:

```php
require_once '../../app/config.php';
require_once '../../app/auth.php';

// Lógica do controller
// Renderização da view
include '../../views/layouts/_header.php';
// Conteúdo
include '../../views/layouts/_footer.php';
```

Veja detalhes em [Desenvolvimento > Fase 1](desenvolvimento/fase1-implementacao.md).

## Padrões Arquiteturais

### Singleton Pattern

Usado para conexão de banco de dados:

```php
$db = Database::getInstance();
```

### Repository Pattern (Futuro)

Models funcionam como repositories:

```php
$pedidoModel = new Pedido(Database::getInstance());
$pedido = $pedidoModel->find(1);
```

### Adapter Pattern

`LegacyAdapter` permite compatibilidade com código legado:

```php
$pdo = LegacyAdapter::getPdo(); // Compatível com código antigo
```

## Fluxo de Requisição

### Requisição Típica

1. **Cliente** → Requisição HTTP para `public/pedidos/pedido_detalhes.php`
2. **Apache** → Processa via PHP-FPM
3. **PHP** → Carrega `app/config.php` (conexão, sessão)
4. **Auth** → Verifica autenticação (`app/auth.php`)
5. **Controller** → Lógica de negócio (arquivo PHP)
6. **Model** → Acesso a dados (`app/Models/Pedido.php`)
7. **Database** → Query no PostgreSQL
8. **View** → Renderização (`views/layouts/_header.php` + conteúdo + `_footer.php`)
9. **Resposta** → HTML enviado ao cliente

### Exemplo de Fluxo

```php
// 1. Configuração e autenticação
require_once '../../app/config.php';
require_once '../../app/auth.php';
requireLogin();

// 2. Lógica (Controller)
$pedidoModel = new Pedido(Database::getInstance());
$pedido = $pedidoModel->findCompleto($id);

// 3. Renderização (View)
include '../../views/layouts/_header.php';
// Conteúdo específico
include '../../views/layouts/_footer.php';
```

## Banco de Dados

### PostgreSQL

- **Versão**: 18.1
- **Conexão**: Remota via `DATABASE_URL`
- **Driver**: PDO PostgreSQL (`pdo_pgsql`)

### Estrutura de Tabelas Principais

- `pedidos` - Pedidos do sistema
- `pedido_itens` - Itens de pedidos
- `pedido_arte` - Relação pedidos/arte-finalistas
- `clientes` - Clientes
- `produtos_catalogo` - Produtos do catálogo
- `usuarios` - Usuários do sistema

### Padrões de Nomenclatura

- Tabelas: `snake_case` (ex: `pedido_arte`)
- Colunas: `snake_case` (ex: `created_at`)
- Chaves primárias: `id`
- Chaves estrangeiras: `{tabela}_id` (ex: `pedido_id`)

## Autenticação e Autorização

### Autenticação

Gerenciada por `app/auth.php`:

```php
requireLogin();           // Verifica se está logado
requireRole(['gestor']); // Verifica perfil específico
```

### Perfis de Usuário

- `gestor` - Acesso total
- `vendedor` - Vendas e pedidos
- `producao` - Produção e estoque
- `arte_finalista` - Arte e design

### Sessões

- Iniciadas automaticamente em `app/config.php`
- Dados armazenados em `$_SESSION`
- Chaves principais: `user_id`, `user_perfil`, `user_nome`

## Segurança

### Proteções Implementadas

1. **Prepared Statements**: Todas as queries usam PDO prepared statements
2. **Validação de Entrada**: Funções de validação em `app/functions.php`
3. **Autenticação**: Sistema de login obrigatório
4. **Autorização**: Verificação de perfis por rota
5. **HTTPS**: Recomendado para produção
6. **Sanitização**: `htmlspecialchars()` em saídas HTML
7. **CSRF Protection**: Tokens CSRF em todos os formulários POST (`app/Core/CSRF.php`)
8. **Rate Limiting**: Limitação de tentativas de login (`app/Core/RateLimiter.php`)
9. **SQL Injection Prevention**: Whitelist de tabelas no BaseModel
10. **Sessões Seguras**: Timeout de 2 horas com renovação automática

### Classes de Segurança

#### CSRF (`app/Core/CSRF.php`)
Proteção contra Cross-Site Request Forgery:
```php
// Gerar token em formulário
<?= CSRF::getField() ?>

// Validar em processador
CSRF::validate($_POST['csrf_token'] ?? '');
```

#### RateLimiter (`app/Core/RateLimiter.php`)
Limitação de tentativas:
```php
// Verificar limite
if (!RateLimiter::check('login', null, 5, 900)) {
    // Bloqueado
}

// Registrar tentativa
RateLimiter::recordAttempt('login');
```

## Performance

### Otimizações Atuais

- Singleton para conexão de banco (evita múltiplas conexões)
- Queries otimizadas com JOINs
- Índices no banco de dados
- Cache de sessão

### Otimizações Implementadas

1. **Cache APCu**: Cache de queries frequentes (`app/Core/Cache.php`)
2. **Singleton Database**: Evita múltiplas conexões
3. **Queries Otimizadas**: JOINs eficientes
4. **Índices no Banco**: Índices otimizados nas tabelas

### Cache

Sistema de cache usando APCu (`app/Core/Cache.php`):
```php
// Obter do cache
$value = Cache::get('chave', $default);

// Armazenar no cache
Cache::set('chave', $value, 300); // TTL de 5 minutos

// Cache-aside pattern
$value = Cache::remember('chave', function() {
    return expensiveOperation();
}, 300);
```

### Áreas de Melhoria (Futuro)

- Compressão de assets
- CDN para assets estáticos
- Otimização de imagens
- Views materializadas no banco

## Logging

### Sistema de Logs

Função `registrarLog()` em `app/functions.php`:

```php
registrarLog('acao', 'detalhes');
```

### Locais de Log

- `storage/logs/` - Logs da aplicação
- `/opt/homebrew/var/log/php-fpm.log` - Logs PHP-FPM
- `/Applications/AMPPS/apps/apache/logs/error_log` - Logs Apache

## Uploads

### Estrutura

```
uploads/
├── pedidos/        # Arquivos de pedidos
├── catalogo/       # Imagens do catálogo
└── background/     # Imagens de fundo
```

### Função de Upload

`uploadArquivo()` em `app/functions.php`:

- Validação de tipo
- Validação de tamanho
- Validação MIME
- Nome único gerado
- Permissões configuradas

## Compatibilidade

### PHP

- **Versão Mínima**: PHP 8.0
- **Versão Atual**: PHP 8.5.2 (Homebrew)
- **Compatibilidade**: PHP 9 (preparado)

### Banco de Dados

- **PostgreSQL**: 18.1+
- **Suporte**: Conexão remota e local

### Navegadores

- Chrome/Edge (últimas versões)
- Firefox (últimas versões)
- Safari (últimas versões)

## Migração e Refatoração

### Estratégia: Strangler Pattern

Migração gradual mantendo código legado funcionando:

1. ✅ **Fase 1**: Estrutura base MVC (completa)
2. 🔄 **Fase 2**: Migração de módulos (em andamento)
3. ⏳ **Fase 3**: Services e Controllers
4. ⏳ **Fase 4**: Roteamento
5. ⏳ **Fase 5**: API REST

Veja [Desenvolvimento > Refatoração](desenvolvimento/refatoracao.md) para detalhes.

## Padrões de Código

### Nomenclatura

- **Classes**: `PascalCase` (ex: `BaseModel`)
- **Métodos**: `camelCase` (ex: `gerarNumero()`)
- **Variáveis**: `snake_case` (ex: `$pedido_id`)
- **Constantes**: `UPPER_SNAKE_CASE` (ex: `UPLOAD_PATH`)

### Estrutura de Arquivos

- Um arquivo por classe
- Namespaces (futuro)
- Autoloading (futuro)

## Testes

### Estrutura

```
tests/
├── test_fase1.php           # Testes da Fase 1
├── test_conexao_remota.php  # Testes de conexão
└── test_pdo_pgsql.php      # Testes de driver
```

### Executar Testes

```bash
php tests/test_fase1.php
```

## Documentação

Toda documentação está em `docs/`:

- [README](README.md) - Índice
- [Instalação](INSTALACAO.md) - Guia de instalação
- [Configuração](CONFIGURACAO.md) - Configurações
- [Guias](guias/) - Guias específicos
- [Desenvolvimento](desenvolvimento/) - Docs de desenvolvimento
- [Troubleshooting](troubleshooting/) - Solução de problemas

## Próximos Passos

1. Completar migração para MVC completo
2. Implementar Services Layer
3. Adicionar roteamento
4. Criar API REST
5. Implementar testes automatizados

Veja [Desenvolvimento > Fase 1](desenvolvimento/fase1-implementacao.md) para detalhes da implementação atual.
