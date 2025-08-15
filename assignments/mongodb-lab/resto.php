<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Response helper function
function sendResponse($success, $data = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    // Load Composer autoloader
    require_once 'vendor/autoload.php';
    
    // Connect to MongoDB with correct connection string
    $client = new MongoDB\Client('mongodb://localhost:27017');
    
    // Select database and collection (corrected to restaurant_db)
    $resto = $client->selectDatabase('restaurant_db')->selectCollection('restaurants');
    
    // Get the requested action
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'ping':
            // Test connection
            $client->selectDatabase('admin')->command(['ping' => 1]);
            sendResponse(true, ['message' => 'Database connection successful']);
            break;
            
        case 'count':
            // Count total restaurants
            try {
                $count = $resto->countDocuments();
                sendResponse(true, ['total_restaurants' => $count]); // ← Fixed: send $count not $client
            } catch (Exception $e) {
                sendResponse(false, null, 'Error counting restaurants: ' . $e->getMessage());
            }
            break;
            
        case 'sample':
            // Get sample restaurant
            try {
                $sample = $resto->findOne();
                if ($sample) {
                    sendResponse(true, [
                        'name' => $sample['name'] ?? 'N/A',
                        'borough' => $sample['borough'] ?? 'N/A',
                        'cuisine' => $sample['cuisine'] ?? 'N/A',
                        'restaurant_id' => $sample['restaurant_id'] ?? 'N/A'
                    ]);
                } else {
                    sendResponse(false, null, 'No restaurants found in collection');
                }
            } catch (Exception $e) {
                sendResponse(false, null, 'Error getting sample: ' . $e->getMessage());
            }
            break;
            
        case 'debug-info':
            // Debug endpoint
            try {
                $databases = [];
                foreach ($client->listDatabases() as $db) {
                    $databases[] = $db['name'];
                }
                
                $collections = [];
                foreach ($client->restaurant_db->listCollections() as $collection) {
                    $collections[] = $collection['name'];
                }
                
                $count = $resto->countDocuments();
                $sample = $resto->findOne();
                
                $debugInfo = [
                    'available_databases' => $databases,
                    'collections_in_restaurant_db' => $collections,
                    'restaurant_count' => $count,
                    'sample_borough' => $sample['borough'] ?? 'N/A',
                    'database_used' => 'restaurant_db',
                    'collection_used' => 'restaurants'
                ];
                
                sendResponse(true, $debugInfo);
            } catch (Exception $e) {
                sendResponse(false, null, 'Debug error: ' . $e->getMessage());
            }
            break;
            
        case 'all-restaurants':
            // Get all restaurants for table view
            try {
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0; // 0 = no limit
                $skip = isset($_GET['skip']) ? (int)$_GET['skip'] : 0;
                
                $options = ['projection' => ['_id' => 0]];
                if ($limit > 0) {
                    $options['limit'] = $limit;
                }
                if ($skip > 0) {
                    $options['skip'] = $skip;
                }
                
                $cursor = $resto->find([], $options);
                $restaurants = [];
                
                foreach ($cursor as $doc) {
                    $restaurant = [];
                    foreach ($doc as $key => $value) {
                        if (is_string($value) || is_numeric($value)) {
                            $restaurant[$key] = $value;
                        } else if (is_array($value) || is_object($value)) {
                            if ($key === 'grades') {
                                // Special handling for grades array
                                $grades = [];
                                foreach ($value as $grade) {
                                    $gradeItem = [];
                                    if (isset($grade['date'])) {
                                        $date = $grade['date'];
                                        if (is_object($date) && method_exists($date, 'toDateTime')) {
                                            $gradeItem['date'] = $date->toDateTime()->format('Y-m-d');
                                        } else {
                                            $gradeItem['date'] = (string) $date;
                                        }
                                    }
                                    if (isset($grade['grade'])) {
                                        $gradeItem['grade'] = $grade['grade'];
                                    }
                                    if (isset($grade['score'])) {
                                        $gradeItem['score'] = $grade['score'];
                                    }
                                    $grades[] = $gradeItem;
                                }
                                $restaurant[$key] = $grades;
                            } else {
                                $restaurant[$key] = json_decode(json_encode($value), true);
                            }
                        }
                    }
                    $restaurants[] = $restaurant;
                }
                
                sendResponse(true, $restaurants);
                
            } catch (Exception $e) {
                sendResponse(false, null, 'Error getting all restaurants: ' . $e->getMessage());
            }
            break;
            
        case 'boroughs':
            // Get distinct boroughs
            try {
                $cursor = $resto->distinct('borough');
                $boroughs = [];
                
                foreach ($cursor as $borough) {
                    if (!empty($borough)) {
                        $boroughs[] = $borough;
                    }
                }
                
                sendResponse(true, $boroughs);
            } catch (Exception $e) {
                sendResponse(false, null, 'Error getting boroughs: ' . $e->getMessage());
            }
            break;
            
        case 'staten-island':
            // Get Staten Island restaurants
            $cursor = $resto->find(
                ['borough' => 'Staten Island'],
                [
                    'projection' => ['_id' => 0],
                    'limit' => 10
                ]
            );
            
            $restaurants = [];
            foreach ($cursor as $doc) {
                // Convert MongoDB document to associative array
                $restaurant = [];
                foreach ($doc as $key => $value) {
                    if (is_string($value) || is_numeric($value)) {
                        $restaurant[$key] = $value;
                    } else if (is_array($value) || is_object($value)) {
                        // Convert objects/arrays to JSON for frontend
                        if ($key === 'grades') {
                            // Special handling for grades array
                            $grades = [];
                            foreach ($value as $grade) {
                                $gradeItem = [];
                                if (isset($grade['date'])) {
                                    $date = $grade['date'];
                                    if (is_object($date) && method_exists($date, 'toDateTime')) {
                                        $gradeItem['date'] = $date->toDateTime()->format('Y-m-d');
                                    } else {
                                        $gradeItem['date'] = (string) $date;
                                    }
                                }
                                if (isset($grade['grade'])) {
                                    $gradeItem['grade'] = $grade['grade'];
                                }
                                if (isset($grade['score'])) {
                                    $gradeItem['score'] = $grade['score'];
                                }
                                $grades[] = $gradeItem;
                            }
                            $restaurant[$key] = $grades;
                        } else {
                            $restaurant[$key] = json_decode(json_encode($value), true);
                        }
                    }
                }
                $restaurants[] = $restaurant;
            }
            
            sendResponse(true, $restaurants);
            break;
            
        case 'high-score':
            // Get restaurants with high scores (> 70)
            $cursor = $resto->find(
                ['grades.score' => ['$gt' => 70]],
                [
                    'projection' => ['_id' => 0],
                    'limit' => 5
                ]
            );
            
            $restaurants = [];
            foreach ($cursor as $doc) {
                $restaurant = [];
                foreach ($doc as $key => $value) {
                    if (is_string($value) || is_numeric($value)) {
                        $restaurant[$key] = $value;
                    } else if ($key === 'grades' && is_array($value)) {
                        // Special handling for grades array
                        $grades = [];
                        foreach ($value as $grade) {
                            $gradeItem = [];
                            if (isset($grade['date'])) {
                                $date = $grade['date'];
                                if (is_object($date) && method_exists($date, 'toDateTime')) {
                                    $gradeItem['date'] = $date->toDateTime()->format('Y-m-d');
                                } else {
                                    $gradeItem['date'] = (string) $date;
                                }
                            }
                            if (isset($grade['grade'])) {
                                $gradeItem['grade'] = $grade['grade'];
                            }
                            if (isset($grade['score'])) {
                                $gradeItem['score'] = $grade['score'];
                            }
                            $grades[] = $gradeItem;
                        }
                        $restaurant[$key] = $grades;
                    } else if (is_array($value) || is_object($value)) {
                        $restaurant[$key] = json_decode(json_encode($value), true);
                    }
                }
                $restaurants[] = $restaurant;
            }
            
            sendResponse(true, $restaurants);
            break;
            
        case 'restaurant-by-id':
            // Get specific restaurant by ID (optional endpoint)
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendResponse(false, null, 'Restaurant ID is required');
            }
            
            try {
                $objectId = new MongoDB\BSON\ObjectId($id);
                $restaurant = $resto->findOne(['_id' => $objectId]);
                
                if ($restaurant) {
                    // Convert to associative array
                    $result = json_decode(json_encode($restaurant), true);
                    sendResponse(true, $result);
                } else {
                    sendResponse(false, null, 'Restaurant not found');
                }
            } catch (Exception $e) {
                sendResponse(false, null, 'Invalid restaurant ID format');
            }
            break;
            
        case 'search':
            // Search restaurants by name or cuisine (optional endpoint)
            $query = $_GET['query'] ?? '';
            $limit = (int) ($_GET['limit'] ?? 10);
            
            if (empty($query)) {
                sendResponse(false, null, 'Search query is required');
            }
            
            $searchCondition = [
                '$or' => [
                    ['name' => ['$regex' => $query, '$options' => 'i']],
                    ['cuisine' => ['$regex' => $query, '$options' => 'i']]
                ]
            ];
            
            $cursor = $resto->find(
                $searchCondition,
                [
                    'projection' => ['_id' => 0, 'name' => 1, 'cuisine' => 1, 'borough' => 1, 'address' => 1],
                    'limit' => $limit
                ]
            );
            
            $results = [];
            foreach ($cursor as $doc) {
                $results[] = json_decode(json_encode($doc), true);
            }
            
            sendResponse(true, $results);
            break;
            
        default:
            sendResponse(false, null, 'Invalid action. Available actions: ping, count, sample, debug-info, boroughs, all-restaurants, staten-island, high-score, restaurant-by-id, search');
            break;
    }
    
} catch (MongoDB\Exception\Exception $e) {
    sendResponse(false, null, 'MongoDB Error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(false, null, 'Error: ' . $e->getMessage());
}
?>