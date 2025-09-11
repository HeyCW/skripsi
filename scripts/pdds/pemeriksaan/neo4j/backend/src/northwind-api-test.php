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
        
        // Test with first few companies
        $testLimit = count($companies); 
        
        for ($i = 0; $i < $testLimit; $i++) {
            $testCompany = $companies[$i]['name'];
            
            echo "  Testing with company [" . ($i+1) . "/{$testLimit}]: {$testCompany}\n";
            
            // Test competitor analysis
            $response = $this->makeRequest('POST', '', ['company' => $testCompany]);
            
            if ($response && $response['success']) {
                $competitors = $response['data'] ?? [];
                $resultCount = $response['result_count'] ?? 0;
                
                $this->addResult("Competitor Analysis Query ({$testCompany})", true, "Found {$resultCount} competitors for '{$testCompany}'");
                
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
                        $this->addResult("Competitor Data Structure ({$testCompany})", true, 'All required fields present');
                        
                        // Check if results are properly sorted by NoProducts DESC
                        $isSorted = true;
                        for ($j = 0; $j < count($competitors) - 1; $j++) {
                            $current = $competitors[$j]['NoProducts'];
                            $next = $competitors[$j + 1]['NoProducts'];
                            if ($current < $next) {
                                $isSorted = false;
                                break;
                            }
                        }
                        
                        if ($isSorted) {
                            $this->addResult("Results Sorting ({$testCompany})", true, 'Results properly sorted by NoProducts DESC');
                        } else {
                            $this->addResult("Results Sorting ({$testCompany})", false, 'Results not properly sorted');
                        }
                        
                        // Display sample results (first 3)
                        $sampleResults = array_slice($competitors, 0, 3);
                        $samples = [];
                        foreach ($sampleResults as $comp) {
                            $samples[] = "{$comp['Competitor']} ({$comp['NoProducts']} products)";
                        }
                        $this->addResult("Sample Competitors ({$testCompany})", true, implode(', ', $samples));
                        
                        // Validate expected results for known companies
                        $this->validateExpectedResults($testCompany, $competitors);
                        
                    } else {
                        $this->addResult("Competitor Data Structure ({$testCompany})", false, 'Missing fields: ' . implode(', ', $missingFields));
                    }
                    
                } else {
                    $this->addResult("Competitor Results ({$testCompany})", true, "No competitors found for '{$testCompany}' (may be expected)");
                }
                
                // Check if query and parameters are logged
                if (isset($response['query']) && isset($response['parameters'])) {
                    $this->addResult("Query Logging ({$testCompany})", true, 'Query and parameters properly logged');
                } else {
                    $this->addResult("Query Logging ({$testCompany})", false, 'Missing query logging information');
                }
                
            } else {
                $error = $response['message'] ?? 'Unknown error';
                $this->addResult("Competitor Analysis Query ({$testCompany})", false, "Analysis failed: {$error}");
            }
        }
        
        // Test result differentiation across multiple companies
        if ($testLimit > 1) {
            $this->testResultDifferentiation($companies, $testLimit);
        }
    }

    private function testResultDifferentiation($companies, $testLimit) {
        echo "  Testing result differentiation across companies...\n";
        
        $allResults = [];
        
        // Get results for each test company
        for ($i = 0; $i < $testLimit; $i++) {
            $testCompany = $companies[$i]['name'];
            $response = $this->makeRequest('POST', '', ['company' => $testCompany]);
            
            if ($response && $response['success']) {
                $competitors = $response['data'] ?? [];
                $allResults[$testCompany] = array_column($competitors, 'Competitor');
            }
        }
        
        // Check if results are different between companies
        if (count($allResults) > 1) {
            $companyNames = array_keys($allResults);
            $allSame = true;
            
            for ($i = 1; $i < count($companyNames); $i++) {
                $company1 = $companyNames[0];
                $company2 = $companyNames[$i];
                
                if ($allResults[$company1] !== $allResults[$company2]) {
                    $allSame = false;
                    break;
                }
            }
            
            if (!$allSame) {
                $this->addResult('Result Differentiation', true, 'Different companies return different competitors as expected');
            } else {
                $this->addResult('Result Differentiation', false, 'All companies return identical competitors (suspicious)');
            }
            
            // Summary
            $summary = [];
            foreach ($allResults as $company => $competitors) {
                $summary[] = "{$company} (" . count($competitors) . " competitors)";
            }
            $this->addResult('Multiple Company Test', true, 'Tested: ' . implode(', ', $summary));
        }
    }

    private function validateExpectedResults($companyName, $actualCompetitors) {
        // Expected results based on real CSV analysis
        $expectedData = $this->getExpectedCompetitorData();
        
        // Find the expected data for this company
        $expected = null;
        foreach ($expectedData as $companyData) {
            if ($companyData['name'] === $companyName) {
                $expected = $companyData['competitors'];
                break;
            }
        }
        
        if ($expected === null) {
            $this->addResult("Expected Results Validation ({$companyName})", true, "No expected data for validation (company not in test dataset)");
            return;
        }
        
        // Check if competitor count matches
        if (count($actualCompetitors) === count($expected)) {
            $this->addResult("Competitor Count Validation ({$companyName})", true, "Expected " . count($expected) . " competitors, got " . count($actualCompetitors));
        } else {
            $this->addResult("Competitor Count Validation ({$companyName})", false, "Expected " . count($expected) . " competitors, got " . count($actualCompetitors));
        }
        
        // Check if top competitors match (first 3)
        $expectedTop3 = array_slice($expected, 0, 3);
        $actualTop3 = array_slice($actualCompetitors, 0, 3);
        
        $topCompetitorsMatch = true;
        for ($i = 0; $i < min(3, count($expectedTop3), count($actualTop3)); $i++) {
            if ($expectedTop3[$i]['Competitor'] !== $actualTop3[$i]['Competitor'] || 
                $expectedTop3[$i]['NoProducts'] !== $actualTop3[$i]['NoProducts']) {
                $topCompetitorsMatch = false;
                break;
            }
        }
        
        if ($topCompetitorsMatch) {
            $this->addResult("Top Competitors Validation ({$companyName})", true, "Top 3 competitors match expected results");
        } else {
            $this->addResult("Top Competitors Validation ({$companyName})", false, "Top 3 competitors do not match expected results");
        }
    }

    private function getExpectedCompetitorData() {
        // Expected competitor data with proper sorting: NoProducts DESC, Competitor ASC
        return [
            [
                'name' => 'Exotic Liquids',
                'competitors' => [
                    ['Competitor' => 'New Orleans Cajun Delights', 'NoProducts' => 4],
                    ['Competitor' => 'Bigfoot Breweries', 'NoProducts' => 3],
                    ['Competitor' => 'Aux joyeux ecclésiastiques', 'NoProducts' => 2],
                    ['Competitor' => 'Grandma Kelly\'s Homestead', 'NoProducts' => 2],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 2],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 2],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 2],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 1],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Refrescos Americanas LTDA', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'New Orleans Cajun Delights',
                'competitors' => [
                    ['Competitor' => 'Grandma Kelly\'s Homestead', 'NoProducts' => 2],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 1],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Grandma Kelly\'s Homestead',
                'competitors' => [
                    ['Competitor' => 'New Orleans Cajun Delights', 'NoProducts' => 4],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 2],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 2],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 1],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'G\'day', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Tokyo Traders',
                'competitors' => [
                    ['Competitor' => 'Svensk Sjöföda AB', 'NoProducts' => 3],
                    ['Competitor' => 'G\'day', 'NoProducts' => 2],
                    ['Competitor' => 'Lyngbysild', 'NoProducts' => 2],
                    ['Competitor' => 'Ma Maison', 'NoProducts' => 2],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 2],
                    ['Competitor' => 'New England Seafood Cannery', 'NoProducts' => 2],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 2],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 2],
                    ['Competitor' => 'Escargots Nouveaux', 'NoProducts' => 1],
                    ['Competitor' => 'Grandma Kelly\'s Homestead', 'NoProducts' => 1],
                    ['Competitor' => 'Nord-Ost-Fisch Handelsgesellschaft mbH', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Cooperativa de Quesos \'Las Cabras\'',
                'competitors' => [
                    ['Competitor' => 'Formaggi Fortini s.r.l.', 'NoProducts' => 3],
                    ['Competitor' => 'Norske Meierier', 'NoProducts' => 3],
                    ['Competitor' => 'Gai pâturage', 'NoProducts' => 2]
                ]
            ],
            [
                'name' => 'Mayumi\'s',
                'competitors' => [
                    ['Competitor' => 'New Orleans Cajun Delights', 'NoProducts' => 4],
                    ['Competitor' => 'Grandma Kelly\'s Homestead', 'NoProducts' => 3],
                    ['Competitor' => 'Svensk Sjöföda AB', 'NoProducts' => 3],
                    ['Competitor' => 'Lyngbysild', 'NoProducts' => 2],
                    ['Competitor' => 'New England Seafood Cannery', 'NoProducts' => 2],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 2],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 2],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 2],
                    ['Competitor' => 'Escargots Nouveaux', 'NoProducts' => 1],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 1],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'G\'day', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Nord-Ost-Fisch Handelsgesellschaft mbH', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Pavlova',
                'competitors' => [
                    ['Competitor' => 'New Orleans Cajun Delights', 'NoProducts' => 4],
                    ['Competitor' => 'Specialty Biscuits', 'NoProducts' => 4],
                    ['Competitor' => 'Bigfoot Breweries', 'NoProducts' => 3],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 3],
                    ['Competitor' => 'Heli Süßwaren GmbH & Co. KG', 'NoProducts' => 3],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 3],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 3],
                    ['Competitor' => 'Svensk Sjöföda AB', 'NoProducts' => 3],
                    ['Competitor' => 'Aux joyeux ecclésiastiques', 'NoProducts' => 2],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 2],
                    ['Competitor' => 'Grandma Kelly\'s Homestead', 'NoProducts' => 2],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 2],
                    ['Competitor' => 'Lyngbysild', 'NoProducts' => 2],
                    ['Competitor' => 'Ma Maison', 'NoProducts' => 2],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 2],
                    ['Competitor' => 'New England Seafood Cannery', 'NoProducts' => 2],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 2],
                    ['Competitor' => 'Zaanse Snoepfabriek', 'NoProducts' => 2],
                    ['Competitor' => 'Escargots Nouveaux', 'NoProducts' => 1],
                    ['Competitor' => 'G\'day', 'NoProducts' => 1],
                    ['Competitor' => 'Nord-Ost-Fisch Handelsgesellschaft mbH', 'NoProducts' => 1],
                    ['Competitor' => 'Refrescos Americanas LTDA', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Specialty Biscuits',
                'competitors' => [
                    ['Competitor' => 'Heli Süßwaren GmbH & Co. KG', 'NoProducts' => 3],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 2],
                    ['Competitor' => 'Zaanse Snoepfabriek', 'NoProducts' => 2],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'PB Knäckebröd AB',
                'competitors' => [
                    ['Competitor' => 'Pasta Buttini s.r.l.', 'NoProducts' => 2],
                    ['Competitor' => 'G\'day', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Refrescos Americanas LTDA',
                'competitors' => [
                    ['Competitor' => 'Bigfoot Breweries', 'NoProducts' => 3],
                    ['Competitor' => 'Aux joyeux ecclésiastiques', 'NoProducts' => 2],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 2],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Heli Süßwaren GmbH & Co. KG',
                'competitors' => [
                    ['Competitor' => 'Specialty Biscuits', 'NoProducts' => 4],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 2],
                    ['Competitor' => 'Zaanse Snoepfabriek', 'NoProducts' => 2],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Plutzer Lebensmittelgroßmärkte AG',
                'competitors' => [
                    ['Competitor' => 'New Orleans Cajun Delights', 'NoProducts' => 4],
                    ['Competitor' => 'Bigfoot Breweries', 'NoProducts' => 3],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 3],
                    ['Competitor' => 'G\'day', 'NoProducts' => 3],
                    ['Competitor' => 'Grandma Kelly\'s Homestead', 'NoProducts' => 3],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 3],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 3],
                    ['Competitor' => 'Aux joyeux ecclésiastiques', 'NoProducts' => 2],
                    ['Competitor' => 'Ma Maison', 'NoProducts' => 2],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 2],
                    ['Competitor' => 'PB Knäckebröd AB', 'NoProducts' => 2],
                    ['Competitor' => 'Pasta Buttini s.r.l.', 'NoProducts' => 2],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 2],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 1],
                    ['Competitor' => 'Refrescos Americanas LTDA', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Nord-Ost-Fisch Handelsgesellschaft mbH',
                'competitors' => [
                    ['Competitor' => 'Svensk Sjöföda AB', 'NoProducts' => 3],
                    ['Competitor' => 'Lyngbysild', 'NoProducts' => 2],
                    ['Competitor' => 'New England Seafood Cannery', 'NoProducts' => 2],
                    ['Competitor' => 'Escargots Nouveaux', 'NoProducts' => 1],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Formaggi Fortini s.r.l.',
                'competitors' => [
                    ['Competitor' => 'Norske Meierier', 'NoProducts' => 3],
                    ['Competitor' => 'Cooperativa de Quesos \'Las Cabras\'', 'NoProducts' => 2],
                    ['Competitor' => 'Gai pâturage', 'NoProducts' => 2]
                ]
            ],
            [
                'name' => 'Norske Meierier',
                'competitors' => [
                    ['Competitor' => 'Formaggi Fortini s.r.l.', 'NoProducts' => 3],
                    ['Competitor' => 'Cooperativa de Quesos \'Las Cabras\'', 'NoProducts' => 2],
                    ['Competitor' => 'Gai pâturage', 'NoProducts' => 2]
                ]
            ],
            [
                'name' => 'Bigfoot Breweries',
                'competitors' => [
                    ['Competitor' => 'Aux joyeux ecclésiastiques', 'NoProducts' => 2],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 2],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 1],
                    ['Competitor' => 'Refrescos Americanas LTDA', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Svensk Sjöföda AB',
                'competitors' => [
                    ['Competitor' => 'Lyngbysild', 'NoProducts' => 2],
                    ['Competitor' => 'New England Seafood Cannery', 'NoProducts' => 2],
                    ['Competitor' => 'Escargots Nouveaux', 'NoProducts' => 1],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Nord-Ost-Fisch Handelsgesellschaft mbH', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Aux joyeux ecclésiastiques',
                'competitors' => [
                    ['Competitor' => 'Bigfoot Breweries', 'NoProducts' => 3],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 2],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 1],
                    ['Competitor' => 'Refrescos Americanas LTDA', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'New England Seafood Cannery',
                'competitors' => [
                    ['Competitor' => 'Svensk Sjöföda AB', 'NoProducts' => 3],
                    ['Competitor' => 'Lyngbysild', 'NoProducts' => 2],
                    ['Competitor' => 'Escargots Nouveaux', 'NoProducts' => 1],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Nord-Ost-Fisch Handelsgesellschaft mbH', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Leka Trading',
                'competitors' => [
                    ['Competitor' => 'New Orleans Cajun Delights', 'NoProducts' => 4],
                    ['Competitor' => 'Bigfoot Breweries', 'NoProducts' => 3],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 3],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 3],
                    ['Competitor' => 'Aux joyeux ecclésiastiques', 'NoProducts' => 2],
                    ['Competitor' => 'Grandma Kelly\'s Homestead', 'NoProducts' => 2],
                    ['Competitor' => 'PB Knäckebröd AB', 'NoProducts' => 2],
                    ['Competitor' => 'Pasta Buttini s.r.l.', 'NoProducts' => 2],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 2],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'G\'day', 'NoProducts' => 1],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 1],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Refrescos Americanas LTDA', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Lyngbysild',
                'competitors' => [
                    ['Competitor' => 'Svensk Sjöföda AB', 'NoProducts' => 3],
                    ['Competitor' => 'New England Seafood Cannery', 'NoProducts' => 2],
                    ['Competitor' => 'Escargots Nouveaux', 'NoProducts' => 1],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Nord-Ost-Fisch Handelsgesellschaft mbH', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Zaanse Snoepfabriek',
                'competitors' => [
                    ['Competitor' => 'Specialty Biscuits', 'NoProducts' => 4],
                    ['Competitor' => 'Heli Süßwaren GmbH & Co. KG', 'NoProducts' => 3],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 2],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Karkki Oy',
                'competitors' => [
                    ['Competitor' => 'Specialty Biscuits', 'NoProducts' => 4],
                    ['Competitor' => 'Bigfoot Breweries', 'NoProducts' => 3],
                    ['Competitor' => 'Heli Süßwaren GmbH & Co. KG', 'NoProducts' => 3],
                    ['Competitor' => 'Aux joyeux ecclésiastiques', 'NoProducts' => 2],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 2],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 2],
                    ['Competitor' => 'Zaanse Snoepfabriek', 'NoProducts' => 2],
                    ['Competitor' => 'Forêts d\'érables', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 1],
                    ['Competitor' => 'Refrescos Americanas LTDA', 'NoProducts' => 1]
                ]
            ],
           [
                'name' => 'G\'day',
                'competitors' => [
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 3],
                    ['Competitor' => 'Ma Maison', 'NoProducts' => 2],
                    ['Competitor' => 'PB Knäckebröd AB', 'NoProducts' => 2],
                    ['Competitor' => 'Pasta Buttini s.r.l.', 'NoProducts' => 2],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 2],
                    ['Competitor' => 'Grandma Kelly\'s Homestead', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Ma Maison',
                'competitors' => [
                    ['Competitor' => 'G\'day', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 1],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Pasta Buttini s.r.l.',
                'competitors' => [
                    ['Competitor' => 'PB Knäckebröd AB', 'NoProducts' => 2],
                    ['Competitor' => 'G\'day', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Escargots Nouveaux',
                'competitors' => [
                    ['Competitor' => 'Svensk Sjöföda AB', 'NoProducts' => 3],
                    ['Competitor' => 'Lyngbysild', 'NoProducts' => 2],
                    ['Competitor' => 'New England Seafood Cannery', 'NoProducts' => 2],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Nord-Ost-Fisch Handelsgesellschaft mbH', 'NoProducts' => 1],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 1],
                    ['Competitor' => 'Tokyo Traders', 'NoProducts' => 1]
                ]
            ],
            [
                'name' => 'Gai pâturage',
                'competitors' => [
                    ['Competitor' => 'Formaggi Fortini s.r.l.', 'NoProducts' => 3],
                    ['Competitor' => 'Norske Meierier', 'NoProducts' => 3],
                    ['Competitor' => 'Cooperativa de Quesos \'Las Cabras\'', 'NoProducts' => 2]
                ]
            ],
            [
                'name' => 'Forêts d\'érables',
                'competitors' => [
                    ['Competitor' => 'New Orleans Cajun Delights', 'NoProducts' => 4],
                    ['Competitor' => 'Specialty Biscuits', 'NoProducts' => 4],
                    ['Competitor' => 'Heli Süßwaren GmbH & Co. KG', 'NoProducts' => 3],
                    ['Competitor' => 'Grandma Kelly\'s Homestead', 'NoProducts' => 2],
                    ['Competitor' => 'Karkki Oy', 'NoProducts' => 2],
                    ['Competitor' => 'Pavlova', 'NoProducts' => 2],
                    ['Competitor' => 'Zaanse Snoepfabriek', 'NoProducts' => 2],
                    ['Competitor' => 'Exotic Liquids', 'NoProducts' => 1],
                    ['Competitor' => 'Leka Trading', 'NoProducts' => 1],
                    ['Competitor' => 'Mayumi\'s', 'NoProducts' => 1],
                    ['Competitor' => 'Plutzer Lebensmittelgroßmärkte AG', 'NoProducts' => 1]
                ]
            ]
        ];
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