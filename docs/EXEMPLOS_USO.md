# Exemplos Práticos de Uso - Melhorias Implementadas

Este documento contém exemplos práticos e completos de como usar as novas funcionalidades implementadas.

## Exemplo 1: Buscar Pedido Completo (Migração Completa)

### Antes (Código Legado)

```php
<?php
require_once '../../../app/config.php';
require_once '../../../app/auth.php';
require_once '../../../app/functions.php';

requireLogin();
requireRole(['gestor']);

$pedido_id = validarPedidoId($_GET['id'] ?? null);

if (!$pedido_id) {
    $_SESSION['erro'] = 'ID de pedido inválido';
    redirect('../../dashboard/dashboard.php');
}

// Buscar pedido
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.nome as cliente_nome, c.cpf_cnpj
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
} catch (PDOException $e) {
    error_log("Erro: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao carregar pedido';
    redirect('../../dashboard/dashboard.php');
}

// Buscar itens
$stmt = $pdo->prepare("SELECT * FROM pedido_itens WHERE pedido_id = ?");
$stmt->execute([$pedido_id]);
$itens = $stmt->fetchAll();
```

### Depois (Usando Services)

```php
<?php
require_once '../../../app/config.php';
require_once '../../../app/auth.php';
require_once '../../../app/functions.php';

use App\Services\PedidoService;
use App\Core\ModelFactory;
use App\Core\Validator;
use App\Core\Logger;

requireLogin();
requireRole(['gestor']);

// Validar ID
$pedido_id = Validator::pedidoId($_GET['id'] ?? null);

if (!$pedido_id) {
    $_SESSION['erro'] = 'ID de pedido inválido';
    redirect('../../dashboard/dashboard.php');
}

try {
    // Buscar pedido completo usando service
    $service = ModelFactory::pedidoService();
    $pedido = $service->buscarCompleto($pedido_id);
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
    
    // Pedido já vem com itens, versões de arte, arquivos, etc.
    $itens = $pedido['itens'] ?? [];
    $versoes_arte = $pedido['versoes_arte'] ?? [];
    $arquivos = $pedido['arquivos'] ?? [];
    
} catch (\Exception $e) {
    Logger::error('Erro ao buscar pedido', [
        'pedido_id' => $pedido_id,
        'usuario_id' => $_SESSION['user_id'] ?? null,
        'erro' => $e->getMessage()
    ]);
    
    $_SESSION['erro'] = 'Erro ao carregar pedido';
    redirect('../../dashboard/dashboard.php');
}
```

---

## Exemplo 2: Atualizar Status com Validações e Eventos

### Antes

```php
// Atualizar status diretamente
$stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
$stmt->execute([$novo_status, $pedido_id]);

// Registrar log manualmente
registrarLog('status_alterado', "Pedido {$pedido_id} alterado para {$novo_status}");
```

### Depois

```php
use App\Services\PedidoService;
use App\Core\ModelFactory;
use App\Core\EventDispatcher;
use App\Core\Logger;

$service = ModelFactory::pedidoService();

try {
    $status_anterior = $pedido['status'];
    
    // Atualizar com validações de negócio
    $service->atualizarStatus(
        $pedido_id,
        $novo_status,
        $observacoes,
        $_SESSION['user_id']
    );
    
    // Disparar evento (listeners podem fazer outras ações)
    EventDispatcher::dispatch('pedido.status_alterado', [
        'pedido_id' => $pedido_id,
        'status_anterior' => $status_anterior,
        'status_novo' => $novo_status,
        'usuario_id' => $_SESSION['user_id'],
        'observacoes' => $observacoes
    ]);
    
    $_SESSION['sucesso'] = 'Status atualizado com sucesso';
    
} catch (InvalidArgumentException $e) {
    $_SESSION['erro'] = 'Status inválido';
} catch (RuntimeException $e) {
    $_SESSION['erro'] = $e->getMessage();
}
```

---

## Exemplo 3: Listagem com Paginação

### Antes

```php
$page = $_GET['page'] ?? 1;
$perPage = 50; // Hardcoded

$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT * FROM pedidos 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$pedidos = $stmt->fetchAll();

// Contar total (outra query)
$stmt = $pdo->query("SELECT COUNT(*) FROM pedidos");
$total = $stmt->fetchColumn();
$totalPaginas = ceil($total / $perPage);
```

### Depois

```php
use App\Services\PedidoService;
use App\Core\ModelFactory;
use App\Core\Paginator;

$service = ModelFactory::pedidoService();

// Parsear parâmetros de paginação
$params = Paginator::parseParams($_GET);
$page = $params['page'];
$perPage = $params['per_page'];

// Buscar com paginação
$resultado = $service->buscarParaDashboard([
    'status' => $_GET['status'] ?? 'todos'
], $page, $perPage);

$pedidos = $resultado['dados'];
$total = $resultado['total'];
$totalPaginas = $resultado['total_paginas'];

// Renderizar navegação HTML
echo Paginator::render($resultado, '?page=');
```

---

## Exemplo 4: Criar Cliente com Validações

### Antes

```php
// Validações espalhadas
if (empty($_POST['nome'])) {
    $_SESSION['erro'] = 'Nome obrigatório';
    // redirect...
}

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $_SESSION['erro'] = 'Email inválido';
    // redirect...
}

// Verificar se CPF já existe
$stmt = $pdo->prepare("SELECT id FROM clientes WHERE cpf_cnpj = ?");
$stmt->execute([$_POST['cpf_cnpj']]);
if ($stmt->fetch()) {
    $_SESSION['erro'] = 'CPF já cadastrado';
    // redirect...
}

// Inserir
$stmt = $pdo->prepare("INSERT INTO clientes (nome, email, cpf_cnpj) VALUES (?, ?, ?)");
$stmt->execute([$_POST['nome'], $_POST['email'], $_POST['cpf_cnpj']]);
```

### Depois

```php
use App\Services\ClienteService;
use App\Core\ModelFactory;
use App\Core\Validator;
use App\Core\ValidationException;
use App\Core\Logger;

$service = ModelFactory::clienteService();

try {
    // Validar campos
    Validator::required($_POST['nome'], 'nome');
    Validator::required($_POST['email'], 'email');
    Validator::required($_POST['cpf_cnpj'], 'cpf_cnpj');
    
    Validator::email($_POST['email']);
    Validator::cpfCnpj($_POST['cpf_cnpj']);
    Validator::telefone($_POST['telefone'] ?? '');
    
    // Criar cliente (service valida duplicatas automaticamente)
    $clienteId = $service->criar([
        'nome' => $_POST['nome'],
        'email' => $_POST['email'],
        'cpf_cnpj' => $_POST['cpf_cnpj'],
        'telefone' => $_POST['telefone'],
        'endereco' => $_POST['endereco'] ?? '',
        'cidade' => $_POST['cidade'] ?? '',
        'estado' => $_POST['estado'] ?? ''
    ]);
    
    Logger::info('Cliente criado', [
        'cliente_id' => $clienteId,
        'usuario_id' => $_SESSION['user_id']
    ]);
    
    $_SESSION['sucesso'] = 'Cliente cadastrado com sucesso';
    redirect("cliente_detalhes.php?id={$clienteId}");
    
} catch (ValidationException $e) {
    $_SESSION['erro'] = $e->getMessage();
} catch (RuntimeException $e) {
    $_SESSION['erro'] = $e->getMessage(); // Ex: CPF já cadastrado
}
```

---

## Exemplo 5: Query Complexa com QueryBuilder

### Antes

```php
$sql = "
    SELECT 
        p.id,
        p.numero,
        p.status,
        c.nome as cliente_nome,
        COUNT(pi.id) as total_itens
    FROM pedidos p
    LEFT JOIN clientes c ON c.id = p.cliente_id
    LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
    WHERE p.status IN ('novo', 'aprovado')
    AND p.urgente = true
    GROUP BY p.id, c.nome
    HAVING COUNT(pi.id) > 0
    ORDER BY p.created_at DESC
    LIMIT 25
";

$stmt = $pdo->query($sql);
$pedidos = $stmt->fetchAll();
```

### Depois

```php
use App\Core\QueryBuilder;
use App\Core\Database;

$db = Database::getInstance();
$qb = new QueryBuilder($db);

$pedidos = $qb->select([
        'p.id',
        'p.numero',
        'p.status',
        'c.nome as cliente_nome',
        'COUNT(pi.id) as total_itens'
    ])
    ->from('pedidos', 'p')
    ->leftJoin('clientes c', 'c.id = p.cliente_id')
    ->leftJoin('pedido_itens pi', 'pi.pedido_id = p.id')
    ->whereIn('p.status', ['novo', 'aprovado'])
    ->where('p.urgente', '=', true)
    ->groupBy(['p.id', 'c.nome'])
    ->having('COUNT(pi.id) > 0')
    ->orderBy('p.created_at', 'DESC')
    ->limit(25)
    ->get();

// Ver SQL gerado (útil para debug)
echo $qb->toSql();
```

---

## Exemplo 6: Sistema de Eventos Completo

```php
use App\Core\EventDispatcher;
use App\Core\Logger;

// Registrar listeners no início da aplicação (ex: em config.php ou bootstrap)

// Listener para log automático
EventDispatcher::listen('pedido.criado', function($data) {
    Logger::info('Pedido criado', [
        'pedido_id' => $data['id'],
        'cliente_id' => $data['cliente_id'],
        'valor' => $data['valor_total']
    ]);
}, 10);

// Listener para enviar email
EventDispatcher::listen('pedido.criado', function($data) {
    // Enviar email de confirmação
    $to = $data['cliente_email'];
    $subject = "Pedido #{$data['numero']} criado com sucesso";
    // ... código de envio de email
}, 5); // Prioridade menor (executa depois)

// Listener para notificação
EventDispatcher::listen('pedido.status_alterado', function($data) {
    Logger::info('Status alterado', $data);
    
    // Notificar vendedor se necessário
    if (in_array($data['status_novo'], ['aprovado', 'cancelado'])) {
        // Enviar notificação
    }
});

// No código que cria pedido:
EventDispatcher::dispatch('pedido.criado', [
    'id' => $pedidoId,
    'numero' => $numero,
    'cliente_id' => $clienteId,
    'cliente_email' => $clienteEmail,
    'valor_total' => $valorTotal
]);

// No código que altera status:
EventDispatcher::dispatch('pedido.status_alterado', [
    'pedido_id' => $pedidoId,
    'status_anterior' => $statusAnterior,
    'status_novo' => $novoStatus,
    'usuario_id' => $usuarioId
]);
```

---

## Exemplo 7: Logging Estruturado

```php
use App\Core\Logger;

// Em operações críticas
try {
    $pedido = $service->buscarCompleto($id);
    
    Logger::info('Pedido visualizado', [
        'pedido_id' => $id,
        'usuario_id' => $_SESSION['user_id'],
        'perfil' => $_SESSION['perfil']
    ]);
    
} catch (\Exception $e) {
    Logger::exception($e, [
        'contexto' => 'visualizacao_pedido',
        'pedido_id' => $id,
        'usuario_id' => $_SESSION['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    throw $e;
}

// Em validações
if (!Validator::email($email)) {
    Logger::warning('Email inválido tentado', [
        'email' => $email,
        'usuario_id' => $_SESSION['user_id'] ?? null
    ]);
}

// Em operações de escrita
Logger::info('Pedido atualizado', [
    'pedido_id' => $pedidoId,
    'campos_alterados' => ['status', 'observacoes'],
    'usuario_id' => $_SESSION['user_id']
]);
```

---

## Exemplo 8: Health Check em Monitoramento

```php
// Script de monitoramento (cron job)
$healthUrl = 'http://localhost:8080/public/api/health.php';
$response = file_get_contents($healthUrl);
$health = json_decode($response, true);

if ($health['status'] !== 'ok') {
    // Enviar alerta
    mail('admin@example.com', 'Sistema com problemas', json_encode($health, JSON_PRETTY_PRINT));
    
    // Log crítico
    error_log("Health check falhou: " . json_encode($health));
}

// Verificar componentes específicos
if ($health['checks']['database']['status'] !== 'ok') {
    // Banco de dados com problema
}

if ($health['checks']['disk_space']['status'] === 'warning') {
    // Disco quase cheio
}
```

---

## Conclusão

Estes exemplos demonstram como migrar código legado para usar as novas funcionalidades, melhorando:
- **Manutenibilidade**: Código mais limpo e organizado
- **Escalabilidade**: Performance otimizada e estrutura preparada para crescimento
- **Testabilidade**: Componentes isolados e fáceis de testar
- **Extensibilidade**: Sistema de eventos permite adicionar funcionalidades sem modificar código existente
