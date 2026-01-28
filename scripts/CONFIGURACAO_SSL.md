# Configuração SSL/HTTPS - BR Bandeiras

## ✅ Certificado SSL Instalado

Foi configurado um certificado SSL auto-assinado para desenvolvimento local.

## 📋 O que foi configurado

1. ✅ Módulo SSL do Apache habilitado
2. ✅ Certificado auto-assinado criado (`/etc/apache2/ssl/brbandeiras.crt`)
3. ✅ Chave privada criada (`/etc/apache2/ssl/brbandeiras.key`)
4. ✅ Virtual Host HTTPS configurado (porta 443)
5. ✅ Redirecionamento HTTP → HTTPS configurado

## 🔒 Acessos Disponíveis

### HTTPS (Seguro)
```
https://brbandeiras.local/
https://www.brbandeiras.local/
https://192.168.1.250/
```

### HTTP (Redireciona para HTTPS)
```
http://brbandeiras.local/ → redireciona para HTTPS
http://192.168.1.250/ → redireciona para HTTPS
```

## ⚠️ Aviso sobre Certificado Auto-Assinado

Como o certificado é **auto-assinado** (não emitido por uma autoridade certificadora), seu navegador mostrará um aviso de segurança ao acessar pela primeira vez.

### Como Aceitar o Certificado

**Chrome/Edge:**
1. Clique em "Avançado" ou "Advanced"
2. Clique em "Prosseguir para brbandeiras.local (não seguro)" ou "Proceed to brbandeiras.local (unsafe)"

**Firefox:**
1. Clique em "Avançado" ou "Advanced"
2. Clique em "Aceitar o Risco e Continuar" ou "Accept the Risk and Continue"

**Safari:**
1. Clique em "Mostrar Detalhes" ou "Show Details"
2. Clique em "Visitar este site" ou "Visit this website"

Após aceitar uma vez, o navegador lembrará da escolha para este site.

## 🔧 Configuração Técnica

### Localização dos Certificados
```
/etc/apache2/ssl/brbandeiras.crt  (Certificado)
/etc/apache2/ssl/brbandeiras.key  (Chave privada)
```

### Virtual Hosts Configurados
- `/etc/apache2/sites-available/brbandeiras.conf` (HTTP - porta 80)
- `/etc/apache2/sites-available/brbandeiras-ssl.conf` (HTTPS - porta 443)

### Validade do Certificado
O certificado é válido por **365 dias** (1 ano).

## 🔄 Renovar Certificado

Para renovar o certificado auto-assinado:

```bash
ssh root@192.168.1.250
cd /etc/apache2/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout brbandeiras.key -out brbandeiras.crt \
  -subj '/C=BR/ST=Goias/L=Goiania/O=BR Bandeiras/CN=brbandeiras.local'
systemctl restart apache2
```

## 🌐 Certificado Válido para Produção

Se você quiser usar um certificado válido (Let's Encrypt) em produção:

### Pré-requisitos
- Domínio público apontando para o servidor
- Porta 80 e 443 acessíveis externamente

### Instalação Let's Encrypt

```bash
# Instalar certbot
apt-get update
apt-get install -y certbot python3-certbot-apache

# Obter certificado
certbot --apache -d brbandeiras.com.br -d www.brbandeiras.com.br

# Renovação automática (já configurado)
certbot renew --dry-run
```

## 🧪 Testar SSL

### Teste Local (no servidor)
```bash
curl -k https://brbandeiras.local/
```

### Verificar Certificado
```bash
openssl s_client -connect brbandeiras.local:443 -servername brbandeiras.local
```

### Verificar Porta 443
```bash
netstat -tlnp | grep :443
```

## 📝 Logs SSL

- Erros SSL: `/var/log/apache2/brbandeiras_ssl_error.log`
- Acesso SSL: `/var/log/apache2/brbandeiras_ssl_access.log`

## 🔍 Troubleshooting

### Apache não inicia após configurar SSL
```bash
# Verificar sintaxe
apache2ctl -t

# Verificar logs
tail -20 /var/log/apache2/error.log

# Verificar se módulo SSL está carregado
apache2ctl -M | grep ssl
```

### Certificado não aceito pelo navegador
- Certificado auto-assinado sempre mostra aviso
- Aceite manualmente uma vez
- Para produção, use Let's Encrypt

### Porta 443 não responde
```bash
# Verificar se está escutando
netstat -tlnp | grep :443

# Verificar firewall
ufw status
# ou
iptables -L -n | grep 443
```

## ✅ Verificação Final

Execute para verificar se tudo está OK:

```bash
# Status do Apache
systemctl status apache2

# Módulo SSL carregado
apache2ctl -M | grep ssl

# Porta 443 escutando
netstat -tlnp | grep :443

# Teste HTTPS
curl -k -I https://brbandeiras.local/
```

## 📚 Referências

- [Apache SSL Configuration](https://httpd.apache.org/docs/2.4/ssl/)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [OpenSSL Documentation](https://www.openssl.org/docs/)
