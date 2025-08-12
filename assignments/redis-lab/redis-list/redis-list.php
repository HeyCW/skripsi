<?php
// Set content type to JSON and disable output buffering
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Error handling - capture any PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to avoid breaking JSON
ob_start(); // Start output buffering to catch any unexpected output

try {
    // Load Composer autoloader
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Redis configuration
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
    
    // Redis list key
    $listKey = 'people_list';
    
    // Get request method and data
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    if ($requestMethod === 'GET' || (isset($_GET['action']) && $_GET['action'] === 'get')) {
        // Handle GET request - return current list
        try {
            $people = $redis->lrange($listKey, 0, -1);
            $listLength = $redis->llen($listKey);
            
            $response = [
                'success' => true,
                'message' => 'Data retrieved successfully',
                'data' => [
                    'people' => $people,
                    'list_length' => $listLength,
                    'redis_status' => 'Connected'
                ]
            ];
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'error' => 'Failed to retrieve data: ' . $e->getMessage(),
                'data' => [
                    'people' => [],
                    'list_length' => 0,
                    'redis_status' => 'Error: ' . $e->getMessage()
                ]
            ];
        }
        
    } elseif ($requestMethod === 'POST') {
        // Handle POST request - perform actions
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $response = [
                'success' => false,
                'message' => 'Invalid JSON input: ' . json_last_error_msg()
            ];
        } else {
            $action = $data['action'] ?? '';
            $name = $data['name'] ?? '';
            
            try {
                switch ($action) {
                    case 'lpush':
                        if (empty($name)) {
                            $response['message'] = 'Name is required for LPUSH';
                            break;
                        }
                        $result = $redis->lpush($listKey, $name);
                        $response = [
                            'success' => true,
                            'message' => "Added '$name' to the left of list (new length: $result)"
                        ];
                        break;
                        
                    case 'rpush':
                        if (empty($name)) {
                            $response['message'] = 'Name is required for RPUSH';
                            break;
                        }
                        $result = $redis->rpush($listKey, $name);
                        $response = [
                            'success' => true,
                            'message' => "Added '$name' to the right of list (new length: $result)"
                        ];
                        break;
                        
                    case 'lpop':
                        $poppedValue = $redis->lpop($listKey);
                        if ($poppedValue === null) {
                            $response['message'] = 'List is empty, nothing to pop';
                        } else {
                            $response = [
                                'success' => true,
                                'message' => "Removed '$poppedValue' from the left of list"
                            ];
                        }
                        break;
                        
                    case 'rpop':
                        $poppedValue = $redis->rpop($listKey);
                        if ($poppedValue === null) {
                            $response['message'] = 'List is empty, nothing to pop';
                        } else {
                            $response = [
                                'success' => true,
                                'message' => "Removed '$poppedValue' from the right of list"
                            ];
                        }
                        break;
                        
                    default:
                        $response['message'] = 'Invalid action. Supported actions: lpush, rpush, lpop, rpop';
                        break;
                }
            } catch (Exception $e) {
                $response = [
                    'success' => false,
                    'message' => 'Redis operation failed: ' . $e->getMessage()
                ];
            }
        }
    } else {
        $response = [
            'success' => false,
            'message' => 'Method not allowed. Use GET or POST.'
        ];
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_type' => get_class($e)
    ];
}

// Clear any output that might have been generated
ob_end_clean();

// Send JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
?>