<?php
/**
 * MongoDB Connection Debug Script
 * Simpan sebagai mongodb-debug.php
 */

require_once 'vendor/autoload.php';

header('Content-Type: application/json');

$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'mongodb_extension' => extension_loaded('mongodb'),
    'environment' => [],
    'connection_attempts' => [],
    'recommendations' => []
];

// Check environment variables
$debug['environment'] = [
    'MONGODB_HOST' => $_ENV['MONGODB_HOST'] ?? getenv('MONGODB_HOST') ?: 'not set',
    'MONGODB_PORT' => $_ENV['MONGODB_PORT'] ?? getenv('MONGODB_PORT') ?: 'not set',
    'MONGODB_DATABASE' => $_ENV['MONGODB_DATABASE'] ?? getenv('MONGODB_DATABASE') ?: 'not set',
    'MONGODB_USERNAME' => $_ENV['MONGODB_USERNAME'] ?? getenv('MONGODB_USERNAME') ?: 'not set',
    'MONGODB_PASSWORD' => isset($_ENV['MONGODB_PASSWORD']) ? '***hidden***' : 'not set'
];

// Get actual values for connection
$mongoHost = $_ENV['MONGODB_HOST'] ?? getenv('MONGODB_HOST') ?: 'localhost';
$mongoPort = $_ENV['MONGODB_PORT'] ?? getenv('MONGODB_PORT') ?: 27017;
$mongoDatabase = $_ENV['MONGODB_DATABASE'] ?? getenv('MONGODB_DATABASE') ?: 'test';
$mongoUsername = $_ENV['MONGODB_USERNAME'] ?? getenv('MONGODB_USERNAME') ?: '';
$mongoPassword = $_ENV['MONGODB_PASSWORD'] ?? getenv('MONGODB_PASSWORD') ?: '';

// Different connection URIs to try
$connectionUris = [
    "mongodb://{$mongoHost}:{$mongoPort}",
    "mongodb://localhost:27017",
    "mongodb://127.0.0.1:27017",
    "mongodb://mongodb:27017",  // Docker service name
    "mongodb://host.docker.internal:27017"  // Docker Desktop
];

// Add authenticated URIs if credentials are provided
if (!empty($mongoUsername) && !empty($mongoPassword)) {
    $connectionUris[] = "mongodb://{$mongoUsername}:{$mongoPassword}@{$mongoHost}:{$mongoPort}/{$mongoDatabase}";
    $connectionUris[] = "mongodb://{$mongoUsername}:{$mongoPassword}@{$mongoHost}:{$mongoPort}";
}

foreach ($connectionUris as $uri) {
    $attempt = [
        'uri' => $uri,
        'status' => 'failed',
        'error' => null,
        'details' => []
    ];
    
    try {
        echo "🔍 Trying connection: {$uri}\n";
        
        // Test basic TCP connection first
        $tcpHost = parse_url($uri, PHP_URL_HOST) ?: $mongoHost;
        $tcpPort = parse_url($uri, PHP_URL_PORT) ?: $mongoPort;
        
        $attempt['details']['tcp_host'] = $tcpHost;
        $attempt['details']['tcp_port'] = $tcpPort;
        
        $tcpConnection = @fsockopen($tcpHost, $tcpPort, $errno, $errstr, 5);
        if ($tcpConnection) {
            $attempt['details']['tcp_connection'] = 'success';
            fclose($tcpConnection);
        } else {
            $attempt['details']['tcp_connection'] = "failed: {$errno} - {$errstr}";
        }
        
        // Try MongoDB connection
        $client = new MongoDB\Client($uri, [
            'serverSelectionTimeoutMS' => 5000,
            'connectTimeoutMS' => 5000,
            'socketTimeoutMS' => 5000
        ]);
        
        // Test with admin command
        $result = $client->selectDatabase('admin')->command(['ping' => 1]);
        
        if ($result) {
            $attempt['status'] = 'success';
            $attempt['details']['ping_result'] = 'ok';
            
            // Get server info
            try {
                $serverInfo = $client->selectDatabase('admin')->command(['buildInfo' => 1]);
                $attempt['details']['mongodb_version'] = $serverInfo['version'] ?? 'unknown';
            } catch (Exception $e) {
                $attempt['details']['mongodb_version'] = 'could not retrieve';
            }
            
            // List databases
            try {
                $databases = $client->listDatabases();
                $dbList = [];
                foreach ($databases as $db) {
                    $dbList[] = $db->getName();
                }
                $attempt['details']['databases'] = $dbList;
            } catch (Exception $e) {
                $attempt['details']['databases'] = 'could not list: ' . $e->getMessage();
            }
            
            echo "✅ Connection successful!\n";
            break; // Exit loop on first success
        }
        
    } catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
        $attempt['error'] = 'Connection timeout: ' . $e->getMessage();
        echo "⏰ Connection timeout\n";
    } catch (MongoDB\Driver\Exception\ServerSelectionTimeoutException $e) {
        $attempt['error'] = 'Server selection timeout: ' . $e->getMessage();
        echo "🔍 Server selection timeout\n";
    } catch (MongoDB\Driver\Exception\ConnectionException $e) {
        $attempt['error'] = 'Connection error: ' . $e->getMessage();
        echo "❌ Connection error\n";
    } catch (Exception $e) {
        $attempt['error'] = 'General error: ' . $e->getMessage();
        echo "❌ General error: " . $e->getMessage() . "\n";
    }
    
    $debug['connection_attempts'][] = $attempt;
}

// Network diagnostics
echo "\n🌐 Network Diagnostics:\n";

$networkTests = [
    'localhost:27017',
    'mongodb:27017', 
    '127.0.0.1:27017',
    "{$mongoHost}:{$mongoPort}"
];

$debug['network_tests'] = [];

foreach ($networkTests as $target) {
    list($testHost, $testPort) = explode(':', $target);
    
    $networkTest = [
        'target' => $target,
        'dns_resolution' => 'unknown',
        'tcp_connection' => 'unknown',
        'ping_available' => false
    ];
    
    // DNS resolution test
    $ip = gethostbyname($testHost);
    if ($ip !== $testHost) {
        $networkTest['dns_resolution'] = "resolved to {$ip}";
    } else {
        $networkTest['dns_resolution'] = 'could not resolve';
    }
    
    // TCP connection test
    $tcpConnection = @fsockopen($testHost, $testPort, $errno, $errstr, 3);
    if ($tcpConnection) {
        $networkTest['tcp_connection'] = 'success';
        fclose($tcpConnection);
    } else {
        $networkTest['tcp_connection'] = "failed: {$errno} - {$errstr}";
    }
    
    // Check if ping command is available
    if (function_exists('exec')) {
        $pingOutput = [];
        exec("ping -c 1 -W 2 {$testHost} 2>/dev/null", $pingOutput, $pingResult);
        $networkTest['ping_available'] = $pingResult === 0;
    }
    
    $debug['network_tests'][] = $networkTest;
    echo "   {$target}: TCP=" . $networkTest['tcp_connection'] . ", DNS=" . $networkTest['dns_resolution'] . "\n";
}

// Generate recommendations
$debug['recommendations'] = [];

if (empty(array_filter($debug['connection_attempts'], fn($attempt) => $attempt['status'] === 'success'))) {
    $debug['recommendations'][] = "❌ No successful connections found";
    
    // Check if MongoDB service might not be running
    if (strpos($debug['environment']['MONGODB_HOST'], 'localhost') !== false || 
        $debug['environment']['MONGODB_HOST'] === 'not set') {
        $debug['recommendations'][] = "🐳 If using Docker: Make sure MONGODB_HOST points to container name (e.g., 'mongodb')";
        $debug['recommendations'][] = "🔗 Update connection string to use Docker service name instead of localhost";
    }
    
    // Check TCP connections
    $tcpFailures = array_filter($debug['network_tests'], fn($test) => strpos($test['tcp_connection'], 'failed') !== false);
    if (count($tcpFailures) === count($debug['network_tests'])) {
        $debug['recommendations'][] = "🚫 All TCP connections failed - MongoDB server might not be running";
        $debug['recommendations'][] = "▶️ Start MongoDB: docker-compose up mongodb -d";
        $debug['recommendations'][] = "🔍 Check MongoDB logs: docker logs mongodb-container-name";
    }
    
    // Environment variable issues
    if ($debug['environment']['MONGODB_HOST'] === 'not set') {
        $debug['recommendations'][] = "⚙️ Set MONGODB_HOST environment variable";
        $debug['recommendations'][] = "🐳 Docker: Add 'MONGODB_HOST=mongodb' to environment section";
    }
    
} else {
    $debug['recommendations'][] = "✅ Found working connection - use the successful URI";
}

// Additional Docker-specific recommendations
$debug['recommendations'][] = "";
$debug['recommendations'][] = "🐳 Docker Troubleshooting:";
$debug['recommendations'][] = "   1. Check if containers are in same network: docker network ls";
$debug['recommendations'][] = "   2. Verify MongoDB is running: docker ps | grep mongo";
$debug['recommendations'][] = "   3. Check MongoDB health: docker exec mongodb-container mongosh --eval 'db.runCommand({ping:1})'";
$debug['recommendations'][] = "   4. Check container connectivity: docker exec php-container ping mongodb";

echo "\n📋 Full debug info:\n";
echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>