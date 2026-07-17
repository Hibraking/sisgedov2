@echo off
setlocal enabledelayedexpansion

REM Migracion de charset utf8mb3 a utf8mb4 - SISGEDO
REM Seguro en MySQL 8.x. Puede tardar en tablas grandes.

set "DB=xyltrylx_sisgedo_muni"
set "USER=root"
set "BASEDIR=%~dp0"

set "MYSQLDIR="
for /d %%D in ("C:\laragon\bin\mysql\*") do (
    if exist "%%D\bin\mysql.exe" set "MYSQLDIR=%%D\bin"
)

if not defined MYSQLDIR (
    echo No se encontro mysql.exe en C:\laragon\bin\mysql
    pause
    exit /b 1
)

echo Usando MySQL en: !MYSQLDIR!
echo Convirtiendo tablas a utf8mb4 (puede tardar un poco)...
echo.

"!MYSQLDIR!\mysql.exe" -u %USER% %DB% < "%BASEDIR%utf8mb4_migracion.sql"
if errorlevel 1 (
    echo.
    echo Ocurrio un error. Revisa el mensaje de arriba.
    pause
    exit /b 1
)

echo.
echo ============================================================
echo  Migracion utf8mb4 completada. Revisa el charset por tabla arriba.
echo ============================================================
pause
