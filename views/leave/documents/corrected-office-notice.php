<?php
/**
 * Joining Office Order Viewer - In-Memory Version
 * Shows official joining approval notice (3 types)
 * 
 * Type 1: Acknowledgment (normal joining)
 * Type 2: Early joining approval
 * Type 3: Extended leave approval
 * 
 * Usage: joining_office_order_viewer.php?leaveApplicationID=5516
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');
set_time_limit(120);

$action = $_GET['action'] ?? 'view';
$leaveApplicationID = isset($_GET['leaveApplicationID']) ? (int)$_GET['leaveApplicationID'] : 0;

if ($leaveApplicationID <= 0) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Error - Missing Parameter</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
            .error-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
            h1 { color: #e53e3e; }
            .info { background: #bee3f8; padding: 15px; border-radius: 4px; margin: 20px 0; }
            .example { background: #f7fafc; padding: 15px; border-radius: 4px; margin: 10px 0; font-family: monospace; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Missing Parameter</h1>
            <p>You need to provide a leave application ID.</p>
            <div class="info">
                <strong>Usage:</strong>
                <div class="example">joining_office_order_viewer.php?leaveApplicationID=<strong>5516</strong></div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'generate') {
    generatePDFData($leaveApplicationID);
} else {
    showViewer($leaveApplicationID);
}

function generatePDFData($leaveApplicationID) {
    try {
        require_once(__DIR__ . '/../../../connection.php');
        require_once(__DIR__ . '/../../../library/number_converter.php');

        // Load mPDF
        $autoload_paths = [
            __DIR__ . '/../../../vendor/autoload.php',
            __DIR__ . '/../../../mpdf/vendor/autoload.php',
            '/home/ferdous/ftp/bitac.technocratsbd.com/vendor/autoload.php',
            '/home/ferdous/ftp/bitac.technocratsbd.com/mpdf/vendor/autoload.php',
        ];
        
        foreach ($autoload_paths as $path) {
            if (file_exists($path)) {
                require_once($path);
                break;
            }
        }
        
        if (!class_exists('\Mpdf\Mpdf')) {
            throw new Exception("mPDF not found");
        }
        
        if (!$con) {
            throw new Exception("Database connection failed");
        }
        
        function dateDiffInDays($date1, $date2) {
            return abs(round((strtotime($date2) - strtotime($date1)) / 86400));
        }
        
        // Get leave application data
        $stmt = $con->prepare("SELECT * FROM leave_applications WHERE dataID = ?");
        $stmt->bind_param("i", $leaveApplicationID);
        $stmt->execute();
        $leaveData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$leaveData) {
            throw new Exception("Leave application not found");
        }
        
        $primaryAppDate = date_create($leaveData['officeNoticeDate']);
        
        // Get joining application data
        $stmt = $con->prepare("SELECT * FROM leave_joining_application WHERE leaveApplicationID = ?");
        $stmt->bind_param("i", $leaveApplicationID);
        $stmt->execute();
        $joiningData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$joiningData) {
            throw new Exception("Joining application not found");
        }
        
        // Get employee data
        $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
        $stmt->bind_param("i", $leaveData['applicantID']);
        $stmt->execute();
        $empData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get designation
        $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
        $stmt->bind_param("i", $empData['designation']);
        $stmt->execute();
        $designationData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get section
        $stmt = $con->prepare("SELECT * FROM sections WHERE id = ?");
        $stmt->bind_param("i", $empData['section_id']);
        $stmt->execute();
        $sectionData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get organization
        $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
        $stmt->bind_param("i", $empData['organization_id']);
        $stmt->execute();
        $orgData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Calculate dates
        $dateDiff = dateDiffInDays($leaveData['primaryLeaveDateFrom'], $leaveData['primaryLeaveDateTo']) + 1;
        $dateF = date_create($leaveData['primaryLeaveDateFrom']);
        $dateT = date_create($leaveData['primaryLeaveDateTo']);
        $officeNoticeDate = date_create($joiningData['approvedDate']);
        
        // Requested dates
        $reqDateDiff = dateDiffInDays($leaveData['primaryLeaveDateFrom'], $joiningData['requestedJoiningDate']) + 1;
        $reqdateF = date_create($leaveData['primaryLeaveDateFrom']);
        $reqdateT = date_create($joiningData['requestedJoiningDate']);
        
        // Approved dates
        $appdateDiff = dateDiffInDays($leaveData['primaryLeaveDateFrom'], $leaveData['approvedDateTo']) + 1;
        $appdateF = date_create($leaveData['primaryLeaveDateFrom']);
        $appdateT = date_create($leaveData['approvedDateTo']);
        
        // Determine approved leave type
        $approvedLeaveType = "";
        $prevApprovedLeaveType = "";
        
        $leaveTypeMap = [
            1 => "গড় বেতন ",
            2 => "অর্ধ-গড় বেতন ",
            3 => "নৈমিত্তিক (Casual Leave)",
            4 => "বিনা বেতনে ছুটি",
            5 => "ঐচ্ছিক (Optional Leave)",
            6 => "সংগনিরোধ ছুটি",
            7 => "প্রসূতি ছুটি",
            8 => "অক্ষমতাজনিত বিশেষ ছুটি",
            9 => "অধ্যয়ন ছুটি",
            10 => "অসাধারণ ছুটি"
        ];
        
        if (isset($leaveTypeMap[$joiningData['approvedLeaveType']])) {
            $approvedLeaveType = $leaveTypeMap[$joiningData['approvedLeaveType']];
        }
        
        if (isset($leaveTypeMap[$leaveData['leaveTypeInTwo']])) {
            $prevApprovedLeaveType = $leaveTypeMap[$leaveData['leaveTypeInTwo']];
        }
        
        // Get signatory (hardcoded user_list.dataID=88 in original)
        $stmt = $con->prepare("SELECT * FROM user_list WHERE dataID = 88");
        $stmt->execute();
        $signatoryUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $signatoryData = null;
        $sigDesignation = null;
        $sigSection = null;
        $sigOrg = null;
        
        if ($signatoryUser) {
            $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
            $stmt->bind_param("i", $signatoryUser['employee_id']);
            $stmt->execute();
            $signatoryData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($signatoryData) {
                $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
                $stmt->bind_param("i", $signatoryData['designation']);
                $stmt->execute();
                $sigDesignation = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $stmt = $con->prepare("SELECT * FROM sections WHERE id = ?");
                $stmt->bind_param("i", $signatoryData['section_id']);
                $stmt->execute();
                $sigSection = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
                $stmt->bind_param("i", $signatoryData['organization_id']);
                $stmt->execute();
                $sigOrg = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
        
        // Get copy to list
        $stmt = $con->prepare("SELECT * FROM leave_notice_copy WHERE applicationID = ? ORDER BY serial ASC");
        $stmt->bind_param("i", $leaveApplicationID);
        $stmt->execute();
        $copyToList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Build HTML
        $html = '<style>
            body { font-family: "Kalpurush", "SolaimanLipi", "Nikosh", Arial, sans-serif; font-size: 15px; line-height: 1.6; }
            p { margin: 10px 0; }
            .text-center { text-align: center; }
            .text-justify { text-align: justify; }
            .heading { text-align: center; font-size: 18px; margin: 10px 0; }
            .underline { text-decoration: underline; }
            .bold { font-weight: bold; }
            table { width: 100%; border-collapse: collapse; }
            .small-text { font-size: 11px; }
        </style>';
        
        // Header
        $html .= '<p class="text-center heading">';
        $html .= 'বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)<br>';
        $html .= 'তেজগাঁও শিল্প এলাকা,<br>';
        $html .= 'ঢাকা-১২০৮ ।<br><br>';
        $html .= '</p>';
        
        // Notice number and date
        $html .= '<table width="100%" style="width: 100%; border: 1px;">';
        $html .= '<tr>';
        $html .= '<td width="50%"><p>নং- ' . banglaNumber($empData['memorialNo']) . '.' . banglaNumber($joiningData['officeNoticeNumber']) . '</p></td>';
        $html .= '<td width="50%" class="text-right" style="text-align: right;"><p class="text-right" style="text-align: right;">তারিখঃ ' . banglaNumber(date_format($officeNoticeDate, "d/m/Y")) . ' খ্রিস্টাব্দ</p></td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        // Title based on joining type
        $titleText = ($joiningData['joiningType'] == 1) ? "অবগতি পত্র" : "অফিস আদেশ";
        $html .= '<p class="text-center heading"><span class="underline">' . $titleText . '</span></p>';
        $html .= '<p>&nbsp;</p>';
        
        // Main content based on joining type
        if ($joiningData['joiningType'] == 1) {
            // Type 1: Normal acknowledgment
            $html .= '<p class="text-justify">&nbsp;&nbsp;&nbsp;';
            $html .= htmlspecialchars($empData['employee_name']) . ', ';
            $html .= htmlspecialchars($designationData['job_title_name']) . ', ';
            $html .= htmlspecialchars($sectionData['section_name']) . ' আপনাকে জানানো যাচ্ছে যে, বিটাক স্মারক নং ';
            $html .= banglaNumber($empData['memorialNo']) . '.' . banglaNumber($leaveData['officeNoticeNumber']);
            $html .= ' অনুযায়ী গৃহীত ' . banglaNumber(date_format($dateF, "d/m/Y"));
            $html .= ' হতে ' . banglaNumber(date_format($dateT, "d/m/Y"));
            $html .= ' তারিখ পর্যন্ত ' . banglaNumber($dateDiff);
            $html .= ' দিনের ছুটি ভোগ শেষে কর্মস্থলে সঠিক সময়ে আপনার যোগদান পত্র খানা এতদ্বারা গৃহীত হলো।';
            $html .= '</p>';
        } else if ($joiningData['joiningType'] == 2) {
            // Type 2: Early joining
            $html .= '<p class="text-justify">&nbsp;&nbsp;&nbsp;';
            $html .= htmlspecialchars($empData['employee_name']) . ', ';
            $html .= htmlspecialchars($designationData['job_title_name']) . ', ';
            $html .= htmlspecialchars($sectionData['section_name']) . ', আপনি বিটাক স্মারক নং ';
            $html .= banglaNumber($empData['memorialNo']) . '.' . banglaNumber($leaveData['officeNoticeNumber']);
            $html .= ',তারিখ ' . banglaNumber(date_format($primaryAppDate, "d/m/Y"));
            $html .= ' মোতাবেক প্রাপ্ত ' . banglaNumber(date_format($dateF, "d/m/Y"));
            $html .= ' হতে ' . banglaNumber(date_format($dateT, "d/m/Y"));
            $html .= ' তারিখ পর্যন্ত ' . banglaNumber($dateDiff);
            $html .= ' দিনের ' . $prevApprovedLeaveType;
            $html .= ' ছুটি পূর্ণ ভোগ না করে অগ্রিম যোগদান পত্র পেশ করেছেন বিধায় আপনার ';
            $html .= banglaNumber(date_format($reqdateF, "d/m/Y"));
            $html .= ' হতে ' . banglaNumber(date_format($reqdateT, "d/m/Y"));
            $html .= ' তারিখ পর্যন্ত ' . banglaNumber($reqDateDiff);
            $html .= ' দিনের ' . $approvedLeaveType . ' ছুটি মঞ্জুর করা হলো।';
            $html .= '</p>';
        } else if ($joiningData['joiningType'] == 3) {
            // Type 3: Extended leave
            $html .= '<p class="text-justify">&nbsp;&nbsp;&nbsp;';
            $html .= htmlspecialchars($empData['employee_name']) . ', ';
            $html .= htmlspecialchars($designationData['job_title_name']) . ', ';
            $html .= htmlspecialchars($sectionData['section_name']) . ', আপনি বিটাক স্মারক নং ';
            $html .= banglaNumber($empData['memorialNo']) . '.' . banglaNumber($leaveData['officeNoticeNumber']);
            $html .= ', তারিখ ' . banglaNumber(date_format($primaryAppDate, "d/m/Y"));
            $html .= ' মোতাবেক প্রাপ্ত ' . banglaNumber(date_format($dateF, "d/m/Y"));
            $html .= ' হতে ' . banglaNumber(date_format($dateT, "d/m/Y"));
            $html .= ' তারিখ পর্যন্ত ' . banglaNumber($dateDiff);
            $html .= ' দিনের ' . $prevApprovedLeaveType;
            $html .= ' ছুটি শেষে সঠিক সময়ে কর্মস্থলে যোগদান করেননি বিধায় আপনার ';
            $html .= banglaNumber(date_format($appdateF, "d/m/Y"));
            $html .= ' হতে ' . banglaNumber(date_format($appdateT, "d/m/Y"));
            $html .= ' তারিখ পর্যন্ত ' . banglaNumber($appdateDiff);
            $html .= ' দিনের ' . $approvedLeaveType . ' ছুটি মঞ্জুর করা হলো।';
            $html .= '</p>';
        }
        
        $html .= '<p>&nbsp;</p>';
        
        // Authority line (only for type 2 and 3)
        if ($joiningData['joiningType'] == 2 || $joiningData['joiningType'] == 3) {
            $html .= '<p>০২। কর্তৃপক্ষের অনুমোদনক্রমে এ আদেশ জারি করা হলো।</p>';
            $html .= '<p>&nbsp;</p>';
        }
        
        // Two column layout - Employee and Signatory
        $html .= '<table>';
        $html .= '<tr>';
        
        // Left column - Employee (recipient)
        $html .= '<td width="40%" style="vertical-align: top;">';
        $html .= '<p>';
        $html .= htmlspecialchars($empData['employee_name']) . '<br>';
        $html .= htmlspecialchars($designationData['job_title_name']) . '<br>';
        $html .= htmlspecialchars($sectionData['section_name']) . ', ';
        $html .= htmlspecialchars($orgData['organization_name']);
        $html .= '</p>';
        $html .= '</td>';
        
        // Middle spacer
        $html .= '<td width="20%">&nbsp;</td>';
        
        // Right column - Signatory
        $html .= '<td width="40%" style="vertical-align: top; text-align: right;">';
        
        if ($signatoryUser && !empty($signatoryUser['signature'])) {
            $sig = $signatoryUser['signature'];
            
            // Detect image type
            $imageType = 'image/png';
            $decoded = @base64_decode($sig, true);
            
            if ($decoded !== false) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = @$finfo->buffer($decoded);
                
                if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
                    $imageType = 'image/jpeg';
                }
                
                $sigBase64 = $sig;
            } else {
                $sigBase64 = base64_encode($sig);
                
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = @$finfo->buffer($sig);
                
                if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
                    $imageType = 'image/jpeg';
                }
            }
            
            if (!empty($sigBase64)) {
                $html .= '<img src="data:' . $imageType . ';base64,' . $sigBase64 . '" style="height: 60px;" /><br>';
            }
        }
        
        if ($signatoryData) {
            $html .= htmlspecialchars(str_replace("জনাব ", "", $signatoryData['employee_name'])) . '<br>';
            if ($sigDesignation) {
                $html .= htmlspecialchars($sigDesignation['job_title_name']) . '<br>';
            }
            if ($sigSection && $sigOrg) {
                $html .= htmlspecialchars($sigSection['section_name']) . ', ';
                $html .= htmlspecialchars($sigOrg['organization_name']);
            }
        }
        
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        // Copy to list
        if (!empty($copyToList)) {
            $html .= '<p>&nbsp;</p>';
            $html .= '<p>অনুলিপি :</p>';
            
            $copySL = 1;
            foreach ($copyToList as $copy) {
                // Get employee details
                $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
                $stmt->bind_param("i", $copy['employeeID']);
                $stmt->execute();
                $copyEmpData = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($copyEmpData) {
                    // Get designation
                    $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
                    $stmt->bind_param("i", $copyEmpData['designation']);
                    $stmt->execute();
                    $copyDesigData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    // Get section
                    $stmt = $con->prepare("SELECT * FROM sections WHERE id = ?");
                    $stmt->bind_param("i", $copyEmpData['section_id']);
                    $stmt->execute();
                    $copySectionData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    // Get organization
                    $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
                    $stmt->bind_param("i", $copyEmpData['organization_id']);
                    $stmt->execute();
                    $copyOrgData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    $html .= '<p>';
                    $html .= banglaNumber($copySL) . '। ';
                    $html .= htmlspecialchars($copyEmpData['employee_name']) . ', ';
                    if ($copyDesigData) {
                        $html .= htmlspecialchars($copyDesigData['job_title_name']) . ', ';
                    }
                    if ($copySectionData) {
                        $html .= htmlspecialchars($copySectionData['section_name']) . ', ';
                    }
                    if ($copyOrgData) {
                        $html .= htmlspecialchars($copyOrgData['organization_name']);
                    }
                    $html .= '</p>';
                    
                    $copySL++;
                }
            }
        }
        
        // Create PDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'default_font' => 'kalpurush',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        
        $mpdf->SetTitle('Joining Office Order - ' . $empData['employee_name']);
        $mpdf->WriteHTML($html);
        
        $pdfContent = $mpdf->Output('', 'S');
        $base64 = base64_encode($pdfContent);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'pdfData' => $base64,
            'title' => 'Joining Office Order - ' . $empData['employee_name']
        ]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

function showViewer($leaveApplicationID) {
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joining Office Order - ID: <?= $leaveApplicationID ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header h1 { font-size: 18px; font-weight: 500; }
        .toolbar { background: #fff; padding: 12px 20px; border-bottom: 1px solid #e0e0e0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #48bb78; color: white; }
        .btn-info { background: #4299e1; color: white; }
        .btn-warning { background: #ed8936; color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .status { margin-left: auto; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 500; }
        .status.loading { background: #e2e8f0; color: #4a5568; }
        .status.ready { background: #c6f6d5; color: #22543d; }
        .status.error { background: #fed7d7; color: #742a2a; }
        .pdf-viewer { height: calc(100vh - 110px); background: #525252; display: flex; flex-direction: column; align-items: center; overflow-y: auto; padding: 20px; }
        #pdfCanvas { max-width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); margin-bottom: 20px; }
        .page-controls { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: white; padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); display: none; align-items: center; gap: 15px; }
        .page-controls.active { display: flex; }
        .page-btn { background: #667eea; color: white; border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }
        .page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .page-info { font-weight: 500; color: #2d3748; }
        .loading-screen, .error-screen { display: flex; justify-content: center; align-items: center; height: calc(100vh - 110px); flex-direction: column; gap: 20px; }
        .spinner { width: 50px; height: 50px; border: 4px solid #e2e8f0; border-top: 4px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .error-icon { font-size: 64px; }
        .error-message { font-size: 16px; color: #e53e3e; max-width: 600px; text-align: center; padding: 20px; }
        .error-details { background: #f7fafc; padding: 15px; border-radius: 4px; margin-top: 10px; font-size: 12px; font-family: monospace; text-align: left; max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
    
    
    <div class="toolbar">
        <button class="btn btn-primary" onclick="loadPDF()" id="btnReload">🔄 Reload</button>
        <button class="btn btn-success" onclick="downloadPDF()" id="btnDownload">💾 Download</button>
        <button class="btn btn-info" onclick="printPDF()" id="btnPrint">🖨️ Print</button>
        <button class="btn btn-warning" onclick="zoomIn()">🔍+ Zoom</button>
        <button class="btn btn-warning" onclick="zoomOut()">🔍- Zoom</button>
        <div class="status loading" id="status">Loading...</div>
    </div>
    
    <div id="loadingScreen" class="loading-screen">
        <div class="spinner"></div>
        <div>Generating PDF...</div>
    </div>
    
    <div id="errorScreen" class="error-screen" style="display: none;">
        <div class="error-icon">⚠️</div>
        <div class="error-message" id="errorMessage"></div>
        <div class="error-details" id="errorDetails" style="display: none;"></div>
        <button class="btn btn-primary" onclick="loadPDF()">Try Again</button>
    </div>
    
    <div id="pdfViewer" class="pdf-viewer" style="display: none;">
        <canvas id="pdfCanvas"></canvas>
    </div>
    
    <div class="page-controls" id="pageControls">
        <button class="page-btn" onclick="prevPage()" id="btnPrev">‹</button>
        <span class="page-info">Page <span id="pageNum">1</span> of <span id="pageCount">1</span></span>
        <button class="page-btn" onclick="nextPage()" id="btnNext">›</button>
    </div>

    <script>
        const leaveApplicationID = <?= $leaveApplicationID ?>;
        let pdfDoc = null, pageNum = 1, pageRendering = false, pageNumPending = null, scale = 1.5;
        let pdfDataBlob = null;
        
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        
        window.addEventListener('DOMContentLoaded', () => loadPDF());
        
        function setStatus(text, type = 'loading') {
            const el = document.getElementById('status');
            el.textContent = text;
            el.className = 'status ' + type;
        }
        
        function disableButtons(disabled) {
            ['btnReload', 'btnDownload', 'btnPrint'].forEach(id => {
                document.getElementById(id).disabled = disabled;
            });
        }
        
        function showLoading() {
            document.getElementById('loadingScreen').style.display = 'flex';
            document.getElementById('errorScreen').style.display = 'none';
            document.getElementById('pdfViewer').style.display = 'none';
            document.getElementById('pageControls').classList.remove('active');
            disableButtons(true);
        }
        
        function showError(message, details = null) {
            document.getElementById('loadingScreen').style.display = 'none';
            document.getElementById('errorScreen').style.display = 'flex';
            document.getElementById('pdfViewer').style.display = 'none';
            document.getElementById('errorMessage').textContent = message;
            
            if (details) {
                document.getElementById('errorDetails').textContent = details;
                document.getElementById('errorDetails').style.display = 'block';
            }
            
            setStatus('Error', 'error');
            disableButtons(false);
        }
        
        function showPDF() {
            document.getElementById('loadingScreen').style.display = 'none';
            document.getElementById('errorScreen').style.display = 'none';
            document.getElementById('pdfViewer').style.display = 'flex';
            document.getElementById('pageControls').classList.add('active');
            disableButtons(false);
        }
        
        function loadPDF() {
            showLoading();
            setStatus('Generating...', 'loading');
            
            fetch(`?action=generate&leaveApplicationID=${leaveApplicationID}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        setStatus('✓ Ready', 'ready');
                        
                        const binaryString = atob(data.pdfData);
                        const bytes = new Uint8Array(binaryString.length);
                        for (let i = 0; i < binaryString.length; i++) {
                            bytes[i] = binaryString.charCodeAt(i);
                        }
                        
                        pdfDataBlob = new Blob([bytes], { type: 'application/pdf' });
                        loadPDFDocument(bytes);
                    } else {
                        showError(data.error, data.trace || null);
                    }
                })
                .catch(e => showError('Failed to load: ' + e.message));
        }
        
        function loadPDFDocument(pdfData) {
            pdfjsLib.getDocument(pdfData).promise.then(pdf => {
                pdfDoc = pdf;
                document.getElementById('pageCount').textContent = pdf.numPages;
                renderPage(pageNum);
                showPDF();
            }).catch(e => showError('PDF Error: ' + e.message));
        }
        
        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(page => {
                const canvas = document.getElementById('pdfCanvas');
                const ctx = canvas.getContext('2d');
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                page.render({ canvasContext: ctx, viewport: viewport }).promise.then(() => {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });
            document.getElementById('pageNum').textContent = num;
            document.getElementById('btnPrev').disabled = num <= 1;
            document.getElementById('btnNext').disabled = num >= pdfDoc.numPages;
        }
        
        function queueRenderPage(num) {
            pageRendering ? pageNumPending = num : renderPage(num);
        }
        
        function prevPage() { if (pageNum > 1) { pageNum--; queueRenderPage(pageNum); } }
        function nextPage() { if (pageNum < pdfDoc.numPages) { pageNum++; queueRenderPage(pageNum); } }
        function zoomIn() { scale += 0.25; renderPage(pageNum); }
        function zoomOut() { if (scale > 0.5) { scale -= 0.25; renderPage(pageNum); } }
        
        function downloadPDF() {
            if (pdfDataBlob) {
                const url = URL.createObjectURL(pdfDataBlob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `joining_office_order_${leaveApplicationID}.pdf`;
                a.click();
                URL.revokeObjectURL(url);
            }
        }
        
        function printPDF() {
            if (pdfDataBlob) {
                const url = URL.createObjectURL(pdfDataBlob);
                window.open(url, '_blank');
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            }
        }
    </script>
</body>
</html>
<?php } ?>