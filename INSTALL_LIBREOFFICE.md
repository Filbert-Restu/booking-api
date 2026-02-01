# Instalasi LibreOffice untuk Konversi DOCX ke PDF

Sistem preview template membutuhkan LibreOffice untuk mengkonversi file DOCX menjadi PDF.

## Instalasi

### Windows
1. Download LibreOffice dari https://www.libreoffice.org/download/download/
2. Install LibreOffice
3. Tambahkan LibreOffice ke PATH:
   - Buka Environment Variables
   - Edit Path dan tambahkan: `C:\Program Files\LibreOffice\program`
4. Restart terminal/command prompt

### Linux (Ubuntu/Debian)
```bash
sudo apt-get update
sudo apt-get install libreoffice
```

### macOS
```bash
brew install libreoffice
```

## Verifikasi Instalasi

Test apakah LibreOffice sudah terinstall dengan menjalankan:

```bash
# Windows
soffice --version

# Linux/macOS
libreoffice --version
```

## Testing Konversi

Test konversi manual:

```bash
# Convert DOCX to PDF
libreoffice --headless --convert-to pdf --outdir /path/to/output /path/to/input.docx
```

## Troubleshooting

### LibreOffice not found
Jika mendapat error "LibreOffice not found", pastikan:
1. LibreOffice sudah terinstall
2. Path sudah ditambahkan ke environment PATH
3. Restart terminal/server setelah instalasi

### Permission denied
```bash
# Linux/macOS
sudo chmod +x /path/to/soffice
```

### Fallback Mode
Jika LibreOffice tidak tersedia, sistem akan otomatis fallback ke mode download DOCX dengan pesan informasi yang sesuai.

## Server Production

Untuk production server, pastikan:
1. Install LibreOffice di server
2. User yang menjalankan PHP/Laravel memiliki akses ke LibreOffice
3. Directory `storage/app/temp/` memiliki permission write (755 atau 777)

```bash
# Set permission untuk temp directory
chmod -R 755 storage/app/temp/
```

## Alternative: Docker

Jika menggunakan Docker, tambahkan di Dockerfile:

```dockerfile
# Install LibreOffice
RUN apt-get update && \
    apt-get install -y libreoffice && \
    rm -rf /var/lib/apt/lists/*
```
