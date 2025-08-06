<?php
require_once 'vendor/autoload.php';

use Predis\Client;

class GlobalTemperatureApp {
    private $redis;
    private $connectionStatus;
    
    public function __construct() {
        $this->connectionStatus = $this->connectToRedis();
    }
    
    private function connectToRedis() {
        $configs = [
            [
                'name' => 'Remote Redis (54.176.6.43)',
                'scheme' => 'tcp',
                'host' => '54.176.6.43',
                'port' => 6379,
                'timeout' => 5
            ],
            [
                'name' => 'Local Redis (127.0.0.1)',
                'scheme' => 'tcp',
                'host' => '127.0.0.1',
                'port' => 6379,
                'timeout' => 5
            ],
            [
                'name' => 'Docker Redis (localhost)',
                'scheme' => 'tcp',
                'host' => 'localhost',
                'port' => 6379,
                'timeout' => 5
            ]
        ];
        
        foreach ($configs as $config) {
            try {
                $testRedis = new Client([
                    'scheme' => $config['scheme'],
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'timeout' => $config['timeout']
                ]);
                
                // Test connection
                $result = $testRedis->ping();
                if ($result && (strtoupper($result) === 'PONG' || $result === 1)) {
                    $this->redis = $testRedis;
                    return [
                        'success' => true,
                        'message' => "✅ Connected to " . $config['name'],
                        'config' => $config,
                        'ping_result' => $result
                    ];
                }
            } catch (Exception $e) {
                // Continue to next config
                continue;
            }
        }
        
        return [
            'success' => false,
            'message' => "❌ Failed to connect to any Redis server",
            'error' => "All connection attempts failed"
        ];
    }
    
    public function getConnectionStatus() {
        return $this->connectionStatus;
    }
    
    public function testRedisCommands() {
        if (!$this->connectionStatus['success']) {
            return ['success' => false, 'message' => 'No Redis connection'];
        }
        
        $tests = [];
        
        try {
            // Test basic commands
            $tests['ping'] = $this->redis->ping();
            $tests['info'] = $this->redis->info('server');
            
            // Test RedisTimeSeries commands
            try {
                $testKey = 'test_ts_' . time();
                $this->redis->executeRaw(['TS.CREATE', $testKey, 'RETENTION', '60000']);
                $this->redis->executeRaw(['TS.ADD', $testKey, '*', '123.45']);
                $data = $this->redis->executeRaw(['TS.GET', $testKey]);
                $this->redis->executeRaw(['DEL', $testKey]);
                
                $tests['timeseries'] = 'Available';
            } catch (Exception $e) {
                $tests['timeseries'] = 'Not Available - ' . $e->getMessage();
            }
            
            return [
                'success' => true,
                'tests' => $tests
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Command test failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function debugRedis() {
        if (!$this->connectionStatus['success']) {
            return ['error' => 'No Redis connection'];
        }
        
        $debug = [];
        
        try {
            $debug['basic_ping'] = $this->redis->ping();
            
            try {
                $modules = $this->redis->executeRaw(['MODULE', 'LIST']);
                $debug['modules'] = $modules;
                
                $hasTimeSeries = false;
                if (is_array($modules)) {
                    foreach ($modules as $module) {
                        if (is_array($module) && in_array('timeseries', $module)) {
                            $hasTimeSeries = true;
                            break;
                        }
                    }
                }
                $debug['has_timeseries'] = $hasTimeSeries;
            } catch (Exception $e) {
                $debug['module_check_error'] = $e->getMessage();
            }
            
            try {
                $keys = $this->redis->keys('*');
                $debug['all_keys'] = $keys;
            } catch (Exception $e) {
                $debug['keys_error'] = $e->getMessage();
            }
            
            // 4. Test TimeSeries commands specifically
            try {
                $tsInfo = $this->redis->executeRaw(['TS.INFO', 'land_avg_temp']);
                $debug['ts_info'] = $tsInfo;
            } catch (Exception $e) {
                $debug['ts_info_error'] = $e->getMessage();
            }
            
            // 5. Test TS.RANGE command dengan response lengkap
            try {
                $tsRange = $this->redis->executeRaw(['TS.RANGE', 'land_avg_temp', '-', '+', 'COUNT', '5']);
                $debug['ts_range_result'] = $tsRange;
                $debug['ts_range_type'] = gettype($tsRange);
            } catch (Exception $e) {
                $debug['ts_range_error'] = $e->getMessage();
            }
            
            // 6. Coba buat test time series
            try {
                $testKey = 'debug_test_' . time();
                $this->redis->executeRaw(['TS.CREATE', $testKey]);
                $this->redis->executeRaw(['TS.ADD', $testKey, '*', '123.45']);
                $testData = $this->redis->executeRaw(['TS.RANGE', $testKey, '-', '+']);
                $this->redis->executeRaw(['DEL', $testKey]);
                
                $debug['test_create_success'] = true;
                $debug['test_data'] = $testData;
                $debug['test_data_type'] = gettype($testData);
            } catch (Exception $e) {
                $debug['test_create_error'] = $e->getMessage();
            }
            
        } catch (Exception $e) {
            $debug['general_error'] = $e->getMessage();
        }
        
        return $debug;
    }
    
    public function showDebugInfo() {
        $debug = $this->debugRedis();
        
        echo "<div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h3>🔍 Debug Information</h3>";
        echo "<pre style='background: white; padding: 10px; border-radius: 3px; font-size: 12px;'>";
        echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo "</pre>";
        echo "</div>";
    }
    
    public function listAllKeys() {
        if (!$this->connectionStatus['success']) {
            return [];
        }
        
        try {
            $keys = $this->redis->keys('*');
            error_log("[listAllKeys] Found keys: " . json_encode($keys));
            return $keys;
        } catch (Exception $e) {
            error_log("[listAllKeys] Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function uploadCSV($csvFile) {
    $results = ['success' => false, 'message' => '', 'count' => 0];

    error_log("[uploadCSV] Memulai upload CSV: $csvFile");

    if (!$this->connectionStatus['success']) {
        $results['message'] = 'Error: No Redis connection available';
        error_log("[uploadCSV] Gagal: Tidak ada koneksi Redis");
        return $results;
    }

    try {
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            // Auto-detect delimiter from first line
            $firstLine = fgets($handle);
            rewind($handle); // Reset file pointer
            
            $delimiter = ',';
            
            error_log("[uploadCSV] Detected delimiter: " . ($delimiter === "\t" ? "TAB" : "COMMA"));
            
            $headers = fgetcsv($handle, 1000, $delimiter);
            error_log("[uploadCSV] Header CSV: " . json_encode($headers));

            // Bersihkan whitespace dari headers
            $headers = array_map('trim', $headers);
            $count = 0;
            $errors = [];

            // Validate headers
            if (count($headers) < 9) {
                $results['message'] = 'Invalid CSV: Expected at least 9 columns, got ' . count($headers);
                return $results;
            }

            // Buat time series terlebih dahulu
            $createResult = $this->createTimeSeriesIfNotExists();
            if (!$createResult['success']) {
                $results['message'] = 'Error creating time series: ' . $createResult['message'];
                return $results;
            }

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                error_log("[uploadCSV] Membaca baris ke-" . ($count + 1) . ": " . json_encode($data));
                
                // Skip empty lines
                if (empty($data) || (count($data) == 1 && empty($data[0]))) {
                    continue;
                }
                
                if (count($data) >= 9) {
                    $dateStr = trim($data[0]);
                    
                    // Handle different date formats
                    $timestamp = false;
                    
                    // Try M/d/yyyy format first (like 1/1/1970)
                    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $matches)) {
                        $month = $matches[1];
                        $day = $matches[2];
                        $year = $matches[3];
                        $timestamp = mktime(0, 0, 0, $month, $day, $year);
                    } else {
                        // Fallback to strtotime for other formats
                        $timestamp = strtotime($dateStr);
                    }
                    
                    if ($timestamp === false) {
                        error_log("[uploadCSV] Invalid date format: $dateStr");
                        $errors[] = "Invalid date format at row " . ($count + 1) . ": $dateStr";
                        continue;
                    }
                    
                    $timestampMs = $timestamp * 1000; // Convert to milliseconds
                    error_log("[uploadCSV] Date: $dateStr, Timestamp (ms): $timestampMs");

                    // Tambahkan data ke masing-masing time series dengan error handling
                    $addResults = [];
                    $addResults[] = $this->addDataPointSafe('land_avg_temp', $timestampMs, $data[1]);
                    $addResults[] = $this->addDataPointSafe('land_avg_temp_uncertainty', $timestampMs, $data[2]);
                    $addResults[] = $this->addDataPointSafe('land_max_temp', $timestampMs, $data[3]);
                    $addResults[] = $this->addDataPointSafe('land_max_temp_uncertainty', $timestampMs, $data[4]);
                    $addResults[] = $this->addDataPointSafe('land_min_temp', $timestampMs, $data[5]);
                    $addResults[] = $this->addDataPointSafe('land_min_temp_uncertainty', $timestampMs, $data[6]);
                    $addResults[] = $this->addDataPointSafe('land_ocean_avg_temp', $timestampMs, $data[7]);
                    $addResults[] = $this->addDataPointSafe('land_ocean_avg_temp_uncertainty', $timestampMs, $data[8]);

                    // Check if any additions failed
                    $failedAdds = array_filter($addResults, function($result) {
                        return !$result['success'];
                    });

                    if (!empty($failedAdds)) {
                        error_log("[uploadCSV] Some data points failed for row " . ($count + 1));
                        foreach ($failedAdds as $failed) {
                            $errors[] = $failed['error'];
                        }
                    }

                    $count++;
                    
                    // Progress logging every 100 rows
                    if ($count % 100 === 0) {
                        error_log("[uploadCSV] Processed $count rows...");
                    }
                } else {
                    error_log("[uploadCSV] Baris tidak valid (jumlah kolom: " . count($data) . "): " . json_encode($data));
                    $errors[] = "Invalid row " . ($count + 1) . ": expected 9 columns, got " . count($data);
                    
                    // Stop if too many invalid rows
                    if (count($errors) > 10) {
                        $results['message'] = 'Too many invalid rows. Please check CSV format.';
                        return $results;
                    }
                }
            }

            fclose($handle);

            // Buat compaction rules
            $this->createCompactionRules();
            error_log("[uploadCSV] Compaction rules dibuat");

            $results['success'] = true;
            $results['message'] = "Data berhasil diupload dengan delimiter " . ($delimiter === "\t" ? "TAB" : "COMMA");
            $results['count'] = $count;
            
            if (!empty($errors) && count($errors) <= 10) {
                $results['message'] .= '. Warnings: ' . implode('; ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $results['message'] .= ' and ' . (count($errors) - 3) . ' more...';
                }
            }

            error_log("[uploadCSV] Upload selesai. Jumlah data tersimpan: $count");
        } else {
            error_log("[uploadCSV] Tidak bisa membuka file: $csvFile");
            $results['message'] = 'Cannot open CSV file';
        }
    } catch (Exception $e) {
        $results['message'] = 'Error: ' . $e->getMessage();
        error_log("[uploadCSV] Exception terjadi: " . $e->getMessage());
    }

    return $results;
}

    private function addDataPointSafe($key, $timestamp, $value) {
        try {
            // Handle empty values or string "N/A" values
            if (empty($value) || $value === 'N/A' || $value === '' || $value === null) {
                error_log("[addDataPointSafe] Skipping empty/null value for $key");
                return ['success' => true, 'result' => 'skipped_empty'];
            }
            
            $numValue = floatval($value);
            if (!is_nan($numValue) && is_finite($numValue)) {
                $result = $this->redis->executeRaw(['TS.ADD', $key, $timestamp, $numValue]);
                error_log("[addDataPointSafe] Added to $key: $timestamp = $numValue, result: " . json_encode($result));
                return ['success' => true, 'result' => $result];
            } else {
                error_log("[addDataPointSafe] Skipping invalid value for $key: $value");
                return ['success' => true, 'result' => 'skipped_invalid'];
            }
        } catch (Exception $e) {
            error_log("[addDataPointSafe] Error adding to $key: " . $e->getMessage());
            return ['success' => false, 'error' => "Failed to add to $key: " . $e->getMessage()];
        }
    }

    private function createTimeSeriesIfNotExists() {
        $timeSeries = [
            'land_avg_temp',
            'land_avg_temp_uncertainty', 
            'land_max_temp',
            'land_max_temp_uncertainty',
            'land_min_temp', 
            'land_min_temp_uncertainty',
            'land_ocean_avg_temp',
            'land_ocean_avg_temp_uncertainty'
        ];
        
        $errors = [];
        
        foreach ($timeSeries as $ts) {
            try {
                $result = $this->redis->executeRaw(['TS.CREATE', $ts, 'RETENTION', '0', 'LABELS', 'type', 'temperature']);
                error_log("[createTimeSeriesIfNotExists] Created $ts: " . json_encode($result));
            } catch (Exception $e) {
                // Check if error is because key already exists
                if (strpos($e->getMessage(), 'TSDB: key already exists') !== false) {
                    error_log("[createTimeSeriesIfNotExists] $ts already exists, continuing...");
                } else {
                    $errors[] = "Failed to create $ts: " . $e->getMessage();
                    error_log("[createTimeSeriesIfNotExists] Error creating $ts: " . $e->getMessage());
                }
            }
            
            // Buat time series untuk compaction tahunan
            foreach (['yearly_avg', 'yearly_max', 'yearly_min'] as $suffix) {
                try {
                    $yearlyKey = $ts . '_' . $suffix;
                    $result = $this->redis->executeRaw(['TS.CREATE', $yearlyKey, 'RETENTION', '0', 'LABELS', 'type', $suffix]);
                    error_log("[createTimeSeriesIfNotExists] Created $yearlyKey: " . json_encode($result));
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'TSDB: key already exists') !== false) {
                        error_log("[createTimeSeriesIfNotExists] $yearlyKey already exists, continuing...");
                    } else {
                        $errors[] = "Failed to create $yearlyKey: " . $e->getMessage();
                        error_log("[createTimeSeriesIfNotExists] Error creating $yearlyKey: " . $e->getMessage());
                    }
                }
            }
        }
        
        return [
            'success' => empty($errors),
            'message' => empty($errors) ? 'All time series created successfully' : implode('; ', $errors),
            'errors' => $errors
        ];
    }
    
    private function createCompactionRules() {
        $timeSeries = [
            'land_avg_temp',
            'land_avg_temp_uncertainty', 
            'land_max_temp',
            'land_max_temp_uncertainty',
            'land_min_temp', 
            'land_min_temp_uncertainty',
            'land_ocean_avg_temp',
            'land_ocean_avg_temp_uncertainty'
        ];
        
        foreach ($timeSeries as $ts) {
            try {
                // Buat rule untuk compaction tahunan dengan agregasi AVG, MAX, MIN
                $this->redis->executeRaw(['TS.CREATERULE', $ts, $ts . '_yearly_avg', 'AGGREGATION', 'AVG', '31536000000']); // 1 year in ms
                $this->redis->executeRaw(['TS.CREATERULE', $ts, $ts . '_yearly_max', 'AGGREGATION', 'MAX', '31536000000']);
                $this->redis->executeRaw(['TS.CREATERULE', $ts, $ts . '_yearly_min', 'AGGREGATION', 'MIN', '31536000000']);
            } catch (Exception $e) {
                // Rule mungkin sudah ada, abaikan error
            }
        }
    }
    
    public function forceCompaction() {
        if (!$this->connectionStatus['success']) {
            return ['success' => false, 'message' => 'No Redis connection'];
        }
        
        try {
            $timeSeries = [
                'land_avg_temp',
                'land_avg_temp_uncertainty', 
                'land_max_temp',
                'land_max_temp_uncertainty',
                'land_min_temp', 
                'land_min_temp_uncertainty',
                'land_ocean_avg_temp',
                'land_ocean_avg_temp_uncertainty'
            ];
            
            $results = [];
            
            foreach ($timeSeries as $ts) {
                try {
                    // Try to get some sample data to determine time range
                    $sampleData = $this->redis->executeRaw(['TS.RANGE', $ts, '-', '+', 'COUNT', '1']);
                    
                    if (is_array($sampleData) && !empty($sampleData)) {
                        // Force compaction by using TS.CREATERULE with smaller bucket
                        // First delete existing rules if any
                        try {
                            $this->redis->executeRaw(['TS.DELETERULE', $ts, $ts . '_yearly_avg']);
                            $this->redis->executeRaw(['TS.DELETERULE', $ts, $ts . '_yearly_max']);
                            $this->redis->executeRaw(['TS.DELETERULE', $ts, $ts . '_yearly_min']);
                        } catch (Exception $e) {
                            // Rules might not exist, ignore
                        }
                        
                        // Create new rules
                        $yearlyMs = 31536000000; // 1 year in ms
                        
                        $this->redis->executeRaw(['TS.CREATERULE', $ts, $ts . '_yearly_avg', 'AGGREGATION', 'AVG', $yearlyMs]);
                        $this->redis->executeRaw(['TS.CREATERULE', $ts, $ts . '_yearly_max', 'AGGREGATION', 'MAX', $yearlyMs]);
                        $this->redis->executeRaw(['TS.CREATERULE', $ts, $ts . '_yearly_min', 'AGGREGATION', 'MIN', $yearlyMs]);
                        
                        $results[] = "Compaction rules recreated for $ts";
                    }
                } catch (Exception $e) {
                    $results[] = "Error processing $ts: " . $e->getMessage();
                }
            }
            
            return [
                'success' => true,
                'message' => 'Compaction forced',
                'details' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkKeyExists($key) {
        try {
            $info = $this->redis->executeRaw(['TS.INFO', $key]);
            return is_array($info); // If key exists, TS.INFO returns array
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'key does not exist') !== false) {
                return false;
            }
            // For other errors, assume key doesn't exist
            error_log("[checkKeyExists] Error checking key $key: " . $e->getMessage());
            return false;
        }
    }

    private function getValueAtTimestamp($key, $timestamp) {
        try {
            // Try to get exact value at timestamp
            $rangeData = $this->redis->executeRaw(['TS.RANGE', $key, $timestamp, $timestamp]);
            
            if (is_array($rangeData) && !empty($rangeData) && is_array($rangeData[0])) {
                return round(floatval($rangeData[0][1]), 3);
            }
            
            return 'N/A';
        } catch (Exception $e) {
            error_log("[getValueAtTimestamp] Error untuk $key at $timestamp: " . $e->getMessage());
            return 'N/A';
        }
    }
    
    public function getRawData($limit = 100) {
        if (!$this->connectionStatus['success']) {
            return [];
        }

        set_time_limit(120);
        
        try {
            // Cek dulu apakah key ada
            $keyExists = $this->checkKeyExists('land_avg_temp');
            if (!$keyExists) {
                error_log("[getRawData] Key 'land_avg_temp' tidak ada");
                return [];
            }
            
            // Ambil data dari time series utama
            $data = $this->redis->executeRaw(['TS.RANGE', 'land_avg_temp', '-', '+', 'COUNT', $limit]);
            
            // Debug logging
            error_log("[getRawData] Raw data type: " . gettype($data));
            
            // Handle error responses dari Redis
            if (is_string($data)) {
                error_log("[getRawData] Redis returned error: " . $data);
                return [];
            }
            
            // Pastikan $data adalah array
            if (!is_array($data)) {
                error_log("[getRawData] Data bukan array, melainkan: " . gettype($data));
                return [];
            }
            
            if (empty($data)) {
                error_log("[getRawData] No data found in time series");
                return [];
            }
            
            $result = [];
            $timeSeries = [
                'land_avg_temp' => 'Land Average Temperature',
                'land_avg_temp_uncertainty' => 'Land Average Temperature Uncertainty',
                'land_max_temp' => 'Land Max Temperature', 
                'land_max_temp_uncertainty' => 'Land Max Temperature Uncertainty',
                'land_min_temp' => 'Land Min Temperature',
                'land_min_temp_uncertainty' => 'Land Min Temperature Uncertainty',
                'land_ocean_avg_temp' => 'Land And Ocean Average Temperature',
                'land_ocean_avg_temp_uncertainty' => 'Land And Ocean Average Temperature Uncertainty'
            ];
            
            foreach ($data as $point) {
                // Pastikan $point juga array dengan struktur yang benar
                if (!is_array($point) || count($point) < 2) {
                    error_log("[getRawData] Point tidak valid: " . json_encode($point));
                    continue;
                }
                
                $timestamp = intval($point[0]) / 1000; // Convert back to seconds
                $date = date('Y-m-d', $timestamp);
                
                $row = [
                    'date' => $date,
                    'timestamp' => $point[0] // Keep original timestamp for lookup
                ];
                
                // Ambil data dari semua time series untuk timestamp yang sama
                foreach ($timeSeries as $key => $label) {
                    $row[$key] = $this->getValueAtTimestamp($key, $point[0]);
                }
                
                // Remove timestamp from final result
                unset($row['timestamp']);
                $result[] = $row;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("[getRawData] Exception: " . $e->getMessage());
            return [];
        }
    }
    
    public function getYearlyData() {
        if (!$this->connectionStatus['success']) {
            return [];
        }
        
        try {
            // Cek apakah yearly compaction keys ada
            $yearlyKeyExists = $this->checkKeyExists('land_avg_temp_yearly_avg');
            
            if (!$yearlyKeyExists) {
                error_log("[getYearlyData] Yearly compaction keys don't exist yet. Creating manual yearly aggregation...");
                return $this->generateManualYearlyData();
            }
            
            $result = [];
            $data = $this->redis->executeRaw(['TS.RANGE', 'land_avg_temp_yearly_avg', '-', '+']);
            
            // Handle case where data might be error string
            if (is_string($data) || !is_array($data)) {
                error_log("[getYearlyData] No yearly data available or error: " . json_encode($data));
                // Fallback to manual aggregation
                return $this->generateManualYearlyData();
            }
            
            foreach ($data as $point) {
                if (!is_array($point) || count($point) < 2) {
                    continue;
                }
                
                $timestamp = $point[0] / 1000;
                $year = date('Y', $timestamp);
                
                $row = ['year' => $year];
                
                $timeSeries = [
                    'land_avg_temp' => 'Land Average Temperature',
                    'land_max_temp' => 'Land Max Temperature',
                    'land_min_temp' => 'Land Min Temperature',
                    'land_ocean_avg_temp' => 'Land And Ocean Average Temperature'
                ];
                
                foreach ($timeSeries as $key => $label) {
                    try {
                        // Ambil data AVG, MAX, MIN untuk tahun ini
                        $avgData = $this->redis->executeRaw(['TS.RANGE', $key . '_yearly_avg', $point[0], $point[0]]);
                        $maxData = $this->redis->executeRaw(['TS.RANGE', $key . '_yearly_max', $point[0], $point[0]]);
                        $minData = $this->redis->executeRaw(['TS.RANGE', $key . '_yearly_min', $point[0], $point[0]]);
                        
                        $row[$key . '_avg'] = (is_array($avgData) && !empty($avgData)) ? round($avgData[0][1], 3) : 'N/A';
                        $row[$key . '_max'] = (is_array($maxData) && !empty($maxData)) ? round($maxData[0][1], 3) : 'N/A';
                        $row[$key . '_min'] = (is_array($minData) && !empty($minData)) ? round($minData[0][1], 3) : 'N/A';
                    } catch (Exception $e) {
                        $row[$key . '_avg'] = 'N/A';
                        $row[$key . '_max'] = 'N/A';
                        $row[$key . '_min'] = 'N/A';
                    }
                }
                
                $result[] = $row;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("[getYearlyData] Exception: " . $e->getMessage());
            // Fallback to manual aggregation
            return $this->generateManualYearlyData();
        }
    }
    
    private function generateManualYearlyData() {
        if (!$this->connectionStatus['success']) {
            return [];
        }
        
        try {
            error_log("[generateManualYearlyData] Generating manual yearly aggregation...");
            
            // Ambil semua data dari time series utama
            $rawData = $this->redis->executeRaw(['TS.RANGE', 'land_avg_temp', '-', '+']);
            
            if (!is_array($rawData) || empty($rawData)) {
                error_log("[generateManualYearlyData] No raw data available");
                return [];
            }
            
            $yearlyStats = [];
            $timeSeries = [
                'land_avg_temp',
                'land_max_temp', 
                'land_min_temp',
                'land_ocean_avg_temp'
            ];
            
            // Process each data point and group by year
            foreach ($rawData as $point) {
                if (!is_array($point) || count($point) < 2) continue;
                
                $timestamp = $point[0] / 1000;
                $year = date('Y', $timestamp);
                
                if (!isset($yearlyStats[$year])) {
                    $yearlyStats[$year] = [];
                    foreach ($timeSeries as $ts) {
                        $yearlyStats[$year][$ts] = ['values' => [], 'sum' => 0, 'count' => 0];
                    }
                }
                
                // Get values for all time series at this timestamp
                foreach ($timeSeries as $ts) {
                    try {
                        $value = $this->getValueAtTimestamp($ts, $point[0]);
                        if ($value !== 'N/A' && is_numeric($value)) {
                            $yearlyStats[$year][$ts]['values'][] = floatval($value);
                            $yearlyStats[$year][$ts]['sum'] += floatval($value);
                            $yearlyStats[$year][$ts]['count']++;
                        }
                    } catch (Exception $e) {
                        error_log("[generateManualYearlyData] Error getting value for $ts: " . $e->getMessage());
                    }
                }
            }
            
            // Calculate averages, min, max for each year
            $result = [];
            foreach ($yearlyStats as $year => $stats) {
                $row = ['year' => $year];
                
                foreach ($timeSeries as $ts) {
                    $values = $stats[$ts]['values'];
                    if (!empty($values)) {
                        $row[$ts . '_avg'] = round($stats[$ts]['sum'] / $stats[$ts]['count'], 3);
                        $row[$ts . '_max'] = round(max($values), 3);
                        $row[$ts . '_min'] = round(min($values), 3);
                    } else {
                        $row[$ts . '_avg'] = 'N/A';
                        $row[$ts . '_max'] = 'N/A';
                        $row[$ts . '_min'] = 'N/A';
                    }
                }
                
                $result[] = $row;
            }
            
            // Sort by year
            usort($result, function($a, $b) {
                return strcmp($a['year'], $b['year']);
            });
            
            error_log("[generateManualYearlyData] Generated " . count($result) . " years of manual aggregation");
            return $result;
            
        } catch (Exception $e) {
            error_log("[generateManualYearlyData] Exception: " . $e->getMessage());
            return [];
        }
    }
}

// Inisialisasi aplikasi
$app = new GlobalTemperatureApp();
$connectionStatus = $app->getConnectionStatus();
$message = '';
$rawData = [];
$yearlyData = [];

// Handle force compaction
if (isset($_GET['force_compaction']) && $connectionStatus['success']) {
    $compactionResult = $app->forceCompaction();
    echo "<div style='background: #e3f2fd; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3>🔄 Force Compaction Result</h3>";
    if ($compactionResult['success']) {
        echo "<p style='color: green;'>" . htmlspecialchars($compactionResult['message']) . "</p>";
        if (isset($compactionResult['details'])) {
            echo "<ul>";
            foreach ($compactionResult['details'] as $detail) {
                echo "<li>" . htmlspecialchars($detail) . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: red;'>" . htmlspecialchars($compactionResult['message']) . "</p>";
    }
    echo "</div>";
}

// Handle debug
if (isset($_GET['debug']) && $connectionStatus['success']) {
    $app->showDebugInfo();
}

// Handle list keys
if (isset($_GET['list_keys']) && $connectionStatus['success']) {
    $keys = $app->listAllKeys();
    echo "<div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3>🔑 Redis Keys</h3>";
    if (empty($keys)) {
        echo "<p>No keys found in Redis. Data belum di-upload.</p>";
    } else {
        echo "<p>Found " . count($keys) . " keys:</p>";
        echo "<ul>";
        foreach ($keys as $key) {
            echo "<li>" . htmlspecialchars($key) . "</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
}

// Handle connection test
if (isset($_GET['test_redis'])) {
    $testResults = $app->testRedisCommands();
}

// Handle upload
if ($_POST && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileName = $_FILES['csv_file']['name'];
        echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
        echo "File yang diupload: " . htmlspecialchars($fileName);
        
        // Debug CSV content first 1000 chars and detect separator
        $content = file_get_contents($_FILES['csv_file']['tmp_name']);
        $lines = explode("\n", $content);
        $firstLine = isset($lines[0]) ? $lines[0] : '';
        $separator = (strpos($firstLine, "\t") !== false) ? "TAB" : "COMMA";
        
        echo "<h4>Debug CSV Content:</h4>";
        echo "<p><strong>Detected separator:</strong> $separator</p>";
        echo "<p><strong>First line:</strong> " . htmlspecialchars($firstLine) . "</p>";
        echo "<pre style='background: white; padding: 10px; border-radius: 3px; font-size: 12px;'>" . htmlspecialchars(substr($content, 0, 1000)) . "</pre>";
        echo "</div>";
        
        $uploadResult = $app->uploadCSV($_FILES['csv_file']['tmp_name']);
        $message = $uploadResult['message'];
        if ($uploadResult['success']) {
            $message .= " ({$uploadResult['count']} records)";
        }
    } else {
        $message = 'Error uploading file.';
    }
}

// Handle data view
$viewType = $_GET['view'] ?? 'raw';
if ($viewType === 'raw') {
    $rawData = $app->getRawData(100);
} else if ($viewType === 'yearly') {
    $yearlyData = $app->getYearlyData();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Land Temperature</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .connection-status {
            background: #e9ecef;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #6c757d;
        }
        .connection-status.success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .connection-status.error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .test-button {
            background: #17a2b8;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        .test-button:hover {
            background: #138496;
        }
        .test-results {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
        }
        .debug-buttons {
            text-align: center;
            margin: 10px 0;
        }
        .upload-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        .upload-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        input[type="file"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #0056b3;
        }
        button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .nav-buttons {
            margin: 20px 0;
            text-align: center;
        }
        .nav-buttons a {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .nav-buttons a.active {
            background: #dc3545;
        }
        .nav-buttons a:hover {
            opacity: 0.8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f0f0;
        }
        .table-container {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌡️ Global Land Temperature</h1>
        
        <!-- Connection Status -->
        <div class="connection-status <?= $connectionStatus['success'] ? 'success' : 'error' ?>">
            <strong>Redis Connection Status:</strong> <?= htmlspecialchars($connectionStatus['message']) ?>
            <a href="?test_redis=1" class="test-button">Test Redis Commands</a>
            
            <?php if (isset($testResults)): ?>
                <div class="test-results">
                    <strong>Redis Test Results:</strong><br>
                    <?php if ($testResults['success']): ?>
                        • PING: <?= htmlspecialchars($testResults['tests']['ping']) ?><br>
                        • TimeSeries: <?= htmlspecialchars($testResults['tests']['timeseries']) ?><br>
                        • Server Info: Available
                    <?php else: ?>
                        Error: <?= htmlspecialchars($testResults['message']) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Debug Buttons -->
        <?php if ($connectionStatus['success']): ?>
            <div class="debug-buttons">
                <a href="?debug=1" class="test-button">🔍 Debug Redis TimeSeries</a>
                <a href="?list_keys=1" class="test-button">🔑 List All Keys</a>
                <a href="?force_compaction=1" class="test-button">🔄 Force Compaction</a>
                <a href="?" class="test-button">✨ Clear Debug</a>
            </div>
        <?php endif; ?>
        
        <!-- Upload Section dengan AJAX -->
<div class="upload-section">
    <h3>Upload CSV File</h3>
    <form id="uploadForm" class="upload-form">
        <input type="file" id="csvFile" name="csv_file" accept=".csv,.tsv" required <?= !$connectionStatus['success'] ? 'disabled' : '' ?>>
        <button type="submit" id="uploadBtn" <?= !$connectionStatus['success'] ? 'disabled' : '' ?>>
            <span id="uploadText">Upload</span>
            <span id="uploadSpinner" style="display: none;">⏳ Uploading...</span>
        </button>
        <?php if (!$connectionStatus['success']): ?>
            <small style="color: #dc3545;">Upload disabled - No Redis connection</small>
        <?php endif; ?>
    </form>
    
    <!-- Progress Bar -->
    <div id="progressContainer" style="display: none; margin-top: 15px;">
        <div style="background: #e9ecef; border-radius: 4px; overflow: hidden;">
            <div id="progressBar" style="background: #007bff; height: 8px; width: 0%; transition: width 0.3s;"></div>
        </div>
        <small id="progressText" style="color: #666; font-size: 11px;">Preparing upload...</small>
    </div>
    
    <!-- Upload Result -->
    <div id="uploadResult" style="margin-top: 15px;"></div>
    
    <!-- CSV Debug Preview -->
    <div id="csvDebug" style="margin-top: 15px;"></div>
</div>
        
        <!-- Navigation -->
        <div class="nav-buttons">
            <a href="?view=raw" class="<?= $viewType === 'raw' ? 'active' : '' ?>">Raw Data</a>
            <a href="?view=yearly" class="<?= $viewType === 'yearly' ? 'active' : '' ?>">Yearly Compaction</a>
        </div>
        
        <!-- Raw Data Table -->
        <?php if ($viewType === 'raw'): ?>
            <h3>📊 Raw Data (Latest 100 records)</h3>
            <?php if (empty($rawData)): ?>
                <div class="no-data">
                    <?php if (!$connectionStatus['success']): ?>
                        Redis connection required to display data.
                    <?php else: ?>
                        Tidak ada data. Silakan upload file CSV terlebih dahulu.<br>
                        <small>Pastikan CSV file berisi data dengan format yang benar dan TimeSeries module aktif.</small>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p><strong>Menampilkan <?= count($rawData) ?> record data mentah</strong></p>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Land Avg Temp</th>
                                <th>Land Avg Temp Uncertainty</th>
                                <th>Land Max Temp</th>
                                <th>Land Max Temp Uncertainty</th>
                                <th>Land Min Temp</th>
                                <th>Land Min Temp Uncertainty</th>
                                <th>Land Ocean Avg Temp</th>
                                <th>Land Ocean Avg Temp Uncertainty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rawData as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['date']) ?></td>
                                    <td><?= $row['land_avg_temp'] ?></td>
                                    <td><?= $row['land_avg_temp_uncertainty'] ?></td>
                                    <td><?= $row['land_max_temp'] ?></td>
                                    <td><?= $row['land_max_temp_uncertainty'] ?></td>
                                    <td><?= $row['land_min_temp'] ?></td>
                                    <td><?= $row['land_min_temp_uncertainty'] ?></td>
                                    <td><?= $row['land_ocean_avg_temp'] ?></td>
                                    <td><?= $row['land_ocean_avg_temp_uncertainty'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Yearly Compaction Data Table -->
        <?php if ($viewType === 'yearly'): ?>
            <h3>📈 Yearly Compaction Data</h3>
            <?php if (empty($yearlyData)): ?>
                <div class="no-data">
                    <?php if (!$connectionStatus['success']): ?>
                        Redis connection required to display data.
                    <?php else: ?>
                        Tidak ada data kompaksi tahunan.<br>
                        <small>
                            <strong>Pilihan untuk menampilkan data tahunan:</strong><br>
                            1. <strong>Otomatis:</strong> Data kompaksi dibuat otomatis setelah upload (mungkin perlu waktu)<br>
                            2. <strong>Manual:</strong> Aplikasi akan menghitung agregasi tahunan secara real-time dari raw data<br>
                            3. <strong>Force:</strong> <a href="?force_compaction=1" style="color: #007bff;">Klik di sini untuk memaksa pembuatan compaction rules</a>
                        </small>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p><strong>Menampilkan <?= count($yearlyData) ?> tahun data kompaksi</strong></p>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2">Year</th>
                                <th colspan="3">Land Avg Temperature</th>
                                <th colspan="3">Land Max Temperature</th>
                                <th colspan="3">Land Min Temperature</th>
                                <th colspan="3">Land Ocean Avg Temperature</th>
                            </tr>
                            <tr>
                                <th>Avg</th>
                                <th>Max</th>
                                <th>Min</th>
                                <th>Avg</th>
                                <th>Max</th>
                                <th>Min</th>
                                <th>Avg</th>
                                <th>Max</th>
                                <th>Min</th>
                                <th>Avg</th>
                                <th>Max</th>
                                <th>Min</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($yearlyData as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['year']) ?></strong></td>
                                    <td><?= $row['land_avg_temp_avg'] ?></td>
                                    <td><?= $row['land_avg_temp_max'] ?></td>
                                    <td><?= $row['land_avg_temp_min'] ?></td>
                                    <td><?= $row['land_max_temp_avg'] ?></td>
                                    <td><?= $row['land_max_temp_max'] ?></td>
                                    <td><?= $row['land_max_temp_min'] ?></td>
                                    <td><?= $row['land_min_temp_avg'] ?></td>
                                    <td><?= $row['land_min_temp_max'] ?></td>
                                    <td><?= $row['land_min_temp_min'] ?></td>
                                    <td><?= $row['land_ocean_avg_temp_avg'] ?></td>
                                    <td><?= $row['land_ocean_avg_temp_max'] ?></td>
                                    <td><?= $row['land_ocean_avg_temp_min'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Footer Information -->
        <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 6px; font-size: 12px; color: #666;">
            <h4>💡 Tips Troubleshooting:</h4>
            <ul style="margin: 0; padding-left: 20px;">
                <li><strong>Jika tidak ada data:</strong> Pastikan file CSV berisi data valid dan Redis TimeSeries module aktif</li>
                <li><strong>Jika error saat upload:</strong> Cek format tanggal di CSV dan pastikan semua kolom ada (minimal 9 kolom)</li>
                <li><strong>Jika data kompaksi kosong:</strong> Aplikasi akan otomatis fallback ke manual aggregation</li>
                <li><strong>Debug tools:</strong> Gunakan tombol "Debug" dan "List Keys" untuk memeriksa status Redis</li>
            </ul>
            <p style="margin-top: 10px;"><strong>Format CSV yang diharapkan:</strong></p>
            <p style="font-size: 11px;">
                • <strong>Separator:</strong> Tab-delimited (TSV) atau Comma-delimited (CSV)<br>
                • <strong>Headers:</strong> dt, LandAverageTemperature, LandAverageTemperatureUncertainty, LandMaxTemperature, LandMaxTemperatureUncertainty, LandMinTemperature, LandMinTemperatureUncertainty, LandAndOceanAverageTemperature, LandAndOceanAverageTemperatureUncertainty<br>
                • <strong>Date format:</strong> M/d/yyyy (contoh: 1/1/1970) atau format tanggal standar lainnya<br>
                • <strong>Values:</strong> Angka desimal, kosong, atau N/A untuk missing data
            </p>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('uploadForm');
    const fileInput = document.getElementById('csvFile');
    const uploadBtn = document.getElementById('uploadBtn');
    const uploadText = document.getElementById('uploadText');
    const uploadSpinner = document.getElementById('uploadSpinner');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const uploadResult = document.getElementById('uploadResult');
    const csvDebug = document.getElementById('csvDebug');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const file = fileInput.files[0];
        if (!file) {
            showMessage('Please select a file', 'error');
            return;
        }

        // Reset UI
        clearMessages();
        showProgress();
        setUploading(true);
        previewCSV(file);
    });

    function previewCSV(file) {
        return new Promise((resolve) => {
            updateProgress(20, 'Reading CSV file...');
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const content = e.target.result;
                const lines = content.split('\n');
                const firstLine = lines[0] || '';
                const separator = firstLine.includes('\t') ? 'TAB' : 'COMMA';
                
                // Show debug info
                csvDebug.innerHTML = `
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 5px;">
                        <h4>📊 CSV Debug Info</h4>
                        <p><strong>File:</strong> ${file.name} (${formatFileSize(file.size)})</p>
                        <p><strong>Detected separator:</strong> ${separator}</p>
                        <p><strong>First line:</strong> <code>${escapeHtml(firstLine.substring(0, 100))}${firstLine.length > 100 ? '...' : ''}</code></p>
                        <p><strong>Total lines:</strong> ~${lines.length}</p>
                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer; color: #007bff;">Preview content (first 500 chars)</summary>
                            <pre style="background: white; padding: 10px; border-radius: 3px; font-size: 11px; margin-top: 5px; white-space: pre-wrap;">${escapeHtml(content.substring(0, 500))}${content.length > 500 ? '\n...' : ''}</pre>
                        </details>
                    </div>
                `;
                
                updateProgress(40, 'CSV preview ready, starting upload...');
                setTimeout(resolve, 500); // Small delay for better UX
            };
            reader.readAsText(file);
        });
    }

    function showProgress() {
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
    }

    function hideProgress() {
        progressContainer.style.display = 'none';
    }

    function updateProgress(percent, text) {
        progressBar.style.width = percent + '%';
        progressText.textContent = text;
    }

    function setUploading(uploading) {
        uploadBtn.disabled = uploading;
        fileInput.disabled = uploading;
        uploadText.style.display = uploading ? 'none' : 'inline';
        uploadSpinner.style.display = uploading ? 'inline' : 'none';
    }

    function showMessage(message, type) {
        uploadResult.innerHTML = `
            <div class="message ${type}" style="padding: 10px; border-radius: 4px; ${type === 'success' ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'}">
                ${escapeHtml(message)}
            </div>
        `;
    }

    function clearMessages() {
        uploadResult.innerHTML = '';
        csvDebug.innerHTML = '';
    }

    function refreshKeysList() {
        // Auto-click List Keys button if available
        const listKeysBtn = document.querySelector('a[href*="list_keys"]');
        if (listKeysBtn) {
            // Create a subtle indicator that keys are being refreshed
            const indicator = document.createElement('div');
            indicator.innerHTML = '<small style="color: #28a745;">🔄 Refreshing keys list...</small>';
            uploadResult.appendChild(indicator);
            
            setTimeout(() => {
                window.location.href = listKeysBtn.href;
            }, 1000);
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
</body>
</html>