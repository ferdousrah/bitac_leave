<?php
/**
 * Annual Leave Certificate Viewer - In-Memory Version
 * Shows yearly leave balance summary (বার্ষিক ছুটি সনদ)
 *
 * Usage: yearly-certificate.php?employeeID=123&year=2024
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');
set_time_limit(120);

$action = $_GET['action'] ?? 'view';
$employeeID = isset($_GET['employeeID']) ? (int)$_GET['employeeID'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

if ($employeeID <= 0 || $year <= 0) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Error - Missing Parameters</title>
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
            <h1>⚠️ Missing Parameters</h1>
            <p>You need to provide both employee ID and year.</p>
            <div class="info">
                <strong>Usage:</strong>
                <div class="example">yearly-certificate.php?employeeID=<strong>123</strong>&year=<strong>2024</strong></div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'generate') {
    generatePDFData($employeeID, $year);
} else {
    showViewer($employeeID, $year);
}

function generatePDFData($employeeID, $year) {
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

        // Get leave summary
        $stmt = $con->prepare("SELECT * FROM yearly_leave_summary WHERE employeeID = ? AND year = ?");
        $stmt->bind_param("ii", $employeeID, $year);
        $stmt->execute();
        $leaveSummary = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$leaveSummary) {
            throw new Exception("এই কর্মচারীর জন্য $year সালের ছুটি সনদ তৈরি করা হয়নি");
        }

        // Get employee details
        $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
        $stmt->bind_param("i", $employeeID);
        $stmt->execute();
        $empData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$empData) {
            throw new Exception("Employee not found");
        }

        // Get designation
        $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
        $stmt->bind_param("i", $empData['designation']);
        $stmt->execute();
        $designationData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Get organization
        $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
        $stmt->bind_param("i", $empData['organization_id']);
        $stmt->execute();
        $orgData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Get signatory
        $signatoryData = null;
        $sigDesignation = null;
        $sigOrg = null;
        $signatorySignature = null;

        if (!empty($leaveSummary['signatory'])) {
            $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
            $stmt->bind_param("i", $leaveSummary['signatory']);
            $stmt->execute();
            $signatoryData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($signatoryData) {
                $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
                $stmt->bind_param("i", $signatoryData['designation']);
                $stmt->execute();
                $sigDesignation = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
                $stmt->bind_param("i", $signatoryData['organization_id']);
                $stmt->execute();
                $sigOrg = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // Get signature
                $stmt = $con->prepare("SELECT signature FROM user_list WHERE employee_id = ?");
                $stmt->bind_param("i", $leaveSummary['signatory']);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                if ($result) {
                    $signatorySignature = $result['signature'];
                }
                $stmt->close();
            }
        }

        // Get copy-to list
        $stmt = $con->prepare("SELECT * FROM leaveSummary_copy WHERE leaveSummaryID = ?");
        $stmt->bind_param("i", $leaveSummary['leaveSummaryID']);
        $stmt->execute();
        $copyToList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Convert days to years, months, days
        function daysToYMD($totalDays) {
            $years = floor($totalDays / 360);
            $months = floor(($totalDays - ($years * 360)) / 30);
            $days = round($totalDays - ($years * 360) - ($months * 30));
            return ['years' => $years, 'months' => $months, 'days' => $days];
        }

        $fullAvgLeave = daysToYMD($leaveSummary['fullHalfSalaryInDays']);
        $halfAvgLeave = daysToYMD($leaveSummary['HalfSalaryInDays']);
        $withoutPayLeave = daysToYMD($leaveSummary['withoutSalaryInDays']);

        // Bengali month names
        $months = [
            '01' => 'জানুয়ারি', '02' => 'ফেব্রুয়ারি', '03' => 'মার্চ', '04' => 'এপ্রিল',
            '05' => 'মে', '06' => 'জুন', '07' => 'জুলাই', '08' => 'আগস্ট',
            '09' => 'সেপ্টেম্বর', '10' => 'অক্টোবর', '11' => 'নভেম্বর', '12' => 'ডিসেম্বর'
        ];

        // Format date in Bengali
        $certDate = date_create($leaveSummary['date']);
        $day = banglaNumber(date_format($certDate, 'd'));
        $month = $months[date_format($certDate, 'm')];
        $yearBn = banglaNumber(date_format($certDate, 'Y'));
        $certificateDateBengali = $day . ' ' . $month . ' ' . $yearBn;

		$noticeDate = date_create($leaveSummary['noticeDate']);

        // Load logo images
        $bdgovLogo = '';
        $bitacLogo = '';

        // Try different paths for bdgov logo
        $bdgovPaths = [
            __DIR__ . '/../../../uploads/bdgov.png',
            '/home/ferdous/ftp/bitac.technocratsbd.com/uploads/bdgov.png'
        ];

        foreach ($bdgovPaths as $path) {
            if (file_exists($path)) {
                $imageData = file_get_contents($path);
                $base64 = base64_encode($imageData);
                $bdgovLogo = '<img src="data:image/png;base64,' . $base64 . '" width="80" />';
                break;
            }
        }

        // Try different paths for BITAC logo
        $bitacPaths = [
            __DIR__ . '/../../../uploads/bitac-logo-inner.png',
            '/home/ferdous/ftp/bitac.technocratsbd.com/uploads/bitac-logo-inner.png'
        ];

        foreach ($bitacPaths as $path) {
            if (file_exists($path)) {
                $imageData = file_get_contents($path);
                $base64 = base64_encode($imageData);
                $bitacLogo = '<img src="data:image/png;base64,' . $base64 . '" width="80" />';
                break;
            }
        }

        // Build HTML
        $html = '<style>
            body { font-family: "Kalpurush", "SolaimanLipi", "Nikosh", Arial, sans-serif; font-size: 14px; line-height: 1.6; }
            p { margin: 10px 0; }
            .text-center { text-align: center; }
            .text-justify { text-align: justify; }
            .text-right { text-align: right; }
            .underline { text-decoration: underline; }
            .blue { color: #0066cc; }
            .bold { font-weight: bold; }
            table { width: 100%; border-collapse: collapse; }
            .small-text { font-size: 11px; }
        </style>';

        // Header with logos
        $html .= '<table><tr>';
        $html .= '<td width="100" style="vertical-align: middle;">';
        $html .= $bdgovLogo ? $bdgovLogo : '<div style="width: 80px; height: 80px;">&nbsp;</div>';
        $html .= '</td>';

        $html .= '<td class="text-center" style="vertical-align: middle;">';
        $html .= '<span style="font-size: 20px;">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</span><br>';
        $html .= '<span style="font-size: 14px;">শিল্প মন্ত্রণালয়</span><br>';
        $html .= '<span style="font-size: 14px;">বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)</span><br>';
        $html .= '<span style="font-size: 14px;">১১৬/খ, তেজগাঁও শিল্প এলাকা, ঢাকা-১২০৮।</span>';
        $html .= '</td>';

        $html .= '<td width="100" style="vertical-align: middle; text-align: right;">';
        $html .= $bitacLogo ? $bitacLogo : '<div style="width: 80px; height: 80px;">&nbsp;</div>';
        $html .= '</td>';
        $html .= '</tr></table>';

        $html .= '<p>&nbsp;</p>';

        // Memorial number and date
        $html .= '<table><tr>';
        $html .= '<td width="50%"><p>স্মারক নং- ' . htmlspecialchars(banglaNumber($leaveSummary['memorial_number'])) . '</p></td>';
        $html .= '<td width="50%" class="text-right"><p>তারিখঃ ' . banglaNumber(date_format($noticeDate, 'd/m/Y')) . ' খ্রিস্টাব্দ</p></td>';
        $html .= '</tr></table>';

        $html .= '<p>&nbsp;</p>';

        // Title
        $html .= '<p class="text-center" style="font-size: 18px;"><span class="underline blue">বার্ষিক ছুটি সনদ - ' . banglaNumber($year) . '</span></p>';

        $html .= '<p>&nbsp;</p>';

        // Main content
        $html .= '<p class="text-justify">&nbsp;&nbsp;&nbsp;এতদ্দ্বারা আপনি ';
        $html .= htmlspecialchars($empData['employee_name']) . ', ';
        $html .= htmlspecialchars($designationData['job_title_name']) . ', ';
        $html .= htmlspecialchars($orgData['organization_name']);
        $html .= ' কে জানানো যাচ্ছে যে, ' . $certificateDateBengali;
        $html .= ' তারিখ পর্যন্ত আপনার ছুটির বিবরণ নিম্নরূপঃ</p>';

        $html .= '<p>&nbsp;</p>';

        // Leave balance table
        $html .= '<table><tr>';
        $html .= '<td width="25%">&nbsp;</td>';
        $html .= '<td width="50%">';
        $html .= '<p>ক) পূর্ণ গড় বেতনে জমা ছুটি : ';
        $html .= banglaNumber($fullAvgLeave['years']) . ' বছর, ';
        $html .= banglaNumber($fullAvgLeave['months']) . ' মাস, ';
        $html .= banglaNumber($fullAvgLeave['days']) . ' দিন</p>';

        $html .= '<p>খ) অর্ধ গড় বেতনে জমা ছুটি : ';
        $html .= banglaNumber($halfAvgLeave['years']) . ' বছর, ';
        $html .= banglaNumber($halfAvgLeave['months']) . ' মাস, ';
        $html .= banglaNumber($halfAvgLeave['days']) . ' দিন</p>';

        $html .= '<p>গ) অসাধারণ (বিনা বেতনে) ছুটি : ';
        $html .= banglaNumber($withoutPayLeave['years']) . ' বছর, ';
        $html .= banglaNumber($withoutPayLeave['months']) . ' মাস, ';
        $html .= banglaNumber($withoutPayLeave['days']) . ' দিন</p>';
        $html .= '</td>';
        $html .= '<td width="25%">&nbsp;</td>';
        $html .= '</tr></table>';

        $html .= '<p>&nbsp;</p>';

        // Authority line
        $html .= '<p>০২। কর্তৃপক্ষের অনুমোদনক্রমে এ বার্ষিক ছুটি সনদ প্রদান করা হলো।</p>';

        $html .= '<p>&nbsp;</p>';
        $html .= '<p>&nbsp;</p>';

        // Signature section
        $html .= '<table><tr>';

        // Left - Employee
        $html .= '<td width="40%" style="vertical-align: top;">';
        $html .= '<p>';
        $html .= htmlspecialchars($empData['employee_name']) . '<br>';
        $html .= htmlspecialchars($designationData['job_title_name']) . '<br>';
        $html .= htmlspecialchars($orgData['organization_name']);
        $html .= '</p>';
        $html .= '</td>';

        $html .= '<td width="20%">&nbsp;</td>';

        // Right - Signatory
        $html .= '<td width="40%" style="vertical-align: top; text-align: right;">';
        $html .= '<p>';

        if (!empty($signatorySignature)) {
            $sig = $signatorySignature;

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
                $html .= '<span class="small-text">' . banglaNumber(date_format($noticeDate, "d.m.Y")) . '</span><br>';
            }
        }

        if ($signatoryData) {
            $html .= htmlspecialchars(str_replace("জনাব ", "", $signatoryData['employee_name'])) . '<br>';
            if ($sigDesignation) {
                $html .= htmlspecialchars($sigDesignation['job_title_name']) . '<br>';
            }
            if ($sigOrg) {
                $html .= htmlspecialchars($sigOrg['organization_name']);
            }
        }

        $html .= '</p>';
        $html .= '</td>';
        $html .= '</tr></table>';

        // Copy-to list
        if (!empty($copyToList)) {
            $html .= '<p>&nbsp;</p>';
            $html .= '<p>অনুলিপি (সদয় অবগতি ও প্রয়োজনীয় কার্যার্থে):</p>';

            $copySL = 1;
            foreach ($copyToList as $copy) {
                // Get copy employee
                $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
                $stmt->bind_param("i", $copy['copyTo']);
                $stmt->execute();
                $copyEmp = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($copyEmp) {
                    // Get designation
                    $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
                    $stmt->bind_param("i", $copyEmp['designation']);
                    $stmt->execute();
                    $copyDesig = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    // Get section
                    $stmt = $con->prepare("SELECT * FROM sections WHERE id = ?");
                    $stmt->bind_param("i", $copyEmp['section_id']);
                    $stmt->execute();
                    $copySection = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    // Get organization
                    $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
                    $stmt->bind_param("i", $copyEmp['organization_id']);
                    $stmt->execute();
                    $copyOrg = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $html .= '<p>';
                    $html .= banglaNumber($copySL) . '। ';
                    $html .= htmlspecialchars($copyEmp['employee_name']) . ', ';
                    if ($copyDesig) {
                        $html .= htmlspecialchars($copyDesig['job_title_name']) . ', ';
                    }
                    if ($copySection && !empty($copySection['section_name'])) {
                        $html .= htmlspecialchars($copySection['section_name']) . ', ';
                    }
                    if ($copyOrg) {
                        $html .= htmlspecialchars($copyOrg['organization_name']);
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

        $mpdf->SetTitle('Leave Certificate ' . $year . ' - ' . $empData['employee_name']);
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', 'S');
        $base64 = base64_encode($pdfContent);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'pdfData' => $base64,
            'title' => 'Leave Certificate ' . $year . ' - ' . $empData['employee_name']
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

function showViewer($employeeID, $year) {
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annual Leave Certificate <?= $year ?> - Employee <?= $employeeID ?></title>
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
        const employeeID = <?= $employeeID ?>;
        const year = <?= $year ?>;
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

            fetch(`?action=generate&employeeID=${employeeID}&year=${year}`)
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
                a.download = `leave_certificate_${employeeID}_${year}.pdf`;
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
