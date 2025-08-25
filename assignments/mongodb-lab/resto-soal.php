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
function sendResponse($success, $data = null, $error = null, $meta = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'meta' => $meta,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    // Load Composer autoloader
    require_once 'vendor/autoload.php';
    
    // Connect to MongoDB
    $client = new MongoDB\Client('mongodb://localhost:27017');
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
                sendResponse(true, ['total_restaurants' => $count]);
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
            
        case 'filter-options':
            // Get unique boroughs and cuisines for filter dropdowns
            try {
                $boroughs = $resto->distinct('borough');
                $cuisines = $resto->distinct('cuisine');
                
                // Sort the arrays
                sort($boroughs);
                sort($cuisines);
                
                sendResponse(true, [
                    'boroughs' => array_values(array_filter($boroughs)),
                    'cuisines' => array_values(array_filter($cuisines))
                ]);
            } catch (Exception $e) {
                sendResponse(false, null, 'Error getting filter options: ' . $e->getMessage());
            }
            break;
            
        case 'restaurants':
            // Get filtered restaurants with pagination
            try {
                // Get parameters
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = max(1, min(100, intval($_GET['limit'] ?? 50))); // Max 100 per page
                $search = $_GET['search'] ?? '';
                $borough = $_GET['borough'] ?? '';
                $cuisine = $_GET['cuisine'] ?? '';
                $maxScore = $_GET['max_score'] ?? '';
                $sortBy = $_GET['sort_by'] ?? 'name';
                $sortDir = ($_GET['sort_dir'] ?? 'asc') === 'desc' ? -1 : 1;
                
                // Build MongoDB filter
                $filter = [];
                
                // Search filter (case-insensitive regex)
                if (!empty($search)) {
                    $filter['$or'] = [
                        ['name' => ['$regex' => $search, '$options' => 'i']],
                        ['cuisine' => ['$regex' => $search, '$options' => 'i']],
                        ['borough' => ['$regex' => $search, '$options' => 'i']],
                        ['address.street' => ['$regex' => $search, '$options' => 'i']],
                        ['address.zipcode' => ['$regex' => $search, '$options' => 'i']]
                    ];
                }
                
                // Borough filter
                if (!empty($borough)) {
                    $filter['borough'] = $borough;
                }
                
                // Cuisine filter
                if (!empty($cuisine)) {
                    $filter['cuisine'] = $cuisine;
                }
                
                // Score filter - find restaurants where latest grade score <= maxScore
                if (!empty($maxScore) && is_numeric($maxScore)) {
                    $maxScoreNum = floatval($maxScore);
                    
                    // Use aggregation pipeline to filter by latest score
                    $pipeline = [
                        ['$match' => $filter],
                        [
                            '$addFields' => [
                                'latestGrade' => [
                                    '$arrayElemAt' => [
                                        [
                                            '$sortArray' => [
                                                'input' => '$grades',
                                                'sortBy' => ['date' => -1] // Sort by date descending
                                            ]
                                        ],
                                        0 // Get first element (latest)
                                    ]
                                ]
                            ]
                        ],
                        [
                            '$match' => [
                                'latestGrade.score' => ['$lte' => $maxScoreNum]
                            ]
                        ]
                    ];
                    
                    // Count total matching documents
                    $countPipeline = array_merge($pipeline, [
                        ['$count' => 'total']
                    ]);
                    
                    $countResult = $resto->aggregate($countPipeline)->toArray();
                    $totalCount = $countResult[0]['total'] ?? 0;
                    
                    // Add sorting and pagination
                    $pipeline[] = ['$sort' => [$sortBy => $sortDir]];
                    $pipeline[] = ['$skip' => ($page - 1) * $limit];
                    $pipeline[] = ['$limit' => $limit];
                    
                    $cursor = $resto->aggregate($pipeline);
                    
                } else {
                    // Simple query without score filtering
                    
                    // Count total matching documents
                    $totalCount = $resto->countDocuments($filter);
                    
                    // Build sort array
                    $sort = [$sortBy => $sortDir];
                    
                    // Execute query with pagination
                    $options = [
                        'sort' => $sort,
                        'skip' => ($page - 1) * $limit,
                        'limit' => $limit
                    ];
                    
                    $cursor = $resto->find($filter, $options);
                }
                
                // Process results
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
                
                // Calculate pagination info
                $totalPages = ceil($totalCount / $limit);
                
                $meta = [
                    'total_count' => $totalCount,
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                    'filters_applied' => [
                        'search' => $search,
                        'borough' => $borough,
                        'cuisine' => $cuisine,
                        'max_score' => $maxScore
                    ],
                    'sort' => [
                        'column' => $sortBy,
                        'direction' => $sortDir === 1 ? 'asc' : 'desc'
                    ]
                ];
                
                sendResponse(true, $restaurants, null, $meta);
                
            } catch (Exception $e) {
                sendResponse(false, null, 'Error getting restaurants: ' . $e->getMessage());
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
                    'collection_used' => 'restaurants',
                    'available_actions' => [
                        'ping', 'count', 'sample', 'debug-info', 
                        'filter-options', 'restaurants', 'all-restaurants'
                    ]
                ];
                
                sendResponse(true, $debugInfo);
            } catch (Exception $e) {
                sendResponse(false, null, 'Debug error: ' . $e->getMessage());
            }
            break;
            
        default:
            sendResponse(false, null, 'Invalid action. Available actions: ping, count, sample, debug-info, filter-options, restaurants, all-restaurants');
            break;
    }
    
} catch (MongoDB\Exception\Exception $e) {
    sendResponse(false, null, 'MongoDB Error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(false, null, 'Error: ' . $e->getMessage());
}
?>