param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = 'Stop'
$resolvedRoot = (Resolve-Path -LiteralPath $ProjectRoot).Path
$backupDir = Join-Path $resolvedRoot 'setup\backups'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

if (-not $backupDir.StartsWith($resolvedRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Nieprawidłowy katalog kopii zapasowej.'
}

New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

$dbFile = "database-$stamp.sql"
docker compose --project-directory $resolvedRoot run --rm --entrypoint wp wpcli db export "/setup/backups/$dbFile" --allow-root
if ($LASTEXITCODE -ne 0) { throw 'Eksport bazy danych nie powiódł się.' }

$archive = Join-Path $backupDir "files-$stamp.zip"
$sources = @(
    (Join-Path $resolvedRoot 'wp-theme'),
    (Join-Path $resolvedRoot 'uploads'),
    (Join-Path $resolvedRoot 'plugins')
) | Where-Object { Test-Path -LiteralPath $_ }

Compress-Archive -LiteralPath $sources -DestinationPath $archive -CompressionLevel Optimal
Write-Host "Backup gotowy: $backupDir"
