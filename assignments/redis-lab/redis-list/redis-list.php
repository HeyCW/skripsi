<?php
 require_once __DIR__ . '/vendor/autoload.php';
// Redis List Management - PHP Backend Solution

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// TODO 5: LENGKAPI REDIS CONNECTION SETUP - JAWABAN
// Setup koneksi ke Redis server
try {
    $redisConfig = [
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
        'timeout' => 5.0,
    ];
    
    // Initialize Redis client
    $redis = new Predis\Client($redisConfig);
    
    // Test connection
    $redis->ping();
    
    // Set Redis key name untuk list
    $listKey = 'people_list';
    
} catch (Exception $e) {
    sendResponse(false, null, 'Failed to connect to Redis: ' . $e->getMessage());
}

/**
 * Send JSON response
 */
function sendResponse($success, $data = null, $message = '', $extra = []) {
    $response = array_merge([
        'success' => $success,
        'message' => $message
    ], $extra);
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle POST requests (LPUSH, RPUSH, LPOP, RPOP)
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $name = $input['name'] ?? '';
        
        switch ($action) {
            case 'lpush':
                handleLPush($redis, $listKey, $name);
                break;
                
            case 'rpush':
                handleRPush($redis, $listKey, $name);
                break;
                
            case 'lpop':
                handleLPop($redis, $listKey);
                break;
                
            case 'rpop':
                handleRPop($redis, $listKey);
                break;
                
            default:
                sendResponse(false, null, 'Invalid action');
        }
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Handle GET requests (retrieve list data)
        $action = $_GET['action'] ?? '';
        
        if ($action === 'get') {
            handleGetList($redis, $listKey);
        } else {
            sendResponse(false, null, 'Invalid action');
        }
    }
    
} catch (Exception $e) {
    error_log("Redis Error: " . $e->getMessage());
    sendResponse(false, null, 'Server error: ' . $e->getMessage());
}

// TODO 6: LENGKAPI LPUSH OPERATION HANDLER - JAWABAN
// Handle LPUSH operation (add to left/beginning of list)
function handleLPush($redis, $listKey, $name) {
    try {
        // Validate name parameter tidak kosong
        if (empty(trim($name))) {
            sendResponse(false, null, 'Name is required for LPUSH operation');
            return;
        }
        
        // Gunakan Redis LPUSH command
        $result = $redis->lPush($listKey, trim($name));
        
        if ($result !== false) {
            // Send success response dengan message
            sendResponse(true, null, "Added '{$name}' to the beginning of list. List length: {$result}");
        } else {
            sendResponse(false, null, 'Failed to add item to list');
        }
        
    } catch (Exception $e) {
        sendResponse(false, null, 'LPUSH error: ' . $e->getMessage());
    }
}

// TODO 7: LENGKAPI RPUSH OPERATION HANDLER - JAWABAN
// Handle RPUSH operation (add to right/end of list)
function handleRPush($redis, $listKey, $name) {
    try {
        // Validate name parameter tidak kosong
        if (empty(trim($name))) {
            sendResponse(false, null, 'Name is required for RPUSH operation');
            return;
        }
        
        // Gunakan Redis RPUSH command
        $result = $redis->rPush($listKey, trim($name));
        
        if ($result !== false) {
            // Send success response dengan message
            sendResponse(true, null, "Added '{$name}' to the end of list. List length: {$result}");
        } else {
            sendResponse(false, null, 'Failed to add item to list');
        }
        
    } catch (Exception $e) {
        sendResponse(false, null, 'RPUSH error: ' . $e->getMessage());
    }
}

// TODO 8: LENGKAPI LPOP OPERATION HANDLER - JAWABAN
// Handle LPOP operation (remove from left/beginning of list)
function handleLPop($redis, $listKey) {
    try {
        // Gunakan Redis LPOP command
        $removedItem = $redis->lPop($listKey);
        
        // Check jika list kosong (LPOP returns false)
        if ($removedItem === false) {
            sendResponse(false, null, 'List is empty - nothing to remove from beginning');
        } else {
            // Send appropriate response dengan removed item
            $currentLength = $redis->lLen($listKey);
            sendResponse(true, null, "Removed '{$removedItem}' from beginning of list. Remaining items: {$currentLength}");
        }
        
    } catch (Exception $e) {
        sendResponse(false, null, 'LPOP error: ' . $e->getMessage());
    }
}

// TODO 9: LENGKAPI RPOP OPERATION HANDLER - JAWABAN
// Handle RPOP operation (remove from right/end of list)
function handleRPop($redis, $listKey) {
    try {
        // Gunakan Redis RPOP command
        $removedItem = $redis->rPop($listKey);
        
        // Check jika list kosong (RPOP returns false)
        if ($removedItem === false) {
            sendResponse(false, null, 'List is empty - nothing to remove from end');
        } else {
            // Send appropriate response dengan removed item
            $currentLength = $redis->lLen($listKey);
            sendResponse(true, null, "Removed '{$removedItem}' from end of list. Remaining items: {$currentLength}");
        }
        
    } catch (Exception $e) {
        sendResponse(false, null, 'RPOP error: ' . $e->getMessage());
    }
}

// TODO 10: LENGKAPI GET LIST OPERATION - JAWABAN
// Retrieve all items dari Redis list
function handleGetList($redis, $listKey) {
    try {
        // Gunakan Redis LRANGE command untuk get semua items (0, -1)
        $people = $redis->lRange($listKey, 0, -1);
        
        // Get list length menggunakan LLEN command
        $listLength = $redis->lLen($listKey);
        
        // Check Redis connection status
        $redisStatus = 'Connected';
        try {
            $redis->ping();
        } catch (Exception $e) {
            $redisStatus = 'Disconnected';
        }
        
        // Prepare response data dengan people array, list_length, redis_status
        $responseData = [
            'people' => $people ?: [], // Ensure it's an array even if empty
            'list_length' => $listLength,
            'redis_status' => $redisStatus
        ];
        
        // Send success response dengan data
        sendResponse(true, $responseData, 'List data retrieved successfully');
        
    } catch (Exception $e) {
        // Handle errors
        sendResponse(false, [
            'people' => [],
            'list_length' => 0,
            'redis_status' => 'Error'
        ], 'Error retrieving list data: ' . $e->getMessage());
    }
}

?>