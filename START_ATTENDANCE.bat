@echo off
REM Face-IT Dashboard - Complete Startup
REM This script starts the attendance server, PHP server, and opens the dashboard

setlocal enabledelayedexpansion

echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║  FACE-IT ATTENDANCE SYSTEM - LOCAL STARTUP                   ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.

REM Configuration
set "PROJECT_DIR=C:\Users\clpur\Codin\classes\CAPSTONE\Face-It"
set "CLASS_ID=4"
set "CAMERA=0"

REM Verify project directory
cd /d "%PROJECT_DIR%" || (
    echo ERROR: Project directory not found
    echo Expected: %PROJECT_DIR%
    pause
    exit /b 1
)

echo Project directory: %CD%
echo.

REM Prompt for class ID
echo ============================================================
echo STEP 1: Configuration
echo ============================================================
set /p CLASS_ID="Enter Class ID (default 4): "
set /p CAMERA="Enter Camera Index (default 0): "

if "%CLASS_ID%"=="" set CLASS_ID=4
if "%CAMERA%"=="" set CAMERA=0

echo.
echo Configuration:
echo   - Class ID: %CLASS_ID%
echo   - Camera: %CAMERA%
echo   - Attendance Server: ws://localhost:8765
echo   - PHP Server: http://localhost:8000
echo.

REM Start Attendance Server
echo ============================================================
echo STEP 2: Starting Attendance Server
echo ============================================================
echo Starting on ws://localhost:8765
echo.
echo Waiting for server to load models (this takes ~10 seconds)...
echo.
start "Attendance Server" cmd /k "python attendance_server.py --class-id %CLASS_ID% --camera %CAMERA%"
timeout /t 5 /nobreak

REM Start PHP Server
echo ============================================================
echo STEP 3: Starting PHP Server
echo ============================================================
echo Starting on http://localhost:8000
echo.
start "PHP Server" cmd /k "php -S localhost:8000"
timeout /t 2 /nobreak

REM Open Dashboard
echo ============================================================
echo STEP 4: Opening Dashboard
echo ============================================================
echo Opening http://localhost:8000/main.php
echo.
start "" "http://localhost:8000/main.php"

timeout /t 2 /nobreak

REM Display instructions
cls
echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║  SERVICES STARTED SUCCESSFULLY                               ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.
echo Services running:
echo   ✓ Attendance Server (Face Detection)
echo     ws://localhost:8765
echo.
echo   ✓ PHP Server (Dashboard)
echo     http://localhost:8000
echo.
echo   ✓ Dashboard UI
echo     http://localhost:8000/main.php
echo.
echo ============================================================
echo WHAT TO DO NOW:
echo ============================================================
echo.
echo 1. Dashboard should open in your browser
echo.
echo 2. Check status on dashboard:
echo    - Look for "Server: Connected" (green)
echo    - Select a class from the dropdown
echo    - Click "Start Attendance"
echo.
echo 3. Face your camera - you should see:
echo    - Student name highlighted in green
echo    - Activity log showing detections
echo    - FPS counter updating
echo.
echo 4. To run automated tests (optional):
echo    Open new terminal and run:
echo    python test_websocket.py all
echo.
echo 5. Check browser console (F12) for any errors
echo.
echo ============================================================
echo TROUBLESHOOTING:
echo ============================================================
echo.
echo If you see "Server: Disconnected" on dashboard:
echo   - Check Attendance Server terminal for errors
echo   - Verify WebSocket server started properly
echo   - Check if port 8765 is in use
echo.
echo If camera won't open:
echo   - Try different camera index (0, 1, 2...)
echo   - Check if another app is using the camera
echo   - Close Teams/Zoom if running
echo.
echo If no detections:
echo   - Check lighting (needs good lighting)
echo   - Face students face the camera
echo   - Lower threshold: --threshold 0.45
echo.
echo ============================================================
echo.
echo Press any key to close this window...
pause