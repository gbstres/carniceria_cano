@echo off
setlocal
title Actualizar sistema Carniceria Cano
color 0A

set "PROJECT_DIR=C:\xampp\htdocs\carniceriacano"
set "GIT_EXE="

if exist "C:\Program Files\Git\cmd\git.exe" set "GIT_EXE=C:\Program Files\Git\cmd\git.exe"
if not defined GIT_EXE if exist "C:\Program Files (x86)\Git\cmd\git.exe" set "GIT_EXE=C:\Program Files (x86)\Git\cmd\git.exe"
if not defined GIT_EXE set "GIT_EXE=git"

echo.
echo ==========================================
echo   ACTUALIZACION DE CODIGO DEL SISTEMA
echo ==========================================
echo.

cd /d "%PROJECT_DIR%"
if errorlevel 1 (
    echo ERROR: No se pudo abrir la carpeta del sistema.
    echo Ruta esperada: %PROJECT_DIR%
    pause
    exit /b 1
)

echo Verificando Git...
"%GIT_EXE%" --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Git no esta instalado o no se encontro en la PC.
    pause
    exit /b 1
)

if not exist ".git" (
    echo ERROR: Esta carpeta no esta ligada a un repositorio Git.
    pause
    exit /b 1
)

echo.
echo Actualizando codigo desde origin/main...
"%GIT_EXE%" pull origin main
if errorlevel 1 (
    echo.
    echo ERROR: No se pudo actualizar el codigo.
    echo Revisa la conexion a internet o si hay cambios locales pendientes.
    pause
    exit /b 1
)

echo.
echo ==========================================
echo   ACTUALIZACION COMPLETADA
echo ==========================================
echo.
echo Ahora ejecuta actualizar_bd.bat si la version incluye cambios de base de datos.
pause
exit /b 0
