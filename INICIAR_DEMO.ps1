$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) { composer install --no-interaction; if ($LASTEXITCODE -ne 0) { throw 'No se pudieron instalar las dependencias.' } }
python scripts/local_database.py
if ($LASTEXITCODE -ne 0) { throw 'No se pudo iniciar PostgreSQL aislado.' }
if (-not (Select-String -LiteralPath '.env' -Pattern '^APP_KEY=base64:.+' -Quiet)) { php artisan key:generate --no-ansi --force }
php artisan migrate --force --no-ansi
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron aplicar las migraciones.' }
php artisan db:seed --force --no-ansi
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron crear los datos DEMO.' }
Write-Host 'Accesos locales: .local/ACCESOS_DEMO.txt'
Write-Host 'Portal: http://127.0.0.1:8099'
php artisan serve --host=127.0.0.1 --port=8099 --no-reload
