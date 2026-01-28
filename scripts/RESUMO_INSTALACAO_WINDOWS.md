# Resumo Rápido - Instalar Certificado no Windows

## Método Mais Rápido (Recomendado)

1. **Baixe o certificado:**
   ```powershell
   scp root@192.168.1.250:/etc/apache2/ssl/brbandeiras.crt C:\Users\%USERNAME%\Downloads\
   ```

2. **Clique duas vezes no arquivo `brbandeiras.crt`**

3. **Clique em "Instalar certificado"**

4. **Selecione "Usuário atual" → Avançar**

5. **Selecione "Colocar todos os certificados no seguinte repositório"**

6. **Clique em "Procurar" → Selecione "Autoridades de Certificação Raiz Confiáveis" → OK**

7. **Avançar → Concluir → Sim (no aviso)**

8. **Feche e reabra o navegador**

9. **Acesse: `https://brbandeiras.local/`**

## ✅ Pronto!

O certificado está instalado e o site deve abrir sem avisos de segurança.

Para mais detalhes, consulte: `INSTALAR_CERTIFICADO_WINDOWS.md`
