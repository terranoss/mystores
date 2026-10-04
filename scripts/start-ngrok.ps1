# Starts an ngrok tunnel to local Laravel (:8000) using a reserved static domain.
# Production is untouched — only backend/.env is updated for local OAuth callbacks.
#
# Prerequisites:
#   1. ngrok installed + authtoken configured (ngrok config add-authtoken ...)
#   2. Free static domain claimed at https://dashboard.ngrok.com/domains
#   3. Copy ngrok.domain.example -> ngrok.domain and put your hostname (no https://)
#   4. Register https://<domain>/api/shopee/callback in the Shopee sandbox Partner Console
#
# Flags:
#   -SyncOnly   Update backend/.env + config:clear, then exit (used by start-dev-all.ps1)

param(
    [switch]$SyncOnly
)

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent $PSScriptRoot
$domainFile = Join-Path $repoRoot "ngrok.domain"
$envFile = Join-Path $repoRoot "backend\.env"

if (-not (Test-Path $domainFile)) {
    Write-Host "[-] Missing ngrok.domain"
    Write-Host "    1. Claim a free static domain: https://dashboard.ngrok.com/domains"
    Write-Host "    2. Copy ngrok.domain.example to ngrok.domain"
    Write-Host "    3. Put only the hostname, e.g. my-app.ngrok-free.app"
    exit 1
}

$domain = (Get-Content $domainFile -Raw).Trim()
if ([string]::IsNullOrWhiteSpace($domain) -or $domain -eq "your-static-name.ngrok-free.app") {
    Write-Host "[-] ngrok.domain still has the placeholder. Replace it with your claimed domain."
    exit 1
}
$domain = $domain -replace '^https?://', '' -replace '/.*$', ''

if (-not (Get-Command ngrok -ErrorAction SilentlyContinue)) {
    Write-Host "[-] ngrok is not installed or not on PATH."
    exit 1
}

if (-not (Test-Path $envFile)) {
    Write-Host "[-] Missing backend/.env"
    exit 1
}

$redirectUrl = "https://$domain/api/shopee/callback"
Write-Host "[*] Syncing local backend/.env for ngrok OAuth..."
Write-Host "    SHOPEE_REDIRECT_URL=$redirectUrl"
Write-Host "    FRONTEND_URL=http://localhost:5173"
Write-Host "    APP_URL=http://localhost:8000"

$lines = Get-Content $envFile
$updated = foreach ($line in $lines) {
    if ($line -match '^APP_URL=') {
        "APP_URL=http://localhost:8000"
    }
    elseif ($line -match '^FRONTEND_URL=') {
        "FRONTEND_URL=http://localhost:5173"
    }
    elseif ($line -match '^SHOPEE_REDIRECT_URL=') {
        "SHOPEE_REDIRECT_URL=$redirectUrl"
    }
    elseif ($line -match '^SHOPEE_SANDBOX=') {
        "SHOPEE_SANDBOX=true"
    }
    else {
        $line
    }
}

$hasRedirect = $updated | Where-Object { $_ -match '^SHOPEE_REDIRECT_URL=' }
$hasFrontend = $updated | Where-Object { $_ -match '^FRONTEND_URL=' }
$hasSandbox = $updated | Where-Object { $_ -match '^SHOPEE_SANDBOX=' }
$hasAppUrl = $updated | Where-Object { $_ -match '^APP_URL=' }

if (-not $hasAppUrl) { $updated += "APP_URL=http://localhost:8000" }
if (-not $hasFrontend) { $updated += "FRONTEND_URL=http://localhost:5173" }
if (-not $hasRedirect) { $updated += "SHOPEE_REDIRECT_URL=$redirectUrl" }
if (-not $hasSandbox) { $updated += "SHOPEE_SANDBOX=true" }

$updated | Set-Content -Path $envFile -Encoding utf8

Push-Location (Join-Path $repoRoot "backend")
try {
    php artisan config:clear | Out-Null
    Write-Host "[+] Config cache cleared"
}
finally {
    Pop-Location
}

if ($SyncOnly) {
    Write-Host "[+] Env sync complete"
    exit 0
}

Write-Host "[*] Starting ngrok http --url=$domain 8000"
Write-Host "[*] Keep this running while linking stores. Shopee will callback:"
Write-Host "    $redirectUrl"
Write-Host "[*] After authorize, browser returns to http://localhost:5173/dashboard"
Write-Host ""

ngrok http --url=$domain 8000
