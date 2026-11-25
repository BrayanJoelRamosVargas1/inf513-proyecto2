# Read-only configuration resolution. No registry, service, ACL or process changes.
function Read-DocumentServiceConfiguration {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)][string]$Action,
        [Parameter(Mandatory=$true)][string]$ConfigPath,
        [Parameter(Mandatory=$true)][string]$InstallDirectory
    )
    if ($Action -notin @('Build','Install','Start','Stop','Status','EnableAutomatic','Uninstall')) {
        throw 'Unknown document service action.'
    }
    $target = [IO.Path]::GetFullPath($InstallDirectory)
    $installedFile = Join-Path $target 'service.json'
    $suppliedFile = (Resolve-Path -LiteralPath $ConfigPath -ErrorAction Stop).Path
    if ($Action -in @('Build','Install')) {
        $selectedFile = $suppliedFile
        $bytes = [IO.File]::ReadAllBytes($selectedFile)
    } else {
        # All management settings come from this single installed snapshot.
        $selectedFile = (Resolve-Path -LiteralPath $installedFile -ErrorAction Stop).Path
        $bytes = [IO.File]::ReadAllBytes($selectedFile)
        if (![String]::Equals($suppliedFile,$selectedFile,[StringComparison]::OrdinalIgnoreCase)) {
            $external = [IO.File]::ReadAllBytes($suppliedFile)
            $hasher = [Security.Cryptography.SHA256]::Create()
            try {
                $same = [Convert]::ToBase64String($hasher.ComputeHash($external)) -eq [Convert]::ToBase64String($hasher.ComputeHash($bytes))
            } finally { $hasher.Dispose() }
            if (!$same) { throw 'External configuration differs from installed service.json. Use the installed configuration; no management action was performed.' }
        }
    }
    $text = [Text.Encoding]::UTF8.GetString($bytes).TrimStart([char]0xFEFF)
    $settings = $text | ConvertFrom-Json -ErrorAction Stop
    foreach ($name in @($settings.service_name,$settings.worker_name)) {
        if ($name -notmatch '^[A-Za-z0-9_-]{1,40}$') { throw 'Invalid service or worker name.' }
    }
    [pscustomobject]@{
        Settings = $settings
        SelectedFile = $selectedFile
        InstalledFile = $installedFile
        Target = $target
    }
}
