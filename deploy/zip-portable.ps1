<#
.SYNOPSIS
  Creates a zip that unpacks correctly on Linux/cPanel.

.DESCRIPTION
  Windows PowerShell's Compress-Archive is extremely slow for thousands of files, and
  [ZipFile]::CreateFromDirectory on .NET Framework stores paths with BACKSLASHES (afisab\app\Foo.php), which a Linux
  host then unpacks as files literally named "afisab\app\Foo.php" instead of folders. This writes every entry with a
  forward slash and keeps empty folders (storage/framework/...), which Laravel needs.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)] [string] $Source,
    [Parameter(Mandatory)] [string] $Destination
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root = (Resolve-Path $Source).Path.TrimEnd('\')
if (Test-Path $Destination) { throw "Destination already exists: $Destination" }

$stream = [System.IO.File]::Open($Destination, [System.IO.FileMode]::CreateNew)
$zip = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $files = 0
    $dirs = 0
    Get-ChildItem -LiteralPath $root -Recurse -File -Force | ForEach-Object {
        $name = $_.FullName.Substring($root.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $name, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        $files++
    }
    # Empty folders (no file anywhere beneath them) need their own entry.
    Get-ChildItem -LiteralPath $root -Recurse -Directory -Force | Where-Object { -not (Get-ChildItem -LiteralPath $_.FullName -Recurse -File -Force | Select-Object -First 1) } | ForEach-Object {
        $zip.CreateEntry($_.FullName.Substring($root.Length + 1).Replace('\', '/') + '/') | Out-Null
        $dirs++
    }
    Write-Host "Zipped $files files and $dirs empty folders."
}
finally {
    $zip.Dispose()
    $stream.Dispose()
}
