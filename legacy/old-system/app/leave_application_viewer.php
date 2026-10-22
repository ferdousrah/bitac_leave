<?php
/**
 * Leave Application PDF Viewer
 * This version displays the PDF in a nice viewer with controls
 */

$leaveApplicationID = isset($_GET['leaveApplicationID']) ? intval($_GET['leaveApplicationID']) : 0;

if ($leaveApplicationID <= 0) {
    die('Invalid leave application ID');
}

// Show the viewer page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Application Viewer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 20px;
            margin: 0;
        }
        
        .controls {
            background: #34495e;
            padding: 10px 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .pdf-container {
            width: 100%;
            height: calc(100vh - 120px);
            border: none;
            background: white;
        }
        
        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📄 Leave Application - ID: <?php echo htmlspecialchars($leaveApplicationID); ?></h1>
    </div>
    
    <div class="controls">
        <a href="javascript:location.reload()" 
           class="btn btn-success">
            🔄 Refresh PDF
        </a>
        <a href="leave_application_pdf_secure.php?leaveApplicationID=<?php echo $leaveApplicationID; ?>" 
           class="btn" 
           target="_blank">
            💾 Download PDF
        </a>
        <a href="javascript:window.print()" class="btn">
            🖨️ Print
        </a>
    </div>
    
    <div class="pdf-container">
        <iframe src="leave_application_pdf_secure.php?leaveApplicationID=<?php echo $leaveApplicationID; ?>#toolbar=1&navpanes=0&scrollbar=1"></iframe>
    </div>
</body>
</html>