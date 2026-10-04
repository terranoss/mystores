# Single command script to build and deploy to live server
$ErrorActionPreference = "Stop"

Write-Host ">>> Starting Build and Deploy to Live (mystores.melur.com)..." -ForegroundColor Cyan
python deploy/deploy.py
