$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) { composer install --no-interaction; if ($LASTEXITCODE -ne 0) { throw 'No se pudieron instalar las dependencias.' } }
if (-not (Test-Path -LiteralPath 'public/build/manifest.json')) {
    if (Get-Command npm -ErrorAction SilentlyContinue) { npm ci; npm run build; if ($LASTEXITCODE -ne 0) { throw 'No se pudo compilar la interfaz React (npm run build).' } }
    else { throw 'Falta public/build/manifest.json y Node/npm no esta disponible. Compile con npm ci y npm run build, o use el paquete con los recursos incluidos.' }
}
python scripts/local_database.py
if ($LASTEXITCODE -ne 0) { throw 'No se pudo iniciar PostgreSQL aislado.' }
if (-not (Select-String -LiteralPath '.env' -Pattern '^APP_KEY=base64:.+' -Quiet)) { php artisan key:generate --no-ansi --force }
Write-Host 'Migraciones pendientes (se aplicaran a la base indicada en .env):'
php artisan migrate:status --pending --no-ansi
php artisan migrate --force --no-ansi
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron aplicar las migraciones.' }
php artisan db:seed --force --no-ansi
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron crear los datos DEMO.' }
Write-Host 'Accesos locales: .local/ACCESOS_DEMO.txt'
Write-Host 'Portal: http://127.0.0.1:8099'
php artisan serve --host=127.0.0.1 --port=8099 --no-reload
