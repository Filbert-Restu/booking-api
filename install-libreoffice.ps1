Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  LibreOffice Portable Installer for PKL" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Check if already installed
$portablePath1 = "LibreOfficePortable\App\libreoffice\program\soffice.exe"
$portablePath2 = "libreoffice-portable\App\libreoffice\program\soffice.exe"

if ((Test-Path $portablePath1) -or (Test-Path $portablePath2)) {
    Write-Host "✅ LibreOffice Portable already installed!" -ForegroundColor Green
    if (Test-Path $portablePath1) {
        Write-Host "   Location: $portablePath1" -ForegroundColor Gray
        & $portablePath1 --version
    } else {
        Write-Host "   Location: $portablePath2" -ForegroundColor Gray
        & $portablePath2 --version
    }
    Write-Host ""
    Write-Host "No action needed. You can close this window." -ForegroundColor Green
    exit 0
}

Write-Host "📥 Installing LibreOffice Portable..." -ForegroundColor Yellow
Write-Host ""

# Try different installation methods
Write-Host "Checking available installation methods..." -ForegroundColor Cyan

# Method 1: Winget (Windows Package Manager)
Write-Host ""
Write-Host "[Method 1] Trying winget..." -ForegroundColor Cyan
$wingetAvailable = Get-Command winget -ErrorAction SilentlyContinue
if ($wingetAvailable) {
    Write-Host "✓ Winget found!" -ForegroundColor Green
    Write-Host "Installing LibreOffice (this may take 5-10 minutes)..." -ForegroundColor Yellow

    try {
        $process = Start-Process winget -ArgumentList "install", "--id", "TheDocumentFoundation.LibreOffice", `
            "--silent", "--accept-source-agreements", "--accept-package-agreements" `
            -Wait -PassThru -NoNewWindow

        if ($process.ExitCode -eq 0) {
            Write-Host "✅ LibreOffice installed successfully via winget!" -ForegroundColor Green
            Write-Host "   Location: C:\Program Files\LibreOffice\program\soffice.exe" -ForegroundColor Gray
            exit 0
        } else {
            Write-Host "⚠️  Winget installation failed (Exit code: $($process.ExitCode))" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "⚠️  Winget installation error: $($_.Exception.Message)" -ForegroundColor Yellow
    }
} else {
    Write-Host "✗ Winget not available" -ForegroundColor Yellow
}

# Method 2: Chocolatey
Write-Host ""
Write-Host "[Method 2] Trying Chocolatey..." -ForegroundColor Cyan
$chocoAvailable = Get-Command choco -ErrorAction SilentlyContinue
if ($chocoAvailable) {
    Write-Host "✓ Chocolatey found!" -ForegroundColor Green
    Write-Host "Installing LibreOffice..." -ForegroundColor Yellow

    try {
        $process = Start-Process choco -ArgumentList "install", "libreoffice-fresh", "-y" `
            -Wait -PassThru -NoNewWindow -Verb RunAs

        if ($process.ExitCode -eq 0) {
            Write-Host "✅ LibreOffice installed successfully via Chocolatey!" -ForegroundColor Green
            exit 0
        } else {
            Write-Host "⚠️  Chocolatey installation failed" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "⚠️  Chocolatey error: $($_.Exception.Message)" -ForegroundColor Yellow
    }
} else {
    Write-Host "✗ Chocolatey not available" -ForegroundColor Yellow
}

# Method 3: Manual Download Instructions
Write-Host ""
Write-Host "============================================" -ForegroundColor Red
Write-Host "  MANUAL INSTALLATION REQUIRED" -ForegroundColor Red
Write-Host "============================================" -ForegroundColor Red
Write-Host ""
Write-Host "Automated installation not available." -ForegroundColor Yellow
Write-Host "Please install manually using one of these options:" -ForegroundColor Yellow
Write-Host ""

Write-Host "Option 1: Download Portable Version (Recommended)" -ForegroundColor Cyan
Write-Host "  1. Open: https://portableapps.com/apps/office/libreoffice_portable"
Write-Host "  2. Click 'Download' button"
Write-Host "  3. Run the downloaded .paf.exe file"
Write-Host "  4. Choose install location:" -ForegroundColor Yellow
Write-Host "     $PWD" -ForegroundColor Green
Write-Host "  5. It will create: LibreOfficePortable\" -ForegroundColor Gray
Write-Host ""

Write-Host "Option 2: Download Regular Installer" -ForegroundColor Cyan
Write-Host "  1. Open: https://www.libreoffice.org/download/download/"
Write-Host "  2. Download Windows x86-64 version"
Write-Host "  3. Run installer (will install to C:\Program Files\LibreOffice)"
Write-Host ""

Write-Host "Option 3: Use Winget (if you have Windows 10+)" -ForegroundColor Cyan
Write-Host "  Open PowerShell as Administrator and run:"
Write-Host "  winget install TheDocumentFoundation.LibreOffice" -ForegroundColor Green
Write-Host ""

Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "After installation, run this script again to verify." -ForegroundColor Yellow
Write-Host ""

# Open download page
$response = Read-Host "Open download page in browser? (Y/N)"
if ($response -eq 'Y' -or $response -eq 'y') {
    Start-Process "https://portableapps.com/apps/office/libreoffice_portable"
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
