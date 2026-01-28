#!/bin/bash

# Script para adicionar hostname ao /etc/hosts
# Execute com: sudo bash scripts/adicionar_hostname.sh

HOSTNAME="brbandeiras.local"
IP="192.168.1.250"
HOSTS_FILE="/etc/hosts"

echo "Adicionando $HOSTNAME ao $HOSTS_FILE..."

# Verificar se já existe
if grep -q "$IP.*$HOSTNAME" "$HOSTS_FILE" 2>/dev/null; then
    echo "✓ Entrada já existe:"
    grep "$IP.*$HOSTNAME" "$HOSTS_FILE"
    exit 0
fi

# Adicionar entrada
echo "$IP    $HOSTNAME www.$HOSTNAME" >> "$HOSTS_FILE"

if [ $? -eq 0 ]; then
    echo "✓ Entrada adicionada com sucesso!"
    echo ""
    echo "Agora você pode acessar em:"
    echo "  http://$HOSTNAME/"
    echo "  http://www.$HOSTNAME/"
else
    echo "✗ Erro ao adicionar entrada"
    exit 1
fi
