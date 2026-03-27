$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$php = "C:\xampp\php\php.exe"
$processor = Join-Path $root "functions\run_sync_queue_processor.php"
$logDir = Join-Path $root "logs"
$logFile = Join-Path $logDir "sync_queue.log"
$lockFile = Join-Path $logDir "sync_queue.lock"

if (-not (Test-Path $php)) {
    throw "No se encontró PHP en $php"
}

if (-not (Test-Path $processor)) {
    throw "No se encontró el procesador en $processor"
}

if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir | Out-Null
}

$lockHandle = $null

try {
    $lockHandle = [System.IO.File]::Open($lockFile, [System.IO.FileMode]::OpenOrCreate, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
} catch {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $logFile -Value "$timestamp [skip] Ya existe una corrida activa del sincronizador."
    exit 0
}

try {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $env:XDEBUG_MODE = "off"
    $raw = & $php $processor 100 2>&1
    $output = ($raw | Out-String).Trim()

    if ([string]::IsNullOrWhiteSpace($output)) {
        Add-Content -Path $logFile -Value "$timestamp [warn] El procesador no devolvió salida."
        exit 0
    }

    $singleLine = ($output -replace "\r?\n", " ").Trim()
    Add-Content -Path $logFile -Value "$timestamp $singleLine"
} finally {
    if ($lockHandle -ne $null) {
        $lockHandle.Close()
    }
    if (Test-Path $lockFile) {
        Remove-Item $lockFile -Force -ErrorAction SilentlyContinue
    }
}


