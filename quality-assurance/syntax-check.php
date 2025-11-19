<?php
/**
 * Quick Syntax Check for All PHP Files
 * Validates PHP syntax without executing
 */

function checkPHPFileSyntax($file) {
    $output = [];
    $returnCode = 0;

    // Use php -l to check syntax
    $phpPath = trim(shell_exec('which php'));

    if (empty($phpPath)) {
        echo "PHP not found in PATH. Skipping syntax check for: $file\n";
        return ['file' => $file, 'status' => 'skipped', 'error' => 'PHP not in PATH'];
    }

    exec("$phpPath -l \"$file\" 2>&1", $output, $returnCode);

    if ($returnCode === 0) {
        return ['file' => $file, 'status' => 'valid', 'error' => null];
    } else {
        return ['file' => $file, 'status' => 'error', 'error' => implode(' ', $output)];
    }
}

function findAllPHPFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

// Get all PHP files
$directory = __DIR__ . '/..';
$phpFiles = findAllPHPFiles($directory);

echo "🔍 Checking PHP syntax for " . count($phpFiles) . " files...\n\n";

$results = [
    'valid' => 0,
    'errors' => 0,
    'skipped' => 0,
    'files' => []
];

foreach ($phpFiles as $file) {
    $result = checkPHPFileSyntax($file);
    $results['files'][] = $result;

    switch ($result['status']) {
        case 'valid':
            $results['valid']++;
            echo "✅ {$file}\n";
            break;
        case 'error':
            $results['errors']++;
            echo "❌ {$file}\n";
            echo "   Error: {$result['error']}\n";
            break;
        case 'skipped':
            $results['skipped']++;
            echo "⏭  {$file}\n";
            break;
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 SYNTAX CHECK SUMMARY\n";
echo str_repeat("=", 50) . "\n";

echo "✅ Valid files: {$results['valid']}\n";
echo "❌ Errors found: {$results['errors']}\n";
echo "⏭  Skipped files: {$results['skipped']}\n";

if ($results['errors'] > 0) {
    echo "\n🚨 SYNTAX ERRORS FOUND!\n";
    echo "Please fix the above errors before deployment.\n";
    exit(1);
} else {
    echo "\n✅ ALL PHP FILES VALID!\n";
    echo "No syntax errors found.\n";
    exit(0);
}
?>