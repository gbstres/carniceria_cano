@echo off
setlocal
if "%~1"=="" (
  echo Uso: backfill_sucursal_inicial.bat ID_SUCURSAL [BATCH_SIZE]
  exit /b 1
)
set BATCH_SIZE=%~2
if "%BATCH_SIZE%"=="" set BATCH_SIZE=20
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\carniceriacano\scripts\backfill_sucursal_inicial.ps1" -IdSucursal %1 -BatchSize %BATCH_SIZE%
