<?php
/**
 * Redis TimeSeries API Testing Script
 * Script untuk testing redis-timeseries.php API
 */

require_once __DIR__ . '/vendor/autoload.php';

class RedisTimeSeriesTester {
    private $baseUrl;
    private $results = [];
    private $testCsvFile = 'test_timeseries_data.csv';
    private $redis;
    
    public function __construct($baseUrl = 'http://localhost') {
        $this->baseUrl = rtrim($baseUrl, '/');
        
        // Initialize Redis connection for direct testing
        try {
            $this->redis = new Predis\Client([
                'scheme' => 'tcp',
                'host'   => '127.0.0.1',
                'port'   => 6379,
                'timeout'=> 5
            ]);
            $this->redis->ping();
        } catch (Exception $e) {
            echo "❌ Warning: Could not connect to Redis directly: " . $e->getMessage() . "\n";
            echo "   Direct Redis verification will be skipped.\n\n";
            $this->redis = null;
        }
    }
    
    public function runTests() {
        echo "🚀 Starting Redis TimeSeries API Tests...\n\n";
        
        // Clear any existing data first
        $this->clearRedisData();
        
        // Test all operations
        $this->testGetEmptyData();
        $this->testCreateSampleCsv();
        $this->testUploadCsv();
        $this->testVerifyKeys();
        $this->testVerifyAggregationRules();
        $this->testGetRawData();
        $this->testGetAggregatedData();
        $this->testDataConsistency();
        $this->testErrorHandling();
        $this->testTimestampFormats();
        
        // Print summary
        $this->printSummary();
        
        // Cleanup
        $this->cleanup();
    }
    
    private function testGetEmptyData() {
        echo "📋 Testing Get Empty Data...\n";
        
        $response = $this->makeRequest('GET', '?raw=true');
        
        if (is_array($response) && count($response) === 0) {
            $this->addResult('Get Empty Raw Data', true, "Empty array returned as expected");
        } else {
            $this->addResult('Get Empty Raw Data', false, 'Expected empty array, got: ' . json_encode($response));
        }
        
        $response = $this->makeRequest('GET', '?agr=true');
        
        if (is_array($response) && count($response) === 0) {
            $this->addResult('Get Empty Aggregated Data', true, "Empty array returned as expected");
        } else {
            $this->addResult('Get Empty Aggregated Data', false, 'Expected empty array, got: ' . json_encode($response));
        }
    }
    
    private function testCreateSampleCsv() {
        echo "📁 Testing Sample CSV Creation...\n";
        
        $csvContent = "dt,Temperature_Average,Humidity_Max,Pressure_Min,WindSpeed_Sum,Rainfall_Average\n";
        $csvContent .= "01/01/2024,25.5,80.2,1013.2,15.3,2.5\n";
        $csvContent .= "01/02/2024,26.1,82.5,1012.8,18.7,1.2\n";
        $csvContent .= "01/03/2024,24.8,79.1,1014.1,12.9,0.0\n";
        $csvContent .= "01/04/2024,27.2,85.3,1011.5,22.1,3.8\n";
        $csvContent .= "01/05/2024,25.9,81.7,1013.9,16.4,1.5\n";
        $csvContent .= "01/06/2024,28.3,87.2,1010.8,25.6,4.2\n";
        $csvContent .= "01/07/2024,23.1,75.8,1015.3,8.9,0.3\n";
        
        $result = file_put_contents($this->testCsvFile, $csvContent);
        
        if ($result !== false && file_exists($this->testCsvFile)) {
            $lines = count(file($this->testCsvFile));
            $this->addResult('Create Sample CSV', true, "CSV created with {$lines} lines (including header)");
        } else {
            $this->addResult('Create Sample CSV', false, 'Failed to create CSV file');
        }
    }
    
    private function testUploadCsv() {
        echo "⬆️ Testing CSV Upload...\n";
        
        if (!file_exists($this->testCsvFile)) {
            $this->addResult('CSV Upload', false, 'Test CSV file not found');
            return;
        }
        
        $response = $this->uploadFile($this->testCsvFile);
        
        if ($response && is_array($response) && count($response) > 0) {
            $rowCount = count($response);
            $firstRow = $response[0];
            $columns = array_keys($firstRow);
            
            $this->addResult('CSV Upload Success', true, "Uploaded successfully, got {$rowCount} rows with columns: " . implode(', ', $columns));
            
            // Check if date column exists
            if (in_array('date', $columns)) {
                $this->addResult('CSV Upload Date Column', true, "Date column found");
            } else {
                $this->addResult('CSV Upload Date Column', false, "Date column missing");
            }
            
            // Check expected columns
            $expectedColumns = ['Temperature_Average', 'Humidity_Max', 'Pressure_Min', 'WindSpeed_Sum', 'Rainfall_Average'];
            $missingColumns = array_diff($expectedColumns, $columns);
            
            if (empty($missingColumns)) {
                $this->addResult('CSV Upload Expected Columns', true, "All expected columns found");
            } else {
                $this->addResult('CSV Upload Expected Columns', false, "Missing columns: " . implode(', ', $missingColumns));
            }
            
        } elseif (is_array($response) && isset($response['error'])) {
            $this->addResult('CSV Upload', false, 'Upload failed: ' . $response['error']);
        } else {
            $this->addResult('CSV Upload', false, 'Invalid response format: ' . json_encode($response));
        }
    }
    
    private function testVerifyKeys() {
        echo "🔑 Testing Key Verification...\n";
        
        if (!$this->redis) {
            $this->addResult('Key Verification', false, 'Redis connection not available');
            return;
        }
        
        $allKeys = $this->redis->keys('*');
        sort($allKeys);
        
        $expectedKeys = [
            'Temperature_Average', 'Temperature_Average_compacted',
            'Humidity_Max', 'Humidity_Max_compacted', 
            'Pressure_Min', 'Pressure_Min_compacted',
            'WindSpeed_Sum', 'WindSpeed_Sum_compacted',
            'Rainfall_Average', 'Rainfall_Average_compacted'
        ];
        
        $this->addResult('Total Keys Created', true, "Found " . count($allKeys) . " keys: " . implode(', ', $allKeys));
        
        $missingKeys = array_diff($expectedKeys, $allKeys);
        $extraKeys = array_diff($allKeys, $expectedKeys);
        
        if (empty($missingKeys)) {
            $this->addResult('Expected Keys Present', true, "All expected keys found");
        } else {
            $this->addResult('Expected Keys Present', false, "Missing keys: " . implode(', ', $missingKeys));
        }
        
        if (empty($extraKeys)) {
            $this->addResult('No Extra Keys', true, "No unexpected keys found");
        } else {
            $this->addResult('No Extra Keys', false, "Extra keys found: " . implode(', ', $extraKeys));
        }
    }
    
    private function testVerifyAggregationRules() {
        echo "📊 Testing Aggregation Rules...\n";
        
        if (!$this->redis) {
            $this->addResult('Aggregation Rules', false, 'Redis connection not available');
            return;
        }
        
        $expectedRules = [
            'Temperature_Average' => 'avg',
            'Humidity_Max' => 'max',
            'Pressure_Min' => 'min', 
            'WindSpeed_Sum' => 'sum',
            'Rainfall_Average' => 'avg'
        ];
        
        $ruleResults = [];
        
        foreach ($expectedRules as $key => $expectedAgg) {
            try {
                $info = $this->redis->executeRaw(['TS.INFO', $key]);
                $hasCorrectRule = false;
                $actualAgg = 'none';
                
                for ($i = 0; $i < count($info); $i++) {
                    if ($info[$i] === 'rules' && isset($info[$i+1])) {
                        $rules = $info[$i+1];
                        if (is_array($rules) && count($rules) > 0) {
                            foreach ($rules as $rule) {
                                if (is_array($rule) && count($rule) >= 2) {
                                    $actualAgg = strtolower($rule[2]);
                                    if ($actualAgg === $expectedAgg) {
                                        $hasCorrectRule = true;
                                    }
                                    break;
                                }
                            }
                        }
                        break;
                    }
                }
                
                $ruleResults[$key] = [
                    'expected' => $expectedAgg,
                    'actual' => $actualAgg,
                    'correct' => $hasCorrectRule
                ];
                
                if ($hasCorrectRule) {
                    $this->addResult("Rule for {$key}", true, "Correct aggregation: {$actualAgg}");
                } else {
                    $this->addResult("Rule for {$key}", false, "Expected: {$expectedAgg}, Got: {$actualAgg}");
                }
                
            } catch (Exception $e) {
                $this->addResult("Rule for {$key}", false, "Error checking rule: " . $e->getMessage());
            }
        }
        
        $correctRules = array_filter($ruleResults, function($rule) { return $rule['correct']; });
        $totalRules = count($ruleResults);
        $correctCount = count($correctRules);
        
        $this->addResult('Overall Aggregation Rules', $correctCount === $totalRules, "{$correctCount}/{$totalRules} rules are correct");
    }
    
    private function testGetRawData() {
        echo "📈 Testing Get Raw Data...\n";
        
        $response = $this->makeRequest('GET', '?raw=true');
        
        if ($response && is_array($response) && count($response) > 0) {
            $rowCount = count($response);
            $firstRow = $response[0];
            
            // Check structure
            if (isset($firstRow['date'])) {
                $this->addResult('Raw Data Structure', true, "Got {$rowCount} rows with date field");
                
                // Check date format
                $dateValue = $firstRow['date'];
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
                    $this->addResult('Raw Data Date Format', true, "Date format is correct: {$dateValue}");
                } else {
                    $this->addResult('Raw Data Date Format', false, "Invalid date format: {$dateValue}");
                }
                
                // Check data values
                $dataColumns = array_filter(array_keys($firstRow), function($key) {
                    return $key !== 'date';
                });
                
                $hasValidData = false;
                foreach ($dataColumns as $col) {
                    if (is_numeric($firstRow[$col])) {
                        $hasValidData = true;
                        break;
                    }
                }
                
                if ($hasValidData) {
                    $this->addResult('Raw Data Values', true, "Found numeric data in columns: " . implode(', ', $dataColumns));
                } else {
                    $this->addResult('Raw Data Values', false, "No numeric data found");
                }
                
            } else {
                $this->addResult('Raw Data Structure', false, "Missing date field in response");
            }
            
        } elseif (is_array($response) && isset($response['error'])) {
            $this->addResult('Get Raw Data', false, 'Error: ' . $response['error']);
        } else {
            $this->addResult('Get Raw Data', false, 'Invalid or empty response');
        }
    }
    
    private function testGetAggregatedData() {
        echo "📊 Testing Get Aggregated Data...\n";
        
        // Wait a bit for aggregation to process
        sleep(3);
        
        $response = $this->makeRequest('GET', '?agr=true');
        
        if ($response && is_array($response)) {
            if (count($response) > 0) {
                $rowCount = count($response);
                $firstRow = $response[0];
                $compactedColumns = array_filter(array_keys($firstRow), function($key) {
                    return strpos($key, '_compacted') !== false;
                });
                
                $this->addResult('Aggregated Data Structure', true, "Got {$rowCount} rows with compacted columns: " . implode(', ', $compactedColumns));
                
                // Check if compacted data has values
                $hasValues = false;
                foreach ($compactedColumns as $col) {
                    if (is_numeric($firstRow[$col]) && $firstRow[$col] !== null) {
                        $hasValues = true;
                        break;
                    }
                }
                
                if ($hasValues) {
                    $this->addResult('Aggregated Data Values', true, "Compacted data contains numeric values");
                } else {
                    $this->addResult('Aggregated Data Values', false, "No numeric values in compacted data (may be normal for new data)");
                }
                
            } else {
                $this->addResult('Aggregated Data', false, "Empty aggregated data (may be normal if compaction hasn't run yet)");
            }
        } else {
            $this->addResult('Aggregated Data', false, 'Invalid response format');
        }
    }
    
    private function testDataConsistency() {
        echo "🔍 Testing Data Consistency...\n";
        
        if (!$this->redis) {
            $this->addResult('Data Consistency', false, 'Redis connection not available');
            return;
        }
        
        // Check that raw keys have data
        $rawKeys = ['Temperature_Average', 'Humidity_Max', 'Pressure_Min', 'WindSpeed_Sum', 'Rainfall_Average'];
        $dataConsistency = true;
        $consistencyDetails = [];
        
        foreach ($rawKeys as $key) {
            try {
                $data = $this->redis->executeRaw(['TS.RANGE', $key, '-', '+']);
                $count = is_array($data) ? count($data) : 0;
                $consistencyDetails[] = "{$key}: {$count} points";
                
                if ($count === 0) {
                    $dataConsistency = false;
                }
            } catch (Exception $e) {
                $consistencyDetails[] = "{$key}: ERROR";
                $dataConsistency = false;
            }
        }
        
        if ($dataConsistency) {
            $this->addResult('Raw Data Consistency', true, implode(', ', $consistencyDetails));
        } else {
            $this->addResult('Raw Data Consistency', false, implode(', ', $consistencyDetails));
        }
        
        // Test that all raw keys have same number of data points (should be same as CSV rows)
        $dataCounts = [];
        foreach ($rawKeys as $key) {
            try {
                $data = $this->redis->executeRaw(['TS.RANGE', $key, '-', '+']);
                $dataCounts[$key] = is_array($data) ? count($data) : 0;
            } catch (Exception $e) {
                $dataCounts[$key] = 0;
            }
        }
        
        $uniqueCounts = array_unique(array_values($dataCounts));
        if (count($uniqueCounts) === 1) {
            $count = $uniqueCounts[0];
            $this->addResult('Data Point Consistency', true, "All keys have {$count} data points");
        } else {
            $this->addResult('Data Point Consistency', false, "Inconsistent data counts: " . json_encode($dataCounts));
        }
    }
    
    private function testErrorHandling() {
        echo "❌ Testing Error Handling...\n";
        
        // Test upload with invalid file
        $response = $this->makeRequestFailed('POST', '', null, ['csv_file' => 'nonexistent.csv']);
        if ($response) {
            $this->addResult('Invalid File Upload', true, "Correctly handled invalid file");
        } else {
            $this->addResult('Invalid File Upload', false, "Should return error for invalid file");
        }
        
        // Test invalid GET parameters
        $response = $this->makeRequest('GET', '?invalid=true');
        if ($response && is_array($response)) {
            $this->addResult('Invalid GET Parameter', true, "Handled invalid parameter gracefully");
        } else {
            $this->addResult('Invalid GET Parameter', false, "Should handle invalid parameters");
        }
        
        // Test GET with conflicting parameters
        $response = $this->makeRequest('GET', '?raw=true&agr=true');
        if ($response && is_array($response)) {
            $this->addResult('Conflicting Parameters', true, "Handled conflicting parameters");
        } else {
            $this->addResult('Conflicting Parameters', false, "Should handle conflicting parameters");
        }
    }
    
    private function testTimestampFormats() {
        echo "📅 Testing Timestamp Formats...\n";
        
        // Create CSV with different timestamp format
        $csvContent2 = "dt,Test_Average\n";
        $csvContent2 .= "2024-01-01,100.5\n";
        $csvContent2 .= "2024-01-02,200.7\n";
        
        $testFile2 = 'test_timestamp_format.csv';
        file_put_contents($testFile2, $csvContent2);
        
        $response = $this->uploadFile($testFile2);
        
        if ($response && is_array($response) && count($response) > 0) {
            $this->addResult('Alternative Timestamp Format', true, "Successfully uploaded CSV with YYYY-MM-DD format");
        } else {
            $this->addResult('Alternative Timestamp Format', false, "Failed to upload CSV with YYYY-MM-DD format");
        }
        
        // Cleanup
        unlink($testFile2);
        
        if ($this->redis) {
            try {
                $this->redis->executeRaw(['DEL', 'Test_Average']);
                $this->redis->executeRaw(['DEL', 'Test_Average_compacted']);
            } catch (Exception $e) {
                // Ignore
            }
        }
    }
    
    private function clearRedisData() {
        if (!$this->redis) {
            echo "🧹 Skipping Redis cleanup (no connection)...\n\n";
            return;
        }
        
        echo "🧹 Clearing existing Redis data...\n";
        
        $keys = $this->redis->keys('*');
        $deletedCount = 0;
        
        foreach ($keys as $key) {
            try {
                $this->redis->executeRaw(['DEL', $key]);
                $deletedCount++;
            } catch (Exception $e) {
                // Continue
            }
        }
        
        echo "   Cleared {$deletedCount} keys from Redis\n\n";
    }
    
    private function uploadFile($filePath) {
        if (!file_exists($filePath)) {
            return ['error' => 'File does not exist'];
        }
        
        $url = $this->baseUrl . '/redis-timeseries.php';
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'csv_file' => new CURLFile($filePath, 'text/csv', basename($filePath))
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo "   POST upload {$filePath} - HTTP {$httpCode}\n";
        
        if ($error) {
            echo "   ❌ CURL Error: {$error}\n";
            return ['error' => $error];
        }
        
        if ($httpCode >= 400) {
            echo "   ❌ Error response: {$response}\n";
            return ['error' => "HTTP {$httpCode}"];
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "   ❌ Invalid JSON response\n";
            return ['error' => 'Invalid JSON response'];
        }
        
        return $decoded;
    }
    
    private function makeRequest($method, $endpoint = '', $data = null, $files = null) {
        $url = $this->baseUrl . '/redis-timeseries.php' . $endpoint;
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                curl_getinfo($ch, CURLINFO_HEADER_OUT) ?: [],
                ['Content-Type: application/json']
            ));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        echo "   {$method} {$endpoint} - HTTP {$httpCode}\n";
        
        if ($error) {
            echo "   ❌ CURL Error: {$error}\n";
            return false;
        }
        
        if ($httpCode >= 400) {
            echo "   ❌ Error response: {$response}\n";
            return false;
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "   ❌ Invalid JSON response\n";
            return false;
        }
        
        return $decoded;
    }

    private function makeRequestFailed($method, $endpoint = '', $data = null, $files = null) {
        $url = $this->baseUrl . '/redis-timeseries.php' . $endpoint;
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                curl_getinfo($ch, CURLINFO_HEADER_OUT) ?: [],
                ['Content-Type: application/json']
            ));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        echo "   {$method} {$endpoint} - HTTP {$httpCode}\n";
        
        if ($error) {
            echo "   ❌ CURL Error: {$error}\n";
            return false;
        }
        
        if ($httpCode >= 400) {
            echo "   ❌ Error response: {$response}\n";
            return false;
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return true;
        }
        
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
    
    private function cleanup() {
        echo "🧹 Cleaning up test files...\n";
        
        if (file_exists($this->testCsvFile)) {
            unlink($this->testCsvFile);
            echo "   Removed {$this->testCsvFile}\n";
        }
        
        echo "\n";
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
            echo "🎉 All tests passed! Redis TimeSeries API is working correctly.\n";
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
        echo "# Upload CSV:\n";
        echo "curl -X POST {$this->baseUrl}/redis-timeseries.php -F 'csv_file=@data.csv'\n\n";
        echo "# Get raw data:\n";
        echo "curl -X GET '{$this->baseUrl}/redis-timeseries.php?raw=true'\n\n";
        echo "# Get aggregated data:\n";
        echo "curl -X GET '{$this->baseUrl}/redis-timeseries.php?agr=true'\n\n";
        echo "# Get default data:\n";
        echo "curl -X GET '{$this->baseUrl}/redis-timeseries.php'\n\n";
        
        if ($this->redis) {
            echo "Redis Direct Commands:\n";
            echo "redis-cli TS.RANGE Temperature_Average - +\n";
            echo "redis-cli TS.INFO Temperature_Average\n";
            echo "redis-cli KEYS '*'\n";
            echo "redis-cli FLUSHDB  # Clear all data\n";
        }
    }
}

// Check for Predis dependency
if (!class_exists('Predis\\Client')) {
    echo "⚠️ Warning: Predis not found. Redis verification will be limited.\n";
    echo "   Install with: composer require predis/predis\n\n";
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

echo "Testing Redis TimeSeries API at: {$baseUrl}/redis-timeseries.php\n\n";

$tester = new RedisTimeSeriesTester($baseUrl);
$tester->runTests();