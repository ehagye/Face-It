<?php
ob_start();
require 'config.php';
ob_end_clean();

echo "<pre style='background: #1e293b; color: #e2e8f0; padding: 20px; font-family: monospace;'>";

/**
 * Fetch and display all professors
 */
function check_professors($config) {
    echo "=== PROFESSORS TABLE ===\n";
    $url = $config['SUPABASE_URL'] . "/rest/v1/professors?select=*";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['SUPABASE_KEY']}",
            "apikey: {$config['SUPABASE_KEY']}"
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n\n";
    
    if ($http_code === 200) {
        $professors = json_decode($response, true);
        if (empty($professors)) {
            echo "❌ No professors found\n";
        } else {
            echo "✓ Found " . count($professors) . " professor(s):\n\n";
            foreach ($professors as $prof) {
                echo "  ID: {$prof['professor_id']}\n";
                echo "  Name: {$prof['first_name']} {$prof['last_name']}\n";
                echo "  Email: {$prof['email']}\n";
                echo "\n";
            }
        }
    } else {
        echo "❌ Query failed\n";
        echo "Response: " . substr($response, 0, 200) . "\n";
    }
    echo "\n";
}

/**
 * Fetch and display all classes
 */
function check_classes($config) {
    echo "=== CLASSES TABLE ===\n";
    $url = $config['SUPABASE_URL'] . "/rest/v1/classes?select=*";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['SUPABASE_KEY']}",
            "apikey: {$config['SUPABASE_KEY']}"
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n\n";
    
    if ($http_code === 200) {
        $classes = json_decode($response, true);
        if (empty($classes)) {
            echo "❌ No classes found\n";
        } else {
            echo "✓ Found " . count($classes) . " class(es):\n\n";
            foreach ($classes as $class) {
                echo "  ID: {$class['class_id']}\n";
                echo "  Name: {$class['class_name']}\n";
                echo "  Professor ID: {$class['professor_id']}\n";
                echo "  Start Time: {$class['scheduled_start_time']}\n";
                echo "\n";
            }
        }
    } else {
        echo "❌ Query failed\n";
        echo "Response: " . substr($response, 0, 200) . "\n";
    }
    echo "\n";
}

/**
 * Fetch and display all students
 */
function check_students($config) {
    echo "=== STUDENTS TABLE ===\n";
    $url = $config['SUPABASE_URL'] . "/rest/v1/students?select=student_id,first_name,last_name,class_id";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['SUPABASE_KEY']}",
            "apikey: {$config['SUPABASE_KEY']}"
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n\n";
    
    if ($http_code === 200) {
        $students = json_decode($response, true);
        if (empty($students)) {
            echo "❌ No students found\n";
        } else {
            echo "✓ Found " . count($students) . " student(s):\n\n";
            foreach ($students as $student) {
                echo "  ID: {$student['student_id']}\n";
                echo "  Name: {$student['first_name']} {$student['last_name']}\n";
                echo "  Class ID: {$student['class_id']}\n";
                echo "\n";
            }
        }
    } else {
        echo "❌ Query failed\n";
        echo "Response: " . substr($response, 0, 200) . "\n";
    }
    echo "\n";
}

/**
 * Test professor lookup by email
 */
function test_professor_lookup($email, $config) {
    echo "=== TEST: LOOKUP PROFESSOR BY EMAIL ===\n";
    echo "Looking for: $email\n\n";
    
    $url = $config['SUPABASE_URL'] . "/rest/v1/professors?select=professor_id,first_name,last_name,email";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['SUPABASE_KEY']}",
            "apikey: {$config['SUPABASE_KEY']}"
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $professors = json_decode($response, true) ?? [];
        
        $found = null;
        foreach ($professors as $prof) {
            echo "Comparing:\n";
            echo "  Database: '" . $prof['email'] . "' (length: " . strlen($prof['email']) . ")\n";
            echo "  Looking:  '" . $email . "' (length: " . strlen($email) . ")\n";
            
            if (strtolower($prof['email']) === strtolower($email)) {
                $found = $prof;
                echo "  ✓ MATCH!\n\n";
                break;
            } else {
                echo "  ✗ No match\n\n";
            }
        }
        
        if ($found) {
            echo "✓ Professor found:\n";
            echo "  ID: {$found['professor_id']}\n";
            echo "  Name: {$found['first_name']} {$found['last_name']}\n";
            echo "  Email: {$found['email']}\n";
        } else {
            echo "❌ Professor NOT found\n";
        }
    } else {
        echo "❌ Query failed (HTTP $http_code)\n";
    }
    echo "\n";
}

// Run all checks
check_professors($config);
check_classes($config);
check_students($config);
test_professor_lookup('professor@faceit.edu', $config);

echo "</pre>";
?>