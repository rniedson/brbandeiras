# Otimização de Performance - Relatório Lighthouse

**Data:** 2026-01-25  
**Score Atual:** Performance 50% | Best Practices 77%

---

## 🚨 Problemas Críticos Identificados

### 1. Tempo de Resposta do Servidor: **7.3 segundos** ⚠️ CRÍTICO

**Impacto:** 
- FCP: 5.7s (deveria ser < 1.6s)
- LCP: 5.7s (deveria ser < 2.4s)
- Speed Index: 8.6s (deveria ser < 2.3s)

**Causa Raiz:**
- Múltiplas queries SQL sequenciais no `dashboard_gestor.php`
- Sem cache de resultados
- Sem compressão de resposta

**Solução:**

#### A. Otimizar Queries SQL

```php
// ❌ ANTES: 7 queries separadas
foreach ($statusQueries as $key => $query) {
    $result = $pdo->query($query);
    $stats[$key] = $result->fetchColumn();
}

// ✅ DEPOIS: 1 query única com CASE
$stmt = $pdo->query("
    SELECT 
        COUNT(*) FILTER (WHERE status = 'cancelado') as cancelado,
        COUNT(*) FILTER (WHERE status = 'orcamento') as orcamento,
        COUNT(*) FILTER (WHERE status = 'arte') as arte,
        COUNT(*) FILTER (WHERE status = 'producao') as producao,
        COUNT(*) FILTER (WHERE status = 'pronto') as pronto,
        COUNT(*) FILTER (WHERE status = 'entregue') as entregue,
        COUNT(*) FILTER (WHERE urgente = true AND status NOT IN ('entregue', 'cancelado')) as urgentes
    FROM pedidos
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
```

#### B. Implementar Cache

```php
// Adicionar no início de dashboard_gestor.php
$cache_key = 'dashboard_stats_' . date('Y-m-d-H-i'); // Cache por minuto
$cache_file = sys_get_temp_dir() . '/brbandeiras_' . md5($cache_key) . '.cache';

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 60) {
    $stats = json_decode(file_get_contents($cache_file), true);
} else {
    // Executar queries...
    file_put_contents($cache_file, json_encode($stats));
}
```

#### C. Habilitar Compressão

Adicionar no `.htaccess`:

```apache
# Compressão GZIP
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
```

E no início do PHP:

```php
// Habilitar compressão de saída
if (extension_loaded('zlib') && !ob_get_level()) {
    ob_start('ob_gzhandler');
}
```

---

### 2. CSS Não Utilizado: **15 KiB** (99% do Font Awesome)

**Problema:** Carregando toda biblioteca Font Awesome mas usando poucos ícones

**Solução:**

```html
<!-- ❌ ANTES -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- ✅ DEPOIS: Usar apenas ícones necessários -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/solid.min.css">
<!-- Ou melhor ainda: usar SVG inline -->
```

Ou criar um arquivo CSS customizado apenas com os ícones usados.

---

### 3. JavaScript Não Utilizado: **1,064 KiB**

**Problema:** Extensões do Chrome carregando muito código não utilizado

**Nota:** Isso é principalmente de extensões do navegador (MetaMask, etc). Não há muito o que fazer no lado do servidor, mas podemos:

- Defer carregamento de scripts não críticos
- Usar `async` para scripts de terceiros

---

### 4. Layout Shifts (CLS): **0.161**

**Problema:** Elemento `kanban-container` causando shift de 0.151

**Solução:**

```css
/* Adicionar altura mínima para evitar shift */
.kanban-container {
    min-height: 800px; /* Altura aproximada do conteúdo */
}

/* Ou usar skeleton loader */
.kanban-container:empty::before {
    content: '';
    display: block;
    height: 800px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}
```

---

### 5. Falta de Headers de Segurança

**Problemas:**
- Sem CSP (Content Security Policy)
- Sem HSTS
- Sem COOP (Cross-Origin-Opener-Policy)
- Sem X-Frame-Options

**Solução:** Adicionar no `.htaccess`:

```apache
# Security Headers
<IfModule mod_headers.c>
    # Content Security Policy
    Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self';"
    
    # HSTS (apenas em HTTPS)
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS
    
    # X-Frame-Options
    Header always set X-Frame-Options "SAMEORIGIN"
    
    # X-Content-Type-Options
    Header always set X-Content-Type-Options "nosniff"
    
    # Referrer Policy
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Permissions Policy
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>
```

---

### 6. Sem Compressão no Documento Principal

**Problema:** Documento HTML de 171 KB sem compressão

**Solução:** Já mencionada acima (mod_deflate + ob_gzhandler)

---

### 7. Cache de Recursos Estáticos

**Problema:** Recursos não têm headers de cache

**Solução:** Adicionar no `.htaccess`:

```apache
# Cache de recursos estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    
    # Imagens
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    
    # CSS e JavaScript
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    
    # Fontes
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"
    ExpiresByType font/ttf "access plus 1 year"
    
    # HTML (cache curto)
    ExpiresByType text/html "access plus 0 seconds"
</IfModule>

# Cache-Control headers
<IfModule mod_headers.c>
    <FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|css|js|woff|woff2|ttf)$">
        Header set Cache-Control "max-age=31536000, public"
    </FilesMatch>
    
    <FilesMatch "\.(html|php)$">
        Header set Cache-Control "no-cache, must-revalidate"
    </FilesMatch>
</IfModule>
```

---

## 📋 Plano de Implementação

### Fase 1: Otimizações Imediatas (Alto Impacto)

1. ✅ **Otimizar queries SQL** - Reduzir de 7 para 1 query
2. ✅ **Habilitar compressão GZIP** - Reduzir tamanho de resposta
3. ✅ **Adicionar cache de estatísticas** - Reduzir carga no banco
4. ✅ **Adicionar headers de cache** - Melhorar carregamento de recursos

**Impacto Esperado:** 
- Server response time: 7.3s → ~2-3s
- FCP: 5.7s → ~2-3s
- LCP: 5.7s → ~2-3s

### Fase 2: Otimizações de Conteúdo

1. ✅ **Otimizar Font Awesome** - Usar apenas ícones necessários
2. ✅ **Corrigir Layout Shifts** - Adicionar min-height no kanban
3. ✅ **Defer scripts não críticos** - Melhorar TTI

**Impacto Esperado:**
- CLS: 0.161 → ~0.05
- TTI: 5.7s → ~4s

### Fase 3: Segurança

1. ✅ **Adicionar CSP header**
2. ✅ **Adicionar outros security headers**
3. ✅ **Configurar HSTS** (quando em HTTPS)

**Impacto Esperado:**
- Best Practices: 77% → ~90%

---

## 🔧 Arquivos a Modificar

1. `/public/dashboard/dashboard_gestor.php` - Otimizar queries
2. `/public/.htaccess` - Adicionar compressão e cache
3. `/public/dashboard/dashboard_gestor.php` - Adicionar cache de stats
4. CSS customizado - Substituir Font Awesome completo

---

## 📊 Métricas Esperadas Após Otimização

| Métrica | Atual | Meta | Melhoria |
|---------|-------|------|----------|
| Performance Score | 50% | 75-85% | +50-70% |
| FCP | 5.7s | <2s | -65% |
| LCP | 5.7s | <2.5s | -56% |
| Speed Index | 8.6s | <3s | -65% |
| TTI | 5.7s | <4s | -30% |
| CLS | 0.161 | <0.1 | -38% |
| Server Response | 7.3s | <1s | -86% |

---

## 🚀 Próximos Passos

1. Implementar otimizações da Fase 1
2. Testar com Lighthouse novamente
3. Ajustar conforme necessário
4. Implementar Fases 2 e 3

---

**Última atualização:** 2026-01-25
