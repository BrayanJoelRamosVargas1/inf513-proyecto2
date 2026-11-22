[CmdletBinding()]
param(
    [ValidateSet('Build','Install','Start','Stop','Status','EnableAutomatic','Uninstall')][string]$Action = 'Build',
    [Parameter(Mandatory=$true)][string]$ConfigPath,
    [string]$InstallDirectory = "$env:ProgramData\FICCT\DocumentProcessor"
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'service_configuration.ps1')
$configuration = Read-DocumentServiceConfiguration -Action $Action -ConfigPath $ConfigPath -InstallDirectory $InstallDirectory
$configFile = $configuration.SelectedFile
$settings = $configuration.Settings
$serviceName = $settings.service_name
$target = $configuration.Target
$binary = Join-Path $target 'FicctDocumentProcessor.exe'
$installedConfig = $configuration.InstalledFile
$expectedImage = '"' + $binary + '" "' + $installedConfig + '"'
$source = Join-Path $PSScriptRoot 'DocumentProcessorService.cs'
$admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if ($Action -notin @('Build','Status') -and !$admin) {
    throw 'Administrator required for Windows Service Control Manager. Nothing was installed or changed.'
}
if ($Action -in @('Start','Stop','EnableAutomatic','Uninstall')) {
    $registered = (Get-ItemProperty -LiteralPath "HKLM:\SYSTEM\CurrentControlSet\Services\$serviceName" -Name ImagePath -ErrorAction Stop).ImagePath
    if ($registered -ne $expectedImage) { throw 'Refusing to manage a service registered to another executable/configuration.' }
}
function Invoke-ServiceControl([string[]]$Arguments) {
    $result = & "$env:WINDIR\System32\sc.exe" @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) { throw ($result -join "`n") }
}
function Build-Host {
    New-Item -ItemType Directory -Path $target -Force | Out-Null
    $compiler = Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'
    if (!(Test-Path -LiteralPath $compiler)) { throw '.NET Framework compiler unavailable.' }
    $compiled = & $compiler /nologo /target:exe /platform:x64 /optimize+ "/out:$binary" /reference:System.ServiceProcess.dll /reference:System.Web.Extensions.dll $source 2>&1
    if ($LASTEXITCODE -ne 0) { throw ($compiled -join "`n") }
}
switch ($Action) {
    'Build' {
        Build-Host
        $hasher = [Security.Cryptography.SHA256]::Create()
        try { [BitConverter]::ToString($hasher.ComputeHash([IO.File]::ReadAllBytes($binary))).Replace('-','').ToLowerInvariant() }
        finally { $hasher.Dispose() }
    }
    'Install' {
        $allowed = [IO.Path]::GetFullPath((Join-Path $env:ProgramData 'FICCT')) + [IO.Path]::DirectorySeparatorChar
        if (!$target.StartsWith($allowed,[StringComparison]::OrdinalIgnoreCase)) { throw 'Installation must use a dedicated directory below ProgramData\FICCT.' }
        if (Test-Path -LiteralPath $target) {
            $foreign = Get-ChildItem -LiteralPath $target -Force | Where-Object { $_.Name -notin @('FicctDocumentProcessor.exe','service.json') }
            if ($foreign) { throw 'Installation directory contains unrelated files; nothing changed.' }
        }
        $ancestor = $target
        while ($ancestor) {
            if ((Test-Path -LiteralPath $ancestor) -and ((Get-Item -LiteralPath $ancestor -Force).Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw 'Reparse points are not allowed in the installation path.' }
            $ancestor = [IO.Path]::GetDirectoryName($ancestor)
        }
        if (Test-Path -LiteralPath $target) {
            if (Get-ChildItem -LiteralPath $target -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) { throw 'Reparse points are not allowed in installed files.' }
        }
        if (Get-Service -Name $serviceName -ErrorAction SilentlyContinue) { throw 'Service already exists. Inspect it before replacing anything.' }
        foreach ($path in @($settings.root,$settings.python,$settings.php)) {
            if (![IO.Path]::IsPathRooted($path) -or !(Test-Path -LiteralPath $path)) { throw 'Configured runtime paths must exist.' }
        }
        # Runtime/data access is deliberately a separate account-specific preparation.
        # Never grant broad write access to code, private originals or antivirus signatures.
        # Protect the installed executable/configuration against unprivileged modification.
        New-Item -ItemType Directory -Path $target -Force | Out-Null
        $acl = New-Object Security.AccessControl.DirectorySecurity
        $acl.SetAccessRuleProtection($true,$false)
        foreach ($sid in @('S-1-5-18','S-1-5-32-544')) {
            $rule = New-Object Security.AccessControl.FileSystemAccessRule((New-Object Security.Principal.SecurityIdentifier($sid)),'FullControl','ContainerInherit,ObjectInherit','None','Allow')
            $acl.AddAccessRule($rule)
        }
        Set-Acl -LiteralPath $target -AclObject $acl
        Build-Host
        Copy-Item -LiteralPath $configFile -Destination $installedConfig
        # LocalService instead of LocalSystem. No passwords in a command line or service config.
        New-Service -Name $serviceName -BinaryPathName $expectedImage -StartupType Manual -DisplayName 'FICCT - procesamiento documental' -Credential (New-Object Management.Automation.PSCredential('NT AUTHORITY\LocalService',(New-Object Security.SecureString))) | Out-Null
        Invoke-ServiceControl @('sidtype',$serviceName,'unrestricted')
        $serviceSid = (New-Object Security.Principal.NTAccount('NT SERVICE',$serviceName)).Translate([Security.Principal.SecurityIdentifier])
        $readRule = New-Object Security.AccessControl.FileSystemAccessRule($serviceSid,'ReadAndExecute','ContainerInherit,ObjectInherit','None','Allow')
        $acl.AddAccessRule($readRule)
        Set-Acl -LiteralPath $target -AclObject $acl
        Invoke-ServiceControl @('failure',$serviceName,'reset=','86400','actions=','restart/10000/restart/30000/restart/60000')
        Invoke-ServiceControl @('failureflag',$serviceName,'1')
        Write-Output 'Installed in manual mode. Prepare account ACLs/dependencies and verify Start before EnableAutomatic. Reboot validation is still required.'
    }
    'Start' { Start-Service -Name $serviceName; (Get-Service -Name $serviceName).WaitForStatus('Running',[TimeSpan]::FromSeconds(30)) }
    'Stop' { Stop-Service -Name $serviceName; (Get-Service -Name $serviceName).WaitForStatus('Stopped',[TimeSpan]::FromSeconds(30)) }
    'EnableAutomatic' {
        if ((Get-Service -Name $serviceName).Status -ne 'Running') { throw 'Verify a running service before enabling automatic start.' }
        $heartbeatFile = Join-Path $settings.root ('.local\document-processor\' + $settings.worker_name + '.host.json')
        $heartbeat = Get-Content -LiteralPath $heartbeatFile -Raw | ConvertFrom-Json
        if ($heartbeat.mode -ne 'windows-service' -or $heartbeat.state -ne 'running' -or ([DateTime]::UtcNow - [DateTime]::Parse($heartbeat.time_utc).ToUniversalTime()).TotalSeconds -gt 15) { throw 'No recent, running SCM heartbeat.' }
        $dependencyNames = @($settings.required_services | Where-Object { $_ })
        if ($dependencyNames.Count -eq 0 -and $settings.external_database -ne $true) { throw 'Configure database startup dependencies or explicitly confirm an external database before automatic start.' }
        foreach ($dependency in $dependencyNames) {
            if ((Get-Service -Name $dependency -ErrorAction Stop).Status -ne 'Running') { throw 'A configured database dependency is not running.' }
        }
        $checkScript = Join-Path $settings.root 'scripts\windows\check_document_service.py'
        & $settings.python $checkScript --config $installedConfig --host-binary $binary
        if ($LASTEXITCODE -ne 0) { throw 'Service/worker/queue health is not confirmed.' }
        if ($dependencyNames.Count -gt 0) { Invoke-ServiceControl @('config',$serviceName,'depend=',($dependencyNames -join '/')) }
        Invoke-ServiceControl @('config',$serviceName,'start=','delayed-auto')
    }
    'Status' { Get-Service -Name $serviceName -ErrorAction SilentlyContinue | Select-Object Name,Status,StartType }
    'Uninstall' {
        $current = Get-Service -Name $serviceName -ErrorAction Stop
        if ($current.Status -ne 'Stopped') { throw 'Stop the owned service before uninstalling it.' }
        Invoke-ServiceControl @('delete',$serviceName)
        Write-Output 'Service registration removed; runtime, originals and installed files preserved.'
    }
}
