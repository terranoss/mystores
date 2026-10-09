<#
.SYNOPSIS
    MyStore Distribution Packaging Script
.DESCRIPTION
    Builds the pre-compiled React frontend, prepares production Laravel dependencies,
    bundles documentation and Flutter app, and creates a ready-to-sell zip archive.
#>

param(
    [string]$Version = "1.0.0",
    [switch]$SkipMobileBuild = $false
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RootDir = Split-Path -Parent $ScriptDir
$BuildDir = Join-Path $RootDir "dist_release"
$OutputDir = Join-Path $RootDir "release_artifacts"

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host " Building MyStore Release Package v$Version" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan

# Cleanup old build directories
if (Test-Path $BuildDir) {
    Remove-Item -Recurse -Force $BuildDir
}
if (!(Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir | Out-Null
}
New-Item -ItemType Directory -Path $BuildDir | Out-Null

# -------------------------------------------------------------------------
# STEP 1: Build React Frontend
# -------------------------------------------------------------------------
Write-Host "`n[1/5] Building React 19 Frontend..." -ForegroundColor Yellow
$FrontendDir = Join-Path $RootDir "frontend"
Push-Location $FrontendDir
try {
    npm run build
} finally {
    Pop-Location
}

# -------------------------------------------------------------------------
# STEP 2: Copy Frontend Dist into Backend Public
# -------------------------------------------------------------------------
Write-Host "`n[2/5] Copying compiled assets into backend public directory..." -ForegroundColor Yellow
$FrontendDist = Join-Path $FrontendDir "dist"
$BackendPublic = Join-Path $RootDir "backend\public"

Copy-Item -Path "$FrontendDist\*" -Destination $BackendPublic -Recurse -Force

# -------------------------------------------------------------------------
# STEP 3: Assemble Production Web Directory
# -------------------------------------------------------------------------
Write-Host "`n[3/5] Packaging Backend..." -ForegroundColor Yellow
$WebDest = Join-Path $BuildDir "web"
New-Item -ItemType Directory -Path $WebDest | Out-Null

# Robocopy / exclude development and transient files
$ExcludeDirs = @('.git', 'node_modules', 'tests')
$ExcludeFiles = @('.env', '.env.production', 'installed', 'license.json')

Get-ChildItem -Path (Join-Path $RootDir "backend") | Where-Object {
    $_.Name -notin $ExcludeDirs -and $_.Name -notin $ExcludeFiles
} | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination $WebDest -Recurse -Force
}

# Clean runtime logs & install locks in destination
$StorageLogs = Join-Path $WebDest "storage\logs"
if (Test-Path $StorageLogs) {
    Get-ChildItem -Path $StorageLogs -File | Remove-Item -Force
}
$InstalledLock = Join-Path $WebDest "storage\installed"
if (Test-Path $InstalledLock) {
    Remove-Item -Force $InstalledLock
}
$LicenseJson = Join-Path $WebDest "storage\app\license.json"
if (Test-Path $LicenseJson) {
    Remove-Item -Force $LicenseJson
}

# Run Composer --no-dev
Write-Host "Installing production composer dependencies in package..." -ForegroundColor Gray
Push-Location $WebDest
try {
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
} finally {
    Pop-Location
}

# -------------------------------------------------------------------------
# STEP 4: Package Flutter Companion App
# -------------------------------------------------------------------------
Write-Host "`n[4/5] Bundling Flutter Companion App..." -ForegroundColor Yellow
$MobileDest = Join-Path $BuildDir "mobile"
New-Item -ItemType Directory -Path $MobileDest | Out-Null

$MobileExclude = @('.dart_tool', 'build', '.idea', 'shopee_manager.iml')
Get-ChildItem -Path (Join-Path $RootDir "mobile") | Where-Object {
    $_.Name -notin $MobileExclude
} | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination $MobileDest -Recurse -Force
}

# Check if flutter is installed to compile release APK
if (!$SkipMobileBuild -and (Get-Command "flutter" -ErrorAction SilentlyContinue)) {
    Write-Host "Compiling standalone Android APK for distribution..." -ForegroundColor Gray
    Push-Location (Join-Path $RootDir "mobile")
    try {
        flutter build apk --release
        $BuiltApk = Join-Path $RootDir "mobile\build\app\outputs\flutter-apk\app-release.apk"
        if (Test-Path $BuiltApk) {
            Copy-Item -Path $BuiltApk -Destination (Join-Path $BuildDir "MyStore-v$Version.apk") -Force
            Write-Host "  ✓ Bundled pre-built APK: MyStore-v$Version.apk" -ForegroundColor Green
        }
    } catch {
        Write-Warning "Flutter APK build failed or skipped. The mobile source code is still included."
    } finally {
        Pop-Location
    }
}

# -------------------------------------------------------------------------
# STEP 5: Add Documentation & Create Final Zip
# -------------------------------------------------------------------------
Write-Host "`n[5/5] Adding Documentation & Creating Zip Archive..." -ForegroundColor Yellow

$DocsSource = Join-Path $RootDir "Docs\documentation.html"
if (Test-Path $DocsSource) {
    Copy-Item -Path $DocsSource -Destination (Join-Path $BuildDir "documentation.html") -Force
}

# Create quickstart README.txt
$ReadmeContent = @"
==================================================
 MyStore - E-Commerce & POS Management Application
 Version: $Version
==================================================

Thank you for purchasing MyStore!

PACKAGE CONTENTS:
-----------------
1. web/                 Upload all files inside this folder to your cPanel or VPS web root.
2. documentation.html   Open this in your web browser for complete step-by-step setup instructions.
3. mobile/              Full Flutter companion app source code for customization.
4. MyStore-v$Version.apk Pre-built Android APK ready for direct install on phones or POS tablets.

QUICK START INSTRUCTIONS:
-------------------------
1. Upload the 'web' folder to your cPanel public_html or subdomain folder.
2. Create an empty database in cPanel MySQL.
3. Open your browser and navigate to:
   https://your-domain.com/install
4. Follow the on-screen 5-step installer wizard.
5. Enter your Stacklane License Key to activate.

For full instructions, open 'documentation.html'.
"@

Set-Content -Path (Join-Path $BuildDir "README.txt") -Value $ReadmeContent -Encoding UTF8

$ZipArchive = Join-Path $OutputDir "mystores-v$Version.zip"
if (Test-Path $ZipArchive) {
    Remove-Item -Force $ZipArchive
}

Write-Host "Compressing archive to: $ZipArchive" -ForegroundColor Gray
Compress-Archive -Path "$BuildDir\*" -DestinationPath $ZipArchive -CompressionLevel Optimal

# Cleanup temp build directory
Remove-Item -Recurse -Force $BuildDir

Write-Host "`n==================================================" -ForegroundColor Green
Write-Host " SUCCESS! Distribution package created:" -ForegroundColor Green
Write-Host " $ZipArchive" -ForegroundColor Green
Write-Host "==================================================" -ForegroundColor Green
