<?php

$zip = new ZipArchive();
$file = 'storage/app/document-templates/1770106644_executive_summary_Lembar Executive Summary.docx';

if ($zip->open($file) === true) {
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    // Show first 3000 chars
    echo "=== TEMPLATE CONTENT (first 3000 chars) ===\n";
    echo substr($xml, 0, 3000);
    echo "\n\n";

    // Find all possible placeholder patterns
    echo "=== SEARCHING FOR PLACEHOLDERS ===\n";

    // Pattern 1: ${field}
    preg_match_all('/\$\{([a-zA-Z0-9_]+)\}/', $xml, $matches1);
    echo "\n\${field} format: " . count($matches1[1]) . " found\n";
    if (!empty($matches1[1])) {
        echo "Examples: " . implode(', ', array_slice(array_unique($matches1[1]), 0, 10)) . "\n";
    }

    // Pattern 2: {{field}}
    preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $xml, $matches2);
    echo "\n{{field}} format: " . count($matches2[1]) . " found\n";
    if (!empty($matches2[1])) {
        echo "Examples: " . implode(', ', array_slice(array_unique($matches2[1]), 0, 10)) . "\n";
    }

    // Pattern 3: [field]
    preg_match_all('/\[([a-zA-Z0-9_]+)\]/', $xml, $matches3);
    echo "\n[field] format: " . count($matches3[1]) . " found\n";
    if (!empty($matches3[1])) {
        echo "Examples: " . implode(', ', array_slice(array_unique($matches3[1]), 0, 10)) . "\n";
    }

    // Pattern 4: <field>
    preg_match_all('/<w:t>([^<]+)<\/w:t>/', $xml, $texts);
    echo "\n\nAll text content:\n";
    $uniqueTexts = array_unique($texts[1]);
    foreach (array_slice($uniqueTexts, 0, 20) as $text) {
        if (strlen(trim($text)) > 0) {
            echo "- " . $text . "\n";
        }
    }
} else {
    echo "Failed to open file: $file\n";
}
