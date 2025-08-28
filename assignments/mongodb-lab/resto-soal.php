<?php
// SOAL LATIHAN - Restaurant Data Backend Filtering
// Lengkapi bagian TODO untuk membuat aplikasi yang berfungsi penuh

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
    // TODO 1: LENGKAPI MONGODB CONNECTION CONFIGURATION DAN ERROR HANDLING
    // Tugas: Load Composer autoloader dan buat connection ke MongoDB
    // HINT: Gunakan MongoDB\Client
    // HINT: Select database 'restaurant_db' dan collection 'restaurants'
    // HINT: Tambahkan error handling untuk connection failure
    
    // KODE ANDA DI SINI:
    
    
    
    // Get the requested action
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'ping':
            // Test connection
            $client->selectDatabase('admin')->command(['ping' => 1]);
            sendResponse(true, ['message' => 'Database connection successful']);
            break;
            
        // TODO 2: LENGKAPI FUNCTION GETFILTEROPTIONS()
        // Tugas: Ambil unique boroughs dan cuisines untuk dropdown filters
        case 'filter-options':
            try {
                // HINT: Gunakan distinct() method untuk ambil unique values
                // HINT: Sort arrays dan filter empty values
                // HINT: Return dalam format ['boroughs' => [...], 'cuisines' => [...]]
                
                // KODE ANDA DI SINI:
                
                
            } catch (Exception $e) {
                sendResponse(false, null, 'Error getting filter options: ' . $e->getMessage());
            }
            break;
            
        // TODO 3-6: LENGKAPI CASE 'RESTAURANTS' UNTUK FILTERING DAN PAGINATION
        case 'restaurants':
            try {
                // TODO 3: LENGKAPI PARAMETER EXTRACTION DAN VALIDATION
                // HINT: Extract page, limit, search, borough, cuisine, maxScore, sortBy, sortDir
                // HINT: Validate dan set default values
                // HINT: Ensure page >= 1, limit between 1-100
                
                // KODE ANDA DI SINI:
                
                
                // TODO 4: LENGKAPI BUILDQUERY() - BUAT MONGODB FILTER
                // HINT: Build $filter array untuk MongoDB query
                // HINT: Search menggunakan $or dengan regex pattern matching
                // HINT: Borough dan cuisine menggunakan exact match
                
                // KODE ANDA DI SINI:
                
                
                // TODO 5: LENGKAPI SCORE FILTERING DAN AGGREGATION PIPELINE
                // HINT: Jika maxScore ada, gunakan aggregation pipeline
                // HINT: $addFields untuk latest grade, $match untuk score filter
                // HINT: Jika tidak ada maxScore, gunakan simple find query
                
                if (!empty($maxScore) && is_numeric($maxScore)) {
                    $maxScoreNum = floatval($maxScore);
                    
                    // KODE ANDA DI SINI - LENGKAPI AGGREGATION PIPELINE:
                    
                    
                } else {
                    // TODO: LENGKAPI SIMPLE QUERY LOGIC
                    // HINT: Count documents, build sort array, execute find dengan options
                    
                    // KODE ANDA DI SINI:
                    
                }
                
                // Process results - Convert MongoDB documents to arrays
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
                
                // TODO 6: LENGKAPI PAGINATION INFO DAN RESPONSE
                // HINT: Calculate totalPages, build meta array dengan pagination info
                // HINT: Include filters_applied dan sort information
                
                // KODE ANDA DI SINI:
                
                
            } catch (Exception $e) {
                sendResponse(false, null, 'Error getting restaurants: ' . $e->getMessage());
            }
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
                        'filter-options', 'restaurants'
                    ]
                ];
                
                sendResponse(true, $debugInfo);
            } catch (Exception $e) {
                sendResponse(false, null, 'Debug error: ' . $e->getMessage());
            }
            break;
            
        default:
            sendResponse(false, null, 'Invalid action. Available actions: ping, count, sample, debug-info, filter-options, restaurants');
            break;
    }
    
} catch (MongoDB\Exception\Exception $e) {
    sendResponse(false, null, 'MongoDB Error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(false, null, 'Error: ' . $e->getMessage());
}
