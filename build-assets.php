#!/usr/bin/env php
<?php
/**
 * Build script for minifying CSS and JS assets
 *
 * @package CloudflareR2WC
 */

require_once __DIR__ . '/vendor/autoload.php';

use MatthiasMullie\Minify;

// Define paths
$baseDir = __DIR__;
$srcDir = $baseDir . '/assets/src';
$assetsDir = $baseDir . '/assets';

// Create asset directories
if (!is_dir($assetsDir . '/js')) {
    mkdir($assetsDir . '/js', 0755, true);
}
if (!is_dir($assetsDir . '/css')) {
    mkdir($assetsDir . '/css', 0755, true);
}

echo "Building assets...\n";

// JavaScript files
$jsFiles = [
    'admin' => $srcDir . '/js/admin.js',
    'product-r2-selector' => $srcDir . '/js/product-r2-selector.js',
];

foreach ($jsFiles as $name => $sourceFile) {
    if (!file_exists($sourceFile)) {
        echo "Warning: Source file not found: $sourceFile\n";
        continue;
    }

    // Copy unminified version (for SCRIPT_DEBUG)
    $destFile = $assetsDir . '/js/' . $name . '.js';
    copy($sourceFile, $destFile);
    echo "✓ Copied: $destFile\n";

    // Create minified version
    $minifier = new Minify\JS($sourceFile);
    $minifiedFile = $assetsDir . '/js/' . $name . '.min.js';
    $minifier->minify($minifiedFile);
    echo "✓ Minified: $minifiedFile\n";
}

// CSS files
$cssFiles = [
    'admin' => $srcDir . '/css/admin.css',
    'product-r2-selector' => $srcDir . '/css/product-r2-selector.css',
];

foreach ($cssFiles as $name => $sourceFile) {
    if (!file_exists($sourceFile)) {
        echo "Warning: Source file not found: $sourceFile\n";
        continue;
    }

    // Copy unminified version (for SCRIPT_DEBUG)
    $destFile = $assetsDir . '/css/' . $name . '.css';
    copy($sourceFile, $destFile);
    echo "✓ Copied: $destFile\n";

    // Create minified version
    $minifier = new Minify\CSS($sourceFile);
    $minifiedFile = $assetsDir . '/css/' . $name . '.min.css';
    $minifier->minify($minifiedFile);
    echo "✓ Minified: $minifiedFile\n";
}

echo "\n✅ Build complete!\n";
