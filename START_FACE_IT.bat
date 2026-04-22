@echo off
REM Face-IT Dashboard - Universal Startup (PHP or Python)
REM Works whether or not PHP is installed

setlocal enabledelayedexpansion

echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║  FACE-IT ATTENDANCE SYSTEM - STARTUP                         ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.

REM Configuration
set "PROJECT_DIR=C:\Users\clpur\Codin\classes\CAPSTONE\Face-It"
set "CLASS_ID=4"
set "CAMERA=0"
set "HTTP_SERVER="

REM Verify project directory
cd /d "%PROJECT_DIR%" || (
    echo ERROR: Project directory not found: %PROJECT_DIR%
    pause
    exit /b 1
)

echo Project directory: OK
echo.

REM ============================================================
REM DETECT HTTP SERVER
REM ============================================================
echo Detecting available HTTP server...
echo.

REM Try PHP first
php -v >nul 2>&1
if !errorlevel! equ 0 (
    echo ✓ PHP found
    set "HTTP_SERVER=php"
) else (
    echo ✗ PHP not found, using Python instead
    set "HTTP_SERVER=python"
)

echo.

REM ============================================================
REM STEP 1: Get configuration
REM ============================================================
echo ============================================================
echo STEP 1: Configuration
echo ============================================================
set /p CLASS_ID="Enter Class ID (default 4): "
set /p CAMERA="Enter Camera Index (default 0): "

if "!CLASS_ID!"=="" set CLASS_ID=4
if "!CAMERA!"=="" set CAMERA=0

echo.
echo Configuration:
echo   - Class ID: !CLASS_ID!
echo   - Camera: !CAMERA!
echo   - HTTP Server: !HTTP_SERVER!
echo   - Attendance Server: ws://localhost:8765
echo   - Dashboard Server: http://localhost:8000
echo.

REM ============================================================
REM STEP 2: Start Attendance Server
REM ============================================================
echo ============================================================
echo STEP 2: Starting Attendance Server
echo ============================================================
echo Starting on ws://localhost:8765
echo.
echo This loads the face detection model (takes ~10 seconds)
echo.
start "Attendance Server" cmd /k "python attendance_server.py --class-id !CLASS_ID! --camera !CAMERA!"
timeout /t 5 /nobreak

REM ============================================================
REM STEP 3: Start HTTP Server
REM ============================================================
echo ============================================================
echo STEP 3: Starting HTTP Server
echo ============================================================

if "!HTTP_SERVER!"=="php" (
    echo Using PHP: php -S localhost:8000
    start "HTTP Server (PHP)" cmd /k "php -S localhost:8000"
) else (
    echo Using Python: python -m http.server 8000
    start "HTTP Server (Python)" cmd /k "python -m http.server 8000 --directory ."
)

timeout /t 2 /nobreak

REM ============================================================
REM STEP 4: Open Dashboard
REM ============================================================
echo ============================================================
echo STEP 4: Opening Dashboard
echo ============================================================
echo Opening http://localhost:8000/main.php
echo.

REM Wait a moment for server to start
timeout /t 2 /nobreak

start "" "http://localhost:8000/main.php"

timeout /t 2 /nobreak

REM ============================================================
REM Summary
REM ============================================================
cls
echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║  STARTUP COMPLETE!                                           ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.
echo Services:
echo   ✓ Attendance Server
echo     ws://localhost:8765
echo     (Face detection, real-time broadcasting)
echo.
echo   ✓ HTTP Server (!HTTP_SERVER!)
echo     http://localhost:8000
echo     (Dashboard & static files)
echo.
echo   ✓ Dashboard UI
echo     http://localhost:8000/main.php
echo     (Should open in your browser)
echo.
echo ============================================================
echo WHAT TO DO NOW:
echo ============================================================
echo.
echo 1. Wait for dashboard to fully load in browser
echo.
echo 2. Check the status box - look for:
echo    ✓ Server: Connected (should be GREEN)
echo    ✓ Attendance: Inactive (will show when you start)
echo.
echo 3. Select a class from the dropdown
echo.
echo 4. Click "Start Attendance" button
echo.
echo 5. Face the camera - when detected:
echo    • Your name will highlight in GREEN
echo    • Activity log will show detection
echo    • FPS counter will update
echo.
echo 6. Stop attendance when done
echo.
echo ============================================================
echo OPTIONAL: Run Automated Tests
echo ============================================================
echo.
echo Open a new PowerShell/Terminal and run:
echo.
echo   python test_websocket.py all
echo.
echo This tests all WebSocket events without needing faces
echo.
echo ============================================================
echo TROUBLESHOOTING:
echo ============================================================
echo.
if "!HTTP_SERVER!"=="python" (
    echo NOTE: Using Python HTTP server instead of PHP
    echo PHP was not found, but Python server works fine!
    echo If you need PHP, install it and restart this script
    echo.
)
echo Dashboard shows "Server: Disconnected"?
echo   - Check Attendance Server terminal for errors
echo   - Verify it says "[SERVER] Listening..."
echo   - Wait 10 seconds for model to load
echo.
echo Camera won't open?
echo   - Try camera index 1 or 2 instead of 0
echo   - Close Teams/Zoom if they're using camera
echo   - Check if camera is connected
echo.
echo No face detections?
echo   - Need good lighting (bright room)
echo   - Face must be directly facing camera
echo   - Check Attendance Server terminal for face detections
echo   - Try lowering threshold: --threshold 0.45
echo.
echo ============================================================
echo.
echo Dashboard should be opening now...
echo.
echo Press any key to close this window when done testing
pause