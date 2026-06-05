# Script to download Composer and install dependencies

Write-Host "=== Installing Composer and Dependencies ===" -ForegroundColor Cyan
Write-Host ""

# Check PHP
$phpPath = "D:\App\Xampp\php\php.exe"
if (-not (Test-Path $phpPath)) {
    Write-Host "PHP not found at: $phpPath" -ForegroundColor Red
    Write-Host "Please check your PHP path." -ForegroundColor Yellow
    exit 1
}

Write-Host "Found PHP: $phpPath" -ForegroundColor Green

# Download Composer
$composerPhar = "composer.phar"
if (-not (Test-Path $composerPhar)) {
    Write-Host "Downloading Composer..." -ForegroundColor Yellow
    try {
        Invoke-WebRequest -Uri "https://getcomposer.org/download/latest-stable/composer.phar" -OutFile $composerPhar
        Write-Host "Composer downloaded successfully" -ForegroundColor Green
    } catch {
        Write-Host "Error downloading Composer: $_" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "Composer already exists" -ForegroundColor Green
}

# Run composer install
Write-Host ""
Write-Host "Installing dependencies..." -ForegroundColor Yellow
Write-Host ""

& $phpPath $composerPhar install --ignore-platform-req=ext-intl --ignore-platform-req=ext-zip --no-interaction

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "=== Completed! ===" -ForegroundColor Green
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Cyan
    Write-Host "1. Enable intl extension in php.ini (see FIX_INTL_EXTENSION.md)" -ForegroundColor Yellow
    Write-Host "2. Run: php artisan key:generate" -ForegroundColor Yellow
    Write-Host "3. Configure database in .env" -ForegroundColor Yellow
    Write-Host "4. Run: php artisan migrate" -ForegroundColor Yellow
} else {
    Write-Host ""
    Write-Host "Error occurred during installation" -ForegroundColor Red
    Write-Host "Exit code: $LASTEXITCODE" -ForegroundColor Red
}
