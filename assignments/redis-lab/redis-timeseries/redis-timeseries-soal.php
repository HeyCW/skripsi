<?php
// ==========================================
// REDIS TIMESERIES PHP BACKEND - SOAL LATIHAN
// ==========================================

require_once __DIR__ . '/vendor/autoload.php';

// TODO 1: LENGKAPI REDIS CONNECTION CONFIGURATION
// Tugas: Lengkapi konfigurasi koneksi Redis
// HINT: Gunakan class Predis\Client dengan array konfigurasi
// HINT: Set scheme, host, port, dan timeout

// KODE ANDA DI SINI:
$redis = new Predis\Client([
    // LENGKAPI KONFIGURASI REDIS DISINI
    
    
    
]);

// TODO 2: LENGKAPI FUNCTION UPLOADCSV()
// Tugas: Lengkapi function untuk upload CSV dan buat TimeSeries
function uploadCsv($file) {
    global $redis;
    
    $file_tmp = $file;
    $handle = fopen($file_tmp, 'r');
    $label = basename($file);
    $label_compacted = $label.'_compacted';
    
    if ($handle !== false) {
        // Read the first row to use as headers
        $headers = fgetcsv($handle, 0, ',');
        
        foreach($headers as $h){
            if($h == 'dt') continue;
            
            // HINT: Gunakan executeRaw() dengan command 'DEL' untuk hapus existing TimeSeries
            // HINT: Gunakan executeRaw() dengan command 'TS.CREATE' untuk buat TimeSeries baru
            // HINT: Buat juga TimeSeries untuk compacted data dengan suffix '_compacted'
            
            // KODE ANDA DI SINI - LENGKAPI BAGIAN CREATE TIMESERIES:
            try {
                // Deleting existing TimeSeries
                
                
                // Create new TimeSeries
                
                
                // Create compacted TimeSeries
                
                
            } catch (Exception $e) {
                // Ignore jika key tidak ada
            }
            
            // TODO 4: LENGKAPI AGGREGATION RULE
            // HINT: Gunakan executeRaw() dengan 'TS.CREATERULE'
            // HINT: Set AGGREGATION berdasarkan nama kolom (avg, max, min, sum)
            // HINT: Gunakan yearly window: 31536000000 (365 * 24 * 60 * 60 * 1000)
            
            // KODE ANDA DI SINI - LENGKAPI AGGREGATION RULES:
            $yearlyWindow = 31536000000; // 365 hari dalam milliseconds
            
            if(str_contains($h, 'Average')){
                // Create rule untuk average
                
            } else if(str_contains($h, 'Max')){
                // Create rule untuk maximum
                
            } else if(str_contains($h, 'Min')){
                // Create rule untuk minimum
                
            } else if(str_contains($h, 'Sum')){
                // Create rule untuk sum
                
            } else {
                // Default sebagai average
                
            }
        }
        
        // TODO 2B: LENGKAPI PROSES CSV ROWS DAN INSERT KE TIMESERIES
        // Tugas: Baca setiap baris CSV dan insert data ke TimeSeries
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            // HINT: Convert tanggal ke timestamp (milliseconds)
            
            // KODE ANDA DI SINI - CONVERT TIMESTAMP:
            // $timestamp = /* LENGKAPI KONVERSI TIMESTAMP */;
            
            // HINT: Loop melalui data columns (mulai dari index 1)
            
            // KODE ANDA DI SINI - INSERT DATA KE TIMESERIES:
            for($i = 1; $i < count($data); $i++){
                
                
            }
        }
        
        fclose($handle);
        sleep(2); // Tunggu compaction
        return true;
        
    } else {
        throw new Exception('Could not open CSV file');
    }
}

// TODO 3: LENGKAPI FUNCTION GETDATAFROMREDIS()
// Tugas: Lengkapi function untuk ambil data dari Redis TimeSeries
function getDataFromRedis($filterCompacted = false) {
    global $redis;
    
    
    // KODE ANDA DI SINI:
    $allKeys = [];
    $rawData = [];
    
    // Ambil semua keys
    $keys = $redis->keys('*');
    
    // Filter compacted keys jika diperlukan
    if ($filterCompacted) {
        
    }
    
    // Kumpulkan data dari setiap TimeSeries
    foreach ($keys as $key) {
        try {
            // Ambil data dari TimeSeries
            
            
            // Validasi data
            
            
            // Process setiap datapoint
            
            
        } catch (Exception $e) {
            continue;
        }
    }
    
    // Sort dan restructure data
    ksort($rawData);
    
    $tableData = [];
    foreach ($rawData as $date => $keyValues) {
        $row = ['date' => $date];
        
        foreach ($allKeys as $key) {
            $row[$key] = isset($keyValues[$key]) ? $keyValues[$key] : null;
        }
        
        $tableData[] = $row;
    }
    
    return $tableData;
}

// TODO 5: LENGKAPI FUNCTION UNTUK AGGREGATED DATA
// Tugas: Buat function khusus untuk ambil aggregated data
function getAggregatedData() {
    global $redis;
    
    
    // KODE ANDA DI SINI:
    $allKeys = [];
    $rawData = [];
    
    // Ambil hanya compacted keys
    $keys = $redis->keys('*_compacted');
    
    foreach ($keys as $key) {
        try {
            // LENGKAPI: Ambil data compacted dan process
            
            
        } catch (Exception $e) {
            continue;
        }
    }
    
    // Sort dan restructure
    ksort($rawData);
    
    $tableData = [];
    foreach ($rawData as $date => $keyValues) {
        $row = ['date' => $date];
        
        foreach ($allKeys as $key) {
            $row[$key] = isset($keyValues[$key]) ? $keyValues[$key] : null;
        }
        
        $tableData[] = $row;
    }
    
    return $tableData;
}

// Set JSON header
header('Content-Type: application/json');

// TODO 6: LENGKAPI HTTP REQUEST HANDLING
// Tugas: Handle different HTTP methods dan create API endpoints
// KODE ANDA DI SINI - LENGKAPI HTTP REQUEST HANDLING:

// Handle POST request (file upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    
    // HINT: Ambil file dari $_FILES['csv_file']['tmp_name']
    // HINT: Gunakan is_uploaded_file() untuk validasi
    // HINT: Call uploadCsv() function
    // HINT: Return raw data setelah upload dengan getDataFromRedis(true)
    // HINT: Handle exception dengan json_encode(['error' => message])
    
    // $file = /* AMBIL FILE DARI $_FILES */;
    
    
// Handle GET request (data retrieval)    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // HINT: Cek $_GET['raw'] untuk raw data endpoint
    // HINT: Cek $_GET['agr'] untuk aggregated data endpoint  
    // HINT: Default return raw data jika tidak ada parameter
    
    if (isset($_GET['raw']) && $_GET['raw'] === 'true') {
        // Return raw data
        
        
    } elseif (isset($_GET['agr']) && $_GET['agr'] === 'true') {
        // Return aggregated data
        
        
    } else {
        // Default: return raw data
        
        
    }
    
    exit;
    
// Handle unsupported methods
} else {
    // HINT: Return error untuk unsupported HTTP methods
    // HINT: HTTP 405 Method Not Allowed
    
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
