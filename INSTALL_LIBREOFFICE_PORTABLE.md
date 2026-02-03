# Install LibreOffice Portable - Quick Guide

## 📥 Download LibreOffice Portable

### Option 1: PortableApps (Recommended)

1. **Download:** https://portableapps.com/apps/office/libreoffice_portable
   - Klik tombol "Download"
   - File size: ~300MB
   - Format: `.paf.exe`

2. **Install ke project folder:**

   ```powershell
   # Jalankan installer
   # Pilih install location: E:\PKL\peminjaman-tempat\booking-api
   # Folder akan jadi: booking-api\LibreOfficePortable\
   ```

3. **Verify:**
   ```powershell
   cd E:\PKL\peminjaman-tempat\booking-api
   Test-Path "LibreOfficePortable\App\libreoffice\program\soffice.exe"
   # Should return: True
   ```

### Option 2: Direct Download (Alternative)

1. **Download:** https://www.libreoffice.org/download/portable-versions/
   - Pilih "Windows x86-64 Portable"
   - Extract ke: `booking-api\libreoffice-portable\`

2. **Verify:**
   ```powershell
   cd E:\PKL\peminjaman-tempat\booking-api
   Test-Path "libreoffice-portable\App\libreoffice\program\soffice.exe"
   # Should return: True
   ```

### Option 3: Install Global (System-wide)

Jika mau install untuk semua aplikasi:

```powershell
# Using winget (Windows 10+)
winget install TheDocumentFoundation.LibreOffice

# OR download installer:
# https://www.libreoffice.org/download/download/
# Install ke: C:\Program Files\LibreOffice
```

---

## ✅ Verification

After installation, verify LibreOffice is detected:

```powershell
cd E:\PKL\peminjaman-tempat\booking-api
php artisan tinker
```

Then in tinker:

```php
$controller = new App\Http\Controllers\DocumentTemplateController();
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('getLibreOfficePath');
$method->setAccessible(true);
echo $method->invoke($controller);
// Should show path to soffice.exe
```

OR simple test:

```powershell
# Test portable
& "LibreOfficePortable\App\libreoffice\program\soffice.exe" --version

# Test global
& "C:\Program Files\LibreOffice\program\soffice.exe" --version
```

---

## 🎯 Quick Install Commands

### For Portable (Recommended for development):

```powershell
cd E:\PKL\peminjaman-tempat\booking-api

# Download portable version (use browser)
Start-Process "https://portableapps.com/apps/office/libreoffice_portable"

# After download, run installer and choose this folder as destination
# LibreOffice will be in: booking-api\LibreOfficePortable\
```

### For Global (Better for production):

```powershell
# Using winget (easiest)
winget install TheDocumentFoundation.LibreOffice -h

# Will install to: C:\Program Files\LibreOffice
```

---

## 🔍 Current Detection Priority

The application will search for LibreOffice in this order:

1. **Local Portable** (Project folder - No admin needed):
   - `booking-api/libreoffice-portable/App/libreoffice/program/soffice.exe`
   - `booking-api/LibreOfficePortable/App/libreoffice/program/soffice.exe`

2. **System-wide** (Global installation):
   - `C:\Program Files\LibreOffice\program\soffice.exe`
   - `C:\Program Files (x86)\LibreOffice\program\soffice.exe`

3. **PATH** (Environment variable)

---

## 📝 Notes

- **Portable version**: ~350MB, no admin rights needed, isolated to project
- **Global version**: ~400MB, needs admin, available system-wide
- **Both work perfectly** - choose based on your needs
- Code will auto-detect whichever is installed first (priority order above)

## 🚀 After Installation

1. **Clear log:**

   ```powershell
   Clear-Content storage\logs\laravel.log
   ```

2. **Test PDF generation:**
   - Login to web app
   - Generate document
   - Click file icon to preview
   - Should see PDF inline in browser

3. **Check logs if error:**
   ```powershell
   Get-Content storage\logs\laravel.log -Tail 50 | Select-String "PDF Conversion"
   ```
