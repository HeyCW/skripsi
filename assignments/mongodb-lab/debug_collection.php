<?php
// Test Direct Connection to MongoDB

require_once 'vendor/autoload.php';

echo "=== Direct Connection Test ===\n\n";

// Test multiple connection variations
$connectionTests = [
    'default' => 'mongodb://localhost:27017',
    'explicit_localhost' => 'mongodb://localhost:27017',
    '127.0.0.1' => 'mongodb://127.0.0.1:27017',
    'with_database' => 'mongodb://localhost:27017/restaurant_db'
];

foreach ($connectionTests as $name => $uri) {
    echo "Testing $name ($uri):\n";
    
    try {
        $client = new MongoDB\Client($uri);
        
        // Force connection by doing an operation
        $admin = $client->selectDatabase('admin');
        $ping = $admin->command(['ping' => 1]);
        echo "  ✅ Connection successful\n";
        
        // Test specific database and collection
        $db = $client->selectDatabase('restaurant_db');
        $collection = $db->selectCollection('restaurants');
        
        // Force a read operation
        $count = $collection->countDocuments();
        echo "  📊 Document count: $count\n";
        
        if ($count > 0) {
            // Get sample document
            $sample = $collection->findOne();
            echo "  📄 Sample name: " . ($sample['name'] ?? 'N/A') . "\n";
            echo "  📍 Sample borough: " . ($sample['borough'] ?? 'N/A') . "\n";
            
            // Test distinct operation
            try {
                $boroughs = $collection->distinct('borough');
                $boroughArray = iterator_to_array($boroughs);
                echo "  🏙️ Distinct boroughs: " . count($boroughArray) . " found\n";
                echo "  🏙️ First few boroughs: " . implode(', ', array_slice($boroughArray, 0, 3)) . "\n";
            } catch (Exception $e) {
                echo "  ❌ Distinct error: " . $e->getMessage() . "\n";
            }
        }
        
        echo "  ✅ This connection works!\n\n";
        
    } catch (Exception $e) {
        echo "  ❌ Connection failed: " . $e->getMessage() . "\n\n";
    }
}

// Test your exact current code
echo "=== Your Current Code Test ===\n";
try {
    $client = new MongoDB\Client();
    echo "Client created successfully\n";
    
    $resto = $client->selectDatabase('restaurant_db')->selectCollection('restaurants');
    echo "Collection object created\n";
    
    // Test basic operations
    $count = $resto->countDocuments();
    echo "Document count: $count\n";
    
    if ($count === 0) {
        echo "❌ STILL GETTING 0 - Let's debug further...\n";
        
        // Force reconnection
        unset($client, $resto);
        
        $client = new MongoDB\Client('mongodb://localhost:27017');
        $resto = $client->selectDatabase('restaurant_db')->selectCollection('restaurants');
        $count2 = $resto->countDocuments();
        echo "After forced reconnection: $count2\n";
        
        // Check if it's a timing issue
        sleep(1);
        $count3 = $resto->countDocuments();
        echo "After 1 second delay: $count3\n";
        
    } else {
        echo "✅ Working now! Count: $count\n";
        
        // Test the boroughs
        $cursor = $resto->distinct('borough');
        $boroughs = [];
        foreach ($cursor as $borough) {
            if (!empty($borough)) {
                $boroughs[] = $borough;
            }
        }
        echo "Boroughs found: " . json_encode($boroughs) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Direct MongoDB Command Comparison ===\n";
echo "Run this in terminal to compare:\n";
echo "docker exec -it mongodb mongosh --eval \"use restaurant_db; db.restaurants.countDocuments()\"\n";
?>