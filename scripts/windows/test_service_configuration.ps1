[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$OutputPath)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'service_configuration.ps1')
$manager = Join-Path $PSScriptRoot 'manage_document_service.ps1'
$work = Join-Path ([IO.Path]::GetTempPath()) ('ficct-win01-' + [Guid]::NewGuid().ToString('N'))
[IO.Directory]::CreateDirectory($work) | Out-Null
$installedDirectory = Join-Path $work 'installed'
[IO.Directory]::CreateDirectory($installedDirectory) | Out-Null
$installed = Join-Path $installedDirectory 'service.json'
$external = Join-Path $work 'external.json'
$identical = Join-Path $work 'identical.json'
$settingsFixture = @{service_name='FICCTFixture';worker_name='fixture';root='C:\fixture';python='Invoke-FixtureHealth';php='C:\fixture\php.exe';required_services=@('InstalledWanted');external_database=$false;environment=@{}}
function Save-Fixture($path,$settings) { [IO.File]::WriteAllText($path,($settings | ConvertTo-Json -Depth 8)) }
Save-Fixture $installed $settingsFixture
$externalFixture = $settingsFixture.Clone();$externalFixture.required_services=@('ExternalOld');Save-Fixture $external $externalFixture
$installedBytes = [Convert]::ToBase64String([IO.File]::ReadAllBytes($installed))
$checks = New-Object 'Collections.Generic.List[string]'
$management = @('Start','Stop','Status','EnableAutomatic','Uninstall')
try {
    foreach ($action in $management) {
        $rejected=$false
        try { Read-DocumentServiceConfiguration -Action $action -ConfigPath $external -InstallDirectory $installedDirectory | Out-Null }
        catch { if ($_.Exception.Message -notlike 'External configuration differs*') { throw };$rejected=$true }
        if (!$rejected) { throw "Stale configuration accepted for $action" }
    }
    $checks.Add('M01_copia_desactualizada_rechazada_en_cinco_acciones')
    foreach ($action in $management) {
        $selected=Read-DocumentServiceConfiguration -Action $action -ConfigPath $installed -InstallDirectory $installedDirectory
        if ($selected.SelectedFile -ne $installed -or $selected.Settings.required_services[0] -ne 'InstalledWanted') { throw 'Installed settings not selected.' }
    }
    $checks.Add('M02_administracion_usa_solo_la_configuracion_instalada')
    [IO.File]::WriteAllBytes($identical,[IO.File]::ReadAllBytes($installed))
    $selected=Read-DocumentServiceConfiguration -Action EnableAutomatic -ConfigPath $identical -InstallDirectory $installedDirectory
    if ($selected.SelectedFile -ne $installed) { throw 'Identical external copy became the source.' }
    $checks.Add('M03_copia_identica_aceptada_pero_se_lee_la_instalada')
    $rejected=$false
    try { Read-DocumentServiceConfiguration -Action Status -ConfigPath $external -InstallDirectory (Join-Path $work 'missing') | Out-Null } catch { $rejected=$true }
    if (!$rejected) { throw 'Missing installed config fell back to external.' }
    $checks.Add('M04_sin_configuracion_instalada_no_hay_fallback_externo')
    foreach ($key in @('root','python','worker_name','external_database','environment')) {
        $changed=$settingsFixture.Clone()
        if ($key -eq 'environment') { $changed[$key]=@{FICCT_TEST_VALUE='different'} }
        elseif ($key -eq 'external_database') { $changed[$key]=$true }
        else { $changed[$key]='different' }
        Save-Fixture $external $changed
        $rejected=$false
        try { Read-DocumentServiceConfiguration -Action EnableAutomatic -ConfigPath $external -InstallDirectory $installedDirectory | Out-Null } catch { $rejected=$true }
        if (!$rejected) { throw "Mismatch accepted: $key" }
    }
    $checks.Add('M05_rutas_identidad_entorno_y_base_divergentes_rechazados')
    [IO.File]::WriteAllText($installed,'invalid JSON')
    $rejected=$false
    try { Read-DocumentServiceConfiguration -Action Status -ConfigPath $installed -InstallDirectory $installedDirectory | Out-Null } catch { $rejected=$true }
    if (!$rejected) { throw 'Invalid installed JSON accepted.' }
    Save-Fixture $installed $settingsFixture
    $checks.Add('M06_configuracion_instalada_invalida_rechazada')
    foreach ($action in @('Build','Install')) {
        $selected=Read-DocumentServiceConfiguration -Action $action -ConfigPath $external -InstallDirectory (Join-Path $work 'not-installed')
        if ($selected.SelectedFile -ne $external) { throw 'External setup source unavailable.' }
    }
    $checks.Add('M07_compilacion_e_instalacion_inicial_conservan_fuente_externa')
    # Execute the real EnableAutomatic block; all SCM and health calls below are doubles.
    $script:Controls = New-Object 'Collections.Generic.List[object]'
    function Get-Service { param([string]$Name,$ErrorAction) [pscustomobject]@{Status='Running'} }
    function Get-Content { param([string]$LiteralPath,[switch]$Raw) return (@{mode='windows-service';state='running';time_utc=[DateTime]::UtcNow.ToString('o')} | ConvertTo-Json) }
    function Invoke-ServiceControl { param([string[]]$Arguments) $script:Controls.Add($Arguments) }
    function Invoke-FixtureHealth { $script:HealthArguments=$args;$global:LASTEXITCODE=0 }
    $selected=Read-DocumentServiceConfiguration -Action EnableAutomatic -ConfigPath $identical -InstallDirectory $installedDirectory
    $settings=$selected.Settings;$installedConfig=$selected.InstalledFile;$serviceName=$settings.service_name;$binary=Join-Path $installedDirectory 'FicctDocumentProcessor.exe'
    $tokens=$null;$errors=$null
    $ast=[Management.Automation.Language.Parser]::ParseFile($manager,[ref]$tokens,[ref]$errors)
    if ($errors.Count) { throw 'Manager parse failed.' }
    $switch=$ast.Find({param($n) $n -is [Management.Automation.Language.SwitchStatementAst]},$true)
    $clause=$switch.Clauses | Where-Object {$_.Item1.Value -eq 'EnableAutomatic'}
    $text=$clause.Item2.Extent.Text
    & ([ScriptBlock]::Create($text.Substring(1,$text.Length-2)))
    $dependency=@($script:Controls | Where-Object {$_[2] -eq 'depend='})[0][3]
    if ($dependency -ne 'InstalledWanted' -or $script:HealthArguments -notcontains $installed) { throw 'Dependency and health sources diverged.' }
    $checks.Add('M08_bloque_real_EnableAutomatic_dependencias_y_salud_coinciden')
    [IO.File]::WriteAllText($external,([IO.File]::ReadAllText($installed) + "`n"))
    $rejected=$false
    try { Read-DocumentServiceConfiguration -Action Status -ConfigPath $external -InstallDirectory $installedDirectory | Out-Null } catch { $rejected=$true }
    if (!$rejected) { throw 'Non-identical copy accepted.' }
    $checks.Add('M09_copia_con_formato_distinto_rechazada_conservadoramente')
    foreach ($action in $management) {
        $rejected=$false
        try { & $manager -Action $action -ConfigPath $external -InstallDirectory $installedDirectory | Out-Null }
        catch { if ($_.Exception.Message -notlike 'External configuration differs*') { throw };$rejected=$true }
        if (!$rejected) { throw "Actual manager accepted stale configuration for $action" }
    }
    $checks.Add('M11_script_real_rechaza_discrepancias_antes_de_administrar')
    if ([Convert]::ToBase64String([IO.File]::ReadAllBytes($installed)) -ne $installedBytes) { throw 'Installed fixture was modified.' }
    $checks.Add('M10_archivo_instalado_preservado')
    $report=@{result='PASS';tests=$checks.Count;checks=@($checks.ToArray());SCM_calls='ALL_MOCKED';LocalService_acceptance='NOT_VERIFIED';reboot='NOT_VERIFIED';scope='Real configuration files and actual EnableAutomatic AST; no installed services or registry modifications'}
    [IO.File]::WriteAllText([IO.Path]::GetFullPath($OutputPath),($report | ConvertTo-Json -Depth 8))
    $report | ConvertTo-Json -Depth 8
} finally {
    # The exact generated temporary directory is the only deletion target.
    $resolved=[IO.Path]::GetFullPath($work)
    $parent=[IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\')+'\'
    if ($resolved.StartsWith($parent,[StringComparison]::OrdinalIgnoreCase) -and ([IO.Path]::GetFileName($resolved) -like 'ficct-win01-*')) {
        Remove-Item -LiteralPath $resolved -Recurse -Force
    }
}
