# Enable PHP GD Extension

## Problem

The PDF conversion is failing with error:

```
The PHP GD extension is required, but is not installed.
```

The approval sheet document contains images (signatures, logos, etc.) and dompdf needs the GD extension to process images in DOCX → PDF conversion.

## Solution

### Step 1: Open php.ini as Administrator

Your PHP configuration file is located at:

```
C:\Program Files\php-8.4.6\php.ini
```

1. Press Windows key + R
2. Type: `notepad "C:\Program Files\php-8.4.6\php.ini"`
3. Click "Run as administrator" (or right-click Notepad and select "Run as administrator", then open the file)

### Step 2: Enable GD Extension

1. Press Ctrl+F to open Find dialog
2. Search for: `extension=gd`
3. You should find a line like: `;extension=gd` (with semicolon)
4. Remove the semicolon at the beginning: `extension=gd`
5. Save the file (Ctrl+S)

### Step 3: Restart Laravel Server

1. Stop your current Laravel development server (Ctrl+C in the terminal)
2. Start it again:
   ```bash
   php artisan serve
   ```

### Step 4: Verify GD is Loaded

Run this command to check if GD extension is now active:

```bash
php -m | Select-String -Pattern "gd"
```

You should see "gd" in the output.

### Step 5: Test PDF Preview

1. Go to the ketua-ormawa page
2. Click the PDF preview button for approval sheet
3. The PDF should now open successfully

## Alternative: If GD Extension File is Missing

If enabling the extension doesn't work, the GD DLL file might be missing. Check if this file exists:

```
C:\Program Files\php-8.4.6\ext\php_gd.dll
```

If the file is missing:

1. Download the same PHP version from https://windows.php.net/download/
2. Extract the zip file
3. Copy `php_gd.dll` from the `ext` folder to `C:\Program Files\php-8.4.6\ext\`

## Why is this needed?

- DOCX files can contain images (logos, signatures, charts)
- When converting DOCX → HTML → PDF, dompdf needs to process these images
- PHP's GD extension provides image processing capabilities
- Without GD, dompdf cannot render images and throws an error

## Technical Details

The error occurs in this flow:

1. PhpWord loads DOCX file
2. PhpWord converts DOCX to HTML (images included)
3. dompdf attempts to render HTML with images
4. dompdf calls GD functions to process PNG/JPG images
5. **Error**: GD extension not available
