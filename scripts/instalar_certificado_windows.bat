@echo off
REM Script batch para instalar certificado SSL no Windows
REM Execute como Administrador

echo ╔════════════════════════════════════════════════════════════╗
echo ║     Instalação de Certificado SSL - BR Bandeiras        ║
echo ╚════════════════════════════════════════════════════════════╝
echo.

REM Verificar se está executando como administrador
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ⚠️  Este script precisa ser executado como Administrador!
    echo.
    echo Como executar:
    echo 1. Clique com botão direito no arquivo .bat
    echo 2. Selecione "Executar como administrador"
    echo.
    pause
    exit /b 1
)

REM Solicitar caminho do certificado
set /p CERT_PATH="Digite o caminho completo do certificado (brbandeiras.crt): "

REM Verificar se o arquivo existe
if not exist "%CERT_PATH%" (
    echo ✗ Arquivo não encontrado: %CERT_PATH%
    echo.
    echo Exemplo de caminho:
    echo C:\Users\SeuUsuario\Downloads\brbandeiras.crt
    echo.
    pause
    exit /b 1
)

echo.
echo 📦 Instalando certificado...
echo.

REM Instalar certificado usando certutil
certutil -addstore -user "Root" "%CERT_PATH%"

if %errorLevel% equ 0 (
    echo.
    echo ✅ Certificado instalado com sucesso!
    echo.
    echo Próximos passos:
    echo 1. Feche todos os navegadores
    echo 2. Reabra o navegador
    echo 3. Acesse: https://brbandeiras.local/
    echo.
) else (
    echo.
    echo ✗ Erro ao instalar certificado
    echo.
)

pause
