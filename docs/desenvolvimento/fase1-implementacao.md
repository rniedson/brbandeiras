# Fase 1: Estrutura Base MVC - Implementação Completa

**Data de Implementação:** 2025-01-25  
**Status:** ✅ Implementado e Testado

---

## 📋 Visão Geral

A Fase 1 implementa a fundação arquitetural do sistema mantendo **100% de compatibilidade** com o código legado existente. Todas as classes foram criadas sem quebrar funcionalidades existentes.

---

## 🏗️ Estrutura Criada

```
app/
├── Core/
│   ├── Database.php          ✅ Singleton para conexão PDO
│   ├── BaseModel.php         ✅ Classe base com CRUD genérico
│   └── LegacyAdapter.php     ✅ Bridge para código legado
├── Models/
│   └── Pedido.php            ✅ Modelo específico de pedidos
├── config_legacy.php          ✅ Config que mantém $pdo global
└── test_fase1.php            ✅ Testes de validação
```

---

## 📚 Documentação das Classes

### 1. Database (app/Core/Database.php)

**Padrão:** Singleton  
**Responsabilidade:** Gerenciar conexão PDO centralizada

#### Métodos Principais

```php
// Obter instância única
$db = Database::getInstance();

// Executar query
$stmt = $db->query("SELECT * FROM pedidos WHERE id = ?", [1]);
$pedido = $stmt->fetch();

// Transação com callback
$resultado = $db->transaction(function($db) {
    $db->query("INSERT INTO pedidos (...) VALUES (...)");
    $db->query("INSERT INTO pedido_itens (...) VALUES (...)");
    return "sucesso";
});

// Acesso direto ao PDO (quando necessário)
$pdo = $db->getPdo();
```

#### Características

- ✅ Singleton pattern (uma única instância)
- ✅ Reutiliza lógica de conexão de `app/config.php`
- ✅ Suporta `DATABASE_URL` e variáveis individuais
- ✅ Tratamento de erros robusto
- ✅ Suporte a transações com rollback automático

---

### 2. BaseModel (app/Core/BaseModel.php)

**Padrão:** Classe Abstrata  
**Responsabilidade:** Fornecer CRUD genérico para todos os modelos

#### Métodos Disponíveis

```php
// Buscar por ID
$pedido = $model->find(1);

// Buscar por campo
$pedidos = $model->findBy('status', 'arte');

// Listar todos com condições
$pedidos = $model->findAll(
    ['status' => 'arte', 'urgente' => true],
    'created_at DESC',
    10,  // limit
    0    // offset
);

// Criar novo registro
$id = $model->create([
    'numero' => '20250125-0001',
    'cliente_id' => 1,
    'valor_total' => 1000.00
]);

// Atualizar registro
$model->update(1, [
    'status' => 'producao',
    'valor_final' => 950.00
]);

// Deletar registro
$model->delete(1);

// Contar registros
$total = $model->count(['status' => 'arte']);

// Verificar existência
if ($model->exists(1)) {
    // ...
}
```

#### Características

- ✅ CRUD completo genérico
- ✅ Suporte a PostgreSQL RETURNING clause
- ✅ Validação básica de dados
- ✅ Retorna arrays associativos (compatível com código atual)
- ✅ Métodos protegidos para queries customizadas

---

### 3. Pedido (app/Models/Pedido.php)

**Padrão:** Modelo Específico  
**Responsabilidade:** Lógica específica de pedidos

#### Métodos Específicos

```php
$pedidoModel = new Pedido(Database::getInstance());

// Gerar número único de pedido
$numero = $pedidoModel->gerarNumero('11987654321');
// Retorna: "20250125-0001-4321"

// Buscar pedido com dados do cliente
$pedido = $pedidoModel->comCliente(1);

// Buscar pedido com itens
$pedido = $pedidoModel->comItens(1);

// Buscar por status
$pedidos = $pedidoModel->findByStatus('arte');

// Buscar por vendedor
$pedidos = $pedidoModel->findByVendedor(5, 'arte'); // status opcional

// Buscar para dashboard (com relacionamentos)
$pedidos = $pedidoModel->findParaDashboard([
    'status' => 'arte',
    'urgente' => true
]);

// Buscar completo (todos os relacionamentos)
$pedido = $pedidoModel->findCompleto(1);

// Atualizar status
$pedidoModel->atualizarStatus(1, 'producao', 'Observação', $usuarioId);
```

#### Características

- ✅ Métodos específicos de domínio
- ✅ Queries otimizadas com JOINs
- ✅ Suporte a relacionamentos complexos
- ✅ Lógica de negócio encapsulada

---

### 4. LegacyAdapter (app/Core/LegacyAdapter.php)

**Padrão:** Adapter  
**Responsabilidade:** Compatibilidade com código legado

#### Uso

```php
// Código legado continua funcionando
require_once '../app/Core/LegacyAdapter.php';

$pdo = LegacyAdapter::getPdo();
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
$stmt->execute([1]);
```

#### Características

- ✅ Acesso estático ao PDO
- ✅ Métodos de transação disponíveis
- ✅ Zero breaking changes

---

### 5. config_legacy.php

**Responsabilidade:** Manter variável `$pdo` global funcionando

#### Uso

```php
// Em vez de:
require_once '../app/config.php';

// Pode usar (durante migração):
require_once '../app/config_legacy.php';

// $pdo global continua funcionando normalmente
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM pedidos");
```

#### Características

- ✅ Mantém `$pdo` global
- ✅ Mantém `$GLOBALS['pdo']`
- ✅ Define todas as constantes do sistema
- ✅ Compatível com código existente

---

## 🔄 Exemplos de Migração

### Antes (Código Legado)

```php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

// Buscar pedido
$stmt = $pdo->prepare("
    SELECT p.*, c.nome as cliente_nome 
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

// Criar pedido
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        INSERT INTO pedidos (numero, cliente_id, valor_total) 
        VALUES (?, ?, ?) 
        RETURNING id
    ");
    $stmt->execute([$numero, $cliente_id, $valor_total]);
    $pedido_id = $stmt->fetchColumn();
    
    // Inserir itens...
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

### Depois (Código Novo - Opcional)

```php
require_once '../app/Core/Database.php';
require_once '../app/Models/Pedido.php';
require_once '../app/auth.php';

requireLogin();

$db = Database::getInstance();
$pedidoModel = new Pedido($db);

// Buscar pedido
$pedido = $pedidoModel->comCliente($pedido_id);

// Criar pedido
$pedido_id = $db->transaction(function($db) use ($pedidoModel, $cliente_id, $valor_total) {
    $numero = $pedidoModel->gerarNumero();
    
    $pedido_id = $pedidoModel->create([
        'numero' => $numero,
        'cliente_id' => $cliente_id,
        'valor_total' => $valor_total,
        'status' => 'arte'
    ]);
    
    // Inserir itens usando modelo...
    
    return $pedido_id;
});
```

---

## ✅ Compatibilidade

### Código Legado Continua Funcionando

```php
// ✅ Funciona normalmente
require_once '../app/config.php';
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM pedidos");
```

```php
// ✅ Também funciona
require_once '../app/config_legacy.php';
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM pedidos");
```

### Código Novo Disponível

```php
// ✅ Nova arquitetura disponível
require_once '../app/Core/Database.php';
require_once '../app/Models/Pedido.php';

$pedidoModel = new Pedido(Database::getInstance());
$pedido = $pedidoModel->find(1);
```

---

## 🧪 Testes

Execute os testes para validar a implementação:

```bash
cd /Applications/AMPPS/www/brbandeiras
php app/test_fase1.php
```

Os testes validam:
- ✅ Database singleton funcionando
- ✅ Queries executando corretamente
- ✅ Transações com commit/rollback
- ✅ BaseModel CRUD básico
- ✅ Modelo Pedido com métodos específicos
- ✅ LegacyAdapter para compatibilidade
- ✅ config_legacy.php mantendo $pdo global

---

## 📊 Benefícios Alcançados

### 1. Zero Breaking Changes
- ✅ Todo código existente continua funcionando
- ✅ Migração pode ser gradual
- ✅ Sem pressa para refatorar tudo

### 2. Base Sólida
- ✅ Arquitetura preparada para expansão
- ✅ Padrões estabelecidos
- ✅ Código reutilizável

### 3. Manutenibilidade
- ✅ Lógica centralizada
- ✅ Menos duplicação
- ✅ Fácil de testar

### 4. Performance
- ✅ Singleton evita múltiplas conexões
- ✅ Queries otimizadas
- ✅ Sem overhead adicional

---

## 🚀 Próximos Passos

### Imediato (Opcional)
- Usar `config_legacy.php` em novos arquivos
- Criar novos modelos usando BaseModel
- Migrar um módulo piloto gradualmente

### Fase 2 (Futuro)
- Criar Services (lógica de negócio)
- Criar Controllers
- Implementar roteamento
- Adicionar validação centralizada

---

## 📝 Notas Importantes

1. **Compatibilidade Total**: O código legado não precisa ser modificado
2. **Migração Gradual**: Migre módulos conforme necessário
3. **Testes**: Sempre teste após migrar código
4. **Performance**: Nova arquitetura não adiciona overhead

---

## 🔗 Arquivos Relacionados

- `app/Core/Database.php` - Classe singleton de banco
- `app/Core/BaseModel.php` - Classe base para modelos
- `app/Models/Pedido.php` - Modelo de exemplo
- `app/Core/LegacyAdapter.php` - Adaptador de compatibilidade
- `app/config_legacy.php` - Config com compatibilidade
- `app/test_fase1.php` - Testes de validação

---

**Implementação concluída em:** 2025-01-25  
**Próxima revisão:** Após uso em produção
