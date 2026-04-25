@echo off
REM Face-IT Setup Diagnostic Script
REM Run this to check if everything is configured correctly

setlocal enabledelayedexpansion

echo.
echo ╔═══════════════════════════════════════════════════════════╗
echo ║  FACE-IT SETUP DIAGNOSTIC TOOL                            ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.

REM Check Python
echo [1] Checking Python...
python --version >nul 2>&1
if !errorlevel! equ 0 (
    echo ✓ Python is installed
    for /f "tokens=2" %%i in ('python --version 2^>^&1') do set PYTHON_VERSION=%%i
    echo  Version: !PYTHON_VERSION!
) else (
    echo ✗ Python NOT found - install from python.org
    pause
    exit /b 1
)

echo.
echo [2] Checking required files...

if exist "attendance_server.py" (
    echo ✓ attendance_server.py found
) else (
    echo ✗ attendance_server.py NOT found
)

if exist "test_websocket.py" (
    echo ✓ test_websocket.py found
) else (
    echo ✗ test_websocket.py NOT found
)

if exist ".env" (
    echo ✓ .env file found
) else (
    echo ✗ .env file NOT found - create it with Supabase credentials
)

if exist "AI_Facial_Detection" (
    if exist "AI_Facial_Detection\__init__.py" (
        echo ✓ AI_Facial_Detection package found
    ) else (
        echo ✗ AI_Facial_Detection exists but __init__.py is missing
    )
) else (
    echo ✗ AI_Facial_Detection folder NOT found
)

echo.
echo [3] Checking Python packages...

python -c "import websockets; print('✓ websockets installed')" 2>nul || echo ✗ websockets NOT installed
python -c "import cv2; print('✓ opencv-python installed')" 2>nul || echo ✗ opencv-python NOT installed
python -c "import insightface; print('✓ insightface installed')" 2>nul || echo ✗ insightface NOT installed
python -c "import numpy; print('✓ numpy installed')" 2>nul || echo ✗ numpy NOT installed
python -c "import supabase; print('✓ supabase installed')" 2>nul || echo ✗ supabase NOT installed
python -c "import dotenv; print('✓ python-dotenv installed')" 2>nul || echo ✗ python-dotenv NOT installed

echo.
echo [4] Checking port 8765...
netstat -ano | findstr 8765 >nul 2>&1
if !errorlevel! equ 0 (
    echo ✓ Port 8765 is in use (server might be running)
) else (
    echo ✓ Port 8765 is available
)

echo.
echo [5] Checking PHP...
php --version >nul 2>&1
if !errorlevel! equ 0 (
    echo ✓ PHP is installed
    php --version | findstr /R "^PHP" | set /p PHP_VERSION=
    echo  !PHP_VERSION!
) else (
    echo ✗ PHP NOT installed (optional, but recommended)
)

echo.
echo ╔═══════════════════════════════════════════════════════════╗
echo ║  SUMMARY                                                  ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
echo If all checks passed (all ✓):
echo   1. Run: python attendance_server.py --class-id 4
echo   2. In another window: python test_websocket.py connection
echo   3. Should see "✓ Connected"
echo.
echo If you see ✗ errors:
echo   - Missing files: Check folder structure
echo   - Missing packages: Run "pip install -r requirements.txt"
echo   - No .env file: Create it with Supabase credentials
echo.
echo Press any key to close...
pause