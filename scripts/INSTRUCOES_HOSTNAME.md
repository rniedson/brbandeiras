# Configuração de Hostname Amigável

## O que foi configurado

No servidor foi configurado o hostname `brbandeiras.local` para acesso à aplicação.

## Configuração no Servidor

✅ **Apache Virtual Host** configurado para aceitar:
- `brbandeiras.local`
- `www.brbandeiras.local`
- `192.168.1.250` (IP direto)

✅ **/etc/hosts do servidor** atualizado

## Configuração no Seu Computador

Para acessar usando o hostname amigável no seu computador, você precisa adicionar uma entrada no arquivo `/etc/hosts`.

### macOS / Linux

**Opção 1: Executar o script automático**
```bash
cd /Applications/AMPPS/www/brbandeiras
sudo bash scripts/configurar_hostname_local.sh
```

**Opção 2: Adicionar manualmente**
```bash
sudo nano /etc/hosts
```

Adicione esta linha:
```
192.168.1.250    brbandeiras.local www.brbandeiras.local
```

Salve e feche o arquivo.

### Windows

1. Abra o Bloco de Notas como **Administrador**
2. Abra o arquivo: `C:\Windows\System32\drivers\etc\hosts`
3. Adicione esta linha no final:
```
192.168.1.250    brbandeiras.local www.brbandeiras.local
```
4. Salve o arquivo

## Acesso à Aplicação

Após configurar o `/etc/hosts`, você pode acessar a aplicação de três formas:

1. **Hostname principal:**
   ```
   http://brbandeiras.local/
   ```

2. **Hostname com www:**
   ```
   http://www.brbandeiras.local/
   ```

3. **IP direto (sempre funciona):**
   ```
   http://192.168.1.250/
   ```

## Verificação

Teste se está funcionando:

```bash
# Teste de ping
ping brbandeiras.local

# Teste HTTP (se tiver curl)
curl -I http://brbandeiras.local/
```

## Remover Configuração

Se quiser remover a entrada do `/etc/hosts`:

**macOS/Linux:**
```bash
sudo nano /etc/hosts
# Remova a linha com brbandeiras.local
```

**Windows:**
1. Abra `C:\Windows\System32\drivers\etc\hosts` como Administrador
2. Remova a linha com brbandeiras.local
3. Salve

## Troubleshooting

### O hostname não funciona

1. Verifique se a entrada está no `/etc/hosts`:
   ```bash
   cat /etc/hosts | grep brbandeiras
   ```

2. Limpe o cache DNS do navegador:
   - Chrome/Edge: Feche e abra novamente
   - Firefox: Limpe cache (Ctrl+Shift+Delete)

3. Teste ping:
   ```bash
   ping brbandeiras.local
   ```

4. Se ping funcionar mas navegador não, tente:
   - `http://brbandeiras.local/` (com barra no final)
   - Limpar cache do navegador
   - Testar em modo anônimo/privado

### Ainda não funciona

Use o IP direto que sempre funciona:
```
http://192.168.1.250/
```
