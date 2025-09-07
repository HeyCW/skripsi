<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1); // Show errors for debugging
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
$neo4jBoltPort = '7687';     // Bolt port untuk Neo4j
$neo4jHttpPort = '7474';     // HTTP port untuk Neo4j (optional)
$neo4jUser = 'neo4j';
$neo4jPassword = 'password123'; // Sesuaikan dengan password Neo4j Anda
$neo4jDatabase = 'neo4j';    // Default database name

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
            
            // Create Neo4j client with explicit driver configuration
            $connectionString = "bolt://{$user}:{$password}@{$host}:{$port}";
            
            $this->client = ClientBuilder::create()
                ->withDriver($this->driverAlias, $connectionString)
                ->withDefaultDriver($this->driverAlias)
                ->build();
                
        } catch (Exception $e) {
            // Try alternative connection methods
            try {
                // Fallback to HTTP if Bolt fails
                $httpConnectionString = "http://{$user}:{$password}@{$host}:7474";
                $this->client = ClientBuilder::create()
                    ->withDriver($this->driverAlias, $httpConnectionString)
                    ->withDefaultDriver($this->driverAlias)
                    ->build();
            } catch (Exception $e2) {
                throw new Exception("Failed to create Neo4j client with both Bolt and HTTP: " . $e->getMessage() . " | " . $e2->getMessage());
            }
        }
    }
    
    public function executeQuery($query, $parameters = []) {
        try {
            // Execute query using the configured driver
            if ($this->database !== 'neo4j') {
                // For specific database
                $result = $this->client->runStatement($query, $parameters, $this->driverAlias, $this->database);
            } else {
                // For default database
                $result = $this->client->run($query, $parameters, $this->driverAlias);
            }
            return $result;
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
    
    public function getConnectionInfo() {
        return [
            'database' => $this->database,
            'client_type' => 'Laudis Neo4j PHP Client',
            'driver_alias' => $this->driverAlias
        ];
    }
}

// Create missing relationships if they don't exist
function createMissingRelationships($neo4j) {
    try {
        // Check if SUPPLIES relationships exist
        $checkSupplies = "MATCH ()-[r:SUPPLIES]->() RETURN count(r) as count";
        $result = $neo4j->executeQuery($checkSupplies);
        $suppliesCount = $result->first()->get('count');
        
        // Check if PART_OF relationships exist  
        $checkPartOf = "MATCH ()-[r:PART_OF]->() RETURN count(r) as count";
        $result = $neo4j->executeQuery($checkPartOf);
        $partOfCount = $result->first()->get('count');
        
        if ($suppliesCount == 0) {
            // Create SUPPLIES relationships based on existing data
            $suppliesQuery = "
                MATCH (s:Supplier), (p:Product)
                WHERE s.supplierID = p.supplierID
                CREATE (s)-[:SUPPLIES]->(p)
                RETURN count(*) as created
            ";
            $result = $neo4j->executeQuery($suppliesQuery);
            error_log("Created SUPPLIES relationships: " . $result->first()->get('created'));
        }
        
        if ($partOfCount == 0) {
            // Create PART_OF relationships based on existing data
            $partOfQuery = "
                MATCH (p:Product), (c:Category)
                WHERE p.categoryID = c.categoryID
                CREATE (p)-[:PART_OF]->(c)
                RETURN count(*) as created
            ";
            $result = $neo4j->executeQuery($partOfQuery);
            error_log("Created PART_OF relationships: " . $result->first()->get('created'));
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating relationships: " . $e->getMessage());
        return false;
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
    // Initialize Neo4j connection using Laudis client
    $neo4j = new Neo4jService($neo4jHost, $neo4jBoltPort, $neo4jUser, $neo4jPassword, $neo4jDatabase);
    
    // Test connection first
    if (!$neo4j->testConnection()) {
        handleError("Cannot connect to Neo4j Docker container. Please check if Neo4j is running and credentials are correct.", [
            'host' => $neo4jHost,
            'port' => $neo4jBoltPort,
            'database' => $neo4jDatabase,
            'user' => $neo4jUser,
            'connection_type' => 'Bolt Protocol (Laudis Client)'
        ]);
    }
    
    // Create missing relationships if needed (since data already exists)
    createMissingRelationships($neo4j);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $company = trim($_POST['company'] ?? '');
        
        if (empty($company)) {
            echo json_encode([
                'success' => false,
                'message' => 'Company name is required'
            ]);
            exit;
        }
        
        // The main competitor analysis query as specified
        // This finds suppliers that supply products in the same categories as the input company
        $query = "
            MATCH (s1:Supplier)-[:SUPPLIES]->(p1:Product)-[:PART_OF]->(c:Category)<-[:PART_OF]-(p2:Product)<-[:SUPPLIES]-(s2:Supplier) 
            WHERE s1.companyName = \$company AND s1 <> s2 
            RETURN s2.companyName as Competitor, count(DISTINCT c) as NoProducts 
            ORDER BY NoProducts DESC, Competitor ASC
        ";
        
        $parameters = ['company' => $company];
        
        $result = $neo4j->executeQuery($query, $parameters);
        $data = resultToArray($result);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'query' => $query,
            'parameters' => $parameters,
            'result_count' => count($data)
        ]);
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $action = $_GET['action'] ?? 'default';

        switch ($action) {
            case 'getCompanies':
                $query = "MATCH (s:Supplier) RETURN s.companyName as companyName ORDER BY s.companyName";
                $result = $neo4j->executeQuery($query);
            
                $companies = [];
                foreach ($result as $record) {
                    $companyName = $record->get('companyName');
                    if (!empty($companyName)) {
                        $companies[] = [
                            'name' => $companyName // For compatibility
                        ];
                    }
                }
                $response = [
                    'success' => true,
                    'message' => 'Companies retrieved successfully',
                    'data' => $companies,
                    'count' => count($companies)
                ];
                // Ensure clean JSON output
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit(); // Important: stop execution here
                
            case 'getStats':
                // Get database statistics
                $statsQueries = [
                    'suppliers' => "MATCH (s:Supplier) RETURN count(s) as count",
                    'products' => "MATCH (p:Product) RETURN count(p) as count",
                    'categories' => "MATCH (c:Category) RETURN count(c) as count",
                    'supplies_relationships' => "MATCH ()-[r:SUPPLIES]->() RETURN count(r) as count",
                    'part_of_relationships' => "MATCH ()-[r:PART_OF]->() RETURN count(r) as count"
                ];
            
                $stats = [];
                foreach ($statsQueries as $key => $query) {
                    try {
                        $result = $neo4j->executeQuery($query);
                        $stats[$key] = $result->first()->get('count');
                    } catch (Exception $e) {
                        $stats[$key] = 0;
                    }
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Statistics retrieved successfully',
                    'data' => $stats
                ];
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit();
                
            default:
                // Handle invalid actions
                $response = [
                    'success' => false,
                    'message' => "Invalid action: '{$action}'. Supported actions are: getCompanies, getStats",
                    'data' => [],
                    'error_code' => 'INVALID_ACTION'
                ];
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400); // Bad Request
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit();
        }
    }
    
} catch (Exception $e) {
    error_log("Application Error: " . $e->getMessage());
    handleError('Application error: ' . $e->getMessage(), [
        'host' => $neo4jHost,
        'bolt_port' => $neo4jBoltPort,
        'database' => $neo4jDatabase,
        'error_type' => get_class($e),
        'error_trace' => $e->getTraceAsString()
    ]);
}
?>