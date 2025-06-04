<?php
require_once __DIR__ . '/Fetcher.php';

use LIBYACITIES\Fetcher;

function showUsage() {
    echo "Libya Cities Data Processor\n";
    echo "Usage: php index.php [command]\n\n";
    echo "Commands:\n";
    echo "  fetch     - Only fetch data from Overpass API\n";
    echo "  process   - Only process existing input.json data\n";
    echo "  help      - Show this help message\n\n";
}

function fetchOnly($fetcher) {
    echo "Fetching data from Overpass Turbo...\n";
    $fetchSuccess = $fetcher->fetchFromOverpass();
    
    if (!$fetchSuccess) {
        throw new RuntimeException('Failed to save Overpass data to input.json');
    }
    echo "✓ Data fetched successfully and saved to input.json\n";
}

function processOnly($fetcher) {
    if (!file_exists(__DIR__ . '/input.json')) {
        throw new RuntimeException('input.json not found. Run with "fetch" command first.');
    }
    
    echo "Processing existing input.json data...\n";
    $fetcher->processData();
    echo "✓ Data processing completed successfully!\n";
}

function fetchAndProcess($fetcher) {
    fetchOnly($fetcher);
    echo "\n";
    processOnly($fetcher);
}

try {
    echo "Libya Cities Data Processor\n";
    echo "===========================\n\n";
    
    $command = $argv[1] ?? 'both';
    $fetcher = new Fetcher();
    
    switch ($command) {
        case 'fetch':
            fetchOnly($fetcher);
            break;
            
        case 'process':
            processOnly($fetcher);
            break;
            
        case 'both':
            fetchAndProcess($fetcher);
            break;
            
        case 'help':
        case '-h':
        case '--help':
            showUsage();
            exit(0);
            
        default:
            echo "✗ Unknown command: $command\n\n";
            showUsage();
            exit(1);
    }
    
    echo "\n✓ Operation completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}