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

<!DOCTYPE html>
<html>
<head>
    <title>List of People</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        table { border-collapse: collapse; width: 100%; max-width: 500px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: center; }
        th { background-color: #f0f0f0; }
        .buttons { margin: 20px 0; }
        button { padding: 10px 15px; margin: 5px; cursor: pointer; }
        input[type="text"] { padding: 8px; margin: 10px; width: 200px; }
        .container { max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PEOPLE</h1>
        
        <table>
            <thead>
                <tr><th>PEOPLE</th></tr>
            </thead>
            <tbody>
                <?php if (empty($people)): ?>
                    <tr><td colspan="1">No people in list</td></tr>
                <?php else: ?>
                    <?php foreach ($people as $person): ?>
                        <tr><td><?= htmlspecialchars($person) ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Fill remaining rows to make 10 total -->
                <?php for ($i = count($people); $i < 10; $i++): ?>
                    <tr><td>&nbsp;</td></tr>
                <?php endfor; ?>
            </tbody>
        </table>
        
        <div class="buttons">
            <form method="POST" style="display: inline-block;">
                <input type="text" name="name" placeholder="Enter name" required>
                <br>
                <button type="submit" name="action" value="lpush">LPUSH</button>
                <button type="submit" name="action" value="lpop">LPOP</button>
                <button type="submit" name="action" value="rpop">RPOP</button>
                <button type="submit" name="action" value="rpush">RPUSH</button>
            </form>
        </div>
        
        <div>
            <small>
                List length: <?= $redis->llen('people_list') ?> | 
                Redis status: <?= $redis->ping() ? 'Connected' : 'Disconnected' ?>
            </small>
        </div>
    </div>
</body>
</html>