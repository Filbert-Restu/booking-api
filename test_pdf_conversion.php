<?php
/**
 * Test LibreOffice PDF Conversion Quality
 *
 * Usage: php test_pdf_conversion.php <template-id>
 * Example: php test_pdf_conversion.php 1
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get template ID from command line
$templateId = $argv[1] ?? null;

if (!$templateId) {
    echo "❌ Usage: php test_pdf_conversion.php <template-id>\n";
    echo "Example: php test_pdf_conversion.php 1\n";
    exit(1);
}

// Find template
$template = \App\Models\DocumentTemplate::find($templateId);

if (!$template) {
    echo "❌ Template ID {$templateId} not found\n";
    exit(1);
}

echo "=== LibreOffice PDF Conversion Test ===\n\n";
echo "Template: {$template->template_name}\n";
echo "Type: {$template->template_type}\n";
echo "File: {$template->file_path}\n\n";

// Check if file exists
if (!Storage::disk('private')->exists($template->file_path)) {
    echo "❌ Template file not found: {$template->file_path}\n";
    exit(1);
}

$docxPath = Storage::disk('private')->path($template->file_path);
echo "DOCX Path: {$docxPath}\n";
echo "File exists: " . (file_exists($docxPath) ? "✅ YES" : "❌ NO") . "\n";
echo "File size: " . number_format(filesize($docxPath) / 1024, 2) . " KB\n\n";

// Detect OS
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
echo "Operating System: " . PHP_OS . ($isWindows ? " (Windows)" : " (Linux/Mac)") . "\n\n";

// Find LibreOffice
echo "=== Finding LibreOffice ===\n";
$sofficeCommand = null;

if ($isWindows) {
    $possiblePaths = [
        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        getenv('ProgramFiles') . '\\LibreOffice\\program\\soffice.exe',
        getenv('ProgramFiles(x86)') . '\\LibreOffice\\program\\soffice.exe',
    ];

    foreach ($possiblePaths as $path) {
        echo "Checking: {$path} ... ";
        if (file_exists($path)) {
            $sofficeCommand = $path;
            echo "✅ FOUND\n";
            break;
        } else {
            echo "❌ Not found\n";
        }
    }

    if (!$sofficeCommand) {
        echo "\nTrying PATH...\n";
        exec('where soffice 2>NUL', $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            $sofficeCommand = trim($output[0]);
            echo "✅ Found in PATH: {$sofficeCommand}\n";
        } else {
            echo "❌ Not found in PATH\n";
        }
    }
} else {
    exec('which libreoffice 2>/dev/null', $output, $returnCode);
    if ($returnCode === 0 && !empty($output)) {
        $sofficeCommand = 'libreoffice';
        echo "✅ Found: libreoffice\n";
    } else {
        exec('which soffice 2>/dev/null', $output2, $returnCode2);
        if ($returnCode2 === 0 && !empty($output2)) {
            $sofficeCommand = 'soffice';
            echo "✅ Found: soffice\n";
        } else {
            echo "❌ LibreOffice not found\n";
        }
    }
}

if (!$sofficeCommand) {
    echo "\n❌ LibreOffice not installed or not in PATH\n";
    echo "\n📥 Install Instructions:\n";
    if ($isWindows) {
        echo "   Windows: Download from https://www.libreoffice.org/download/download/\n";
        echo "   Then add to PATH: C:\\Program Files\\LibreOffice\\program\n";
    } else {
        echo "   Linux: sudo apt-get install libreoffice\n";
        echo "   Mac: brew install --cask libreoffice\n";
    }
    exit(1);
}

echo "\n✅ LibreOffice Command: {$sofficeCommand}\n";

// Check LibreOffice version
echo "\n=== LibreOffice Version ===\n";
exec("\"{$sofficeCommand}\" --version 2>&1", $versionOutput);
echo implode("\n", $versionOutput) . "\n";

// Setup output paths
$outputDir = storage_path('app/temp');
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$timestamp = time();
$pdfPath = $outputDir . DIRECTORY_SEPARATOR . 'test_' . $templateId . '_' . $timestamp . '.pdf';

echo "\n=== PDF Conversion ===\n";
echo "Output directory: {$outputDir}\n";
echo "Target PDF: {$pdfPath}\n\n";

// Build conversion command WITH enhanced options
$command = '"' . $sofficeCommand . '"' .
          ' --headless' .
          ' --convert-to pdf:writer_pdf_Export' .
          ' --outdir "' . $outputDir . '"' .
          ' "' . $docxPath . '"';

echo "Command: {$command}\n\n";

// Execute
echo "🚀 Converting...\n";
$startTime = microtime(true);

if ($isWindows) {
    $fullCommand = 'cmd /c "' . $command . '"';
    exec($fullCommand . ' 2>&1', $execOutput, $execReturn);
} else {
    exec($command . ' 2>&1', $execOutput, $execReturn);
}

$duration = round(microtime(true) - $startTime, 2);

echo "\n=== Conversion Output ===\n";
echo implode("\n", $execOutput) . "\n";
echo "\nReturn Code: {$execReturn}\n";
echo "Duration: {$duration} seconds\n";

// Check result
sleep(1); // Wait for file write

$baseFilename = pathinfo($docxPath, PATHINFO_FILENAME);
$tempPdfPath = $outputDir . DIRECTORY_SEPARATOR . $baseFilename . '.pdf';

echo "\n=== Checking Result ===\n";
echo "Expected PDF: {$tempPdfPath}\n";
echo "File exists: " . (file_exists($tempPdfPath) ? "✅ YES" : "❌ NO") . "\n";

if (file_exists($tempPdfPath)) {
    $pdfSize = filesize($tempPdfPath);
    echo "PDF size: " . number_format($pdfSize / 1024, 2) . " KB\n";

    // Rename to target path
    if ($tempPdfPath !== $pdfPath) {
        rename($tempPdfPath, $pdfPath);
        echo "Renamed to: {$pdfPath}\n";
    }

    // Check PDF validity (basic check)
    $pdfHeader = file_get_contents($pdfPath, false, null, 0, 5);
    echo "PDF Header: {$pdfHeader} " . ($pdfHeader === '%PDF-' ? "✅ Valid" : "❌ Invalid") . "\n";

    echo "\n✅ SUCCESS - PDF Generated!\n";
    echo "\n📄 Open PDF: {$pdfPath}\n";

    // Show quality check list
    echo "\n=== Quality Checklist ===\n";
    echo "Manual verification required:\n";
    echo "  [ ] Font rendering correct?\n";
    echo "  [ ] Images/signatures in right position?\n";
    echo "  [ ] Table borders visible?\n";
    echo "  [ ] Page margins correct?\n";
    echo "  [ ] No text overflow/cutoff?\n";
    echo "  [ ] Line spacing consistent?\n";

} else {
    echo "\n❌ FAILED - PDF not generated\n";
    echo "\nPossible issues:\n";
    echo "  1. LibreOffice permission issues\n";
    echo "  2. DOCX file corrupted\n";
    echo "  3. Incompatible Word features in template\n";
    echo "  4. Disk space issues\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";
