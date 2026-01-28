# Como Aceitar o Certificado SSL Auto-Assinado

## ⚠️ Aviso Normal

O erro `NET::ERR_CERT_AUTHORITY_INVALID` é **normal e esperado** para certificados auto-assinados. Isso acontece porque o certificado não foi emitido por uma autoridade certificadora reconhecida (como Let's Encrypt, DigiCert, etc.).

## ✅ Como Aceitar o Certificado no Chrome

### Passo a Passo:

1. **Na página de erro**, clique no botão **"Avançadas"** ou **"Advanced"**

2. **Role para baixo** até ver a opção:
   - **"Prosseguir para brbandeiras.local (não seguro)"** (em português)
   - **"Proceed to brbandeiras.local (unsafe)"** (em inglês)

3. **Clique nessa opção**

4. O navegador irá te levar para o site normalmente

### Após Aceitar:

- O Chrome lembrará da sua escolha para este site
- Você não precisará aceitar novamente (a menos que limpe os dados do navegador)
- O site funcionará normalmente com HTTPS

## 🔒 Outros Navegadores

### Firefox:
1. Clique em **"Avançado"** ou **"Advanced"**
2. Clique em **"Aceitar o Risco e Continuar"** ou **"Accept the Risk and Continue"**

### Safari:
1. Clique em **"Mostrar Detalhes"** ou **"Show Details"**
2. Clique em **"Visitar este site"** ou **"Visit this website"**

### Edge:
1. Clique em **"Avançado"** ou **"Advanced"**
2. Clique em **"Continuar para brbandeiras.local (não recomendado)"**

## 🛡️ Instalar Certificado como Confiável (Opcional)

Se quiser evitar o aviso completamente, você pode instalar o certificado como confiável no seu Mac:

### macOS:

1. **Baixe o certificado:**
   ```bash
   scp root@192.168.1.250:/etc/apache2/ssl/brbandeiras.crt ~/Downloads/
   ```

2. **Abra o Keychain Access** (Acesso a Chaves):
   - Abra Spotlight (Cmd + Espaço)
   - Digite "Keychain Access"
   - Abra o aplicativo

3. **Importe o certificado:**
   - Arraste o arquivo `brbandeiras.crt` para o Keychain Access
   - Ou: Arquivo → Importar Itens → Selecione o arquivo

4. **Configure como confiável:**
   - Encontre "brbandeiras.local" na lista
   - Clique duas vezes nele
   - Expanda "Confiar" ou "Trust"
   - Selecione **"Sempre confiar"** ou **"Always Trust"**
   - Feche a janela

5. **Reinicie o navegador**

Agora o certificado será aceito automaticamente!

## 🔄 Certificado Atualizado

O certificado foi atualizado com **Subject Alternative Names (SAN)** incluindo:
- `brbandeiras.local`
- `www.brbandeiras.local`
- `*.brbandeiras.local`
- `192.168.1.250`
- `127.0.0.1`

Isso garante melhor compatibilidade com navegadores modernos.

## 📝 Nota Importante

**Para desenvolvimento local**, certificados auto-assinados são seguros e adequados. O aviso do navegador é apenas uma precaução.

**Para produção**, considere usar:
- **Let's Encrypt** (gratuito, válido)
- Certificado comercial de uma CA reconhecida

## ✅ Verificação

Após aceitar o certificado, você deve ver:
- Cadeado verde no navegador
- URL começando com `https://`
- Site funcionando normalmente

Se ainda tiver problemas, limpe o cache do navegador e tente novamente.
