#!/bin/bash

# Script de Verificação do Apache
# Verifica se o Apache está funcionando corretamente

echo "╔════════════════════════════════════════════════════════════╗"
echo "║          VERIFICAÇÃO DO APACHE - BR BANDEIRAS            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Verificar status do serviço
echo "1. Status do Serviço Apache:"
if systemctl is-active --quiet apache2; then
    echo -e "   ${GREEN}✓ Apache está rodando${NC}"
    systemctl status apache2 --no-pager | grep -E "Active:|Main PID:" | head -2
else
    echo -e "   ${RED}✗ Apache NÃO está rodando${NC}"
    exit 1
fi
echo ""

# 2. Verificar sintaxe da configuração
echo "2. Verificação de Sintaxe:"
if apache2ctl -t > /dev/null 2>&1; then
    echo -e "   ${GREEN}✓ Sintaxe da configuração está OK${NC}"
else
    echo -e "   ${RED}✗ Erro na sintaxe da configuração${NC}"
    apache2ctl -t
fi
echo ""

# 3. Verificar se está escutando na porta 80
echo "3. Porta de Escuta:"
if netstat -tlnp 2>/dev/null | grep -q ":80.*apache2" || ss -tlnp 2>/dev/null | grep -q ":80.*apache2"; then
    echo -e "   ${GREEN}✓ Apache está escutando na porta 80${NC}"
    netstat -tlnp 2>/dev/null | grep ":80.*apache2" || ss -tlnp 2>/dev/null | grep ":80.*apache2"
else
    echo -e "   ${RED}✗ Apache NÃO está escutando na porta 80${NC}"
fi
echo ""

# 4. Verificar Virtual Host
echo "4. Virtual Host Configurado:"
if [ -f /etc/apache2/sites-enabled/brbandeiras.conf ]; then
    echo -e "   ${GREEN}✓ Virtual host brbandeiras.conf está habilitado${NC}"
    echo "   Configuração:"
    cat /etc/apache2/sites-enabled/brbandeiras.conf | grep -E "ServerName|DocumentRoot" | sed 's/^/      /'
else
    echo -e "   ${YELLOW}⚠ Virtual host não encontrado em sites-enabled${NC}"
fi
echo ""

# 5. Verificar módulos necessários
echo "5. Módulos Apache:"
MODULES=("rewrite" "php" "headers")
for mod in "${MODULES[@]}"; do
    if apache2ctl -M 2>/dev/null | grep -q "$mod"; then
        echo -e "   ${GREEN}✓ Módulo $mod está carregado${NC}"
    else
        echo -e "   ${YELLOW}⚠ Módulo $mod não está carregado${NC}"
    fi
done
echo ""

# 6. Verificar arquivo index.php
echo "6. Arquivo da Aplicação:"
if [ -f /var/www/brbandeiras/public/index.php ]; then
    echo -e "   ${GREEN}✓ index.php existe${NC}"
    echo "   Permissões: $(ls -l /var/www/brbandeiras/public/index.php | awk '{print $1, $3, $4}')"
else
    echo -e "   ${RED}✗ index.php NÃO existe${NC}"
fi
echo ""

# 7. Testar resposta HTTP
echo "7. Teste de Resposta HTTP:"
if command -v curl >/dev/null 2>&1; then
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/brbandeiras/public/ 2>/dev/null)
    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "   ${GREEN}✓ HTTP 200 - Aplicação respondendo corretamente${NC}"
    elif [ "$HTTP_CODE" = "302" ] || [ "$HTTP_CODE" = "301" ]; then
        echo -e "   ${GREEN}✓ HTTP $HTTP_CODE - Redirecionamento (normal)${NC}"
    elif [ "$HTTP_CODE" = "403" ]; then
        echo -e "   ${YELLOW}⚠ HTTP 403 - Acesso negado (verificar permissões)${NC}"
    elif [ "$HTTP_CODE" = "404" ]; then
        echo -e "   ${RED}✗ HTTP 404 - Página não encontrada${NC}"
    else
        echo -e "   ${YELLOW}⚠ HTTP $HTTP_CODE - Resposta inesperada${NC}"
    fi
elif command -v wget >/dev/null 2>&1; then
    if wget -q -O /dev/null http://localhost/brbandeiras/public/ 2>/dev/null; then
        echo -e "   ${GREEN}✓ Aplicação respondendo (via wget)${NC}"
    else
        echo -e "   ${RED}✗ Erro ao acessar aplicação${NC}"
    fi
else
    echo -e "   ${YELLOW}⚠ curl/wget não disponível para teste HTTP${NC}"
fi
echo ""

# 8. Verificar logs de erro recentes
echo "8. Logs de Erro (últimas 5 linhas):"
if [ -f /var/log/apache2/error.log ]; then
    ERROR_COUNT=$(tail -100 /var/log/apache2/error.log | grep -i "error\|fatal\|crit" | wc -l)
    if [ "$ERROR_COUNT" -eq 0 ]; then
        echo -e "   ${GREEN}✓ Nenhum erro recente nos logs${NC}"
    else
        echo -e "   ${YELLOW}⚠ $ERROR_COUNT erro(s) encontrado(s) nos últimos logs${NC}"
        tail -5 /var/log/apache2/error.log | grep -i "error\|fatal\|crit" | head -3 | sed 's/^/      /'
    fi
else
    echo -e "   ${YELLOW}⚠ Arquivo de log não encontrado${NC}"
fi
echo ""

# 9. Verificar permissões do diretório
echo "9. Permissões do Diretório:"
DIR_PERMS=$(stat -c "%a" /var/www/brbandeiras/public 2>/dev/null || stat -f "%OLp" /var/www/brbandeiras/public 2>/dev/null)
DIR_OWNER=$(stat -c "%U:%G" /var/www/brbandeiras/public 2>/dev/null || stat -f "%Su:%Sg" /var/www/brbandeiras/public 2>/dev/null)
if [ "$DIR_PERMS" = "755" ] || [ "$DIR_PERMS" = "775" ]; then
    echo -e "   ${GREEN}✓ Permissões OK ($DIR_PERMS)${NC}"
else
    echo -e "   ${YELLOW}⚠ Permissões: $DIR_PERMS (recomendado: 755 ou 775)${NC}"
fi
echo "   Proprietário: $DIR_OWNER"
echo ""

# Resumo final
echo "╔════════════════════════════════════════════════════════════╗"
echo "║                    RESUMO FINAL                           ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Para acessar a aplicação:"
echo "  http://192.168.1.250/brbandeiras/public/"
echo ""
echo "Comandos úteis:"
echo "  - Reiniciar Apache: systemctl restart apache2"
echo "  - Ver logs: tail -f /var/log/apache2/error.log"
echo "  - Ver logs de acesso: tail -f /var/log/apache2/access.log"
echo "  - Verificar configuração: apache2ctl -S"
echo ""
