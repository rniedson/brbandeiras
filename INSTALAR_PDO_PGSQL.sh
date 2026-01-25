#!/bin/bash
# Script para configurar PHP do Homebrew no AMPPS
# Execute com: bash INSTALAR_PDO_PGSQL.sh

echo "🔧 Configurando AMPPS para usar PHP do Homebrew..."
echo ""

# Verificar se PHP do Homebrew está instalado
if [ ! -f "/opt/homebrew/bin/php" ]; then
    echo "❌ PHP do Homebrew não encontrado em /opt/homebrew/bin/php"
    echo "   Instale com: brew install php"
    exit 1
fi

# Verificar se tem pdo_pgsql
if ! /opt/homebrew/bin/php -m | grep -q pdo_pgsql; then
    echo "❌ PHP do Homebrew não tem pdo_pgsql instalado"
    echo "   Instale com: brew install php"
    exit 1
fi

echo "✅ PHP do Homebrew encontrado e tem pdo_pgsql"
echo ""

# Fazer backup do PHP original
if [ ! -f "/Applications/AMPPS/apps/php82/bin/php.original" ]; then
    echo "📦 Fazendo backup do PHP original do AMPPS..."
    sudo mv /Applications/AMPPS/apps/php82/bin/php /Applications/AMPPS/apps/php82/bin/php.original
    echo "✅ Backup criado: /Applications/AMPPS/apps/php82/bin/php.original"
else
    echo "ℹ️  Backup já existe, pulando..."
fi

# Criar symlink
echo "🔗 Criando symlink para PHP do Homebrew..."
sudo ln -sf /opt/homebrew/bin/php /Applications/AMPPS/apps/php82/bin/php
echo "✅ Symlink criado"
echo ""

# Comentar extensões problemáticas no php.ini do AMPPS
echo "📝 Ajustando php.ini do AMPPS..."
sudo sed -i.bak 's/^extension=pdo_pgsql.so/;extension=pdo_pgsql.so/' /Applications/AMPPS/apps/php82/etc/php.ini
sudo sed -i.bak 's/^extension=pgsql.so/;extension=pgsql.so/' /Applications/AMPPS/apps/php82/etc/php.ini
echo "✅ php.ini ajustado"
echo ""

# Testar
echo "🧪 Testando configuração..."
if /Applications/AMPPS/apps/php82/bin/php -m | grep -q pdo_pgsql; then
    echo "✅ SUCESSO! Extensão pdo_pgsql está disponível"
    echo ""
    echo "Testando conexão com banco de dados..."
    /Applications/AMPPS/apps/php82/bin/php -r "require_once '/Applications/AMPPS/www/brbandeiras/app/config.php'; echo '✅ Conexão estabelecida com sucesso!\n';" 2>&1 | grep -v "PHP Warning"
else
    echo "❌ ERRO: Extensão pdo_pgsql ainda não está disponível"
    echo "   Verifique se o symlink foi criado corretamente"
    exit 1
fi

echo ""
echo "🎉 Configuração concluída!"
echo ""
echo "⚠️  IMPORTANTE: Reinicie o Apache no painel do AMPPS para aplicar as mudanças!"
