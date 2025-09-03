<?php
/**
 * Manual Redis Rules Checker
 * Script untuk mengecek rules aggregation secara manual
 */

require_once __DIR__ . '/vendor/autoload.php';

try {
    $redis = new Predis\Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
        'timeout'=> 5
    ]);
    $redis->ping();
    echo "✅ Connected to Redis\n\n";
} catch (Exception $e) {
    echo "❌ Redis connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🔍 Checking Redis TimeSeries Rules...\n\n";

// Get all keys
$allKeys = $redis->keys('*');
$rawKeys = array_filter($allKeys, function($key) {
    return strpos($key, '_compacted') === false;
});

sort($rawKeys);

foreach ($rawKeys as $key) {
    echo "=== Checking Key: {$key} ===\n";
    
    try {
        // Get TS.INFO for this key
        $info = $redis->executeRaw(['TS.INFO', $key]);
        
        echo "Full TS.INFO output:\n";
        for ($i = 0; $i < count($info); $i += 2) {
            if (isset($info[$i+1])) {
                $property = $info[$i];
                $value = $info[$i+1];
                
                if ($property === 'rules') {
                    echo "  {$property}: \n";
                    if (is_array($value) && count($value) > 0) {
                        foreach ($value as $ruleIndex => $rule) {
                            echo "    Rule #{$ruleIndex}: ";
                            if (is_array($rule)) {
                                echo "Target: {$rule[0]}, Aggregation: {$rule[1]}, Bucket: {$rule[2]}ms\n";
                            } else {
                                echo json_encode($rule) . "\n";
                            }
                        }
                    } else {
                        echo "    No rules found\n";
                    }
                } else {
                    if (is_array($value)) {
                        echo "  {$property}: " . json_encode($value) . "\n";
                    } else {
                        echo "  {$property}: {$value}\n";
                    }
                }
            }
        }
        
        // Expected aggregation based on key name
        $expectedAgg = 'avg'; // default
        if (strpos($key, 'Average') !== false) {
            $expectedAgg = 'avg';
        } elseif (strpos($key, 'Max') !== false) {
            $expectedAgg = 'max';
        } elseif (strpos($key, 'Min') !== false) {
            $expectedAgg = 'min';
        } elseif (strpos($key, 'Sum') !== false) {
            $expectedAgg = 'sum';
        }
        
        echo "\nExpected aggregation for '{$key}': {$expectedAgg}\n";
        
        // Check if rule matches expectation
        for ($i = 0; $i < count($info); $i++) {
            if ($info[$i] === 'rules' && isset($info[$i+1])) {
                $rules = $info[$i+1];
                if (is_array($rules) && count($rules) > 0) {
                    foreach ($rules as $rule) {
                        if (is_array($rule) && count($rule) >= 2) {
                            print_r($rule);
                            $actualAgg = strtolower($rule[2]); 
                            if ($actualAgg === $expectedAgg) {
                                echo "✅ Rule is CORRECT: {$actualAgg}\n";
                            } else {
                                echo "❌ Rule is WRONG: expected {$expectedAgg}, got {$actualAgg}\n";
                            }
                            break;
                        }
                    }
                } else {
                    echo "❌ No aggregation rules found\n";
                }
                break;
            }
        }
        
        echo "\n" . str_repeat('-', 50) . "\n\n";
        
    } catch (Exception $e) {
        echo "❌ Error checking {$key}: " . $e->getMessage() . "\n\n";
    }
}

// Summary
echo "📋 SUMMARY:\n";
echo "Total keys checked: " . count($rawKeys) . "\n";
echo "Keys: " . implode(', ', $rawKeys) . "\n\n";

echo "🔧 Manual Commands to Check:\n";
foreach ($rawKeys as $key) {
    echo "redis-cli TS.INFO {$key}\n";
}

echo "\n💡 If rules are wrong, your PHP code might have issues in:\n";
echo "   • String pattern matching (str_contains function)\n";
echo "   • TS.CREATERULE command syntax\n";
echo "   • Column name parsing from CSV header\n";
?>