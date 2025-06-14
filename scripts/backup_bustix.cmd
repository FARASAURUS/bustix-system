@echo off
:: Set tanggal dan waktu (format: YYYY-MM-DD_HH-MM-SS)
for /f "tokens=1-4 delims=/ " %%a in ("%date%") do (
    set YYYY=%%d
    set MM=%%b
    set DD=%%c
)
for /f "tokens=1-3 delims=:. " %%a in ("%time%") do (
    set HH=%%a
    set Min=%%b
    set Sec=%%c
)

:: Hilangkan spasi di jam jika <10
if "%HH:~0,1%"==" " set HH=0%HH:~1,1%

:: Lokasi file backup
set FILE_BACKUP=D:\laragon\www\bustix-system\backups\bustix_%YYYY%-%MM%-%DD%_%HH%-%Min%-%Sec%.sql


:: Jalankan mysqldump untuk database bustix
"D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe" -u root --routines bustix > "%FILE_BACKUP%"

echo Backup selesai: %FILE_BACKUP%
pause