#!/bin/bash

# Script para adicionar brbandeiras.local ao /etc/hosts no Mac
# Execute com: sudo bash scripts/adicionar_hosts_mac.sh

HOSTS_FILE="/etc/hosts"
IP="192.168.1.250"
HOSTNAME="brbandeiras.local"

echo "╔════════════════════════════════════════════════════════════╗"
echo "║     Adicionando brbandeiras.local ao /etc/hosts           ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Verificar se já existe
if grep -q "$IP.*$HOSTNAME" "$HOSTS_FILE" 2>/dev/null; then
    echo "✓ Entrada já existe no $HOSTS_FILE:"
    grep "$IP.*$HOSTNAME" "$HOSTS_FILE"
    echo ""
    echo "Teste com: ping $HOSTNAME"
    exit 0
fi

# Verificar permissões
if [ ! -w "$HOSTS_FILE" ]; then
    echo "✗ Erro: Precisa de permissões de administrador"
    echo ""
    echo "Execute com sudo:"
    echo "  sudo bash $0"
    exit 1
fi

# Adicionar entrada
echo "$IP    $HOSTNAME www.$HOSTNAME" >> "$HOSTS_FILE"

if [ $? -eq 0 ]; then
    echo "✓ Entrada adicionada com sucesso!"
    echo ""
    echo "Linha adicionada:"
    echo "  $IP    $HOSTNAME www.$HOSTNAME"
    echo ""
    echo "Teste agora:"
    echo "  ping -c 2 $HOSTNAME"
    echo "  curl -I http://$HOSTNAME/"
    echo ""
else
    echo "✗ Erro ao adicionar entrada"
    exit 1
fi
