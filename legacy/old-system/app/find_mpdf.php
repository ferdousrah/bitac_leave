<?php
/**
 * MPDF FINDER - This will help you locate your manually downloaded mPDF
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>mPDF Finder</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
    .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
    .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
    .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
    pre { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; }
    .path { background: #e9ecef; padding: 5px 10px; border-radius: 3px; font-family: monospace; }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    h2 { color: #34495e; margin-top: 30px; }
    .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 5px 10px 0; }
    .btn:hover { background: #2980b9; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    table th { background: #34495e; color: white; padding: 10px; text-align: left; }
    table td { padding: 10px; border-bottom: 1px solid #ddd; }
    table tr:hover { background: #f8f9fa; }
</style></head><body><div class='container'>";

echo "<h1>🔍 mPDF Finder & Tester</h1>";
echo "<p>This tool will help you locate your manually downloaded mPDF and test if it works.</p>";

echo "<hr>";

// Function to search for mpdf.php files
function findMpdfFiles($startPath, $maxDepth = 3, $currentDepth = 0) {
    $found = [];
    
    if ($currentDepth > $maxDepth) {
        return $found;
    }
    
    if (!is_dir($startPath) || !is_readable($startPath)) {
        return $found;
    }
    
    $items = @scandir($startPath);
    if ($items === false) {
        return $found;
    }
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $fullPath = $startPath . '/' . $item;
        
        if (is_file($fullPath) && $item == 'mpdf.php') {
            $found[] = $fullPath;
        } elseif (is_dir($fullPath)) {
            $subFound = findMpdfFiles($fullPath, $maxDepth, $currentDepth + 1);
            $found = array_merge($found, $subFound);
        }
    }
    
    return $found;
}

echo "<h2>Step 1: Searching for mpdf.php files...</h2>";

$searchPaths = [
    __DIR__,
    __DIR__ . '/../',
    dirname(__DIR__),
];

$allFoundFiles = [];
foreach ($searchPaths as $searchPath) {
    $foundFiles = findMpdfFiles($searchPath, 3);
    $allFoundFiles = array_merge($allFoundFiles, $foundFiles);
}

$allFoundFiles = array_unique($allFoundFiles);

if (count($allFoundFiles) > 0) {
    echo "<div class='success'><strong>✓ Found " . count($allFoundFiles) . " mpdf.php file(s):</strong></div>";
    echo "<table>";
    echo "<tr><th>#</th><th>Path</th><th>Size</th><th>Action</th></tr>";
    
    foreach ($allFoundFiles as $index => $file) {
        $fileSize = filesize($file);
        $fileSizeKB = round($fileSize / 1024, 2);
        echo "<tr>";
        echo "<td>" . ($index + 1) . "</td>";
        echo "<td class='path'>" . htmlspecialchars($file) . "</td>";
        echo "<td>" . $fileSizeKB . " KB</td>";
        echo "<td><a href='?test=" . urlencode($file) . "' class='btn'>Test This</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'><strong>✗ No mpdf.php files found</strong></div>";
    echo "<p>Searched in:</p><ul>";
    foreach ($searchPaths as $path) {
        echo "<li>" . htmlspecialchars(realpath($path)) . "</li>";
    }
    echo "</ul>";
}

// Check common locations manually
echo "<h2>Step 2: Checking Common Locations</h2>";

$commonPaths = [
    __DIR__ . '/mpdf/mpdf.php',
    __DIR__ . '/library/mpdf/mpdf.php',
    __DIR__ . '/includes/mpdf/mpdf.php',
    __DIR__ . '/vendor/mpdf/mpdf/mpdf.php',
    __DIR__ . '/../mpdf/mpdf.php',
    __DIR__ . '/mpdf-master/mpdf.php',
    __DIR__ . '/mpdf-8.0/mpdf.php',
    __DIR__ . '/mpdf-8.1/mpdf.php',
];

echo "<table>";
echo "<tr><th>Path</th><th>Status</th></tr>";

foreach ($commonPaths as $path) {
    $exists = file_exists($path);
    $status = $exists ? "<span style='color: green;'>✓ EXISTS</span>" : "<span style='color: #999;'>Not found</span>";
    echo "<tr><td class='path'>" . htmlspecialchars($path) . "</td><td>$status</td></tr>";
}
echo "</table>";

// If test parameter is provided, test that file
if (isset($_GET['test'])) {
    $testFile = $_GET['test'];
    
    echo "<hr><h2>Step 3: Testing mPDF from: " . htmlspecialchars($testFile) . "</h2>";
    
    if (file_exists($testFile)) {
        try {
            require_once($testFile);
            echo "<div class='success'>✓ Successfully loaded mpdf.php</div>";
            
            // Check if mPDF class exists
            if (class_exists('mPDF')) {
                echo "<div class='success'>✓ mPDF class found (old version format)</div>";
                
                try {
                    $mpdf = new mPDF('utf-8', 'A4');
                    echo "<div class='success'>✓ mPDF object created successfully!</div>";
                    
                    $mpdf->WriteHTML('<h1>Test PDF</h1><p>This is a test. বাংলা টেক্সট test</p>');
                    echo "<div class='success'>✓ HTML written successfully</div>";
                    
                    echo "<div class='success'>";
                    echo "<h3>✅ SUCCESS! Your mPDF is working!</h3>";
                    echo "<p><strong>Use this in your PHP file:</strong></p>";
                    echo "<pre>";
                    echo htmlspecialchars("require_once('" . $testFile . "');\n");
                    echo "\$mpdf = new mPDF('utf-8', 'A4');\n";
                    echo "\$mpdf->WriteHTML(\$html);\n";
                    echo "\$mpdf->Output('filename.pdf', 'I');";
                    echo "</pre>";
                    echo "<p><a href='?createpdf=" . urlencode($testFile) . "' class='btn'>Generate Sample PDF</a></p>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='error'>✗ Error creating mPDF object: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                
            } elseif (class_exists('\Mpdf\Mpdf')) {
                echo "<div class='success'>✓ Mpdf class found (new namespace format)</div>";
                
                try {
                    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
                    echo "<div class='success'>✓ Mpdf object created successfully!</div>";
                    
                    $mpdf->WriteHTML('<h1>Test PDF</h1><p>This is a test. বাংলা টেক্সট test</p>');
                    echo "<div class='success'>✓ HTML written successfully</div>";
                    
                    echo "<div class='success'>";
                    echo "<h3>✅ SUCCESS! Your mPDF is working!</h3>";
                    echo "<p><strong>Use this in your PHP file:</strong></p>";
                    echo "<pre>";
                    echo htmlspecialchars("require_once('" . $testFile . "');\n");
                    echo "\$mpdf = new \\Mpdf\\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);\n";
                    echo "\$mpdf->WriteHTML(\$html);\n";
                    echo "\$mpdf->Output('filename.pdf', 'I');";
                    echo "</pre>";
                    echo "<p><a href='?createpdf=" . urlencode($testFile) . "' class='btn'>Generate Sample PDF</a></p>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='error'>✗ Error creating Mpdf object: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            } else {
                echo "<div class='error'>✗ Neither mPDF nor Mpdf class found after loading the file</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error loading mpdf.php: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='error'>✗ File does not exist: " . htmlspecialchars($testFile) . "</div>";
    }
}

// Generate sample PDF
if (isset($_GET['createpdf'])) {
    $mpdfPath = $_GET['createpdf'];
    
    if (file_exists($mpdfPath)) {
        require_once($mpdfPath);
        
        // Try old format first
        if (class_exists('mPDF')) {
            $mpdf = new mPDF('utf-8', 'A4');
        } elseif (class_exists('\Mpdf\Mpdf')) {
            $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
        } else {
            die("mPDF class not found");
        }
        
        $html = '
        <h1>Test PDF Generated Successfully!</h1>
        <p>This PDF was generated using mPDF from: ' . htmlspecialchars($mpdfPath) . '</p>
        <h2>Bengali Text Test:</h2>
        <p style="font-size: 16px;">আসসালামু আলাইকুম</p>
        <p style="font-size: 16px;">এটি একটি পরীক্ষা পিডিএফ</p>
        <h2>English Text:</h2>
        <p>If you can see this PDF properly with Bengali text, your mPDF is working correctly!</p>
        <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>
        ';
        
        $mpdf->WriteHTML($html);
        $mpdf->Output('test_mpdf.pdf', 'I');
        exit;
    }
}

echo "<hr>";
echo "<h2>Next Steps</h2>";

if (count($allFoundFiles) > 0) {
    echo "<div class='info'>";
    echo "<p><strong>What to do now:</strong></p>";
    echo "<ol>";
    echo "<li>Click 'Test This' button next to one of the found mpdf.php files above</li>";
    echo "<li>If it works, copy the code shown and use it in your leave application PDF file</li>";
    echo "<li>If it doesn't work, try another mpdf.php file</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='warning'>";
    echo "<p><strong>No mPDF found. Here's what you can do:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Download mPDF manually:</strong>";
    echo "<pre>cd " . __DIR__ . "\nwget https://github.com/mpdf/mpdf/archive/refs/heads/development.zip\nunzip development.zip\nmv mpdf-development mpdf</pre>";
    echo "</li>";
    echo "<li><strong>Or install via Composer (recommended):</strong>";
    echo "<pre>composer require mpdf/mpdf</pre>";
    echo "</li>";
    echo "<li><strong>Or tell me where you put your mPDF folder</strong> and I'll create the correct code for you</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Current Directory:</strong> <span class='path'>" . __DIR__ . "</span></p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

echo "</div></body></html>";
?>