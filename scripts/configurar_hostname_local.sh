#!/bin/bash

# Script para configurar hostname local no computador do usuário
# Adiciona entrada no /etc/hosts para acesso amigável

HOSTNAME="brbandeiras.local"
IP="192.168.1.250"
HOSTS_FILE="/etc/hosts"

echo "╔════════════════════════════════════════════════════════════╗"
echo "║     Configuração de Hostname Local - BR Bandeiras        ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Verificar se já existe
if grep -q "$IP.*$HOSTNAME" "$HOSTS_FILE" 2>/dev/null; then
    echo "✓ Entrada já existe no $HOSTS_FILE:"
    grep "$IP.*$HOSTNAME" "$HOSTS_FILE"
    echo ""
    echo "Você pode acessar a aplicação em:"
    echo "  http://$HOSTNAME/"
    echo "  http://www.$HOSTNAME/"
    echo ""
    exit 0
fi

# Verificar se precisa de sudo
if [ ! -w "$HOSTS_FILE" ]; then
    echo "⚠️  Precisa de permissões de administrador para editar $HOSTS_FILE"
    echo ""
    echo "Execute este comando manualmente:"
    echo ""
    echo "sudo bash -c 'echo \"$IP    $HOSTNAME www.$HOSTNAME\" >> $HOSTS_FILE'"
    echo ""
    echo "Ou adicione manualmente ao arquivo $HOSTS_FILE:"
    echo "$IP    $HOSTNAME www.$HOSTNAME"
    echo ""
    exit 1
fi

# Adicionar entrada
echo "$IP    $HOSTNAME www.$HOSTNAME" >> "$HOSTS_FILE"

if [ $? -eq 0 ]; then
    echo "✓ Entrada adicionada com sucesso!"
    echo ""
    echo "Agora você pode acessar a aplicação em:"
    echo "  http://$HOSTNAME/"
    echo "  http://www.$HOSTNAME/"
    echo "  http://$IP/ (ainda funciona)"
    echo ""
else
    echo "✗ Erro ao adicionar entrada"
    exit 1
fi
