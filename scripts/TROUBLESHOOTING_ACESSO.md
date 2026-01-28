# Troubleshooting - Acesso à Aplicação

## Problema: ERR_ADDRESS_UNREACHABLE

Se você está recebendo `ERR_ADDRESS_UNREACHABLE` ao tentar acessar `http://192.168.1.250/`, siga estes passos:

### 1. Verificar se está na mesma rede

O IP `192.168.1.250` é um IP privado (rede local). Você precisa estar na mesma rede local para acessar.

**Verificar sua rede:**
```bash
# No seu computador (Windows)
ipconfig

# No seu computador (Linux/Mac)
ifconfig
# ou
ip addr show
```

Seu IP deve estar na faixa `192.168.1.x` para acessar o servidor.

### 2. Verificar conectividade de rede

**Teste de ping:**
```bash
ping 192.168.1.250
```

Se o ping não funcionar, há um problema de rede/firewall.

### 3. Verificar Firewall no Servidor

**No servidor (192.168.1.250):**

```bash
# Verificar UFW (Ubuntu/Debian)
ufw status

# Se estiver ativo, permitir HTTP
ufw allow 80/tcp
ufw allow 'Apache'

# Verificar firewalld (CentOS/RHEL)
systemctl status firewalld
firewall-cmd --list-all

# Se estiver ativo, permitir HTTP
firewall-cmd --permanent --add-service=http
firewall-cmd --reload
```

### 4. Verificar se Apache está escutando em todas as interfaces

**No servidor:**
```bash
netstat -tlnp | grep :80
# ou
ss -tlnp | grep :80
```

Deve mostrar algo como:
```
tcp6  0  0 :::80  :::*  LISTEN  apache2
```

Se mostrar apenas `127.0.0.1:80`, o Apache está escutando apenas localmente.

**Corrigir em `/etc/apache2/ports.conf`:**
```apache
Listen 80
# Não usar: Listen 127.0.0.1:80
```

### 5. Verificar Firewall do Roteador

Se você está em uma rede diferente ou o servidor está atrás de um roteador:

1. Verifique se o roteador permite comunicação entre sub-redes
2. Configure port forwarding se necessário
3. Verifique regras de firewall no roteador

### 6. Testar acesso local primeiro

**No servidor mesmo:**
```bash
curl http://192.168.1.250/
# ou
wget -O - http://192.168.1.250/
```

Se funcionar localmente mas não de fora, é problema de firewall/rede.

### 7. Verificar logs do Apache

**No servidor:**
```bash
tail -f /var/log/apache2/access.log
```

Tente acessar do seu computador e veja se aparece alguma requisição nos logs.

### 8. Solução Temporária: Acesso via SSH Tunnel

Se não conseguir resolver o firewall, você pode criar um túnel SSH:

```bash
# No seu computador local
ssh -L 8080:localhost:80 root@192.168.1.250

# Depois acesse:
http://localhost:8080/
```

### 9. Verificar Configuração do Virtual Host

**No servidor:**
```bash
cat /etc/apache2/sites-enabled/brbandeiras.conf
```

Deve ter:
```apache
<VirtualHost *:80>
    ServerName 192.168.1.250
    DocumentRoot /var/www/brbandeiras/public
    ...
</VirtualHost>
```

### 10. Comandos de Diagnóstico Completo

Execute no servidor:
```bash
echo "=== DIAGNÓSTICO DE REDE ==="
echo ""
echo "1. IPs do servidor:"
ip addr show | grep 'inet ' | grep -v '127.0.0.1'
echo ""
echo "2. Apache escutando:"
netstat -tlnp | grep :80
echo ""
echo "3. Firewall status:"
ufw status 2>/dev/null || echo "UFW não configurado"
echo ""
echo "4. Teste local:"
curl -I http://localhost/ 2>&1 | head -1
echo ""
echo "5. Teste via IP:"
curl -I http://192.168.1.250/ 2>&1 | head -1
```

## Soluções Comuns

### Solução 1: Desabilitar Firewall Temporariamente (TESTE)

```bash
# UFW
ufw disable

# firewalld
systemctl stop firewalld
```

**⚠️ ATENÇÃO:** Apenas para teste! Reative o firewall depois com regras adequadas.

### Solução 2: Permitir Porta 80 no Firewall

```bash
# UFW
ufw allow 80/tcp
ufw allow 'Apache'

# firewalld
firewall-cmd --permanent --add-service=http
firewall-cmd --reload
```

### Solução 3: Verificar Roteamento

Se você está em uma rede diferente (ex: `192.168.0.x` tentando acessar `192.168.1.250`):

1. Configure uma rota estática no seu computador
2. Ou use um VPN
3. Ou configure o roteador para permitir comunicação entre sub-redes

## Verificação Final

Após aplicar as correções:

```bash
# No servidor
systemctl restart apache2
netstat -tlnp | grep :80

# No seu computador
ping 192.168.1.250
curl -I http://192.168.1.250/
```

Se tudo estiver OK, você deve conseguir acessar `http://192.168.1.250/` no navegador.
