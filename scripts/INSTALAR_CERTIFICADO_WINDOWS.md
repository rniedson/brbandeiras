# Instalar Certificado SSL no Windows

## 📋 Pré-requisitos

1. Baixar o certificado do servidor
2. Ter acesso de administrador no Windows

## 📥 Passo 1: Baixar o Certificado

### Opção A: Via SCP (se tiver acesso SSH)

```powershell
# No PowerShell ou CMD
scp root@192.168.1.250:/etc/apache2/ssl/brbandeiras.crt C:\Users\SeuUsuario\Downloads\
```

### Opção B: Via Navegador

1. Acesse `https://brbandeiras.local/` no Chrome/Edge
2. Clique no cadeado na barra de endereços
3. Clique em "Certificado" ou "Certificate"
4. Na aba "Detalhes" ou "Details", clique em "Copiar para arquivo" ou "Copy to File"
5. Siga o assistente e salve como `brbandeiras.crt`

### Opção C: Via WinSCP ou FileZilla

Conecte-se ao servidor e baixe o arquivo:
```
/etc/apache2/ssl/brbandeiras.crt
```

## 🔐 Passo 2: Instalar o Certificado

### Método 1: Via Interface Gráfica (Recomendado)

1. **Abra o arquivo do certificado:**
   - Navegue até onde salvou o `brbandeiras.crt`
   - Clique duas vezes no arquivo

2. **Assistente de Importação:**
   - Clique em **"Instalar certificado"** ou **"Install Certificate"**
   - Selecione **"Usuário atual"** ou **"Current User"** (recomendado)
   - Clique em **"Avançar"** ou **"Next"**

3. **Escolher o repositório:**
   - Selecione **"Colocar todos os certificados no seguinte repositório"**
   - Clique em **"Procurar"** ou **"Browse"**
   - Selecione **"Autoridades de Certificação Raiz Confiáveis"** ou **"Trusted Root Certification Authorities"**
   - Clique em **"OK"** → **"Avançar"** → **"Concluir"**

4. **Aviso de Segurança:**
   - Uma janela de aviso aparecerá
   - Clique em **"Sim"** ou **"Yes"** para confirmar

5. **Concluído:**
   - Mensagem "A importação foi bem-sucedida" aparecerá
   - Clique em **"OK"**

### Método 2: Via PowerShell (Como Administrador)

```powershell
# Abra PowerShell como Administrador
# Clique com botão direito no PowerShell → "Executar como administrador"

# Importar certificado
$certPath = "C:\Users\SeuUsuario\Downloads\brbandeiras.crt"
$store = New-Object System.Security.Cryptography.X509Certificates.X509Store("Root", "CurrentUser")
$store.Open("ReadWrite")
$cert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($certPath)
$store.Add($cert)
$store.Close()

Write-Host "Certificado instalado com sucesso!"
```

### Método 3: Via CMD (Como Administrador)

```cmd
REM Abra CMD como Administrador
REM Clique com botão direito no CMD → "Executar como administrador"

certutil -addstore -user "Root" C:\Users\SeuUsuario\Downloads\brbandeiras.crt
```

## ✅ Passo 3: Verificar Instalação

### Via Interface:

1. Pressione **Win + R**
2. Digite: `certmgr.msc`
3. Pressione **Enter**
4. Navegue até: **Autoridades de Certificação Raiz Confiáveis** → **Certificados**
5. Procure por **"brbandeiras.local"** ou **"BR Bandeiras"**

### Via PowerShell:

```powershell
Get-ChildItem Cert:\CurrentUser\Root | Where-Object {$_.Subject -like "*brbandeiras*"}
```

## 🌐 Passo 4: Testar no Navegador

1. **Feche todos os navegadores** (Chrome, Edge, Firefox)

2. **Reabra o navegador**

3. **Acesse:**
   ```
   https://brbandeiras.local/
   ```

4. **Resultado esperado:**
   - ✅ Cadeado verde na barra de endereços
   - ✅ Sem avisos de segurança
   - ✅ Site carrega normalmente

## 🔧 Troubleshooting

### O certificado ainda mostra como inválido

1. **Limpe o cache do navegador:**
   - Chrome/Edge: `Ctrl + Shift + Delete` → Limpar dados de navegação
   - Marque "Imagens e arquivos em cache"
   - Clique em "Limpar dados"

2. **Reinicie o navegador completamente**

3. **Verifique se o certificado está instalado:**
   ```powershell
   certmgr.msc
   ```
   Verifique em: Autoridades de Certificação Raiz Confiáveis → Certificados

4. **Tente reinstalar o certificado**

### Erro ao importar certificado

- Certifique-se de estar executando como **Administrador**
- Verifique se o arquivo `.crt` não está corrompido
- Tente baixar o certificado novamente do servidor

### Certificado não aparece no repositório

- Verifique se instalou no repositório correto: **"Autoridades de Certificação Raiz Confiáveis"**
- Tente instalar novamente usando o Método 2 (PowerShell)

## 📝 Notas Importantes

### Segurança

- Certificados auto-assinados são **seguros para desenvolvimento local**
- Não use certificados auto-assinados em **produção pública**
- Para produção, use **Let's Encrypt** ou certificado comercial

### Validade

- O certificado atual é válido por **365 dias** (1 ano)
- Após expirar, será necessário gerar um novo certificado

### Múltiplos Usuários

- Se instalou em **"Usuário atual"**, apenas você terá o certificado confiável
- Para todos os usuários, instale em **"Computador local"** → **"Autoridades de Certificação Raiz Confiáveis"**

## 🎯 Resumo Rápido

1. ✅ Baixe `brbandeiras.crt` do servidor
2. ✅ Clique duas vezes no arquivo
3. ✅ Instale em "Autoridades de Certificação Raiz Confiáveis"
4. ✅ Reinicie o navegador
5. ✅ Acesse `https://brbandeiras.local/`

## 📚 Comandos Úteis

### Listar certificados instalados:
```powershell
Get-ChildItem Cert:\CurrentUser\Root | Format-Table Subject, Thumbprint, NotAfter
```

### Remover certificado (se necessário):
```powershell
$store = New-Object System.Security.Cryptography.X509Certificates.X509Store("Root", "CurrentUser")
$store.Open("ReadWrite")
$cert = $store.Certificates.Find("FindBySubjectName", "brbandeiras.local", $false)[0]
$store.Remove($cert)
$store.Close()
```

### Verificar certificado de um site:
```powershell
$request = [System.Net.HttpWebRequest]::Create("https://brbandeiras.local/")
try {
    $request.GetResponse() | Out-Null
    $request.ServicePoint.Certificate | Format-List
} catch {}
```
