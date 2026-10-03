@echo off
setlocal
title Arreglar y Sincronizar Sucursal Carniceria Cano
color 0A

set "PROJECT_DIR=C:\xampp\htdocs\carniceriacano"
set "PHP_EXE=C:\xampp\php\php.exe"
set "FIX_RUNNER=%PROJECT_DIR%\scripts\arreglar_sucursal.php"

echo.
echo ======================================================
echo   ARREGLAR E INICIAR SINCRONIZACION DE SUCURSAL A GCP
echo ======================================================
echo.

cd /d "%PROJECT_DIR%"
if errorlevel 1 (
    echo ERROR: No se pudo abrir la carpeta del sistema.
    pause
    exit /b 1
)

if not exist "%PHP_EXE%" (
    echo ERROR: No se encontro PHP en: %PHP_EXE%
    pause
    exit /b 1
)

if not exist "%FIX_RUNNER%" (
    echo ERROR: No se encontro el reparador en: %FIX_RUNNER%
    pause
    exit /b 1
)

echo Ejecutando reparacion y sincronizacion...
"%PHP_EXE%" "%FIX_RUNNER%"
if errorlevel 1 (
    echo.
    echo ERROR: Ocurrio un fallo durante la reparacion.
    pause
    exit /b 1
)

echo.
pause
exit /b 0
