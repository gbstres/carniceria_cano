@echo off
setlocal
title Actualizar base de datos Carniceria Cano
color 0E

echo.
echo ==========================================
echo   ACTUALIZACION DE BASE DE DATOS
echo ==========================================
echo.

cd /d "%~dp0.."

if not exist "db\migrations" (
    echo ERROR: No existe la carpeta db\migrations
    echo.
    pause
    exit /b 1
)

if not exist "functions\run_sql_migrations.php" (
    echo ERROR: No existe el ejecutor de migraciones.
    echo.
    pause
    exit /b 1
)

echo Aplicando migraciones SQL...
C:\xampp\php\php.exe "%cd%\functions\run_sql_migrations.php"
if errorlevel 1 (
    echo.
    echo ERROR: Fallo la actualizacion de base de datos.
    echo Revisa el mensaje mostrado arriba.
    echo.
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
