# Syncs local .env for ngrok, then starts Laravel + queue + Vite + ngrok together.
# Usage (from backend/): composer dev:all
# Or: powershell -ExecutionPolicy Bypass -File ../scripts/start-dev-all.ps1

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent $PSScriptRoot
$backendDir = Join-Path $repoRoot "backend"
$syncScript = Join-Path $PSScriptRoot "start-ngrok.ps1"
$domainFile = Join-Path $repoRoot "ngrok.domain"

& $syncScript -SyncOnly
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$domain = (Get-Content $domainFile -Raw).Trim() -replace '^https?://', '' -replace '/.*$', ''
$ngrokCmd = "ngrok http --url=$domain 8000"

Write-Host "[*] Starting server + queue + vite + ngrok"
Write-Host "    App:     http://localhost:5173"
Write-Host "    API:     http://localhost:8000"
Write-Host "    Tunnel:  https://$domain"
Write-Host ""

Set-Location $backendDir
npx concurrently `
  -c "#93c5fd,#c4b5fd,#fdba74,#86efac" `
  "php artisan serve" `
  "php artisan queue:listen --tries=1 --timeout=0" `
  "cd ../frontend && npm run dev" `
  $ngrokCmd `
  --names=server,queue,vite,ngrok `
  --kill-others
