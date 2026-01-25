#!/bin/bash
# Script para configurar Apache do AMPPS para usar PHP-FPM do Homebrew

echo "🔧 Configurando Apache para usar PHP-FPM do Homebrew..."
echo ""

# Verificar se PHP-FPM está instalado
if [ ! -f "/opt/homebrew/sbin/php-fpm" ] && [ ! -f "/opt/homebrew/bin/php-fpm" ]; then
    echo "❌ PHP-FPM não encontrado"
    echo "   Instale com: brew install php"
    exit 1
fi

PHP_FPM_PATH=$(which php-fpm 2>/dev/null || find /opt/homebrew -name "php-fpm" -type f 2>/dev/null | head -1)
if [ -z "$PHP_FPM_PATH" ]; then
    echo "❌ PHP-FPM não encontrado"
    exit 1
fi

echo "✅ PHP-FPM encontrado: $PHP_FPM_PATH"
echo ""

# Configurar PHP-FPM para escutar na porta 9000
PHP_FPM_CONF="/opt/homebrew/etc/php/8.5/php-fpm.d/www.conf"
if [ -f "$PHP_FPM_CONF" ]; then
    echo "📝 Configurando PHP-FPM..."
    # Verificar se já está configurado
    if ! grep -q "^listen = 127.0.0.1:9000" "$PHP_FPM_CONF"; then
        sudo sed -i.bak 's/^listen = .*/listen = 127.0.0.1:9000/' "$PHP_FPM_CONF"
        echo "✅ PHP-FPM configurado para porta 9000"
    else
        echo "ℹ️  PHP-FPM já está configurado"
    fi
else
    echo "⚠️  Arquivo de configuração não encontrado: $PHP_FPM_CONF"
fi

# Iniciar PHP-FPM
echo ""
echo "🚀 Iniciando PHP-FPM..."
brew services start php 2>&1 | tail -3 || {
    echo "Tentando iniciar manualmente..."
    sudo $PHP_FPM_PATH -D
}

sleep 2

# Verificar se está rodando
if lsof -i :9000 > /dev/null 2>&1; then
    echo "✅ PHP-FPM está rodando na porta 9000"
else
    echo "❌ PHP-FPM não está rodando. Tente iniciar manualmente:"
    echo "   brew services start php"
    echo "   OU"
    echo "   sudo $PHP_FPM_PATH -D"
fi

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "PRÓXIMO PASSO: Modificar httpd.conf do AMPPS"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "1. Abra o painel do AMPPS"
echo "2. Vá em Apache > Config > httpd.conf"
echo "3. Comente estas linhas (adicione # no início):"
echo "   LoadModule php_module /Applications/AMPPS/apps/php82/lib/libphp8.so"
echo "   PHPIniDir \"/Applications/AMPPS/apps/php82/etc\""
echo ""
echo "4. Adicione estas linhas:"
echo "   LoadModule proxy_module modules/mod_proxy.so"
echo "   LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so"
echo "   <FilesMatch \.php$>"
echo "       SetHandler \"proxy:fcgi://127.0.0.1:9000\""
echo "   </FilesMatch>"
echo ""
echo "5. Salve e reinicie o Apache"
echo ""
