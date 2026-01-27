# Melhorias de Manutenibilidade e Escalabilidade - Documentação

## Visão Geral

Este documento descreve as melhorias implementadas no sistema para aumentar manutenibilidade e escalabilidade, incluindo exemplos práticos de uso.

## Índice

1. [Autoloader PSR-4](#autoloader-psr-4)
2. [Sistema de Configuração](#sistema-de-configuração)
3. [Repositories](#repositories)
4. [Services](#services)
5. [ModelFactory](#modelfactory)
6. [QueryBuilder](#querybuilder)
7. [Paginator](#paginator)
8. [Validator](#validator)
9. [Logger](#logger)
10. [EventDispatcher](#eventdispatcher)
11. [Lazy Loading](#lazy-loading)
12. [Health Check](#health-check)

---

## Autoloader PSR-4

O autoloader permite carregar classes automaticamente sem múltiplos `require_once`.

### Uso

```php
// Não precisa mais fazer:
// require_once __DIR__ . '/app/Core/Database.php';
// require_once __DIR__ . '/app/Repositories/PedidoRepository.php';

// Basta usar:
use App\Core\Database;
use App\Repositories\PedidoRepository;

// O autoloader carrega automaticamente
```

**Localização:** `app/autoload.php` (já incluído em `app/config.php`)

---

## Sistema de Configuração

Acesso centralizado e type-safe às configurações do sistema.

### Exemplos de Uso

```php
use App\Core\Config;

// Obter valor simples
$dbHost = Config::get('DB_HOST', 'localhost');

// Obter como inteiro
$dbPort = Config::getInt('DB_PORT', 5432);

// Obter como booleano
$isDev = Config::getBool('APP_ENV', false);

// Obter como string
$dbName = Config::getString('DB_NAME', '');

// Obter como array (separado por vírgula)
$allowedOrigins = Config::getArray('CORS_ALLOWED_ORIGINS', []);

// Verificar se existe
if (Config::has('CUSTOM_SETTING')) {
    // ...
}
```

**Localização:** `app/Core/Config.php`

---

## Repositories

Centralizam queries SQL relacionadas a entidades específicas.

### Exemplo: PedidoRepository

```php
use App\Repositories\PedidoRepository;
use App\Core\Database;

$db = Database::getInstance();
$repository = new PedidoRepository($db);

// Buscar pedido completo com relacionamentos
$pedido = $repository->buscarCompletoParaGestor(123);

// Buscar itens do pedido
$itens = $repository->buscarItens(123);

// Buscar versões de arte
$versoes = $repository->buscarVersoesArte(123);

// Buscar para dashboard com filtros
$pedidos = $repository->buscarParaDashboard([
    'status' => 'novo',
    'urgente' => true
], 'p.updated_at DESC', 25, 0);
```

### Exemplo: ClienteRepository

```php
use App\Repositories\ClienteRepository;

$repository = new ClienteRepository($db);

// Buscar por ID
$cliente = $repository->buscarPorId(5);

// Buscar por CPF/CNPJ
$cliente = $repository->buscarPorCpfCnpj('123.456.789-00');

// Buscar todos com filtros
$clientes = $repository->buscarTodos([
    'nome' => 'João',
    'cidade' => 'Goiânia'
], 'nome ASC', 25, 0);

// Buscar com estatísticas
$cliente = $repository->buscarComEstatisticas(5);
```

**Localização:** `app/Repositories/`

---

## Services

Camada de lógica de negócio que utiliza repositories.

### Exemplo: PedidoService

```php
use App\Services\PedidoService;
use App\Core\ModelFactory;

// Usando ModelFactory (recomendado)
$service = ModelFactory::pedidoService();

// Ou criando manualmente
$service = new PedidoService(
    new PedidoRepository(Database::getInstance()),
    Database::getInstance()
);

// Buscar pedido completo
$pedido = $service->buscarCompleto(123);

// Atualizar status com validações
try {
    $service->atualizarStatus(123, 'aprovado', 'Pedido aprovado pelo gestor', $usuarioId);
} catch (InvalidArgumentException $e) {
    // Status inválido
} catch (RuntimeException $e) {
    // Erro de negócio (ex: tentar cancelar pedido entregue)
}

// Buscar para dashboard com paginação
$resultado = $service->buscarParaDashboard([
    'status' => 'novo'
], 1, 25);

// $resultado contém:
// - dados: array de pedidos
// - total: total de registros
// - pagina: página atual
// - por_pagina: itens por página
// - total_paginas: total de páginas
```

### Exemplo: ClienteService

```php
use App\Services\ClienteService;

$service = ModelFactory::clienteService();

// Criar cliente com validações
try {
    $clienteId = $service->criar([
        'nome' => 'João Silva',
        'cpf_cnpj' => '123.456.789-00',
        'email' => 'joao@example.com',
        'telefone' => '(62) 99999-9999'
    ]);
} catch (InvalidArgumentException $e) {
    // Dados inválidos
} catch (RuntimeException $e) {
    // Cliente já existe (mesmo CPF/CNPJ)
}

// Buscar com paginação
$resultado = $service->buscarTodos(['nome' => 'João'], 1, 25);
```

**Localização:** `app/Services/`

---

## ModelFactory

Simplifica criação de models, repositories e services.

### Exemplos

```php
use App\Core\ModelFactory;

// Criar model Pedido
$pedidoModel = ModelFactory::pedido();

// Criar repository
$pedidoRepo = ModelFactory::pedidoRepository();
$clienteRepo = ModelFactory::clienteRepository();

// Criar service (recomendado)
$pedidoService = ModelFactory::pedidoService();
$clienteService = ModelFactory::clienteService();
```

**Localização:** `app/Core/ModelFactory.php`

---

## QueryBuilder

Construtor de queries SQL de forma segura e legível.

### Exemplos

```php
use App\Core\QueryBuilder;
use App\Core\Database;

$db = Database::getInstance();
$qb = new QueryBuilder($db);

// Query simples
$pedidos = $qb->select(['id', 'numero', 'status'])
    ->from('pedidos', 'p')
    ->where('status', '=', 'novo')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Query com JOINs
$pedidos = $qb->select(['p.id', 'p.numero', 'c.nome as cliente_nome'])
    ->from('pedidos', 'p')
    ->leftJoin('clientes c', 'c.id = p.cliente_id')
    ->where('p.status', '=', 'novo')
    ->where('p.urgente', '=', true)
    ->orderBy('p.created_at', 'DESC')
    ->get();

// Query com WHERE IN
$pedidos = $qb->select('*')
    ->from('pedidos')
    ->whereIn('status', ['novo', 'aprovado', 'em_producao'])
    ->get();

// Buscar primeiro resultado
$pedido = $qb->select('*')
    ->from('pedidos')
    ->where('id', '=', 123)
    ->first();
```

**Localização:** `app/Core/QueryBuilder.php`

---

## Paginator

Sistema de paginação padronizado.

### Exemplos

```php
use App\Core\Paginator;

// Paginar array de dados
$dados = [/* array grande */];
$resultado = Paginator::paginate($dados, 2, 25);

// Criar estrutura de paginação para queries SQL
$total = 150; // Do COUNT
$paginacao = Paginator::create($total, 1, 25);

// Usar offset e limit na query
$sql = "SELECT * FROM pedidos LIMIT ? OFFSET ?";
$stmt = $db->query($sql, [$paginacao['limit'], $paginacao['offset']]);

// Parsear parâmetros da requisição
$params = Paginator::parseParams($_GET);
// Retorna: ['page' => 1, 'per_page' => 25]

// Renderizar HTML de navegação
echo Paginator::render($resultado, '?page=');
```

**Localização:** `app/Core/Paginator.php`

---

## Validator

Sistema de validação centralizado.

### Exemplos

```php
use App\Core\Validator;
use App\Core\ValidationException;

// Validar ID de pedido
$pedidoId = Validator::pedidoId($_GET['id'] ?? null);
if (!$pedidoId) {
    // ID inválido
}

// Validar email
if (!Validator::email($email)) {
    // Email inválido
}

// Validar campo obrigatório
try {
    Validator::required($nome, 'nome');
    Validator::required($email, 'email');
} catch (ValidationException $e) {
    // Campo obrigatório faltando
    echo $e->getMessage();
}

// Validar tamanho
Validator::minLength($senha, 8, 'senha');
Validator::maxLength($nome, 100, 'nome');

// Validar CPF/CNPJ
if (Validator::cpfCnpj($cpfCnpj)) {
    // Válido
}

// Validar telefone
if (Validator::telefone($telefone)) {
    // Válido
}

// Validar valor em lista
Validator::in($status, ['novo', 'aprovado', 'cancelado'], 'status');
```

**Localização:** `app/Core/Validator.php`

---

## Logger

Sistema de logging estruturado com níveis.

### Exemplos

```php
use App\Core\Logger;

// Logs por nível
Logger::debug('Mensagem de debug', ['variavel' => $valor]);
Logger::info('Operação realizada com sucesso', ['pedido_id' => 123]);
Logger::warning('Atenção: estoque baixo', ['produto_id' => 5]);
Logger::error('Erro ao processar pedido', ['pedido_id' => 123, 'erro' => $e->getMessage()]);
Logger::critical('Erro crítico no sistema', ['componente' => 'pagamento']);

// Log de exceção
try {
    // código que pode lançar exceção
} catch (\Exception $e) {
    Logger::exception($e, [
        'contexto' => 'processamento de pedido',
        'usuario_id' => $_SESSION['user_id']
    ]);
}
```

**Localização:** `app/Core/Logger.php`  
**Arquivos de log:** `storage/logs/YYYY-MM-DD.log`

---

## EventDispatcher

Sistema de eventos/listeners para desacoplar código.

### Exemplos

```php
use App\Core\EventDispatcher;
use App\Core\Logger;

// Registrar listener
EventDispatcher::listen('pedido.criado', function($pedido) {
    Logger::info('Novo pedido criado', ['pedido_id' => $pedido['id']]);
    
    // Enviar email de confirmação
    // Enviar notificação
    // etc.
}, 10); // Prioridade 10

EventDispatcher::listen('pedido.status_alterado', function($data) {
    Logger::info('Status alterado', [
        'pedido_id' => $data['pedido_id'],
        'status_anterior' => $data['status_anterior'],
        'status_novo' => $data['status_novo']
    ]);
});

// Disparar evento
EventDispatcher::dispatch('pedido.criado', [
    'id' => 123,
    'cliente_id' => 5,
    'valor' => 1500.00
]);

EventDispatcher::dispatch('pedido.status_alterado', [
    'pedido_id' => 123,
    'status_anterior' => 'novo',
    'status_novo' => 'aprovado'
]);

// Verificar se há listeners
if (EventDispatcher::hasListeners('pedido.criado')) {
    // Há listeners registrados
}
```

**Localização:** `app/Core/EventDispatcher.php`

---

## Lazy Loading

Carregamento sob demanda de relacionamentos em models.

### Exemplo: Model Pedido

```php
use App\Models\Pedido;
use App\Core\Database;

$db = Database::getInstance();
$pedidoModel = new Pedido($db);

// Buscar pedido básico
$pedido = $pedidoModel->find(123);

// Carregar itens apenas quando necessário (lazy loading)
$itens = $pedidoModel->getItens(123); // Carrega na primeira chamada
$itens = $pedidoModel->getItens(123); // Retorna do cache

// Carregar versões de arte
$versoes = $pedidoModel->getVersoesArte(123);

// Carregar arquivos
$arquivos = $pedidoModel->getArquivos(123);

// Carregar histórico
$historico = $pedidoModel->getHistorico(123);

// Limpar cache (forçar recarregamento)
$pedidoModel->clearCache();
```

**Localização:** `app/Models/Pedido.php`

---

## Health Check

Endpoint para verificar saúde do sistema.

### Uso

```bash
# Acessar via HTTP
curl http://localhost:8080/public/api/health.php

# Resposta JSON:
{
    "status": "ok",
    "timestamp": 1706198400,
    "datetime": "2025-01-25 12:00:00",
    "checks": {
        "database": {
            "status": "ok",
            "response_time_ms": 0
        },
        "cache": {
            "status": "ok",
            "apcu_available": true
        },
        "disk_space": {
            "status": "ok",
            "free_bytes": 1234567890,
            "total_bytes": 5000000000,
            "used_percent": 75.31
        }
    }
}
```

**Localização:** `public/api/health.php`

---

## Migração de Código Legado

### Antes (código legado)

```php
global $pdo;

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
$stmt->execute([$id]);
$pedido = $stmt->fetch();
```

### Depois (usando novos componentes)

```php
use App\Services\PedidoService;
use App\Core\ModelFactory;

$service = ModelFactory::pedidoService();
$pedido = $service->buscarCompleto($id);
```

---

## Próximos Passos

1. **Executar índices de performance:**
   ```bash
   psql -U usuario -d banco -f scripts/criar_indices_performance.sql
   ```

2. **Migrar controllers gradualmente:**
   - Começar pelos mais críticos
   - Manter código legado funcionando
   - Testar cada migração

3. **Adicionar listeners de eventos:**
   - Registrar logs automáticos
   - Notificações por email
   - Integrações externas

4. **Expandir testes unitários:**
   - Adicionar mais casos de teste
   - Testes de integração
   - Testes de performance

5. **Monitorar saúde do sistema:**
   - Configurar alertas no health check
   - Monitorar logs
   - Acompanhar métricas de performance

---

## Suporte

Para dúvidas ou problemas, consulte:
- Código-fonte com PHPDoc completo
- Arquivos de exemplo em `tests/Unit/`
- Documentação inline nas classes
