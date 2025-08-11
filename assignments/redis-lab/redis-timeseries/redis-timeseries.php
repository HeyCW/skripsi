<?php
require_once __DIR__ . '/vendor/autoload.php';

$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => '13.57.221.183',
    'port'   => 6379,
    'timeout'=> 5
]);

function uploadCsv($file) {
    global $redis;
    
    $file_tmp = $file; // atau $_FILES['file']['tmp_name'] jika dari upload
    $handle = fopen($file_tmp, 'r');
    $label = basename($file); // atau $_FILES['file']['name']
    $label_compacted = $label.'_compacted';
    
    if ($handle !== false) {
        // Read the first row to use as headers
        $headers = fgetcsv($handle, 0, ',');
        print_r($headers);
        
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
                // For average
                $redis->executeRaw(['TS.CREATERULE', $h, $h.'_compacted', 'AGGREGATION', 'avg', 31556952000]); 
            } else if(str_contains($h, 'Max')){
                // For maximum  
                $redis->executeRaw(['TS.CREATERULE', $h, $h.'_compacted', 'AGGREGATION', 'max', 31556952000]);
            } else if(str_contains($h, 'Min')){
                // For minimum
                $redis->executeRaw(['TS.CREATERULE', $h, $h.'_compacted', 'AGGREGATION', 'min', 31556952000]);
            } else if(str_contains($h, 'Sum')){
                // For sum
                $redis->executeRaw(['TS.CREATERULE', $h, $h.'_compacted', 'AGGREGATION', 'sum', 31556952000]);
            } else {
                // Default as average
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
        
        // Process reading data from TimeSeries
        $data = [];
        $dataCompact = [];
        $dataGraph = [];
        $dataCompactGraph = [];
        
        foreach($headers as $h){
            if($h == 'dt') continue;
            
            // Ambil data raw
            $temp = $redis->executeRaw(['TS.RANGE', $h, '-', '+']);
            for($i = 0; $i < count($temp); $i++){
                $dataGraph[$h][] = [gmdate('d-m-Y', ($temp[$i][0]/1000)), floatval($temp[$i][1])];
                if(!isset($data[$i])) $data[$i][] = gmdate('d-m-Y', ($temp[$i][0]/1000));
                $data[$i][] = floatval($temp[$i][1]);
            }
            
            // Ambil data compacted
            try {
                $tempCompact = $redis->executeRaw(['TS.RANGE', $h.'_compacted', '-', '+']);
                for($j = 0; $j < count($tempCompact); $j++){
                    $dataCompactGraph[$h.'_compacted'][] = [gmdate('d-m-Y', ($tempCompact[$j][0]/1000)), floatval($tempCompact[$j][1])];
                    if(!isset($dataCompact[$j])) $dataCompact[$j][] = gmdate('d-m-Y', ($tempCompact[$j][0]/1000));
                    $dataCompact[$j][] = floatval($tempCompact[$j][1]);
                }
            } catch (Exception $e) {
                echo "Warning: Compacted data untuk $h belum tersedia: " . $e->getMessage() . "\n";
            }
        }
    } else {
        throw new Exception('Could not open CSV file');
    }
}

function getDataFromRedis() {
    global $redis;
    $data = [];
    $allKeys = [];
    
    // Ambil semua keys terlebih dahulu
    $keys = $redis->keys('*');
    
    // Kumpulkan semua data dari Redis
    $rawData = [];
    foreach ($keys as $key) {
        $temp = $redis->executeRaw(['TS.RANGE', $key, '-', '+']);
        if ($temp === false) {
            continue;
        }
        
        $allKeys[] = $key; // Simpan key untuk header kolom
        
        for ($i = 0; $i < count($temp); $i++) {
            $timestamp = $temp[$i][0];
            $value = floatval($temp[$i][1]);
            $date = gmdate('Y-m-d', ($timestamp / 1000));
            
            // Struktur: $rawData[tanggal][key] = value
            $rawData[$date][$key] = $value;
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
    
    // Return sebagai JSON
    echo json_encode($tableData);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Upload logic sama
    $file = $_FILES['csv_file']['tmp_name'];
    if (is_uploaded_file($file)) {
        try {
            uploadCsv($file);
            echo 'CSV uploaded successfully';
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getDataFromRedis();
    exit;
}
?>