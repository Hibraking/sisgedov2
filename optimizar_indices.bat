@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul

REM ============================================================
REM  Agrega índices a la BD de SISGEDO (mejora de rendimiento).
REM  Es seguro: solo AGREGA índices, no cambia datos.
REM ============================================================

set "DB=xyltrylx_sisgedo_muni"
set "USER=root"
set "BASEDIR=%~dp0"

set "MYSQLDIR="
for /d %%D in ("C:\laragon\bin\mysql\*") do (
    if exist "%%D\bin\mysql.exe" set "MYSQLDIR=%%D\bin"
)

if not defined MYSQLDIR (
    echo No se encontro mysql.exe en C:\laragon\bin\mysql
    echo Abre Laragon y confirma que MySQL este instalado.
    pause
    exit /b 1
)

echo Usando MySQL en: !MYSQLDIR!
echo Aplicando indices (puede tardar un poco en las tablas grandes)...
echo.

"!MYSQLDIR!\mysql.exe" -u %USER% %DB% < "%BASEDIR%optimizar_indices.sql"
if errorlevel 1 (
    echo.
    echo Ocurrio un error. Revisa el mensaje de arriba.
    pause
    exit /b 1
)

echo.
echo ============================================================
echo  Indices aplicados. Revisa el conteo por tabla mostrado arriba.
echo ============================================================
pause
