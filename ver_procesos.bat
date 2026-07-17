@echo off
setlocal enabledelayedexpansion

set "MYSQLDIR="
for /d %%D in ("C:\laragon\bin\mysql\*") do (
    if exist "%%D\bin\mysql.exe" set "MYSQLDIR=%%D\bin"
)
if not defined MYSQLDIR (
    echo No se encontro mysql.exe
    pause
    exit /b 1
)

echo ============================================================
echo  PROCESOS ACTIVOS  (mira la columna estado y el tiempo)
echo ============================================================
"!MYSQLDIR!\mysql.exe" -u root -e "SELECT ID, TIME AS seg, STATE AS estado, LEFT(INFO,60) AS consulta FROM information_schema.PROCESSLIST WHERE INFO IS NOT NULL AND INFO NOT LIKE '%%PROCESSLIST%%';"

echo.
echo ============================================================
echo  PROGRESO DE LA CONVERSION (porcentaje, si esta disponible)
echo ============================================================
"!MYSQLDIR!\mysql.exe" -u root -e "SELECT WORK_COMPLETED, WORK_ESTIMATED, ROUND(100*WORK_COMPLETED/NULLIF(WORK_ESTIMATED,0),1) AS porcentaje FROM performance_schema.events_stages_current;"

echo.
pause
