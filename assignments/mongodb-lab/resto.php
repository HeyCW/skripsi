<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');

try {
    // Load Composer autoloader
    require_once 'vendor/autoload.php';
    
    // Connect to MongoDB
    $client = new MongoDB\Client();
    // Alternative connection: $client = new MongoDB\Client('mongodb://192.168.38.200:27017');
    
    // Select database and collection
    $resto = $client->hnp->restaurants;
    
    // Test connection
    $client->selectDatabase('admin')->command(['ping' => 1]);
    
} catch (Exception $e) {
    die('<div class="error">Connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Data Analysis</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fafafa;
        }
        .borough-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }
        .borough-item {
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        .restaurant {
            background: white;
            margin: 15px 0;
            padding: 15px;
            border-left: 4px solid #28a745;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .restaurant-field {
            margin: 5px 0;
            padding: 3px 0;
        }
        .field-name {
            font-weight: bold;
            color: #495057;
            display: inline-block;
            min-width: 120px;
        }
        .field-value {
            color: #6c757d;
        }
        .grades {
            margin: 10px 0;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
        }
        .grade-item {
            background: white;
            margin: 5px 0;
            padding: 8px;
            border-radius: 3px;
            border-left: 3px solid #ffc107;
        }
        .high-score {
            border-left-color: #dc3545;
        }
        .count {
            background-color: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🍽️ Restaurant Data Analysis</h1>
        
        <div class="success">
            ✅ Successfully connected to MongoDB database!
        </div>

        <!-- Section 1: Borough List -->
        <div class="section">
            <h2>📍 Available Boroughs</h2>
            <?php
            try {
                $cursor = $resto->distinct('borough');
                $boroughCount = 0;
                
                echo '<div class="borough-list">';
                foreach ($cursor as $borough) {
                    if (!empty($borough)) {
                        echo '<span class="borough-item">' . htmlspecialchars($borough) . '</span>';
                        $boroughCount++;
                    }
                }
                echo '</div>';
                echo '<p><strong>Total Boroughs:</strong> <span class="count">' . $boroughCount . '</span></p>';
                
            } catch (Exception $e) {
                echo '<div class="error">Error fetching boroughs: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <!-- Section 2: Staten Island Restaurants -->
        <div class="section">
            <h2>🏝️ Restaurants in Staten Island</h2>
            <?php
            try {
                $cursor = $resto->find(
                    ['borough' => 'Staten Island'],
                    [
                        'projection' => ['_id' => 0],
                        'limit' => 10 // Limit for better performance
                    ]
                );
                
                $count = 0;
                foreach ($cursor as $doc) {
                    $count++;
                    echo '<div class="restaurant">';
                    echo '<h3>Restaurant #' . $count . '</h3>';
                    
                    foreach ($doc as $key => $value) {
                        if (is_string($value) || is_numeric($value)) {
                            echo '<div class="restaurant-field">';
                            echo '<span class="field-name">' . ucfirst(str_replace('_', ' ', $key)) . ':</span> ';
                            echo '<span class="field-value">' . htmlspecialchars($value) . '</span>';
                            echo '</div>';
                        }
                        // Handle arrays and objects (like address, grades)
                        else if (is_array($value) || is_object($value)) {
                            if ($key !== 'grades') { // Handle grades separately later
                                echo '<div class="restaurant-field">';
                                echo '<span class="field-name">' . ucfirst(str_replace('_', ' ', $key)) . ':</span> ';
                                echo '<span class="field-value">' . htmlspecialchars(json_encode($value)) . '</span>';
                                echo '</div>';
                            }
                        }
                    }
                    echo '</div>';
                }
                
                if ($count == 0) {
                    echo '<p>No restaurants found in Staten Island.</p>';
                } else {
                    echo '<p><strong>Showing first 10 restaurants.</strong> <span class="count">' . $count . ' displayed</span></p>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">Error fetching Staten Island restaurants: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <!-- Section 3: High Score Restaurants -->
        <div class="section">
            <h2>⭐ Restaurants with High Scores (> 70)</h2>
            <?php
            try {
                $cursor = $resto->find(
                    ['grades.score' => ['$gt' => 70]],
                    [
                        'projection' => ['_id' => 0],
                        'limit' => 5 // Limit for better performance
                    ]
                );
                
                $count = 0;
                foreach ($cursor as $doc) {
                    $count++;
                    echo '<div class="restaurant">';
                    echo '<h3>High-Rated Restaurant #' . $count . '</h3>';
                    
                    foreach ($doc as $key => $value) {
                        if (is_string($value) || is_numeric($value)) {
                            echo '<div class="restaurant-field">';
                            echo '<span class="field-name">' . ucfirst(str_replace('_', ' ', $key)) . ':</span> ';
                            echo '<span class="field-value">' . htmlspecialchars($value) . '</span>';
                            echo '</div>';
                        }
                        
                        // Handle grades array specially
                        if ($key === "grades" && is_array($value)) {
                            echo '<div class="grades">';
                            echo '<span class="field-name">Grades:</span>';
                            
                            foreach ($value as $grade) {
                                $scoreClass = (isset($grade['score']) && $grade['score'] > 70) ? 'grade-item high-score' : 'grade-item';
                                echo '<div class="' . $scoreClass . '">';
                                
                                if (isset($grade['date'])) {
                                    $date = $grade['date'];
                                    if (is_object($date) && method_exists($date, 'toDateTime')) {
                                        $date = $date->toDateTime()->format('Y-m-d');
                                    }
                                    echo '<strong>Date:</strong> ' . htmlspecialchars($date) . ' | ';
                                }
                                
                                if (isset($grade['grade'])) {
                                    echo '<strong>Grade:</strong> ' . htmlspecialchars($grade['grade']) . ' | ';
                                }
                                
                                if (isset($grade['score'])) {
                                    echo '<strong>Score:</strong> ' . htmlspecialchars($grade['score']);
                                }
                                
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                }
                
                if ($count == 0) {
                    echo '<p>No restaurants found with grades score > 70.</p>';
                } else {
                    echo '<p><strong>Showing first 5 high-rated restaurants.</strong> <span class="count">' . $count . ' displayed</span></p>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">Error fetching high-score restaurants: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <footer style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #6c757d;">
            <p>🔗 MongoDB Restaurant Data Analysis | Generated on <?php echo date('Y-m-d H:i:s'); ?></p>
        </footer>
    </div>
</body>
</html>