@echo off
setlocal
title Actualizar base de datos Carniceria Cano
color 0E

set "PROJECT_DIR=C:\xampp\htdocs\carniceriacano"
set "PHP_EXE=C:\xampp\php\php.exe"
set "MIGRATION_RUNNER=%PROJECT_DIR%\functions\run_sql_migrations.php"

echo.
echo ==========================================
echo   ACTUALIZACION DE BASE DE DATOS
echo ==========================================
echo.

cd /d "%PROJECT_DIR%"
if errorlevel 1 (
    echo ERROR: No se pudo abrir la carpeta del sistema.
    echo Ruta esperada: %PROJECT_DIR%
    pause
    exit /b 1
)

if not exist "%PHP_EXE%" (
    echo ERROR: No se encontro PHP en:
    echo %PHP_EXE%
    pause
    exit /b 1
)

if not exist "%MIGRATION_RUNNER%" (
    echo ERROR: No se encontro el ejecutor de migraciones:
    echo %MIGRATION_RUNNER%
    pause
    exit /b 1
)

if not exist "%PROJECT_DIR%\db\migrations" (
    echo ERROR: No existe la carpeta db\migrations.
    pause
    exit /b 1
)

echo Aplicando migraciones...
"%PHP_EXE%" "%MIGRATION_RUNNER%"
if errorlevel 1 (
    echo.
    echo ERROR: Fallo la actualizacion de base de datos.
    pause
    exit /b 1
)

echo.
echo ==========================================
echo   BASE DE DATOS ACTUALIZADA
echo ==========================================
echo.
pause
exit /b 0
