<?php
/**
 * MongoDB Restaurant API Testing Script
 * Script untuk testing MongoDB Restaurant API
 */

require_once 'vendor/autoload.php';

class MongoRestaurantTester {
    private $baseUrl;
    private $results = [];
    public $apiFile = 'resto.php'; // Sesuaikan dengan nama file API Anda
    
    public function __construct($baseUrl = 'http://localhost') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    public function runTests() {
        echo "🚀 Starting MongoDB Restaurant API Tests...\n\n";
        
        // Test all endpoints
        $this->testPingEndpoint();
        $this->testFilterOptionsEndpoint();
        $this->testRestaurantsEndpoint();
        $this->testRestaurantsWithFilters();
        $this->testRestaurantsPagination();
        $this->testRestaurantsSorting();
        $this->testRestaurantsScoreFilter();
        $this->testErrorHandling();
        
        // Print summary
        $this->printSummary();
    }
    
    private function testPingEndpoint() {
        echo "🏓 Testing Ping Endpoint...\n";
        
        $response = $this->makeRequest('GET', '?action=ping');
        
        if ($response && $response['success'] && isset($response['data']['message'])) {
            $this->addResult('Ping Endpoint', true, $response['data']['message']);
        } else {
            $this->addResult('Ping Endpoint', false, 'Failed to ping database');
        }
    }
    
    
    private function testFilterOptionsEndpoint() {
        echo "🔍 Testing Filter Options Endpoint...\n";
        
        $response = $this->makeRequest('GET', '?action=filter-options');
        
        if ($response && $response['success']) {
            $boroughs = $response['data']['boroughs'] ?? [];
            $cuisines = $response['data']['cuisines'] ?? [];
            
            $boroughCount = count($boroughs);
            $cuisineCount = count($cuisines);
            
            if ($boroughCount > 0 && $cuisineCount > 0) {
                $this->addResult('Filter Options', true, "Boroughs: {$boroughCount}, Cuisines: {$cuisineCount}");
                
                // Test some expected values
                if (in_array('Manhattan', $boroughs)) {
                    $this->addResult('Borough Data Quality', true, 'Manhattan found in boroughs');
                } else {
                    $this->addResult('Borough Data Quality', false, 'Manhattan not found in boroughs');
                }
                
                if (in_array('American', $cuisines) || in_array('Chinese', $cuisines)) {
                    $this->addResult('Cuisine Data Quality', true, 'Common cuisines found');
                } else {
                    $this->addResult('Cuisine Data Quality', false, 'Expected cuisines not found');
                }
            } else {
                $this->addResult('Filter Options', false, 'No filter options returned');
            }
        } else {
            $this->addResult('Filter Options', false, 'Failed to get filter options');
        }
    }
    
    
    private function testRestaurantsEndpoint() {
        echo "🍽️ Testing Basic Restaurants Endpoint...\n";
        
        $response = $this->makeRequest('GET', '?action=restaurants');
        
        if ($response && $response['success'] && is_array($response['data'])) {
            $restaurants = $response['data'];
            $meta = $response['meta'] ?? [];
            
            $count = count($restaurants);
            $totalCount = $meta['total_count'] ?? 0;
            $currentPage = $meta['current_page'] ?? 1;
            
            if ($count > 0) {
                $this->addResult('Basic Restaurants Query', true, "Retrieved {$count} restaurants (total: {$totalCount}, page: {$currentPage})");
                
                // Check restaurant structure
                $firstRestaurant = $restaurants[0];
                $requiredFields = ['name', 'borough', 'cuisine'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($firstRestaurant[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (empty($missingFields)) {
                    $this->addResult('Restaurant Data Structure', true, 'All required fields present');
                } else {
                    $this->addResult('Restaurant Data Structure', false, 'Missing fields: ' . implode(', ', $missingFields));
                }
            } else {
                $this->addResult('Basic Restaurants Query', false, 'No restaurants returned');
            }
        } else {
            $this->addResult('Basic Restaurants Query', false, 'Failed to get restaurants');
        }
    }
    
    private function testRestaurantsWithFilters() {
        echo "🔎 Testing Restaurants with Filters...\n";
        
        // Test search filter
        $response = $this->makeRequest('GET', '?action=restaurants&search=pizza');

        if ($response && $response['success']) {
            $count = $response['meta']['total_count'];

            // Kondisi if harus di dalam tanda kurung
            if ($count == 403) {
                $this->addResult('Search Filter (pizza)', true, "Found {$count} results");
            } else {
                // Beri pesan error yang jelas jika jumlahnya tidak sesuai
                $this->addResult('Search Filter (pizza)', false, "Expected 403 results, but found {$count}");
            }
        } else {
            $this->addResult('Search Filter (pizza)', false, 'Search filter failed');
        }
        
        // Test borough filter
        $response = $this->makeRequest('GET', '?action=restaurants&borough=Manhattan');
        if ($response && $response['success']) {
            $count = $response['meta']['total_count'];

            if ($count == 1883) {
                $this->addResult('Borough Filter (Manhattan)', true, "Found {$count} results");
            } else{
                $this->addResult('Borough Filter (Manhattan)', false, "Expected 1883 results, but found {$count}");
            }
            
            // Verify all results are from Manhattan
            if ($count > 0) {
                $allManhattan = true;
                foreach ($response['data'] as $restaurant) {
                    if (($restaurant['borough'] ?? '') !== 'Manhattan') {
                        $allManhattan = false;
                        break;
                    }
                }
                
                if ($allManhattan) {
                    $this->addResult('Borough Filter Accuracy', true, 'All results from Manhattan');
                } else {
                    $this->addResult('Borough Filter Accuracy', false, 'Some results not from Manhattan');
                }
            }
        } else {
            $this->addResult('Borough Filter (Manhattan)', false, 'Borough filter failed');
        }
        
        // Test cuisine filter
        $response = $this->makeRequest('GET', '?action=restaurants&cuisine=American%20'); 
        if ($response && $response['success']) {
            $count = $response['meta']['total_count'];

            if ($count == 1255) {
                $this->addResult('Cuisine Filter (American)', true, "Found {$count} results");
            }
            else{
                $this->addResult('Cuisine Filter (American)', false, "Expected 1255 results, but found {$count}");
            }

            
        } else {
            $this->addResult('Cuisine Filter (American)', false, 'Cuisine filter failed');
        }
        
        // Test combined filters
        $response = $this->makeRequest('GET', '?action=restaurants&borough=Brooklyn&cuisine=Italian&search=restaurant');
        if ($response && $response['success']) {
            $count = $response['meta']['total_count'];

            if ($count == 15) {
                $this->addResult('Combined Filters', true, "Found {$count} results with multiple filters");
            }
            else{
                $this->addResult('Combined Filters', false, "Expected 15 results, but found {$count}");
            }
            
        } else {
            $this->addResult('Combined Filters', false, 'Combined filters failed');
        }
    }
    
    private function testRestaurantsPagination() {
        echo "📄 Testing Pagination...\n";
        
        // Test different page sizes
        $pageSizes = [5, 10, 25];
        
        foreach ($pageSizes as $size) {
            $response = $this->makeRequest('GET', "?action=restaurants&limit={$size}");
            if ($response && $response['success']) {
                $count = count($response['data']);
                $meta = $response['meta'] ?? [];
                $perPage = $meta['per_page'] ?? 0;
                
                if ($count <= $size && $perPage == $size) {
                    $this->addResult("Pagination Limit {$size}", true, "Returned {$count} items");
                } else {
                    $this->addResult("Pagination Limit {$size}", false, "Expected max {$size}, got {$count}");
                }
            } else {
                $this->addResult("Pagination Limit {$size}", false, 'Pagination failed');
            }
        }
        
        // Test page navigation
        $response1 = $this->makeRequest('GET', '?action=restaurants&page=1&limit=5');
        $response2 = $this->makeRequest('GET', '?action=restaurants&page=2&limit=5');
        
        if ($response1 && $response2 && $response1['success'] && $response2['success']) {
            $page1Items = $response1['data'];
            $page2Items = $response2['data'];
            
            // Check if pages are different
            if (count($page1Items) > 0 && count($page2Items) > 0) {
                $firstItem1 = $page1Items[0]['name'] ?? '';
                $firstItem2 = $page2Items[0]['name'] ?? '';
                
                if ($firstItem1 !== $firstItem2) {
                    $this->addResult('Page Navigation', true, 'Different results on different pages');
                } else {
                    $this->addResult('Page Navigation', false, 'Same results on different pages');
                }
            } else {
                $this->addResult('Page Navigation', false, 'Empty pages returned');
            }
        } else {
            $this->addResult('Page Navigation', false, 'Failed to test pagination');
        }
    }
    
    private function testRestaurantsSorting() {
        echo "🔄 Testing Sorting...\n";
        
        $sortTests = [
            ['name', 'asc'],
            ['name', 'desc'],
            ['borough', 'asc'],
            ['cuisine', 'desc']
        ];
        
        foreach ($sortTests as [$field, $direction]) {
            $response = $this->makeRequest('GET', "?action=restaurants&sort_by={$field}&sort_dir={$direction}&limit=10");
            
            if ($response && $response['success'] && count($response['data']) > 1) {
                $restaurants = $response['data'];
                $isSorted = true;
                
                for ($i = 0; $i < count($restaurants) - 1; $i++) {
                    $current = $restaurants[$i][$field] ?? '';
                    $next = $restaurants[$i + 1][$field] ?? '';
                    
                    if ($direction === 'asc' && $current > $next) {
                        $isSorted = false;
                        break;
                    } else if ($direction === 'desc' && $current < $next) {
                        $isSorted = false;
                        break;
                    }
                }
                
                if ($isSorted) {
                    $this->addResult("Sort {$field} {$direction}", true, "Correctly sorted");
                } else {
                    $this->addResult("Sort {$field} {$direction}", false, "Incorrect sort order");
                }
            } else {
                $this->addResult("Sort {$field} {$direction}", false, 'Sort test failed');
            }
        }
    }
    
    private function testRestaurantsScoreFilter() {
        echo "⭐ Testing Score Filter...\n";

        // Definisikan ekspektasi skor dan jumlah hasil yang diharapkan
        $expectedCounts = [
            5  => 557,
            10 => 1962,
            15 => 3372,
            20 => 3539
        ];

        // Langsung loop pada array ekspektasi untuk mendapatkan skor dan jumlah yang diharapkan
        foreach ($expectedCounts as $maxScore => $expectedCount) {
            $response = $this->makeRequest('GET', "?action=restaurants&max_score={$maxScore}");

            if (!$response || !$response['success']) {
                $this->addResult("Score Filter ≤{$maxScore}", false, 'API request failed');
                continue; // Lanjut ke tes skor berikutnya
            }
            
            // Ambil jumlah total dari response dan data restorannya
            $actualTotal = $response['meta']['total_count']; 
            $restaurants = $response['data'];

            // 1. Validasi Jumlah Hasil
            if ($actualTotal !== $expectedCount) {
                $this->addResult("Score Filter ≤{$maxScore}", false, "Count mismatch: Expected {$expectedCount}, but got {$actualTotal}");
                continue; // Gagal, lanjut ke tes skor berikutnya
            }
            
            // 2. Validasi Skor dari Data yang Dikembalikan (jika ada)
            $scoresAreValid = true;
            if (!empty($restaurants)) {
                foreach ($restaurants as $restaurant) {
                    // Logika untuk mencari skor terbaru (sudah benar dari kode Anda)
                    $latestGrade = null;
                    $latestDate = '';
                    if (isset($restaurant['grades']) && !empty($restaurant['grades'])) {
                        foreach ($restaurant['grades'] as $grade) {
                            if (isset($grade['date']) && $grade['date'] > $latestDate) {
                                $latestDate = $grade['date'];
                                $latestGrade = $grade;
                            }
                        }
                    }

                    // Jika skor terbaru melebihi batas, tes gagal
                    if ($latestGrade && isset($latestGrade['score']) && $latestGrade['score'] > $maxScore) {
                        $scoresAreValid = false;
                        break; // Keluar dari loop restoran
                    }
                }
            }
            
            // 3. Laporkan Hasil Akhir
            if ($scoresAreValid) {
                $this->addResult("Score Filter ≤{$maxScore}", true, "OK: Found {$actualTotal} results and all scores are valid.");
            } else {
                $this->addResult("Score Filter ≤{$maxScore}", false, "FAIL: A restaurant with a score greater than {$maxScore} was found.");
            }
        }
    }
    
    private function testErrorHandling() {
        echo "❌ Testing Error Handling...\n";
        
        // Test invalid action
        $response = $this->makeRequest('GET', '?action=invalid_action');
        if ($response && !$response['success'] && isset($response['error'])) {
            $this->addResult('Invalid Action', true, 'Correctly rejected invalid action');
        } else {
            $this->addResult('Invalid Action', false, 'Should reject invalid actions');
        }
        
        // Test invalid page number
        $response = $this->makeRequest('GET', '?action=restaurants&page=-1');
        if ($response && $response['success']) {
            $meta = $response['meta'] ?? [];
            $currentPage = $meta['current_page'] ?? -1;
            if ($currentPage >= 1) {
                $this->addResult('Invalid Page Handling', true, "Corrected page to {$currentPage}");
            } else {
                $this->addResult('Invalid Page Handling', false, 'Page not corrected');
            }
        } else {
            $this->addResult('Invalid Page Handling', false, 'Should handle invalid page gracefully');
        }
        
        // Test invalid limit
        $response = $this->makeRequest('GET', '?action=restaurants&limit=999');
        if ($response && $response['success']) {
            $meta = $response['meta'] ?? [];
            $perPage = $meta['per_page'] ?? 999;
            if ($perPage <= 100) {
                $this->addResult('Invalid Limit Handling', true, "Limited to {$perPage}");
            } else {
                $this->addResult('Invalid Limit Handling', false, 'Limit not enforced');
            }
        } else {
            $this->addResult('Invalid Limit Handling', false, 'Should handle invalid limit gracefully');
        }
        
        // Test invalid sort field
        $response = $this->makeRequest('GET', '?action=restaurants&sort_by=invalid_field');
        if ($response) {
            // Should either succeed (ignoring invalid sort) or fail gracefully
            $this->addResult('Invalid Sort Field', true, 'Handled invalid sort field');
        } else {
            $this->addResult('Invalid Sort Field', false, 'Should handle invalid sort field');
        }
    }
    
    private function makeRequest($method, $endpoint = '', $data = null) {
        $url = $this->baseUrl . '/' . $this->apiFile . $endpoint;
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30 // Increased timeout for complex queries
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
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
            echo "   ⚠️ HTTP Error: {$response}\n";
            // Don't return false immediately, still try to parse JSON for error details
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "   ❌ Invalid JSON response: " . substr($response, 0, 100) . "...\n";
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
            echo "🎉 All tests passed! MongoDB Restaurant API is working correctly.\n";
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
        echo "curl '{$this->baseUrl}/{$this->apiFile}?action=ping'\n";
        echo "curl '{$this->baseUrl}/{$this->apiFile}?action=count'\n";
        echo "curl '{$this->baseUrl}/{$this->apiFile}?action=restaurants&limit=5'\n";
        echo "curl '{$this->baseUrl}/{$this->apiFile}?action=restaurants&search=pizza&limit=10'\n";
        echo "curl '{$this->baseUrl}/{$this->apiFile}?action=filter-options'\n";
        echo "curl '{$this->baseUrl}/{$this->apiFile}?action=debug-info'\n";
        
        echo "\nPerformance Test Commands:\n";
        echo "time curl '{$this->baseUrl}/{$this->apiFile}?action=restaurants&limit=100'\n";
        echo "time curl '{$this->baseUrl}/{$this->apiFile}?action=restaurants&max_score=20&limit=50'\n";
    }
}

// Command line interface
if ($argc > 1) {
    $baseUrl = $argv[1];
    $apiFile = $argv[2] ?? 'resto.php';
} else {
    echo "MongoDB Restaurant API Tester\n";
    echo str_repeat('=', 40) . "\n";
    echo "Enter API base URL (default: http://localhost): ";
    $handle = fopen("php://stdin", "r");
    $baseUrl = trim(fgets($handle));
    
    echo "Enter API filename (default: resto.php): ";
    $apiFile = trim(fgets($handle));
    
    fclose($handle);
    
    if (empty($baseUrl)) {
        $baseUrl = 'http://localhost';
    }
    
    if (empty($apiFile)) {
        $apiFile = 'resto.php';
    }
}

echo "Testing MongoDB Restaurant API at: {$baseUrl}/{$apiFile}\n\n";

$tester = new MongoRestaurantTester($baseUrl);
$tester->apiFile = $apiFile;
$tester->runTests();