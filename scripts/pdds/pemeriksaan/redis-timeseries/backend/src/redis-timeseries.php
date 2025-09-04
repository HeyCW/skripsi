<?php
require_once __DIR__ . '/vendor/autoload.php';

$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379,
    'timeout'=> 5
]);

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
            
            // Deleting the existing Time Series
            try {
                $redis->executeRaw(['DEL', $h]);
                $redis->executeRaw(['DEL', $h.'_compacted']);
            } catch (Exception $e) {
                // Ignore jika key tidak ada
            }
            
            // Create new Time Series with the same Name
            $redis->executeRaw(['TS.CREATE', $h, 'LABELS', 'data', $label]);
            
            // Create the compaction time series
            $redis->executeRaw(['TS.CREATE', $h.'_compacted', 'LABELS', 'data', $label_compacted]);
            
            // Create the rule berdasarkan nama kolom
            if(str_contains($h, 'Average')){
                $redis->executeRaw(['TS.CREATERULE', $h, $h.'_compacted', 'AGGREGATION', 'avg', 31556952000]); 
            } else if(str_contains($h, 'Max')){
                $redis->executeRaw(['TS.CREATERULE', $h, $h.'_compacted', 'AGGREGATION', 'max', 31556952000]);
            } else if(str_contains($h, 'Min')){
                $redis->executeRaw(['TS.CREATERULE', $h, $h.'_compacted', 'AGGREGATION', 'min', 31556952000]);
            } else if(str_contains($h, 'Sum')){
                $redis->executeRaw(['TS.CREATERULE', $h, $h.'_compacted', 'AGGREGATION', 'sum', 31556952000]);
            } else {
                $redis->executeRaw(['TS.CREATERULE', $h, $h.'_compacted', 'AGGREGATION', 'avg', 31556952000]);
            }
        }
        
        // Process the remaining rows
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            // Convert the first column to a timestamp with epoch time
            $timestamp = strtotime($data[0]) * 1000;
            
            // Jika format tanggal berbeda (MM/DD/YYYY seperti di contoh)
            if (strpos($data[0], '/') !== false) {
                list($month, $day, $year) = explode('/', $data[0]);
                date_default_timezone_set("UTC");
                $timestamp = strtotime($year.'-'.$month.'-'.$day.' 00:00:00') * 1000;
            }
            
            // Insert the data into TimeSeries
            for($i = 1; $i < count($data); $i++){
                if (!empty($data[$i]) && is_numeric($data[$i])) {
                    $redis->executeRaw(['TS.ADD', $headers[$i], $timestamp, $data[$i]]);
                }
            }
        }
        
        fclose($handle);
        
        // Beri waktu untuk compaction berjalan
        sleep(2);
        
        return true; // Return success
        
    } else {
        throw new Exception('Could not open CSV file');
    }
}

function getDataFromRedis($filterCompacted = false) {
    global $redis;
    $data = [];
    $allKeys = [];
    
    // Ambil semua keys terlebih dahulu
    $keys = $redis->keys('*');
    
    // Filter keys berdasarkan parameter
    if ($filterCompacted) {
        // Hanya ambil keys yang bukan compacted (tidak mengandung '_compacted')
        $keys = array_filter($keys, function($key) {
            return strpos($key, '_compacted') === false;
        });
    }
    
    // Kumpulkan semua data dari Redis
    $rawData = [];
    foreach ($keys as $key) {
        try {
            $temp = $redis->executeRaw(['TS.RANGE', $key, '-', '+']);
            
            // Cek apakah $temp adalah array yang valid
            if (!is_array($temp) || empty($temp)) {
                continue; // Skip jika bukan array atau kosong
            }
            
            $allKeys[] = $key; // Simpan key untuk header kolom
            
            // Pastikan $temp adalah array sebelum di-count
            for ($i = 0; $i < count($temp); $i++) {
                // Validasi struktur data
                if (!is_array($temp[$i]) || count($temp[$i]) < 2) {
                    continue; // Skip jika struktur tidak valid
                }
                
                $timestamp = $temp[$i][0];
                $value = floatval($temp[$i][1]);
                $date = gmdate('Y-m-d', ($timestamp / 1000));
                
                // Struktur: $rawData[tanggal][key] = value
                $rawData[$date][$key] = $value;
            }
            
        } catch (Exception $e) {
            // Skip key yang bermasalah
            error_log("Error processing key '$key': " . $e->getMessage());
            continue;
        }
    }
    
    // Sorting tanggal untuk urutan yang konsisten
    ksort($rawData);
    
    // Restructure data untuk tabel
    $tableData = [];
    
    foreach ($rawData as $date => $keyValues) {
        $row = ['date' => $date]; // Kolom pertama adalah tanggal
        
        // Tambahkan value untuk setiap key, atau null jika tidak ada
        foreach ($allKeys as $key) {
            $row[$key] = isset($keyValues[$key]) ? $keyValues[$key] : null;
        }
        
        $tableData[] = $row;
    }
    
    return $tableData;
}

// Set content type to JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Upload logic
    $file = $_FILES['csv_file']['tmp_name'];
    if (is_uploaded_file($file)) {
        try {
            uploadCsv($file);
            
            // PERUBAHAN UTAMA: Setelah upload berhasil, langsung return data
            $data = getDataFromRedis(true); // true = filter compacted keys (raw data only)
            echo json_encode($data);
            
        } catch (Exception $e) {
            // Return error dalam format JSON
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'File upload failed']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET requests
    if (isset($_GET['raw']) && $_GET['raw'] === 'true') {
        // Return raw data (non-compacted)
        $data = getDataFromRedis(true);
        echo json_encode($data);
    } elseif (isset($_GET['agr']) && $_GET['agr'] === 'true') {
        // Return aggregated data (compacted only)
        $keys = $redis->keys('*_compacted');
        $rawData = [];
        $allKeys = [];
        
        foreach ($keys as $key) {
            try {
                $temp = $redis->executeRaw(['TS.RANGE', $key, '-', '+']);
                
                if (!is_array($temp) || empty($temp)) {
                    continue;
                }
                
                $allKeys[] = $key;
                
                for ($i = 0; $i < count($temp); $i++) {
                    if (!is_array($temp[$i]) || count($temp[$i]) < 2) {
                        continue;
                    }
                    
                    $timestamp = $temp[$i][0];
                    $value = floatval($temp[$i][1]);
                    $date = gmdate('Y-m-d', ($timestamp / 1000));
                    
                    $rawData[$date][$key] = $value;
                }
                
            } catch (Exception $e) {
                continue;
            }
        }
        
        ksort($rawData);
        
        $tableData = [];
        foreach ($rawData as $date => $keyValues) {
            $row = ['date' => $date];
            
            foreach ($allKeys as $key) {
                $row[$key] = isset($keyValues[$key]) ? $keyValues[$key] : null;
            }
            
            $tableData[] = $row;
        }
        
        echo json_encode($tableData);
    } else {
        // Return all data (default)
        $data = getDataFromRedis(true);
        echo json_encode($data);
    }
    exit;
}
?>