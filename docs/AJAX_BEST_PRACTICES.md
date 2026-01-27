# Boas Práticas para Endpoints AJAX

Este documento descreve as boas práticas para criar e manter endpoints AJAX no sistema, garantindo que sempre retornem JSON válido e evitando o erro `ERR_EMPTY_RESPONSE`.

## Índice

1. [Problema: ERR_EMPTY_RESPONSE](#problema-err_empty_response)
2. [Solução: AjaxResponse Helper](#solução-ajaxresponse-helper)
3. [Como Usar](#como-usar)
4. [Checklist para Novos Endpoints](#checklist-para-novos-endpoints)
5. [Exemplos](#exemplos)
6. [Debugging](#debugging)
7. [JavaScript: ajax_utils.js](#javascript-ajax_utilsjs)

## Problema: ERR_EMPTY_RESPONSE

O erro `ERR_EMPTY_RESPONSE` ocorre quando o servidor não envia nenhuma resposta HTTP válida. Causas comuns:

1. **Erro fatal no PHP** que interrompe a execução antes de enviar resposta
2. **Conflitos com output buffering** (ob_start/ob_end_clean)
3. **Headers já enviados** antes de tentar enviar JSON
4. **`die()` ou `exit()` com HTML/texto** em requisições AJAX
5. **BOM (Byte Order Mark)** ou espaços antes de `<?php`

## Solução: AjaxResponse Helper

Criamos a classe `AjaxResponse` em `app/ajax_helper.php` que encapsula todas as boas práticas:

- Define `AJAX_REQUEST` antes de carregar `config.php` (evita `die()` com HTML)
- Configura tratamento de erros apropriado
- Registra shutdown function para capturar erros fatais
- Garante que sempre retorna JSON válido, mesmo em caso de erro

## Como Usar

### 1. Estrutura Básica de um Endpoint AJAX

```php
<?php
// IMPORTANTE: Carregar ajax_helper ANTES de config.php
require_once '../../app/ajax_helper.php';
AjaxResponse::init();

require_once '../../app/config.php';
require_once '../../app/auth.php';

// Verificar autenticação
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado', 401);
}

// Verificar método HTTP se necessário
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    AjaxResponse::error('Método não permitido', 405);
}

try {
    // Sua lógica aqui
    
    AjaxResponse::success($dados, 'Operação realizada com sucesso');
    
} catch (PDOException $e) {
    AjaxResponse::error('Erro de banco de dados');
} catch (Exception $e) {
    AjaxResponse::error($e->getMessage());
}
```

### 2. Métodos Disponíveis

#### `AjaxResponse::init()`
Deve ser chamado **ANTES** de carregar `config.php`. Configura tudo necessário.

#### `AjaxResponse::json($data, $statusCode = 200)`
Envia resposta JSON customizada.

```php
AjaxResponse::json([
    'success' => true,
    'custom_field' => 'valor'
], 200);
```

#### `AjaxResponse::success($data = null, $message = null, $statusCode = 200)`
Envia resposta de sucesso padronizada.

```php
AjaxResponse::success($resultado, 'Dados carregados com sucesso');
```

#### `AjaxResponse::error($message, $statusCode = 500)`
Envia resposta de erro padronizada.

```php
AjaxResponse::error('Erro ao processar', 500);
AjaxResponse::error('Não autenticado', 401);
AjaxResponse::error('Sem permissão', 403);
```

## Checklist para Novos Endpoints

Ao criar um novo endpoint AJAX, verifique:

- [ ] Carrega `ajax_helper.php` **ANTES** de `config.php`
- [ ] Chama `AjaxResponse::init()` logo após carregar o helper
- [ ] Verifica autenticação antes de processar
- [ ] Verifica método HTTP se necessário (POST, GET, etc)
- [ ] Verifica permissões/roles se necessário
- [ ] Verifica conexão com banco antes de usar `$pdo`
- [ ] Usa `try/catch` para capturar erros
- [ ] Usa `AjaxResponse::success()` ou `AjaxResponse::error()` para respostas
- [ ] Não usa `echo json_encode()` diretamente
- [ ] Não usa `die()` ou `exit()` com texto/HTML
- [ ] Não há espaços ou BOM antes de `<?php`

## Exemplos

### Exemplo 1: Endpoint GET Simples

```php
<?php
require_once '../../app/ajax_helper.php';
AjaxResponse::init();

require_once '../../app/config.php';
require_once '../../app/auth.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado', 401);
}

try {
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        AjaxResponse::error('ID não fornecido', 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM tabela WHERE id = ?");
    $stmt->execute([$id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resultado) {
        AjaxResponse::error('Registro não encontrado', 404);
    }
    
    AjaxResponse::success($resultado);
    
} catch (PDOException $e) {
    AjaxResponse::error('Erro de banco de dados');
} catch (Exception $e) {
    AjaxResponse::error($e->getMessage());
}
```

### Exemplo 2: Endpoint POST com Validação

```php
<?php
require_once '../../app/ajax_helper.php';
AjaxResponse::init();

require_once '../../app/config.php';
require_once '../../app/auth.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    AjaxResponse::error('Método não permitido', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['campo_obrigatorio'])) {
        AjaxResponse::error('Dados incompletos', 400);
    }
    
    // Validações adicionais
    $campo = trim($input['campo_obrigatorio']);
    if (empty($campo)) {
        AjaxResponse::error('Campo obrigatório não pode estar vazio', 400);
    }
    
    // Processar
    $stmt = $pdo->prepare("INSERT INTO tabela (campo) VALUES (?)");
    $stmt->execute([$campo]);
    
    AjaxResponse::success([
        'id' => $pdo->lastInsertId()
    ], 'Registro criado com sucesso');
    
} catch (PDOException $e) {
    AjaxResponse::error('Erro de banco de dados');
} catch (Exception $e) {
    AjaxResponse::error($e->getMessage());
}
```

### Exemplo 3: Upload de Arquivo

```php
<?php
require_once '../../app/ajax_helper.php';
AjaxResponse::init();

require_once '../../app/config.php';
require_once '../../app/auth.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado', 401);
}

if (!isset($_FILES['arquivo']) || !isset($_POST['pedido_id'])) {
    AjaxResponse::error('Dados incompletos', 400);
}

try {
    $pedido_id = intval($_POST['pedido_id']);
    $arquivo = $_FILES['arquivo'];
    
    // Validações
    $allowed_types = ['pdf', 'jpg', 'png'];
    $file_ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        AjaxResponse::error('Tipo de arquivo não permitido', 400);
    }
    
    // Upload...
    // ...
    
    AjaxResponse::success([
        'arquivo_id' => $arquivo_id,
        'nome' => $arquivo['name']
    ], 'Arquivo enviado com sucesso');
    
} catch (Exception $e) {
    AjaxResponse::error($e->getMessage());
}
```

## Debugging

### Verificar Logs do PHP

```bash
tail -f /Applications/AMPPS/apache/logs/error_log
```

### Testar Endpoint com cURL

```bash
curl -X POST http://localhost:8080/public/api/endpoint.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=seu_session_id" \
  -d '{"campo": "valor"}'
```

### Verificar Resposta no Navegador

1. Abra DevTools (F12)
2. Vá para a aba Network
3. Faça a requisição AJAX
4. Clique na requisição para ver detalhes
5. Verifique Headers e Response

### Problemas Comuns

#### Erro: "Headers already sent"
**Causa:** Output antes de `header()` ou `AjaxResponse::json()`
**Solução:** Verificar se há espaços antes de `<?php` ou echo antes dos headers

#### Erro: "Unexpected end of JSON input"
**Causa:** Resposta vazia ou incompleta
**Solução:** Verificar se há `die()` ou `exit()` sem resposta JSON

#### Erro: "ERR_EMPTY_RESPONSE"
**Causa:** Erro fatal não capturado
**Solução:** Usar `AjaxResponse::init()` que registra shutdown function

## JavaScript: ajax_utils.js

Para facilitar requisições AJAX no frontend, criamos `public/js/ajax_utils.js`:

### Uso Básico

```javascript
// GET
const data = await ajaxGet('/api/endpoint.php', { id: 123 });

// POST
const result = await ajaxPost('/api/endpoint.php', {
    campo: 'valor'
});

// PUT
const updated = await ajaxPut('/api/endpoint.php', {
    id: 123,
    campo: 'novo valor'
});

// DELETE
await ajaxDelete('/api/endpoint.php?id=123');
```

### Com Tratamento de Erro

```javascript
try {
    const data = await ajaxGet('/api/endpoint.php', { id: 123 });
    console.log('Sucesso:', data);
} catch (error) {
    console.error('Erro:', error.message);
    alert('Erro: ' + error.message);
}
```

### Incluir no HTML

```html
<script src="/public/js/ajax_utils.js"></script>
```

## Migração de Endpoints Existentes

Para migrar um endpoint existente:

1. Adicionar `require_once '../../app/ajax_helper.php';` no início
2. Adicionar `AjaxResponse::init();` logo após
3. Substituir `header('Content-Type: application/json');` por remoção (AjaxResponse faz isso)
4. Substituir `echo json_encode(...)` por `AjaxResponse::json(...)` ou `AjaxResponse::success(...)`
5. Substituir `die()` ou `exit()` com texto por `AjaxResponse::error(...)`
6. Garantir que autenticação é verificada antes de processar

## Verificação de BOM/Espaços

Execute o script de verificação:

```bash
# Verificar problemas
php scripts/verificar_bom.php

# Corrigir automaticamente
php scripts/verificar_bom.php --fix
```

## Referências

- Template de referência: `templates/ajax_endpoint_template.php`
- Helper PHP: `app/ajax_helper.php`
- Utilitário JavaScript: `public/js/ajax_utils.js`

## Suporte

Em caso de dúvidas ou problemas, consulte:
- Este documento
- Template em `templates/ajax_endpoint_template.php`
- Exemplos em arquivos já migrados:
  - `public/pedidos/pedido_upload_ajax.php`
  - `public/producao/producao_atualizar_status.php`
  - `public/dashboard/check_updates_simple.php`
