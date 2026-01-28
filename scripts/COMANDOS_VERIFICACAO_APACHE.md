# Comandos para Verificar o Apache

## Verificação Rápida

### 1. Status do Serviço
```bash
systemctl status apache2
# ou
systemctl is-active apache2
```

### 2. Verificar Sintaxe da Configuração
```bash
apache2ctl -t
```

### 3. Ver Virtual Hosts Configurados
```bash
apache2ctl -S
```

### 4. Verificar Porta de Escuta
```bash
netstat -tlnp | grep :80
# ou
ss -tlnp | grep :80
```

### 5. Verificar Módulos Carregados
```bash
apache2ctl -M
# Verificar módulos específicos:
apache2ctl -M | grep -E 'headers|rewrite|php'
```

### 6. Testar Resposta HTTP
```bash
# Via curl (se disponível)
curl -I http://192.168.1.250/

# Via PHP
php -r "\$ch = curl_init('http://192.168.1.250/'); curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true); \$response = curl_exec(\$ch); \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE); echo 'HTTP ' . \$httpCode . PHP_EOL; curl_close(\$ch);"
```

### 7. Ver Logs de Erro
```bash
# Log geral
tail -f /var/log/apache2/error.log

# Log específico do virtual host
tail -f /var/log/apache2/brbandeiras_error.log
```

### 8. Ver Logs de Acesso
```bash
tail -f /var/log/apache2/access.log
# ou
tail -f /var/log/apache2/brbandeiras_access.log
```

## Comandos de Manutenção

### Reiniciar Apache
```bash
systemctl restart apache2
```

### Recarregar Configuração (sem derrubar conexões)
```bash
systemctl reload apache2
```

### Habilitar/Desabilitar Site
```bash
# Habilitar
a2ensite brbandeiras.conf
systemctl reload apache2

# Desabilitar
a2dissite brbandeiras.conf
systemctl reload apache2
```

### Habilitar/Desabilitar Módulo
```bash
# Habilitar
a2enmod headers
systemctl restart apache2

# Desabilitar
a2dismod headers
systemctl restart apache2
```

## Script de Verificação Completa

Execute o script de verificação:
```bash
bash scripts/verificar_apache.sh
```

Ou no servidor remoto:
```bash
ssh root@192.168.1.250
bash /tmp/verificar_apache.sh
```

## Acesso à Aplicação

A aplicação está disponível em:
- **Via IP**: http://192.168.1.250/
- **Via hostname** (se configurado): http://brbandeiras.local/

## Troubleshooting

### Erro 500 Internal Server Error
1. Verificar logs: `tail -20 /var/log/apache2/error.log`
2. Verificar permissões: `ls -la /var/www/brbandeiras/public/`
3. Verificar PHP: `php -v` e `php -m | grep pdo_pgsql`

### Erro 403 Forbidden
1. Verificar permissões do diretório: `ls -ld /var/www/brbandeiras/public`
2. Verificar configuração do virtual host
3. Verificar `.htaccess` se existe

### Erro 404 Not Found
1. Verificar DocumentRoot no virtual host
2. Verificar se o arquivo `index.php` existe
3. Verificar se o virtual host está habilitado: `apache2ctl -S`

### Módulo não encontrado
1. Verificar se está instalado: `dpkg -l | grep apache2`
2. Habilitar módulo: `a2enmod nome_do_modulo`
3. Reiniciar Apache: `systemctl restart apache2`
