# Segurança - BR Bandeiras

Este documento descreve as medidas de segurança implementadas no sistema.

## Proteções Implementadas

### 1. Proteção CSRF

**Classe:** `app/Core/CSRF.php`

Proteção contra Cross-Site Request Forgery em todos os formulários POST.

#### Uso em Formulários

```php
<form method="POST" action="processar.php">
    <?= CSRF::getField() ?>
    <!-- outros campos -->
</form>
```

#### Validação em Processadores

```php
require_once '../app/config.php';

// Validar token CSRF
try {
    CSRF::validate($_POST['csrf_token'] ?? '');
} catch (RuntimeException $e) {
    $_SESSION['erro'] = $e->getMessage();
    redirect('formulario.php');
    exit;
}

// Processar formulário...
```

#### Características

- Tokens únicos por sessão
- Expiração automática (1 hora)
- One-time use (token removido após validação)
- Limpeza automática de tokens antigos

### 2. Rate Limiting

**Classe:** `app/Core/RateLimiter.php`

Limitação de tentativas para prevenir ataques de força bruta.

#### Uso no Login

```php
// Verificar limite antes de processar
if (!RateLimiter::check('login', null, 5, 900)) {
    $remainingTime = RateLimiter::getTimeUntilReset('login');
    $_SESSION['erro'] = "Muitas tentativas. Tente novamente em {$remainingTime} segundos.";
    redirect('login.php');
    exit;
}

// Registrar tentativa falha
RateLimiter::recordAttempt('login');

// Limpar após sucesso
RateLimiter::clear('login');
```

#### Configuração Padrão

- **Máximo de tentativas:** 5
- **Janela de tempo:** 15 minutos (900 segundos)
- **Armazenamento:** APCu (se disponível) ou Sessão

### 3. Proteção SQL Injection

**Arquivo:** `app/Core/BaseModel.php`

Proteção através de whitelist de tabelas e validação de identificadores.

#### Whitelist de Tabelas

Apenas tabelas listadas em `BaseModel::$allowedTables` podem ser usadas:

```php
private static $allowedTables = [
    'pedidos', 'clientes', 'usuarios', 'produtos_catalogo', ...
];
```

#### Validação de Identificadores

- Nomes de tabelas validados contra whitelist
- Nomes de colunas validados (apenas letras, números, underscore)
- Escape de identificadores PostgreSQL quando necessário

### 4. Sessões Seguras

**Arquivo:** `app/auth.php`

#### Configurações

- **Timeout:** 2 horas (7200 segundos)
- **Renovação automática:** A cada 30 minutos de atividade
- **Regeneração de ID:** Automática após período de inatividade

#### Implementação

```php
// Verificar e renovar sessão
renovarSessaoSeNecessario();

// Verificar expiração
if (!verificarSessaoExpirada()) {
    // Redirecionar para login
}
```

### 5. Autenticação

**Arquivo:** `public/auth.php`

- Hash de senhas com `password_hash()` (PASSWORD_DEFAULT)
- Verificação com `password_verify()`
- Rate limiting integrado
- Validação CSRF

### 6. Error Handling

**Classe:** `app/Core/ErrorHandler.php`

Tratamento centralizado de erros:

- Não expõe informações sensíveis em produção
- Loga todos os erros automaticamente
- Responde apropriadamente (JSON para AJAX, HTML para requisições normais)
- Stack trace apenas em desenvolvimento

### 7. Proteção de Arquivos Sensíveis

**Arquivo:** `.gitignore`

Arquivos protegidos:
- `.env` - Credenciais do banco
- `*.log` - Logs do sistema
- `/uploads/*` - Arquivos enviados
- `/storage/logs/*` - Logs da aplicação

**Template:** `.env.example` - Exemplo sem senhas reais

## Boas Práticas

### 1. Sempre Validar Entrada

```php
// Validar CSRF
CSRF::validate($_POST['csrf_token'] ?? '');

// Validar dados
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
```

### 2. Usar Prepared Statements

```php
// Sempre usar placeholders
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
```

### 3. Sanitizar Saída HTML

```php
// Sempre escapar HTML
echo htmlspecialchars($dados_usuario, ENT_QUOTES, 'UTF-8');
```

### 4. Verificar Permissões

```php
// Verificar autenticação
requireLogin();

// Verificar perfil
requireRole(['gestor', 'admin']);
```

### 5. Limpar Cache Após Updates

```php
// Em models com cache
$pedidoModel->update($id, $dados);
$pedidoModel->clearCache($id);
```

## Checklist de Segurança

- [x] CSRF Protection implementado
- [x] Rate Limiting no login
- [x] SQL Injection prevention no BaseModel
- [x] Sessões com timeout adequado
- [x] Error Handler centralizado
- [x] .env protegido no .gitignore
- [x] Senhas com password_hash
- [x] Validação de entrada
- [x] Sanitização de saída

## Próximas Melhorias

- [ ] Implementar HTTPS obrigatório em produção
- [ ] Adicionar Content Security Policy (CSP)
- [ ] Implementar 2FA (Two-Factor Authentication)
- [ ] Adicionar auditoria de ações críticas
- [ ] Implementar backup automático de dados sensíveis
