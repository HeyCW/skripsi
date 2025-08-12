<?php
require_once 'vendor/autoload.php';

// Redis connection with Predis
$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => '54.153.34.27',
    'port'   => 6379,
]);

// Handle form submissions
if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $action = $_POST['action'] ?? '';
    
    if (!empty($name) && in_array($action, ['lpush', 'rpush'])) {
        // Add to list
        if ($action === 'lpush') {
            $redis->lpush('people_list', $name);
        } else {
            $redis->rpush('people_list', $name);
        }
    } elseif (in_array($action, ['lpop', 'rpop'])) {
        // Remove from list
        if ($action === 'lpop') {
            $redis->lpop('people_list');
        } else {
            $redis->rpop('people_list');
        }
    }
    
    // Redirect to prevent resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get current list (max 10 items)
$people = $redis->lrange('people_list', 0, 9);
?>