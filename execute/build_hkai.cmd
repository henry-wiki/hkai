@echo off
setlocal
rem HKAI 外殼 build（Tauri / Rust）——cache 一律指去 lab 共用目錄（唔准入 build\）
set "HERE=%~dp0"
if "%HERE:~-1%"=="\" set "HERE=%HERE:~0,-1%"
cd /d "%HERE%\tauri_src"

echo [1/3] Building HKAI.exe (Tauri / Rust / GNU) ...
set "PATH=C:\Users\user\.cargo\bin;C:\Users\user\tools\winlibs\mingw64\bin;%PATH%"
set "CARGO_TARGET_DIR=G:\server\lab\.rust2-build-cache"

cargo build --release
if errorlevel 1 goto failed

echo [2/3] Copy exe ...
copy /y "%CARGO_TARGET_DIR%\release\hkai_shell.exe" "%HERE%\HKAI.exe" >nul

echo [3/3] Done: %HERE%\HKAI.exe
for %%F in ("%HERE%\HKAI.exe") do echo     size: %%~zF bytes    time: %%~tF
echo.
pause
exit /b 0

:failed
echo.
echo BUILD FAILED
echo.
pause
exit /b 1
