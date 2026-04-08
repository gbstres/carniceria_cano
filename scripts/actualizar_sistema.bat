@echo off
setlocal
title Actualizar sistema Carniceria Cano
color 0A

echo.
echo ==========================================
echo   ACTUALIZACION DE CODIGO DEL SISTEMA
echo ==========================================
echo.

cd /d "%~dp0.."

echo Verificando Git...
git --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Git no esta instalado o no esta disponible en PATH.
    echo Instala Git antes de continuar.
    echo.
    pause
    exit /b 1
)

if not exist ".git" (
    echo ERROR: Esta carpeta no esta ligada a Git.
    echo Ruta actual: %cd%
    echo.
    pause
    exit /b 1
)

echo Carpeta del sistema: %cd%
echo.
echo Descargando cambios de origin/main...
git pull origin main
if errorlevel 1 (
    echo.
    echo ERROR: No se pudo actualizar el codigo.
    echo Revisa conexion, cambios locales o conflictos de Git.
    echo.
    pause
    exit /b 1
)

echo.
echo ==========================================
echo   CODIGO ACTUALIZADO CORRECTAMENTE
echo ==========================================
echo.
echo Siguiente paso:
echo   Ejecuta actualizar_bd.bat si la version incluye cambios de base de datos.
echo.
pause
exit /b 0
