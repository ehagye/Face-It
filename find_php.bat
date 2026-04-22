@echo off
REM Face-IT PHP Setup Diagnostic
REM This script finds PHP and adds it to your PATH

echo.
echo ============================================================
echo   FACE-IT PHP SETUP DIAGNOSTIC
echo ============================================================
echo.

echo Searching for PHP installation...
echo.

REM Check common installation locations
set "PHP_FOUND="

REM Check Program Files
if exist "C:\Program Files\PHP" (
    echo ✓ Found: C:\Program Files\PHP
    set "PHP_FOUND=C:\Program Files\PHP"
)

if exist "C:\Program Files (x86)\PHP" (
    echo ✓ Found: C:\Program Files (x86)\PHP
    set "PHP_FOUND=C:\Program Files (x86)\PHP"
)

REM Check XAMPP
if exist "C:\xampp\php" (
    echo ✓ Found: C:\xampp\php
    set "PHP_FOUND=C:\xampp\php"
)

REM Check Laravel Herd
if exist "C:\Laravel\herd\bin" (
    echo ✓ Found: C:\Laravel\herd\bin
    set "PHP_FOUND=C:\Laravel\herd\bin"
)

REM Check Laragon
if exist "C:\laragon\bin\php" (
    echo ✓ Found: C:\laragon\bin\php
    set "PHP_FOUND=C:\laragon\bin\php"
)

REM Check user documents
if exist "%USERPROFILE%\php" (
    echo ✓ Found: %USERPROFILE%\php
    set "PHP_FOUND=%USERPROFILE%\php"
)

echo.

if "!PHP_FOUND!"=="" (
    echo ✗ PHP NOT FOUND
    echo.
    echo ============================================================
    echo   SOLUTIONS:
    echo ============================================================
    echo.
    echo Option 1: Install PHP Standalone
    echo   - Download from: https://www.php.net/downloads
    echo   - Extract to: C:\php
    echo   - Then run this script again
    echo.
    echo Option 2: Install XAMPP (includes PHP)
    echo   - Download from: https://www.apachefriends.org
    echo   - Install to default location
    echo   - Then run this script again
    echo.
    echo Option 3: Use WSL or Docker
    echo   - WSL: wsl php -S localhost:8000
    echo   - Docker: docker run -it php:latest
    echo.
    echo Option 4: Use Python's built-in HTTP server
    echo   - This works for testing the dashboard!
    echo   - python -m http.server 8000 --directory .
    echo.
    pause
    exit /b 1
) else (
    echo ============================================================
    echo   PHP FOUND!
    echo ============================================================
    echo.
    echo Location: !PHP_FOUND!
    echo.
    
    REM Test if PHP works
    "!PHP_FOUND!\php.exe" -v >nul 2>&1
    if !errorlevel! equ 0 (
        echo ✓ PHP is working
        "!PHP_FOUND!\php.exe" -v
    ) else (
        echo ✗ PHP found but not executable
        echo Check file permissions
        pause
        exit /b 1
    )
    
    echo.
    echo ============================================================
    echo   SOLUTION:
    echo ============================================================
    echo.
    echo Add PHP to your system PATH:
    echo.
    echo 1. Press Windows Key + X
    echo 2. Select "System"
    echo 3. Click "Advanced system settings"
    echo 4. Click "Environment Variables"
    echo 5. Under "User variables" click "New"
    echo 6. Variable name: PATH
    echo 7. Variable value: !PHP_FOUND!
    echo 8. Click OK, then OK, then OK
    echo 9. Restart PowerShell/Terminal
    echo 10. Try: php -S localhost:8000
    echo.
    echo OR use the full path:
    echo "!PHP_FOUND!\php.exe" -S localhost:8000
    echo.
    echo OR use Python instead (faster):
    echo python -m http.server 8000 --directory .
    echo.
    pause
)