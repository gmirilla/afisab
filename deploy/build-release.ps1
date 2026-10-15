<#
.SYNOPSIS
  Builds a ready-to-upload AFISAB release zip for cPanel.

.DESCRIPTION
  1. Builds the front-end assets (npm ci + npm run build).
  2. Copies the application to a clean staging folder, leaving out tests, node_modules, secrets and local data.
  3. Installs PRODUCTION-only PHP dependencies (composer install --no-dev --optimize-autoloader).
  4. Splits the result into the two cPanel folders and zips them:
         afisab/        -> upload to /home/USER/afisab        (not web-accessible)
         public_html/   -> upload to /home/USER/public_html   (the web root)
  5. Writes a SHA-256 checksum next to the zip.

  It never touches your .env, your database or the live server.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File deploy\build-release.ps1
#>
[CmdletBinding()]
param(
    [string] $OutputDir = ''
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
if (-not $OutputDir) { $OutputDir = Join-Path $root 'release' }  # ($PSScriptRoot is empty in param defaults on Windows PowerShell 5.1)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$staging = Join-Path ([System.IO.Path]::GetTempPath()) "afisab-release-$stamp"
$appOut = Join-Path $staging 'afisab'
$webOut = Join-Path $staging 'public_html'

function Step($text) { Write-Host "==> $text" -ForegroundColor Green }

Push-Location $root
try {
    Step 'Building front-end assets'
    npm ci --no-audit --no-fund | Out-Null
    npm run build | Out-Null
    if (-not (Test-Path 'public/build/manifest.json')) { throw 'Vite build did not produce public/build/manifest.json' }

    Step "Copying application to $appOut"
    New-Item -ItemType Directory -Force -Path $appOut | Out-Null
    $excludeDirs = @('node_modules', 'vendor', '.git', 'tests', 'release', '.idea', '.vscode', '.phpunit.cache')
    $excludeFiles = @('.env', '.env.backup', '*.log', '*.sqlite', '.phpunit.result.cache', 'phpunit.xml', 'package-lock.json')
    robocopy $root $appOut /E /XD $excludeDirs 'storage\logs' 'storage\framework\cache' 'storage\framework\sessions' 'storage\framework\views' 'storage\app\private' /XF $excludeFiles /NFL /NDL /NJH /NJS /NP | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy failed ($LASTEXITCODE)" }

    # Empty writable folders the framework needs.
    foreach ($d in 'storage\logs', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\app\private', 'storage\app\public', 'bootstrap\cache') {
        New-Item -ItemType Directory -Force -Path (Join-Path $appOut $d) | Out-Null
    }

    Step 'Installing production PHP dependencies'
    Push-Location $appOut
    try {
        composer install --no-dev --optimize-autoloader --no-interaction --no-progress | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }
    } finally { Pop-Location }

    Step 'Preparing the web root'
    New-Item -ItemType Directory -Force -Path $webOut | Out-Null
    Copy-Item -Path (Join-Path $appOut 'public\*') -Destination $webOut -Recurse -Force
    Copy-Item -Path (Join-Path $appOut 'deploy\index.php.cpanel') -Destination (Join-Path $webOut 'index.php') -Force
    # The web root must not contain the app's own copy of these; they live in afisab/public and are served from public_html.
    Remove-Item -Path (Join-Path $appOut 'public') -Recurse -Force

    Step 'Safety check: nothing secret or local may ship'
    $bad = Get-ChildItem $staging -Recurse -File -Force | Where-Object {
        $path = $_.FullName
        $inVendor = $path -like '*\afisab\vendor\*'
        ($_.Name -eq '.env') -or                                                              # real settings and secrets
        ($_.Extension -in '.sqlite', '.log') -or                                              # local databases and logs
        (($path -like '*\afisab\storage\app\private\*') -and ($_.Name -ne '.gitignore')) -or  # KYC documents and receipts (the .gitignore placeholder is fine)
        ((-not $inVendor) -and ($path -like '*\afisab\tests\*'))                              # our own tests (third-party packages keep theirs)
    }
    if ($bad) { throw "Refusing to build: $(@($bad).Count) file(s) must not ship, e.g. $(@($bad)[0].FullName)" }

    Step 'Creating the zip'
    New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
    $zip = Join-Path $OutputDir "afisab-release-$stamp.zip"
    # Not Compress-Archive (20+ minutes) and not CreateFromDirectory (writes backslash paths that break on Linux).
    & (Join-Path $PSScriptRoot 'zip-portable.ps1') -Source $staging -Destination $zip
    $hash = (Get-FileHash -Algorithm SHA256 $zip).Hash.ToLower()
    "$hash  $(Split-Path -Leaf $zip)" | Set-Content -Encoding ascii "$zip.sha256"

    $mb = [math]::Round((Get-Item $zip).Length / 1MB, 1)
    Write-Host ''
    Write-Host "Release: $zip ($mb MB)" -ForegroundColor Cyan
    Write-Host "SHA-256: $hash"
    Write-Host "Staging folder (safe to delete): $staging"
    Write-Host 'Next: follow docs/DEPLOYMENT.md (upload, .env, migrate, preflight).'
}
finally { Pop-Location }
