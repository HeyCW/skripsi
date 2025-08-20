<?php
// Redis List Management - PHP Backend Exercise

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// TODO 5: LENGKAPI REDIS CONNECTION SETUP
// Tugas: Setup koneksi ke Redis server
// HINT: Gunakan Redis class, connect ke localhost:6379
// HINT: Set Redis key name untuk list (misal: 'people_list')
// HINT: Handle connection errors

// KODE ANDA DI SINI:


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
    // TODO: Initialize Redis connection here
    
    
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

// TODO 6: LENGKAPI LPUSH OPERATION HANDLER
// Tugas: Handle LPUSH operation (add to left/beginning of list)
function handleLPush($redis, $listKey, $name) {
    // TODO: Validate name parameter tidak kosong
    // TODO: Gunakan Redis LPUSH command
    // TODO: Send success response dengan message
    // TODO: Handle errors
    
    // KODE ANDA DI SINI:
    
    
}

// TODO 7: LENGKAPI RPUSH OPERATION HANDLER  
// Tugas: Handle RPUSH operation (add to right/end of list)
function handleRPush($redis, $listKey, $name) {
    // TODO: Validate name parameter tidak kosong
    // TODO: Gunakan Redis RPUSH command
    // TODO: Send success response dengan message
    // TODO: Handle errors
    
    // KODE ANDA DI SINI:
    
    
}

// TODO 8: LENGKAPI LPOP OPERATION HANDLER
// Tugas: Handle LPOP operation (remove from left/beginning of list)
function handleLPop($redis, $listKey) {
    // TODO: Gunakan Redis LPOP command
    // TODO: Check jika list kosong (LPOP returns false)
    // TODO: Send appropriate response dengan removed item atau "list is empty"
    // TODO: Handle errors
    
    // KODE ANDA DI SINI:
    
    
}

// TODO 9: LENGKAPI RPOP OPERATION HANDLER
// Tugas: Handle RPOP operation (remove from right/end of list)
function handleRPop($redis, $listKey) {
    // TODO: Gunakan Redis RPOP command
    // TODO: Check jika list kosong (RPOP returns false)
    // TODO: Send appropriate response dengan removed item atau "list is empty"
    // TODO: Handle errors
    
    // KODE ANDA DI SINI:
    
    
}

// TODO 10: LENGKAPI GET LIST OPERATION
// Tugas: Retrieve all items dari Redis list
function handleGetList($redis, $listKey) {
    // TODO: Gunakan Redis LRANGE command untuk get semua items (0, -1)
    // TODO: Get list length menggunakan LLEN command
    // TODO: Prepare response data dengan people array, list_length, redis_status
    // TODO: Send success response dengan data
    // TODO: Handle errors
    
    // KODE ANDA DI SINI:
    
    
}

?>