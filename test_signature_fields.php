<?php

// Quick test untuk cek signature field consistency
// Run: php test_signature_fields.php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Signature Field Consistency ===" . PHP_EOL . PHP_EOL;

// 1. Check all signatures
$signs = \App\Models\Sign::all();
echo "Total signatures in database: " . $signs->count() . PHP_EOL . PHP_EOL;

if ($signs->isEmpty()) {
    echo "❌ No signatures found in database!" . PHP_EOL;
    echo "Please upload a signature via UI first." . PHP_EOL;
    exit(1);
}

foreach ($signs as $sign) {
    echo "Sign ID: {$sign->id}" . PHP_EOL;
    echo "  User ID: {$sign->user_id}" . PHP_EOL;
    echo "  Signature field: {$sign->signature}" . PHP_EOL;
    echo "  Signed at: {$sign->signed_at}" . PHP_EOL;

    $fullPath = \Illuminate\Support\Facades\Storage::path($sign->signature);
    echo "  Full path: {$fullPath}" . PHP_EOL;
    echo "  File exists: " . (file_exists($fullPath) ? '✅ YES' : '❌ NO') . PHP_EOL;

    if (file_exists($fullPath)) {
        echo "  File size: " . filesize($fullPath) . " bytes" . PHP_EOL;
        echo "  MIME type: " . mime_content_type($fullPath) . PHP_EOL;
    }

    echo PHP_EOL;
}

// 2. Test generateFromTemplate
echo "=== Testing DocumentGenerationService ===" . PHP_EOL . PHP_EOL;

$latestDoc = \App\Models\Document::latest()->first();
if (!$latestDoc) {
    echo "❌ No documents found!" . PHP_EOL;
    exit(1);
}

echo "Testing with document ID: {$latestDoc->id}" . PHP_EOL;
echo "Creator ID: {$latestDoc->creator_id}" . PHP_EOL . PHP_EOL;

$creatorSign = \App\Models\Sign::where('user_id', $latestDoc->creator_id)
                                ->latest()
                                ->first();

if (!$creatorSign) {
    echo "❌ Creator has no signature!" . PHP_EOL;
    echo "User {$latestDoc->creator_id} needs to upload signature." . PHP_EOL;
    exit(1);
}

echo "✅ Creator signature found!" . PHP_EOL;
echo "  Sign ID: {$creatorSign->id}" . PHP_EOL;
echo "  Signature field: {$creatorSign->signature}" . PHP_EOL;

$fullPath = \Illuminate\Support\Facades\Storage::path($creatorSign->signature);
echo "  Full path: {$fullPath}" . PHP_EOL;
echo "  File exists: " . (file_exists($fullPath) ? '✅ YES' : '❌ NO') . PHP_EOL;

if (!file_exists($fullPath)) {
    echo PHP_EOL . "❌ ERROR: Signature file not found!" . PHP_EOL;
    echo "Database has: {$creatorSign->signature}" . PHP_EOL;
    echo "Expected at: {$fullPath}" . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "✅ All checks passed! Ready to generate documents." . PHP_EOL;
