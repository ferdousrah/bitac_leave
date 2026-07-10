<?php
/**
 * Early Joining Application Viewer - In-Memory Version
 * Shows employee request to return early (before leave ends)
 * 
 * Usage: early_joining_viewer.php?leaveApplicationID=5516
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
                <div class="example">early_joining_viewer.php?leaveApplicationID=<strong>5516</strong></div>
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
        $approvedDateTo = date_create($leaveData['primaryLeaveDateTo']);
        $joiningDate = date_create($joiningData['requestedJoiningDate']);
        $submitDate = date_create($joiningData['submitDate']);
        
        // Calculate requested days (early return)
        $dateDiffRequest = dateDiffInDays($leaveData['approvedDateFrom'], $joiningData['requestedJoiningDate']) + 1;
        
        // Get supervisor approval
        $stmt = $con->prepare("SELECT * FROM leave_joining_data_for_approval WHERE leaveApplicationID = ? AND serial = '1'");
        $stmt->bind_param("i", $leaveApplicationID);
        $stmt->execute();
        $supervisorApproval = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $supervisorData = null;
        $supDesignation = null;
        $supSection = null;
        $supOrg = null;
        
        if ($supervisorApproval && !empty($supervisorApproval['signatory'])) {
            $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
            $stmt->bind_param("i", $supervisorApproval['signatory']);
            $stmt->execute();
            $supervisorData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($supervisorData) {
                $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
                $stmt->bind_param("i", $supervisorData['designation']);
                $stmt->execute();
                $supDesignation = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $stmt = $con->prepare("SELECT * FROM sections WHERE id = ?");
                $stmt->bind_param("i", $supervisorData['section_id']);
                $stmt->execute();
                $supSection = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
                $stmt->bind_param("i", $supervisorData['organization_id']);
                $stmt->execute();
                $supOrg = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
        
        // Get supervisor note
        $stmt = $con->prepare("SELECT * FROM leave_joining_data_for_approval WHERE leaveApplicationID = ? AND isSupervisor = '1'");
        $stmt->bind_param("i", $leaveApplicationID);
        $stmt->execute();
        $supervisorNote = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Build HTML
        $html = '<style>
            body { font-family: "Kalpurush", "SolaimanLipi", "Nikosh", Arial, sans-serif; font-size: 14px; line-height: 1.6; }
            p { margin: 10px 0; text-align: justify; }
            .text-right { text-align: right; }
            table { width: 100%; border-collapse: collapse; }
            .signature-section { text-align: center; }
            .small-text { font-size: 11px; }
        </style>';
        
        // Date at top
        $html .= '<p class="text-left">তারিখ: ' . banglaNumber(date_format($submitDate, "d/m/Y")) . '</p>';
        
        // Addressed to
        $html .= '<p>';
        $html .= 'বরাবর<br>';
        $html .= 'পরিচালক (প্রশাসন)<br>';
        $html .= 'বিটাক<br>';
        $html .= '১১৬(খ), তেজগাঁও শিল্প এলাকা, ঢাকা-১২০৮ ।';
        $html .= '</p>';
        
        // Subject
        $html .= '<p>বিষয়ঃ অনুমোদিত ছুটি পূর্ণ ভোগ না করে কর্মস্থলে অগ্রিম যোগদান প্রসঙ্গে।</p><br>';
        
        // Salutation
        $html .= '<p>মহোদয়,</p>';
        
        // First paragraph
        $html .= '<p>&nbsp;&nbsp;&nbsp;যথাবিহীত সম্মান প্রদর্শনপূর্বক নিবেদন এই যে, আমি নিম্ন স্বাক্ষরকারী বিটাক স্মারক নং ';
        $html .= banglaNumber($empData['memorialNo']) . '.' . banglaNumber($leaveData['officeNoticeNumber']);
        $html .= ' অনুযায়ী ' . banglaNumber(date_format($dateF, "d/m/Y"));
        $html .= ' থেকে ' . banglaNumber(date_format($approvedDateTo, "d/m/Y"));
        $html .= ' তারিখ পর্যন্ত, ' . banglaNumber($dateDiff);
        $html .= ' দিনের ছুটি পূর্ণ ভোগ না করে ';
        $html .= banglaNumber(date_format($dateF, "d/m/Y"));
        $html .= ' থেকে ' . banglaNumber(date_format($joiningDate, "d/m/Y"));
        $html .= ' তারিখ পর্যন্ত, ' . banglaNumber($dateDiffRequest);
        $html .= ' দিনের ছুটি ভোগকরতঃ অদ্য কর্মস্থলে যোগদান করতে ইচ্ছুক।</p>';
        
        // Second paragraph
        $html .= '<p>&nbsp;&nbsp;&nbsp;অতএব, মহোদয়ের নিকট বিনীত নিবেদন উপর্যুক্ত বিষয়টি বিবেচনাপূর্বক আমাকে আমার ভোগকৃত ';
        $html .= banglaNumber(date_format($dateF, "d/m/Y"));
        $html .= ' থেকে ' . banglaNumber(date_format($joiningDate, "d/m/Y"));
        $html .= ' তারিখ পর্যন্ত, ' . banglaNumber($dateDiffRequest);
        $html .= ' দিনের ছুটি মঞ্জুরকরতঃ আমাকে কর্মস্থলে যোগদানের অনুমতি প্রদান করে বাধিত করবেন।</p>';
        
        $html .= '<p>&nbsp;</p>';
        
        // Applicant signature
        $html .= '<table><tr><td width="90%">&nbsp;</td><td width="10%">';
        $html .= '<div class="signature-section">';
        $html .= 'নিবেদক<br>_________________________________<br>';
        
        if (!empty($leaveData['signature'])) {
            $sig = $leaveData['signature'];
            
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
                $html .= '<img src="data:' . $imageType . ';base64,' . $sigBase64 . '" style="height: 40px;" /><br>';
            }
        }
        
        $html .= '<span class="small-text">' . banglaNumber(date_format($submitDate, "d.m.Y")) . '</span><br>';
        $html .= htmlspecialchars(str_replace("জনাব ", "", $empData['employee_name'])) . '<br>';
        $html .= htmlspecialchars($designationData['job_title_name']) . '<br>';
        $html .= htmlspecialchars($sectionData['section_name']) . ', ';
        $html .= htmlspecialchars($orgData['organization_name']);
        $html .= '</div></td></tr></table>';
        
        $html .= '<p>&nbsp;</p>';
        
        // Supervisor note
        if ($supervisorNote && !empty($supervisorNote['note'])) {
            $html .= '<p>' . htmlspecialchars($supervisorNote['note']) . '</p>';
        }
        
        // Supervisor signature
        $html .= '<table><tr><td width="90%">&nbsp;</td><td width="10%">';
        $html .= '<div class="signature-section">';
        $html .= 'বিভাগীয় প্রধান<br>_________________________________<br>';
        
        if ($supervisorApproval && $supervisorApproval['isApproved'] == 1 && $supervisorData && !empty($supervisorData['signature'])) {
            $sig = $supervisorData['signature'];
            
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
                $html .= '<img src="data:' . $imageType . ';base64,' . $sigBase64 . '" style="height: 40px;" /><br>';
            }
            
            if (!empty($supervisorApproval['approvedDate'])) {
                $approvedDate = date_create($supervisorApproval['approvedDate']);
                $html .= '<span class="small-text">' . banglaNumber(date_format($approvedDate, "d.m.Y")) . '</span><br>';
            }
        }
        
        if ($supervisorData) {
            $html .= htmlspecialchars(str_replace("জনাব ", "", $supervisorData['employee_name'])) . '<br>';
            if ($supDesignation) {
                $html .= htmlspecialchars($supDesignation['job_title_name']) . '<br>';
            }
            if ($supSection && $supOrg) {
                $html .= htmlspecialchars($supSection['section_name']) . ', ';
                $html .= htmlspecialchars($supOrg['organization_name']);
            }
        }
        
        $html .= '</div></td></tr></table>';
        
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
        
        $mpdf->SetTitle('Early Joining Application - ' . $empData['employee_name']);
        $mpdf->WriteHTML($html);
        
        $pdfContent = $mpdf->Output('', 'S');
        $base64 = base64_encode($pdfContent);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'pdfData' => $base64,
            'title' => 'Early Joining Application - ' . $empData['employee_name']
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
    <title>Early Joining Application - ID: <?= $leaveApplicationID ?></title>
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
                a.download = `early_joining_${leaveApplicationID}.pdf`;
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