# PowerShell helper: charge les variables depuis .env et exécute r2_iceberg_setup.py
# Placez ce script dans le dossier bserp-backend et lancez-le depuis PowerShell.

$envFile = Join-Path $PSScriptRoot '.env'
if (-Not (Test-Path $envFile)) {
    Write-Error "Fichier .env introuvable dans $PSScriptRoot"
    exit 1
}

Get-Content $envFile | ForEach-Object {
    $_ = $_.Trim()
    if ($_ -eq '' -or $_ -match '^\s*#') { return }
    if ($_ -notmatch '=') { return }
    $parts = $_ -split '=', 2
    $name = $parts[0].Trim()
    $value = $parts[1].Trim() -replace '^"|"$',''
    # Skip comments or empty names
    if ($name -eq '') { return }
    Write-Host "Setting environment variable $name"
    Set-Item -Path Env:$name -Value $value
}

# Ensure current directory is script folder
Set-Location -Path $PSScriptRoot

# Optional: clear Laravel config if needed (uncomment if using Laravel in same shell)
# php artisan config:clear

# Run Python script
python r2_iceberg_setup.py

# End
