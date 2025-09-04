<?php
require_once 'vendor/autoload.php';

$client = new MongoDB\Client('mongodb://host.docker.internal:27018');

// List semua databases
echo "Available databases:\n";
$databases = $client->listDatabases();
foreach ($databases as $db) {
    echo "- " . $db->getName() . "\n";
}

// Test beberapa database umum
$testDbs = ['restaurant_db', 'test', 'myapp', 'admin', 'local'];

foreach ($testDbs as $dbName) {
    try {
        $db = $client->selectDatabase($dbName);
        $collections = $db->listCollections();
        
        echo "\nDatabase: $dbName\n";
        foreach ($collections as $collection) {
            $collName = $collection->getName();
            $count = $db->selectCollection($collName)->countDocuments();
            echo "  - $collName: $count documents\n";
        }
    } catch (Exception $e) {
        echo "  Error accessing $dbName: " . $e->getMessage() . "\n";
    }
}