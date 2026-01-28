# Script PowerShell para instalar certificado SSL no Windows
# Execute como Administrador: PowerShell -ExecutionPolicy Bypass -File instalar_certificado_windows.ps1

param(
    [string]$CertPath = ""
)

Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║     Instalação de Certificado SSL - BR Bandeiras        ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Verificar se está executando como administrador
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "⚠️  Este script precisa ser executado como Administrador!" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Como executar:" -ForegroundColor Yellow
    Write-Host "1. Clique com botão direito no PowerShell" -ForegroundColor Yellow
    Write-Host "2. Selecione 'Executar como administrador'" -ForegroundColor Yellow
    Write-Host "3. Execute: PowerShell -ExecutionPolicy Bypass -File instalar_certificado_windows.ps1" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

# Se não forneceu caminho, tentar baixar do servidor
if ([string]::IsNullOrEmpty($CertPath)) {
    Write-Host "📥 Baixando certificado do servidor..." -ForegroundColor Yellow
    
    $downloadPath = "$env:USERPROFILE\Downloads\brbandeiras.crt"
    
    # Tentar baixar via SCP (se disponível)
    if (Get-Command scp -ErrorAction SilentlyContinue) {
        Write-Host "Usando SCP para baixar..." -ForegroundColor Gray
        scp root@192.168.1.250:/etc/apache2/ssl/brbandeiras.crt $downloadPath
        $CertPath = $downloadPath
    } else {
        Write-Host "⚠️  SCP não encontrado. Por favor, baixe o certificado manualmente:" -ForegroundColor Yellow
        Write-Host "   scp root@192.168.1.250:/etc/apache2/ssl/brbandeiras.crt C:\Users\$env:USERNAME\Downloads\" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Ou forneça o caminho do certificado:" -ForegroundColor Yellow
        Write-Host "   PowerShell -ExecutionPolicy Bypass -File instalar_certificado_windows.ps1 -CertPath 'C:\caminho\para\brbandeiras.crt'" -ForegroundColor Yellow
        exit 1
    }
}

# Verificar se o arquivo existe
if (-not (Test-Path $CertPath)) {
    Write-Host "✗ Arquivo não encontrado: $CertPath" -ForegroundColor Red
    exit 1
}

Write-Host "✓ Certificado encontrado: $CertPath" -ForegroundColor Green
Write-Host ""

# Verificar se já está instalado
Write-Host "🔍 Verificando se certificado já está instalado..." -ForegroundColor Yellow
$existingCert = Get-ChildItem Cert:\CurrentUser\Root | Where-Object {$_.Subject -like "*brbandeiras*"}

if ($existingCert) {
    Write-Host "⚠️  Certificado já existe no repositório!" -ForegroundColor Yellow
    Write-Host "   Deseja reinstalar? (S/N): " -NoNewline -ForegroundColor Yellow
    $response = Read-Host
    if ($response -ne "S" -and $response -ne "s") {
        Write-Host "Operação cancelada." -ForegroundColor Gray
        exit 0
    }
    
    # Remover certificado existente
    Write-Host "🗑️  Removendo certificado existente..." -ForegroundColor Yellow
    $store = New-Object System.Security.Cryptography.X509Certificates.X509Store("Root", "CurrentUser")
    $store.Open("ReadWrite")
    $store.Remove($existingCert)
    $store.Close()
    Write-Host "✓ Certificado removido" -ForegroundColor Green
}

# Instalar certificado
Write-Host ""
Write-Host "📦 Instalando certificado..." -ForegroundColor Yellow

try {
    $store = New-Object System.Security.Cryptography.X509Certificates.X509Store("Root", "CurrentUser")
    $store.Open("ReadWrite")
    
    $cert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($CertPath)
    $store.Add($cert)
    $store.Close()
    
    Write-Host "✓ Certificado instalado com sucesso!" -ForegroundColor Green
    Write-Host ""
    
    # Mostrar informações do certificado
    Write-Host "📋 Informações do certificado:" -ForegroundColor Cyan
    Write-Host "   Assunto: $($cert.Subject)" -ForegroundColor Gray
    Write-Host "   Válido até: $($cert.NotAfter)" -ForegroundColor Gray
    Write-Host "   Emitido por: $($cert.Issuer)" -ForegroundColor Gray
    Write-Host ""
    
    Write-Host "✅ Instalação concluída!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Próximos passos:" -ForegroundColor Yellow
    Write-Host "1. Feche todos os navegadores" -ForegroundColor Gray
    Write-Host "2. Reabra o navegador" -ForegroundColor Gray
    Write-Host "3. Acesse: https://brbandeiras.local/" -ForegroundColor Gray
    Write-Host ""
    
} catch {
    Write-Host "✗ Erro ao instalar certificado: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
