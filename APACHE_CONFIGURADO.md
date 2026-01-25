# ✅ Apache Configurado para usar PHP-FPM do Homebrew

## O que foi feito:

1. ✅ **PHP-FPM do Homebrew**: Iniciado e rodando na porta 9000
2. ✅ **httpd.conf modificado**: 
   - Comentado módulo PHP do AMPPS
   - Habilitado módulos proxy e proxy_fcgi
   - Configurado FilesMatch para usar PHP-FPM

## ⚠️ AÇÃO NECESSÁRIA AGORA:

**REINICIE O APACHE NO PAINEL DO AMPPS!**

1. Abra o painel do AMPPS
2. Clique em **"Stop"** no Apache
3. Aguarde 5 segundos
4. Clique em **"Start"** no Apache

## Teste:

Após reiniciar, acesse:
```
http://localhost/brbandeiras/public/
```

O sistema deve funcionar agora! 🎉

## Verificação:

Se quiser verificar se está tudo certo:

```bash
# Verificar se PHP-FPM está rodando
lsof -i :9000

# Verificar configuração do Apache
grep -A 2 "FilesMatch.*php" /Applications/AMPPS/apps/apache/etc/httpd.conf
```

## Mudanças no httpd.conf:

**Linhas comentadas:**
```apache
#LoadModule php_module /Applications/AMPPS/apps/php82/lib/libphp8.so
#PHPIniDir "/Applications/AMPPS/apps/php82/etc"
```

**Linhas adicionadas:**
```apache
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so

<FilesMatch \.php$>
    SetHandler "proxy:fcgi://127.0.0.1:9000"
</FilesMatch>
```
