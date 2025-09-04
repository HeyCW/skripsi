<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set JSON headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include Composer autoloader
require_once 'vendor/autoload.php';

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Exception\Neo4jException;

// Database configuration for Neo4j Docker
$neo4jHost = 'localhost';
$neo4jBoltPort = '7687';
$neo4jUser = 'neo4j';
$neo4jPassword = 'password123';
$neo4jDatabase = 'neo4j';

// Error handler function
function handleError($message, $debugInfo = null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'debug_info' => $debugInfo
    ]);
    exit;
}

class Neo4jService {
    private $client;
    private $database;
    private $driverAlias;
    
    public function __construct($host, $port, $user, $password, $database = 'neo4j') {
        try {
            $this->database = $database;
            $this->driverAlias = 'neo4j_connection';
            
            // TODO 6: BUAT CONNECTION STRING DAN NEO4J CLIENT
            // KODE ANDA DI SINI:
            
            
        } catch (Exception $e) {
            throw new Exception("Failed to create Neo4j client: " . $e->getMessage());
        }
    }
    
    public function executeQuery($query, $parameters = []) {
        try {
            // TODO 7: JALANKAN QUERY NEO4J
            // KODE ANDA DI SINI:
            
            
        } catch (Neo4jException $e) {
            throw new Exception("Neo4j Query Error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }
    
    public function testConnection() {
        try {
            $result = $this->executeQuery("RETURN 'Connection successful' as message");
            return $result->count() > 0;
        } catch (Exception $e) {
            error_log("Neo4j Test Connection Failed: " . $e->getMessage());
            return false;
        }
    }
}

// Convert Neo4j result to array format
function resultToArray($result) {
    $data = [];
    foreach ($result as $record) {
        $row = [];
        foreach ($record as $key => $value) {
            $row[$key] = $value;
        }
        $data[] = $row;
    }
    return $data;
}

// Main execution
try {
    // Initialize Neo4j connection
    $neo4j = new Neo4jService($neo4jHost, $neo4jBoltPort, $neo4jUser, $neo4jPassword, $neo4jDatabase);
    
    // Test connection first
    if (!$neo4j->testConnection()) {
        handleError("Cannot connect to Neo4j Docker container.", [
            'host' => $neo4jHost,
            'port' => $neo4jBoltPort,
            'database' => $neo4jDatabase
        ]);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // TODO 8: HANDLE POST REQUEST UNTUK COMPETITOR ANALYSIS
        // HINT: Ambil company dari $_POST['company']
        // HINT: Validasi company tidak kosong
        // HINT: Buat query Cypher untuk mencari competitor
        // HINT: Return JSON response dengan data competitor
        
        // KODE ANDA DI SINI:
        
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'default';

        switch ($action) {
            case 'getCompanies':
                // TODO 9: HANDLE GET COMPANIES REQUEST
                // HINT: Return JSON response dengan data companies
                
                // KODE ANDA DI SINI:
                
                
                break;

            case 'getStats':
                // TODO 10: HANDLE GET STATISTICS REQUEST
                // HINT: Buat array statsQueries untuk count suppliers, products, categories, dll
                // HINT: Return JSON response dengan statistik
                
                // KODE ANDA DI SINI:
                
                
                break;
        }
    }
    
} catch (Exception $e) {
    error_log("Application Error: " . $e->getMessage());
    handleError('Application error: ' . $e->getMessage());
}
?>