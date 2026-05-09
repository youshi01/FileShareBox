param(
    [string]$IsccPath = "",
    [string]$IssFile = ""
)

$ErrorActionPreference = "Stop"

function Write-Step([string]$message) {
    Write-Host "[build_installer] $message"
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent (Split-Path -Parent $scriptDir)

if ([string]::IsNullOrWhiteSpace($IssFile)) {
    $IssFile = Join-Path $scriptDir "FileCodeBoxPortable.iss"
}

$portableDir = Join-Path $projectRoot "dist\portable"
if (-not (Test-Path $portableDir)) {
    throw "Portable directory does not exist: $portableDir`nRun scripts/package/build_portable.ps1 first."
}

Write-Step "Sanitizing portable directory for installer"
$removeTargets = @(
    (Join-Path $portableDir "start.log"),
    (Join-Path $portableDir "logs\*"),
    (Join-Path $portableDir "runtime\logs\*"),
    (Join-Path $portableDir "runtime\run\*"),
    (Join-Path $portableDir "runtime\mysql-data"),
    (Join-Path $portableDir "app\storage\.installed")
)

foreach ($target in $removeTargets) {
    if (Test-Path $target) {
        Remove-Item -Recurse -Force $target -ErrorAction SilentlyContinue
    }
}

if ([string]::IsNullOrWhiteSpace($IsccPath)) {
    $candidates = @(
        "C:\Program Files (x86)\Inno Setup 7\ISCC.exe",
        "C:\Program Files\Inno Setup 7\ISCC.exe",
        "C:\Program Files (x86)\Inno Setup 6\ISCC.exe",
        "C:\Program Files\Inno Setup 6\ISCC.exe"
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            $IsccPath = $candidate
            break
        }
    }
}

if ([string]::IsNullOrWhiteSpace($IsccPath) -or -not (Test-Path $IsccPath)) {
    throw "ISCC.exe not found. Install Inno Setup 6, or pass -IsccPath."
}

if (-not (Test-Path $IssFile)) {
    throw "ISS file not found: $IssFile"
}

Write-Step "Compiling installer via ISCC"
Write-Step "ISCC: $IsccPath"
Write-Step "ISS : $IssFile"

Push-Location $scriptDir
try {
    & $IsccPath $IssFile
    if ($LASTEXITCODE -ne 0) {
        throw "ISCC failed with exit code $LASTEXITCODE"
    }
} finally {
    Pop-Location
}

Write-Step "Done. Installer output: $projectRoot\dist\installer"
