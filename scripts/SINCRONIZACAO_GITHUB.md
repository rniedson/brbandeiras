# Sincronização com GitHub - Servidor Remoto

## 📋 Status Atual

Os arquivos do servidor foram sincronizados com os arquivos locais (que contêm as correções).

## 🔄 Métodos de Sincronização

### Método 1: Script Automatizado (Recomendado)

Execute o script de sincronização:

```bash
cd /Applications/AMPPS/www/brbandeiras
bash scripts/sincronizar_servidor.sh
```

Este script:
- ✅ Faz backup do `.env` no servidor
- ✅ Cria pacote dos arquivos locais
- ✅ Transfere para o servidor
- ✅ Extrai preservando o `.env`
- ✅ Configura permissões

### Método 2: Git Pull (Se repositório público ou com credenciais)

**No servidor:**

```bash
ssh root@192.168.1.250
cd /var/www/brbandeiras

# Configurar Git (se necessário)
git config --global --add safe.directory /var/www/brbandeiras
git remote add origin https://github.com/rniedson/brbandeiras.git

# Fazer pull
git pull origin main
# ou
git pull origin master
```

**⚠️ Nota:** Se o repositório for privado, você precisará configurar autenticação:
- Token de acesso pessoal do GitHub
- Ou chave SSH

### Método 3: Manual via tar (Como foi feito)

```bash
# No seu computador
cd /Applications/AMPPS/www/brbandeiras
tar --exclude='.git' --exclude='node_modules' --exclude='.env' \
    -czf /tmp/brbandeiras_sync.tar.gz .

# Transferir
scp /tmp/brbandeiras_sync.tar.gz root@192.168.1.250:/tmp/

# No servidor
cd /var/www/brbandeiras
tar -xzf /tmp/brbandeiras_sync.tar.gz --overwrite --exclude='.env'
chown -R www-data:www-data .
```

## 🔐 Configurar Git com Autenticação (Repositório Privado)

### Opção A: Token de Acesso Pessoal

```bash
# No servidor
cd /var/www/brbandeiras
git remote set-url origin https://SEU_TOKEN@github.com/rniedson/brbandeiras.git
git pull origin main
```

### Opção B: Chave SSH

```bash
# Gerar chave SSH no servidor (se não tiver)
ssh-keygen -t ed25519 -C "servidor@brbandeiras"

# Adicionar chave pública ao GitHub
cat ~/.ssh/id_ed25519.pub
# Copiar e adicionar em: GitHub > Settings > SSH and GPG keys

# Configurar remote com SSH
git remote set-url origin git@github.com:rniedson/brbandeiras.git
git pull origin main
```

## 📝 Arquivos Preservados Durante Sincronização

Os seguintes arquivos são **preservados** no servidor durante a sincronização:

- `.env` - Configurações do ambiente
- `storage/logs/*` - Logs da aplicação
- `storage/cache/*` - Cache da aplicação
- `uploads/*` - Arquivos enviados pelos usuários

## ✅ Verificação Pós-Sincronização

Após sincronizar, verifique:

```bash
# No servidor
cd /var/www/brbandeiras

# Verificar arquivos atualizados
ls -la public/dashboard/dashboard.php
ls -la public/dashboard/dashboard_gestor.php

# Verificar .env preservado
cat .env

# Verificar permissões
ls -ld public/dashboard/
```

## 🔄 Sincronização Futura

Para manter o servidor atualizado:

1. **Faça commit e push das alterações locais:**
   ```bash
   cd /Applications/AMPPS/www/brbandeiras
   git add .
   git commit -m "Descrição das alterações"
   git push origin main
   ```

2. **Sincronize com o servidor:**
   ```bash
   bash scripts/sincronizar_servidor.sh
   ```

## 📚 Comandos Úteis

### Ver diferenças entre local e servidor

```bash
# Comparar arquivo específico
diff public/dashboard/dashboard.php \
  <(ssh root@192.168.1.250 "cat /var/www/brbandeiras/public/dashboard/dashboard.php")
```

### Verificar último commit no servidor

```bash
ssh root@192.168.1.250 "cd /var/www/brbandeiras && git log --oneline -1"
```

### Forçar atualização completa

```bash
# No servidor
cd /var/www/brbandeiras
git fetch origin
git reset --hard origin/main
# ou
git reset --hard origin/master
```

## ⚠️ Avisos Importantes

1. **Sempre faça backup** antes de sincronizar
2. **Preserve o `.env`** - contém credenciais importantes
3. **Verifique permissões** após sincronização
4. **Teste a aplicação** após sincronizar

## 🎯 Resumo

- ✅ Arquivos sincronizados com sucesso
- ✅ Correções aplicadas (dashboard.php e dashboard_gestor.php)
- ✅ `.env` preservado
- ✅ Script de sincronização criado para uso futuro
