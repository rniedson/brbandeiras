# Sistema de Auditoria Completo

## Visão Geral

O sistema de auditoria permite rastrear todas as atividades do sistema, fornecendo visibilidade completa sobre ações realizadas por usuários, com filtros avançados e relatórios por período.

## Características Principais

- ✅ **Captura Automática**: Integrado com EventDispatcher para capturar eventos automaticamente
- ✅ **Filtros Avançados**: Por usuário, ação, data, IP, busca textual
- ✅ **Relatórios**: Por dia, semana ou mês
- ✅ **Estatísticas**: Ações mais frequentes, usuários únicos, etc.
- ✅ **Paginação**: Navegação eficiente em grandes volumes de dados
- ✅ **Integração**: Compatível com sistema de logs existente

## Estrutura

### Arquivos Criados

```
app/
├── Repositories/
│   └── AuditoriaRepository.php    # Queries de auditoria
├── Services/
│   └── AuditoriaService.php      # Lógica de negócio
└── Core/
    └── Auditoria.php              # Helper e listeners automáticos

public/auditoria/
├── auditoria.php                  # Interface principal de visualização
└── relatorio.php                   # Relatórios resumidos
```

## Uso

### Registro Manual de Auditoria

```php
use App\Core\Auditoria;

// Registro simples
Auditoria::registrar('pedido_criado', 'Pedido #123 criado');

// Registro com contexto
Auditoria::registrar('pedido_atualizado', 'Pedido atualizado', $usuarioId, [
    'entidade_tipo' => 'pedido',
    'entidade_id' => 123,
    'dados_anteriores' => $dadosAntigos,
    'dados_novos' => $dadosNovos
]);
```

### Registro Automático via Eventos

O sistema captura automaticamente eventos disparados via `EventDispatcher`:

```php
use App\Core\EventDispatcher;

// Disparar evento (automaticamente registrado em auditoria)
EventDispatcher::dispatch('pedido.criado', [
    'id' => 123,
    'numero' => '20250125-0001',
    'cliente_id' => 5
]);

// Outros eventos capturados automaticamente:
// - pedido.criado
// - pedido.atualizado
// - pedido.status_alterado
// - pedido.deletado
// - cliente.criado
// - cliente.atualizado
// - usuario.login
// - usuario.logout
```

### Integração com Função Existente

A função `registrarLog()` existente foi atualizada para usar o sistema de auditoria:

```php
// Continua funcionando como antes
registrarLog('acao_realizada', 'Detalhes da ação');

// Agora usa o sistema de auditoria internamente
```

### Buscar Registros Programaticamente

```php
use App\Services\AuditoriaService;
use App\Core\ModelFactory;

$service = ModelFactory::auditoriaService();

// Buscar com filtros e paginação
$resultado = $service->buscar([
    'usuario_id' => 5,
    'acao' => 'pedido_criado',
    'data_inicio' => '2025-01-01',
    'data_fim' => '2025-01-31'
], 1, 50);

// Buscar por período
$estatisticas = $service->buscarPorPeriodo('mes');

// Buscar por usuário
$atividades = $service->buscarPorUsuario(5, [], 1, 50);

// Gerar relatório completo
$relatorio = $service->gerarRelatorio('semana', ['usuario_id' => 5]);
```

## Interface Web

### Visualização Principal

**URL:** `/public/auditoria/auditoria.php`

**Funcionalidades:**
- Visualização de todos os registros
- Filtros por:
  - Período (dia/semana/mês)
  - Usuário
  - Ação
  - Data início/fim
  - IP
  - Busca textual
- Paginação
- Estatísticas em tempo real
- Ações mais frequentes

### Relatórios

**URL:** `/public/auditoria/relatorio.php`

**Funcionalidades:**
- Relatório resumido por período
- Estatísticas gerais
- Atividades por data
- Ações mais frequentes com gráficos
- Filtro opcional por usuário

## Eventos Capturados Automaticamente

O sistema registra automaticamente os seguintes eventos:

### Pedidos
- `pedido.criado` - Quando um pedido é criado
- `pedido.atualizado` - Quando um pedido é atualizado
- `pedido.status_alterado` - Quando o status muda
- `pedido.deletado` - Quando um pedido é deletado

### Clientes
- `cliente.criado` - Quando um cliente é criado
- `cliente.atualizado` - Quando um cliente é atualizado

### Usuários
- `usuario.login` - Quando um usuário faz login
- `usuario.logout` - Quando um usuário faz logout

### Genérico
- `auditoria.*` - Qualquer evento que comece com `auditoria.`

## Adicionar Novos Eventos

Para adicionar captura automática de novos eventos:

1. **Disparar evento no código:**
```php
EventDispatcher::dispatch('minha.acao', [
    'id' => 123,
    'detalhes' => 'Informações adicionais'
]);
```

2. **Registrar listener em `app/Core/Auditoria.php`:**
```php
EventDispatcher::listen('minha.acao', function($data) {
    Auditoria::registrar('minha_acao', "Descrição da ação", null, [
        'entidade_tipo' => 'minha_entidade',
        'entidade_id' => $data['id'] ?? null
    ]);
}, 100);
```

## Filtros Disponíveis

### Por Usuário
```php
$filtros = ['usuario_id' => 5];
```

### Por Ação
```php
$filtros = ['acao' => 'pedido_criado'];
```

### Por Período
```php
$filtros = [
    'data_inicio' => '2025-01-01',
    'data_fim' => '2025-01-31'
];
```

### Por IP
```php
$filtros = ['ip' => '192.168.1.1'];
```

### Busca Textual
```php
$filtros = ['busca' => 'pedido'];
// Busca em 'acao' e 'detalhes'
```

## Relatórios

### Por Período

```php
// Hoje
$relatorio = $service->gerarRelatorio('dia');

// Últimos 7 dias
$relatorio = $service->gerarRelatorio('semana');

// Este mês
$relatorio = $service->gerarRelatorio('mes');

// Com filtros
$relatorio = $service->gerarRelatorio('mes', ['usuario_id' => 5]);
```

### Estrutura do Relatório

```php
[
    'periodo' => 'dia',
    'data_inicio' => '2025-01-25 00:00:00',
    'data_fim' => '2025-01-25 23:59:59',
    'total_atividades' => 150,
    'total_usuarios_unicos' => 10,
    'total_acoes_unicas' => 25,
    'estatisticas_por_data' => [...],
    'acoes_frequentes' => [...]
]
```

## Performance

- **Índices**: O sistema utiliza índices existentes em `logs_sistema`
- **Paginação**: Limite padrão de 50 registros por página
- **Cache**: Considerar implementar cache para relatórios frequentes

## Segurança

- **Acesso**: Apenas administradores podem acessar (`requireRole(['administrador'])`)
- **Dados Sensíveis**: Evitar registrar senhas ou dados sensíveis nos detalhes
- **IP**: Registrado automaticamente para rastreamento

## Exemplos Práticos

### Exemplo 1: Registrar Criação de Pedido

```php
use App\Core\EventDispatcher;

// No código que cria pedido
EventDispatcher::dispatch('pedido.criado', [
    'id' => $pedidoId,
    'numero' => $numero,
    'cliente_id' => $clienteId,
    'valor_total' => $valorTotal
]);
// Automaticamente registrado em auditoria!
```

### Exemplo 2: Registrar Alteração de Status

```php
use App\Core\EventDispatcher;

EventDispatcher::dispatch('pedido.status_alterado', [
    'pedido_id' => $pedidoId,
    'status_anterior' => 'novo',
    'status_novo' => 'aprovado',
    'usuario_id' => $_SESSION['user_id']
]);
```

### Exemplo 3: Buscar Atividades de um Usuário

```php
use App\Core\ModelFactory;

$service = ModelFactory::auditoriaService();
$atividades = $service->buscarPorUsuario(5, [], 1, 50);

foreach ($atividades['dados'] as $atividade) {
    echo "{$atividade['acao']}: {$atividade['detalhes']}\n";
}
```

## Próximos Passos

1. **Exportação**: Adicionar exportação para CSV/PDF
2. **Alertas**: Configurar alertas para ações suspeitas
3. **Retenção**: Implementar política de retenção de logs
4. **Dashboard**: Criar dashboard com gráficos de atividades
5. **Notificações**: Notificar gestores sobre ações críticas

## Manutenção

### Limpeza de Logs Antigos

```sql
-- Exemplo: Deletar logs com mais de 1 ano
DELETE FROM logs_sistema 
WHERE created_at < NOW() - INTERVAL '1 year';
```

### Backup

Recomenda-se fazer backup regular da tabela `logs_sistema`:

```sql
-- Backup
COPY logs_sistema TO '/backup/logs_sistema_' || CURRENT_DATE || '.csv' CSV HEADER;
```

## Suporte

Para dúvidas ou problemas:
- Consulte código-fonte com PHPDoc completo
- Verifique exemplos em `docs/EXEMPLOS_USO.md`
- Acesse interface web em `/public/auditoria/auditoria.php`
