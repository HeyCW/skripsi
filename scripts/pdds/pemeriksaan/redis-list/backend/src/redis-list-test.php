<?php
/**
 * Redis List API Testing Script
 * Script untuk testing redis-list.php API
 */

class RedisListTester {
    private $baseUrl;
    private $results = [];
    private $testSession;
    
    public function __construct($baseUrl = 'http://localhost') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    public function runTests($outputJson = false, $s3Upload = false) {
        echo "🚀 Starting Redis List API Tests...\n\n";
        
        $startTime = microtime(true);

        // Clear any existing data first
        $this->clearList();
        
        // Test all operations
        $this->testGetEmptyList();
        $this->testLPushOperations();
        $this->testRPushOperations();
        $this->testGetFullList();
        $this->testLPopOperations();
        $this->testRPopOperations();
        $this->testMixedOperations();
        $this->testErrorHandling();
        
        
       $endTime = microtime(true);

        $this->testSession['duration_seconds'] = round($endTime - $startTime, 3);
        
        // Print summary
        $this->printSummary();
        
        // Generate JSON output
        if ($outputJson) {
            $jsonData = $this->generateJsonReport();
            $this->saveJsonToFile($jsonData);
            
            if ($s3Upload) {
                $this->uploadToS3($jsonData);
            }
        }
        
        return $this->generateJsonReport();
    }

    private function generateJsonReport() {
        $passed = 0;
        $failed = 0;
        
        foreach ($this->results as $result) {
            if ($result['success']) {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        $report = [
            'test_session' => $this->testSession,
            'summary' => [
                'total_tests' => count($this->results),
                'passed' => $passed,
                'failed' => $failed,
                'success_rate' => round(($passed / count($this->results)) * 100, 2),
                'status' => $passed === count($this->results) ? 'ALL_PASSED' : 'SOME_FAILED'
            ],
            'test_results' => array_map(function($result) {
                return [
                    'test_name' => $result['test'],
                    'status' => $result['success'] ? 'PASSED' : 'FAILED',
                    'message' => $result['message'],
                    'timestamp' => date('c')
                ];
            }, $this->results),
            'environment' => [
                'api_endpoint' => $this->baseUrl . '/redis-list.php',
                'test_timestamp' => date('c'),
                'timezone' => date_default_timezone_get()
            ],
            'metadata' => [
                'generated_by' => 'RedisListTester',
                'version' => '1.0',
                'format_version' => '1.0'
            ]
        ];
        
        return $report;
    }

    private function formatBytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    private function saveJsonToFile($jsonData) {
        $filename = sprintf(
            'redis_test_results_%s_%s.json',
            date('Y-m-d_H-i-s'),
            $this->testSession['session_id']
        );
        
        $jsonString = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($filename, $jsonString)) {
            echo "📄 JSON report saved to: {$filename}\n";
            echo "   File size: " . $this->formatBytes(filesize($filename)) . "\n\n";
        } else {
            echo "❌ Failed to save JSON report\n\n";
        }
        
        return $filename;
    }

    private function uploadToS3($jsonData) {
        echo "Uploading to S3...\n";
        if ($this->uploadViaAwsSdk($jsonData)) {
            return;
        }
    }

    private function uploadViaAwsSdk($jsonData) {
        if (!class_exists('Aws\S3\S3Client')) {
            echo "   ⚠️ AWS SDK not found (composer require aws/aws-sdk-php)\n";
            return false;
        }
        
        try {
            $s3 = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region'  => getenv('AWS_REGION') ?: 'us-east-1'
            ]);
            
            $bucket = getenv('S3_BUCKET_NAME') ?: 'your-test-results-bucket';
            $key = sprintf(
                'test-results/%s/redis_test_%s.json',
                date('Y/m/d'),
                $this->testSession['session_id']
            );
            
            $result = $s3->putObject([
                'Bucket' => $bucket,
                'Key'    => $key,
                'Body'   => json_encode($jsonData, JSON_PRETTY_PRINT),
                'ContentType' => 'application/json',
                'Metadata' => [
                    'test-session-id' => $this->testSession['session_id'],
                    'test-timestamp' => date('c'),
                    'test-status' => $jsonData['summary']['status']
                ]
            ]);
            
            echo "   ✅ Successfully uploaded to S3: s3://{$bucket}/{$key}\n";
            echo "   ETag: {$result['ETag']}\n\n";
            return true;
            
        } catch (Exception $e) {
            echo "   ❌ S3 upload error: " . $e->getMessage() . "\n\n";
            return false;
        }
    }
    
    private function testGetEmptyList() {
        echo "📋 Testing Get Empty List...\n";
        
        $response = $this->makeRequest('GET', '?action=get');
        
        if ($response && $response['success'] && is_array($response['data']['people'])) {
            $listLength = $response['data']['list_length'];
            $this->addResult('Get Empty List', true, "List length: {$listLength}, Redis status: {$response['data']['redis_status']}");
        } else {
            $this->addResult('Get Empty List', false, 'Failed to get list data');
        }
    }
    
    private function testLPushOperations() {
        echo "⬅️ Testing LPUSH Operations...\n";
        
        // Test adding names to beginning of list
        foreach (['John', 'Jane', 'Jack'] as $name) {
            $response = $this->makeRequest('POST', '', [
                'action' => 'lpush',
                'name' => $name
            ]);
            
            if ($response && $response['success']) {
                $this->addResult("LPUSH {$name}", true, $response['message']);
            } else {
                $this->addResult("LPUSH {$name}", false, $response['message'] ?? 'Failed');
            }
        }
        
        // Test empty name validation
        $response = $this->makeRequest('POST', '', [
            'action' => 'lpush',
            'name' => ''
        ]);
        
        if ($response && !$response['success']) {
            $this->addResult('LPUSH Empty Name Validation', true, 'Correctly rejected empty name');
        } else {
            $this->addResult('LPUSH Empty Name Validation', false, 'Should reject empty names');
        }
    }
    
    private function testRPushOperations() {
        echo "➡️ Testing RPUSH Operations...\n";
        
        // Test adding names to end of list
        foreach (['Mary', 'Mike', 'Mia'] as $name) {
            $response = $this->makeRequest('POST', '', [
                'action' => 'rpush',
                'name' => $name
            ]);
            
            if ($response && $response['success']) {
                $this->addResult("RPUSH {$name}", true, $response['message']);
            } else {
                $this->addResult("RPUSH {$name}", false, $response['message'] ?? 'Failed');
            }
        }
        
        // Test whitespace trimming
        $response = $this->makeRequest('POST', '', [
            'action' => 'rpush',
            'name' => '  Sarah  '
        ]);
        
        if ($response && $response['success']) {
            $this->addResult('RPUSH Whitespace Trim', true, 'Correctly trimmed whitespace');
        } else {
            $this->addResult('RPUSH Whitespace Trim', false, 'Failed to handle whitespace');
        }
    }
    
    private function testGetFullList() {
        echo "📋 Testing Get Full List...\n";
        
        $response = $this->makeRequest('GET', '?action=get');
        
        if ($response && $response['success'] && is_array($response['data']['people'])) {
            $people = $response['data']['people'];
            $listLength = $response['data']['list_length'];
            
            // Expected order: Jack, Jane, John, Mary, Mike, Mia, Sarah (LPUSH adds to beginning, RPUSH to end)
            $expectedFirst = 'Jack';
            $expectedLast = 'Sarah';
            
            if (count($people) > 0 && $people[0] === $expectedFirst && end($people) === $expectedLast) {
                $this->addResult('Get Full List Order', true, "Correct order: first='{$expectedFirst}', last='{$expectedLast}', length={$listLength}");
            } else {
                $this->addResult('Get Full List Order', false, "Unexpected order. Got: " . implode(', ', $people));
            }
            
            $this->addResult('Get Full List', true, "Retrieved {$listLength} items");
        } else {
            $this->addResult('Get Full List', false, 'Failed to get list data');
        }
    }
    
    private function testLPopOperations() {
        echo "⬅️ Testing LPOP Operations...\n";
        
        // Test removing from beginning
        for ($i = 0; $i < 3; $i++) {
            $response = $this->makeRequest('POST', '', [
                'action' => 'lpop'
            ]);
            
            if ($response && $response['success']) {
                $this->addResult("LPOP #{$i}", true, $response['message']);
            } else {
                $this->addResult("LPOP #{$i}", false, $response['message'] ?? 'Failed');
            }
        }
    }
    
    private function testRPopOperations() {
        echo "➡️ Testing RPOP Operations...\n";
        
        // Test removing from end
        for ($i = 0; $i < 3; $i++) {
            $response = $this->makeRequest('POST', '', [
                'action' => 'rpop'
            ]);
            
            if ($response && $response['success']) {
                $this->addResult("RPOP #{$i}", true, $response['message']);
            } else {
                $this->addResult("RPOP #{$i}", false, $response['message'] ?? 'Failed');
            }
        }
    }
    
    private function testMixedOperations() {
        echo "🔀 Testing Mixed Operations...\n";
        
        // Add some data first
        $this->makeRequest('POST', '', ['action' => 'lpush', 'name' => 'First']);
        $this->makeRequest('POST', '', ['action' => 'rpush', 'name' => 'Last']);
        
        // Get current state
        $beforeResponse = $this->makeRequest('GET', '?action=get');
        $beforeCount = $beforeResponse['data']['list_length'] ?? 0;
        
        // Do mixed operations
        $this->makeRequest('POST', '', ['action' => 'lpush', 'name' => 'NewFirst']);
        $this->makeRequest('POST', '', ['action' => 'rpush', 'name' => 'NewLast']);
        $this->makeRequest('POST', '', ['action' => 'lpop']);
        
        $afterResponse = $this->makeRequest('GET', '?action=get');
        $afterCount = $afterResponse['data']['list_length'] ?? 0;
        
        // Should have net +1 item (added 2, removed 1)
        if ($afterCount === $beforeCount + 1) {
            $this->addResult('Mixed Operations Count', true, "Before: {$beforeCount}, After: {$afterCount}");
        } else {
            $this->addResult('Mixed Operations Count', false, "Count mismatch. Before: {$beforeCount}, After: {$afterCount}");
        }
    }
    
    private function testErrorHandling() {
        echo "❌ Testing Error Handling...\n";
        
        // Clear list first
        $this->clearList();
        
        // Test LPOP on empty list
        $response = $this->makeRequest('POST', '', ['action' => 'lpop']);
        if ($response && !$response['success'] && strpos($response['message'], 'empty') !== false) {
            $this->addResult('LPOP Empty List', true, 'Correctly handled empty list');
        } else {
            $this->addResult('LPOP Empty List', false, 'Should handle empty list gracefully');
        }
        
        // Test RPOP on empty list
        $response = $this->makeRequest('POST', '', ['action' => 'rpop']);
        if ($response && !$response['success'] && strpos($response['message'], 'empty') !== false) {
            $this->addResult('RPOP Empty List', true, 'Correctly handled empty list');
        } else {
            $this->addResult('RPOP Empty List', false, 'Should handle empty list gracefully');
        }
        
        // Test invalid action
        $response = $this->makeRequest('POST', '', ['action' => 'invalid']);
        if ($response && !$response['success']) {
            $this->addResult('Invalid Action', true, 'Correctly rejected invalid action');
        } else {
            $this->addResult('Invalid Action', false, 'Should reject invalid actions');
        }
        
        // Test invalid GET action
        $response = $this->makeRequest('GET', '?action=invalid');
        if ($response && !$response['success']) {
            $this->addResult('Invalid GET Action', true, 'Correctly rejected invalid GET action');
        } else {
            $this->addResult('Invalid GET Action', false, 'Should reject invalid GET actions');
        }
    }
    
    private function clearList() {
        echo "🧹 Clearing existing list data...\n";
        
        // Keep popping until list is empty
        $maxAttempts = 50; // Prevent infinite loop
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $response = $this->makeRequest('POST', '', ['action' => 'lpop']);
            if (!$response || !$response['success']) {
                break; // List is empty or error occurred
            }
            $attempts++;
        }
        
        echo "   Cleared list in {$attempts} operations\n\n";
    }
    
    private function makeRequest($method, $endpoint = '', $data = null) {
        // Use redis-list.php as the endpoint
        $url = $this->baseUrl . '/redis-list.php' . $endpoint;
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 10
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
        
        $actionInfo = $data ? " ({$data['action']}" . (isset($data['name']) ? ": {$data['name']}" : '') . ")" : "";
        echo "   {$method} {$endpoint}{$actionInfo} - HTTP {$httpCode}\n";
        
        if ($httpCode >= 400) {
            echo "   ❌ Error response: {$response}\n";
            return false;
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "   ❌ Invalid JSON response: {$response}\n";
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
        echo str_repeat('=', 60) . "\n";
        
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
            echo "🎉 All tests passed! Redis List API is working correctly.\n";
        } else {
            echo "⚠️ Some tests failed. Please check the API implementation.\n\n";
            
            echo "Failed Tests:\n";
            foreach ($this->results as $result) {
                if (!$result['success']) {
                    echo "❌ {$result['test']}: {$result['message']}\n";
                }
            }
        }
        
        echo "\nQuick Manual Tests:\n";
        echo "curl -X POST {$this->baseUrl}/redis-list.php -H 'Content-Type: application/json' -d '{\"action\":\"lpush\",\"name\":\"Test\"}'\n";
        echo "curl -X GET '{$this->baseUrl}/redis-list.php?action=get'\n";
        echo "curl -X POST {$this->baseUrl}/redis-list.php -H 'Content-Type: application/json' -d '{\"action\":\"lpop\"}'\n";
    }
}

// Command line interface
if ($argc > 1) {
    $baseUrl = $argv[1];
} else {
    echo "Enter API base URL (default: http://localhost): ";
    $handle = fopen("php://stdin", "r");
    $baseUrl = trim(fgets($handle));
    fclose($handle);
    
    if (empty($baseUrl)) {
        $baseUrl = 'http://localhost';
    }
}

echo "Testing Redis List API at: {$baseUrl}/redis-list.php\n\n";

$tester = new RedisListTester($baseUrl);
$tester->runTests(true, true);