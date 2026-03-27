param(
    [Parameter(Mandatory = $true)]
    [int]$IdSucursal,
    [int]$BatchSize = 20
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$php = "C:\xampp\php\php.exe"
$script = Join-Path $root "functions\backfill_sucursal.php"

if (-not (Test-Path $php)) {
    throw "No se encontró PHP en $php"
}

if (-not (Test-Path $script)) {
    throw "No se encontró el script de backfill en $script"
}

$env:XDEBUG_MODE = "off"

function Invoke-Backfill {
    param(
        [string]$Table,
        [int]$Limit = 0,
        [int]$Offset = 0
    )

    $args = @($script, $IdSucursal, "--only=$Table")
    if ($Limit -gt 0) {
        $args += "--limit=$Limit"
        $args += "--offset=$Offset"
    }

    Write-Host ""
    Write-Host "Ejecutando $Table" -ForegroundColor Cyan
    if ($Limit -gt 0) {
        Write-Host "Lote offset=$Offset limit=$Limit"
    }

    $raw = & $php @args
    if ($LASTEXITCODE -ne 0) {
        throw "PHP devolvió código $LASTEXITCODE para $Table"
    }

    $jsonLine = ($raw | Select-Object -Last 1)
    $result = $jsonLine | ConvertFrom-Json
    if (-not $result.ok) {
        throw "Falló $Table. Error: $($result.error)"
    }

    Write-Host "OK $Table. Filas: $($result.rows_processed)" -ForegroundColor Green
    return $result
}

$singleTables = @(
    "cc_categorias",
    "cc_clientes",
    "cc_proveedores",
    "cc_derivados",
    "cc_ventas",
    "cc_det_ventas",
    "cc_pagos_clientes",
    "cc_compras",
    "cc_det_compras",
    "cc_gastos",
    "cc_entradas",
    "cc_cierre",
    "cc_cierre_clientes"
)

foreach ($table in $singleTables) {
    Invoke-Backfill -Table $table | Out-Null
}

$offset = 0
while ($true) {
    $result = Invoke-Backfill -Table "cc_productos" -Limit $BatchSize -Offset $offset
    if ($result.rows_processed -lt $BatchSize) {
        break
    }
    $offset += $BatchSize
}

Write-Host ""
Write-Host "Carga inicial completada para sucursal $IdSucursal" -ForegroundColor Yellow
