<?php
/**
 * Neo4j Competitor Analysis API Testing Script
 * Script untuk testing Neo4j Competitor Analysis API
 */

class Neo4jApiTester {
    private $baseUrl;
    private $results = [];
    public $apiFile = 'northwind-api.php'; // Sesuaikan dengan nama file API Anda
    
    public function __construct($baseUrl = 'http://localhost') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    public function runTests() {
        echo "🚀 Starting Neo4j Competitor Analysis API Tests...\n\n";
        
        // Test all endpoints
        $this->testConnectionEndpoint();
        $this->testGetCompaniesEndpoint();
        $this->testGetStatsEndpoint();
        $this->testCompetitorAnalysisValid();
        $this->testCompetitorAnalysisInvalid();
        $this->testErrorHandling();
        $this->testResponseFormat();
        $this->testDataQuality();
        
        // Print summary
        $this->printSummary();
    }
    
    private function testConnectionEndpoint() {
        echo "🔌 Testing Database Connection...\n";
        
        // Test if API responds at all
        $response = $this->makeRequest('GET', '?action=getStats');
        
        if ($response && isset($response['success'])) {
            if ($response['success']) {
                $this->addResult('Database Connection', true, 'Neo4j connection successful');
            } else {
                $this->addResult('Database Connection', false, 'Neo4j connection failed: ' . ($response['message'] ?? 'Unknown error'));
            }
        } else {
            $this->addResult('Database Connection', false, 'API not responding or invalid response format');
        }
    }
    
    private function testGetCompaniesEndpoint() {
        echo "🏢 Testing Get Companies Endpoint...\n";
        
        $response = $this->makeRequest('GET', '?action=getCompanies');
        
        if ($response && $response['success']) {
            $companies = $response['data'] ?? [];
            $count = $response['count'] ?? 0;
            
            if (count($companies) > 0) {
                $this->addResult('Get Companies', true, "Retrieved {$count} companies");
                
                // Check data structure
                $firstCompany = $companies[0];
                if (isset($firstCompany['name'])) {
                    $this->addResult('Company Data Structure', true, 'Company names properly formatted');
                    
                    // Display some sample companies
                    $sampleCompanies = array_slice($companies, 0, 3);
                    $sampleNames = array_map(function($c) { return $c['name']; }, $sampleCompanies);
                    $this->addResult('Sample Companies', true, 'Examples: ' . implode(', ', $sampleNames));
                } else {
                    $this->addResult('Company Data Structure', false, 'Missing name field in company data');
                }
            } else {
                $this->addResult('Get Companies', false, 'No companies found in database');
            }
        } else {
            $error = $response['message'] ?? 'Unknown error';
            $this->addResult('Get Companies', false, "Failed to get companies: {$error}");
        }
    }
    
    private function testGetStatsEndpoint() {
        echo "📊 Testing Get Statistics Endpoint...\n";
        
        $response = $this->makeRequest('GET', '?action=getStats');
        
        if ($response && $response['success']) {
            $stats = $response['data'] ?? [];
            
            $expectedStats = [
                'suppliers' => 'Suppliers',
                'products' => 'Products', 
                'categories' => 'Categories',
                'supplies_relationships' => 'SUPPLIES relationships',
                'part_of_relationships' => 'PART_OF relationships'
            ];
            
            $allStatsPresent = true;
            foreach ($expectedStats as $key => $description) {
                if (isset($stats[$key])) {
                    $count = $stats[$key];
                    $this->addResult("Stats - {$description}", true, "Count: {$count}");
                    
                    if ($count == 0 && in_array($key, ['supplies_relationships', 'part_of_relationships'])) {
                        $this->addResult("Stats Warning - {$description}", false, "No relationships found - may need data setup");
                    }
                } else {
                    $this->addResult("Stats - {$description}", false, "Missing {$key} in statistics");
                    $allStatsPresent = false;
                }
            }
            
            if ($allStatsPresent) {
                $this->addResult('Statistics Complete', true, 'All expected statistics present');
            }
            
            // Check for reasonable data counts
            $suppliers = $stats['suppliers'] ?? 0;
            $products = $stats['products'] ?? 0;
            $categories = $stats['categories'] ?? 0;
            
            if ($suppliers > 0 && $products > 0 && $categories > 0) {
                $this->addResult('Data Presence Check', true, "Found {$suppliers} suppliers, {$products} products, {$categories} categories");
            } else {
                $this->addResult('Data Presence Check', false, 'Missing core data in database');
            }
            
        } else {
            $error = $response['message'] ?? 'Unknown error';
            $this->addResult('Get Statistics', false, "Failed to get statistics: {$error}");
        }
    }
    
    private function testCompetitorAnalysisValid() {
        echo "🎯 Testing Competitor Analysis with Valid Company...\n";
        
        // First get a list of companies to test with
        $companiesResponse = $this->makeRequest('GET', '?action=getCompanies');
        
        if (!$companiesResponse || !$companiesResponse['success'] || empty($companiesResponse['data'])) {
            $this->addResult('Competitor Analysis Setup', false, 'Cannot get companies for testing');
            return;
        }
        
        $companies = $companiesResponse['data'];
        $testCompany = $companies[0]['name']; // Use first company for testing
        
        // Test competitor analysis
        $response = $this->makeRequest('POST', '', ['company' => $testCompany]);
        
        if ($response && $response['success']) {
            $competitors = $response['data'] ?? [];
            $resultCount = $response['result_count'] ?? 0;
            
            $this->addResult('Competitor Analysis Query', true, "Found {$resultCount} competitors for '{$testCompany}'");
            
            if ($resultCount > 0) {
                // Check data structure
                $firstCompetitor = $competitors[0];
                $requiredFields = ['Competitor', 'NoProducts'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($firstCompetitor[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (empty($missingFields)) {
                    $this->addResult('Competitor Data Structure', true, 'All required fields present');
                    
                    // Check if results are properly sorted
                    $isSorted = true;
                    for ($i = 0; $i < count($competitors) - 1; $i++) {
                        $current = $competitors[$i]['NoProducts'];
                        $next = $competitors[$i + 1]['NoProducts'];
                        if ($current < $next) {
                            $isSorted = false;
                            break;
                        }
                    }
                    
                    if ($isSorted) {
                        $this->addResult('Results Sorting', true, 'Results properly sorted by NoProducts DESC');
                    } else {
                        $this->addResult('Results Sorting', false, 'Results not properly sorted');
                    }
                    
                    // Display sample results
                    $sampleResults = array_slice($competitors, 0, 3);
                    $samples = [];
                    foreach ($sampleResults as $comp) {
                        $samples[] = "{$comp['Competitor']} ({$comp['NoProducts']} products)";
                    }
                    $this->addResult('Sample Competitors', true, implode(', ', $samples));
                    
                } else {
                    $this->addResult('Competitor Data Structure', false, 'Missing fields: ' . implode(', ', $missingFields));
                }
                
                // Test with multiple companies
                if (count($companies) > 1) {
                    $testCompany2 = $companies[1]['name'];
                    $response2 = $this->makeRequest('POST', '', ['company' => $testCompany2]);
                    
                    if ($response2 && $response2['success']) {
                        $competitors2 = $response2['data'] ?? [];
                        $this->addResult('Multiple Company Test', true, "'{$testCompany2}' analysis also successful");
                        
                        // Check if results are different (they should be for different companies)
                        $results1 = array_column($competitors, 'Competitor');
                        $results2 = array_column($competitors2, 'Competitor');
                        
                        if ($results1 !== $results2) {
                            $this->addResult('Result Differentiation', true, 'Different companies return different competitors');
                        } else {
                            $this->addResult('Result Differentiation', false, 'Same competitors for different companies (suspicious)');
                        }
                    }
                }
                
            } else {
                $this->addResult('Competitor Results', true, "No competitors found for '{$testCompany}' (may be expected)");
            }
            
            // Check if query and parameters are logged
            if (isset($response['query']) && isset($response['parameters'])) {
                $this->addResult('Query Logging', true, 'Query and parameters properly logged');
            } else {
                $this->addResult('Query Logging', false, 'Missing query logging information');
            }
            
        } else {
            $error = $response['message'] ?? 'Unknown error';
            $this->addResult('Competitor Analysis Query', false, "Analysis failed: {$error}");
        }
    }
    
    private function testCompetitorAnalysisInvalid() {
        echo "❌ Testing Competitor Analysis with Invalid Data...\n";
        
        // Test with empty company name
        $response = $this->makeRequest('POST', '', ['company' => '']);
        
        if ($response && !$response['success']) {
            $this->addResult('Empty Company Validation', true, 'Correctly rejected empty company name');
        } else {
            $this->addResult('Empty Company Validation', false, 'Should reject empty company name');
        }
        
        // Test with non-existent company
        $response = $this->makeRequest('POST', '', ['company' => 'NonExistentCompanyXYZ123']);
        
        if ($response && $response['success']) {
            $resultCount = $response['result_count'] ?? 0;
            if ($resultCount == 0) {
                $this->addResult('Non-existent Company', true, 'No competitors found for non-existent company (expected)');
            } else {
                $this->addResult('Non-existent Company', false, 'Found competitors for non-existent company (unexpected)');
            }
        } else {
            $this->addResult('Non-existent Company', true, 'Properly handled non-existent company');
        }
        
        // Test with missing company parameter
        $response = $this->makeRequest('POST', '', []);
        
        if ($response && !$response['success']) {
            $this->addResult('Missing Parameter Validation', true, 'Correctly rejected missing company parameter');
        } else {
            $this->addResult('Missing Parameter Validation', false, 'Should reject missing company parameter');
        }
    }
    
    private function testErrorHandling() {
        echo "🛠️ Testing Error Handling...\n";
        
        // Test invalid action
        $response = $this->makeRequest('GET', '?action=invalidAction');
        
        // Should either return an error or handle gracefully
        if ($response) {
            $this->addResult('Invalid Action Handling', true, 'API handled invalid action gracefully');
        } else {
            $this->addResult('Invalid Action Handling', false, 'API failed on invalid action');
        }
        
        // Test invalid HTTP method for analysis
        $response = $this->makeRequest('GET', '?company=TestCompany');
        
        // Should not perform analysis via GET
        if ($response && (!isset($response['data']) || !is_array($response['data']) || empty($response['data']))) {
            $this->addResult('HTTP Method Validation', true, 'Correctly requires POST for competitor analysis');
        } else {
            $this->addResult('HTTP Method Validation', false, 'Should not allow competitor analysis via GET');
        }
    }
    
    private function testResponseFormat() {
        echo "📋 Testing Response Format...\n";
        
        // Test GET endpoint response format
        $response = $this->makeRequest('GET', '?action=getCompanies');
        
        if ($response) {
            $requiredFields = ['success', 'message', 'data', 'count'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($response[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (empty($missingFields)) {
                $this->addResult('GET Response Format', true, 'All required fields present');
            } else {
                $this->addResult('GET Response Format', false, 'Missing fields: ' . implode(', ', $missingFields));
            }
            
            // Check JSON validity
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->addResult('JSON Format', true, 'Valid JSON response');
            } else {
                $this->addResult('JSON Format', false, 'Invalid JSON: ' . json_last_error_msg());
            }
        }
        
        // Test POST endpoint response format
        $companiesResponse = $this->makeRequest('GET', '?action=getCompanies');
        if ($companiesResponse && $companiesResponse['success'] && !empty($companiesResponse['data'])) {
            $testCompany = $companiesResponse['data'][0]['name'];
            $response = $this->makeRequest('POST', '', ['company' => $testCompany]);
            
            if ($response && $response['success']) {
                $requiredFields = ['success', 'data', 'query', 'parameters', 'result_count'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($response[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (empty($missingFields)) {
                    $this->addResult('POST Response Format', true, 'All required fields present in analysis response');
                } else {
                    $this->addResult('POST Response Format', false, 'Missing fields: ' . implode(', ', $missingFields));
                }
            }
        }
    }
    
    private function testDataQuality() {
        echo "🔍 Testing Data Quality...\n";
        
        // Test if relationships exist
        $statsResponse = $this->makeRequest('GET', '?action=getStats');
        
        if ($statsResponse && $statsResponse['success']) {
            $stats = $statsResponse['data'];
            
            $suppliesRel = $stats['supplies_relationships'] ?? 0;
            $partOfRel = $stats['part_of_relationships'] ?? 0;
            
            if ($suppliesRel > 0 && $partOfRel > 0) {
                $this->addResult('Relationship Data', true, "SUPPLIES: {$suppliesRel}, PART_OF: {$partOfRel}");
            } else {
                $this->addResult('Relationship Data', false, 'Missing relationship data - competitor analysis may not work properly');
            }
            
            // Check data ratios
            $suppliers = $stats['suppliers'] ?? 0;
            $products = $stats['products'] ?? 0;
            $categories = $stats['categories'] ?? 0;
            
            if ($suppliers > 0 && $products > 0 && $categories > 0) {
                $productPerSupplier = round($products / $suppliers, 2);
                $productPerCategory = round($products / $categories, 2);
                
                $this->addResult('Data Ratios', true, "Products/Supplier: {$productPerSupplier}, Products/Category: {$productPerCategory}");
                
                if ($productPerSupplier < 1) {
                    $this->addResult('Data Quality Warning', false, 'Very few products per supplier - check data integrity');
                }
            }
        }
        
        // Test actual competitor analysis to see if relationships work
        $companiesResponse = $this->makeRequest('GET', '?action=getCompanies');
        if ($companiesResponse && $companiesResponse['success'] && !empty($companiesResponse['data'])) {
            $totalCompetitorTests = 0;
            $successfulAnalyses = 0;
            
            // Test multiple companies to see relationship effectiveness
            $testCompanies = array_slice($companiesResponse['data'], 0, 3);
            
            foreach ($testCompanies as $company) {
                $response = $this->makeRequest('POST', '', ['company' => $company['name']]);
                $totalCompetitorTests++;
                
                if ($response && $response['success'] && ($response['result_count'] ?? 0) > 0) {
                    $successfulAnalyses++;
                }
            }
            
            if ($successfulAnalyses > 0) {
                $successRate = round(($successfulAnalyses / $totalCompetitorTests) * 100, 1);
                $this->addResult('Competitor Analysis Effectiveness', true, "{$successfulAnalyses}/{$totalCompetitorTests} companies have competitors ({$successRate}%)");
            } else {
                $this->addResult('Competitor Analysis Effectiveness', false, 'No companies have competitors - check relationship data');
            }
        }
    }
    
    private function makeRequest($method, $endpoint = '', $data = null) {
        $url = $this->baseUrl . '/' . $this->apiFile . $endpoint;
        
        // Check if curl is available
        if (!function_exists('curl_init')) {
            echo "   ❌ cURL is not available. Using file_get_contents as fallback...\n";
            return $this->makeRequestFallback($url, $method, $data);
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false // For local testing
        ]);
        
        if ($data && $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            echo "   ❌ CURL Error: {$error}\n";
            return false;
        }
        
        // Parse endpoint for display
        $displayEndpoint = $endpoint ?: '/';
        if (strlen($displayEndpoint) > 50) {
            $displayEndpoint = substr($displayEndpoint, 0, 50) . '...';
        }
        
        echo "   {$method} {$displayEndpoint} - HTTP {$httpCode}\n";
        
        if ($httpCode >= 400) {
            echo "   ⚠️ HTTP Error: " . substr($response, 0, 200) . "...\n";
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "   ❌ Invalid JSON response: " . substr($response, 0, 100) . "...\n";
            return false;
        }
        
        return $decoded;
    }
    
    private function makeRequestFallback($url, $method, $data) {
        if ($method === 'POST' && $data) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                    'content' => http_build_query($data),
                    'timeout' => 30
                ]
            ]);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Accept: application/json\r\n",
                    'timeout' => 30
                ]
            ]);
        }
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            echo "   ❌ Failed to fetch: {$url}\n";
            return false;
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "   ❌ Invalid JSON response\n";
            return false;
        }
        
        return $decoded;
    }
    
    private function addResult($test, $success, $message) {
        $this->results[] = [
            'test' => $test,
            'success' => $success,
            'message' => $message
        ];
        
        $icon = $success ? '✅' : '❌';
        echo "   {$icon} {$test}: {$message}\n\n";
    }
    
    private function printSummary() {
        echo "📈 Test Summary:\n";
        echo str_repeat('=', 70) . "\n";
        
        $passed = 0;
        $total = count($this->results);
        
        foreach ($this->results as $result) {
            if ($result['success']) {
                $passed++;
            }
        }
        
        echo "Total Tests: {$total}\n";
        echo "Passed: {$passed}\n";
        echo "Failed: " . ($total - $passed) . "\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 1) . "%\n\n";
        
        if ($passed === $total) {
            echo "🎉 All tests passed! Neo4j Competitor Analysis API is working correctly.\n";
        } else {
            echo "⚠️ Some tests failed. Please check the API implementation.\n\n";
            
            echo "Failed Tests:\n";
            foreach ($this->results as $result) {
                if (!$result['success']) {
                    echo "❌ {$result['test']}: {$result['message']}\n";
                }
            }
        }
        
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "Quick Manual Test Commands:\n";
        echo "curl '{$this->baseUrl}/{$this->apiFile}?action=getStats'\n";
        echo "curl '{$this->baseUrl}/{$this->apiFile}?action=getCompanies'\n";
        echo "curl -X POST -d 'company=YourCompanyName' '{$this->baseUrl}/{$this->apiFile}'\n";
        
        echo "\nNeo4j Data Setup Commands (if relationships missing):\n";
        echo "// Create SUPPLIES relationships\n";
        echo "MATCH (s:Supplier), (p:Product) WHERE s.supplierID = p.supplierID CREATE (s)-[:SUPPLIES]->(p)\n\n";
        echo "// Create PART_OF relationships\n";
        echo "MATCH (p:Product), (c:Category) WHERE p.categoryID = c.categoryID CREATE (p)-[:PART_OF]->(c)\n\n";
        
        echo "Performance Test Commands:\n";
        echo "time curl -X POST -d 'company=SampleCompany' '{$this->baseUrl}/{$this->apiFile}'\n";
    }
}

// Main execution
echo "Neo4j Competitor Analysis API Tester\n";
echo str_repeat('=', 40) . "\n";

// Check if running from command line with arguments
if (php_sapi_name() === 'cli' && $argc > 1) {
    $baseUrl = $argv[1];
    $apiFile = $argv[2] ?? 'northwind-api.php';
} else {
    // Interactive mode
    if (php_sapi_name() === 'cli') {
        echo "Enter API base URL (default: http://localhost): ";
        $baseUrl = trim(fgets(STDIN));
        
        echo "Enter API filename (default: northwind-api.php): ";
        $apiFile = trim(fgets(STDIN));
    } else {
        // Running from web browser - use defaults
        $baseUrl = 'http://localhost';
        $apiFile = 'northwind-api.php';
        echo "Running with defaults: {$baseUrl}/{$apiFile}\n";
    }
    
    if (empty($baseUrl)) {
        $baseUrl = 'http://localhost';
    }
    
    if (empty($apiFile)) {
        $apiFile = 'northwind-api.php';
    }
}

echo "Testing Neo4j Competitor Analysis API at: {$baseUrl}/{$apiFile}\n\n";

$tester = new Neo4jApiTester($baseUrl);
$tester->apiFile = $apiFile;
$tester->runTests();
?>