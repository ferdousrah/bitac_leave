<?php
/**
 * Increment Report PDF Viewer
 * Usage: api/reports/increment-pdf.php?employeeID=X
 */

ini_set('memory_limit', '256M');
set_time_limit(120);
ob_start();

require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../../config/connection.php');

$action     = $_GET['action']      ?? 'view';
$employeeID = isset($_GET['employeeID']) ? (int)$_GET['employeeID'] : 0;

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username'])) {
    if ($action === 'generate') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    } else {
        http_response_code(403);
        echo 'Unauthorized';
    }
    exit;
}

if ($employeeID <= 0) {
    if ($action === 'generate') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid employee ID']);
    } else {
        echo '<p style="font-family:sans-serif;padding:20px">Invalid employee ID</p>';
    }
    exit;
}

if ($action === 'generate') {
    generatePDFData($employeeID);
} else {
    showViewer($employeeID);
}

// ─────────────────────────────────────────────────────────────────────────────
// Generate PDF → return base64 JSON
// ─────────────────────────────────────────────────────────────────────────────
function generatePDFData($employeeID) {
    try {
        if (!class_exists('\Mpdf\Mpdf')) {
            throw new \Exception("mPDF not found. Run: composer require mpdf/mpdf");
        }

        global $con, $obj;

        // ── Employee info ────────────────────────────────────────────────────
        $getEmployeeDetailsQ      = mysqli_query($con, "select * from employee_list where id='$employeeID'");
        $getEmployeeDetailsQW     = mysqli_fetch_assoc($getEmployeeDetailsQ);
        $getDesignationDetailsQ   = mysqli_query($con, "select * from job_title where id='" . intval($getEmployeeDetailsQW['designation']) . "'");
        $getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);
        $getSectionDetailsQ       = mysqli_query($con, "select * from sections where id='" . intval($getEmployeeDetailsQW['section_id']) . "'");
        $getSectionDetailsQRW     = mysqli_fetch_assoc($getSectionDetailsQ);

        // ── Increment data ───────────────────────────────────────────────────
        $getMyIncrementDataQ = mysqli_query($con, "select * from yearly_salary_increment where employeeID='$employeeID' and status=1 ORDER BY incrementYear ASC");

        // ── Build HTML ───────────────────────────────────────────────────────
        $td  = 'style="border:1px solid #ccc; padding:5px 8px;"';
        $th  = 'style="border:1px solid #ccc; padding:5px 8px; background:#f0f0f0;"';
        $tc  = 'style="border:1px solid #ccc; padding:5px 8px; text-align:center;"';
        $thc = 'style="border:1px solid #ccc; padding:5px 8px; background:#f0f0f0; text-align:center;"';

        ob_start();
        ?>
<html>
<head>
<meta charset="UTF-8">
<style>
body  { font-family: kalpurush, sans-serif; font-size: 12px; }
table { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
h2    { text-align: center; font-size: 16px; font-weight: normal; margin: 0 0 2px 0; }
h3    { text-align: center; font-size: 12px; font-weight: normal; margin: 0 0 10px 0; }
h4    { font-size: 13px; font-weight: normal; margin: 10px 0 5px 0; }
th    { font-weight: normal; }
</style>
</head>
<body>

<h2>বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)</h2>
<h3>১১৬(খ), তেজগাঁও শিল্প এলাকা, ঢাকা-১২০৮</h3>

<h4 style="text-align:center;"><?=htmlspecialchars($getEmployeeDetailsQW['employee_name'])?>, <?=htmlspecialchars($getDesignationDetailsQRW['job_title_name'])?></h4>

<table>
    <tr>
        <th <?=$thc?>>ক্রমিক নং</th>
        <th <?=$th?>>বৎসর</th>
        <th <?=$th?>>মূল বেতন</th>
        <th <?=$th?>>বেতন বৃদ্ধির হার</th>
        <th <?=$th?>>বেতন বৃদ্ধির পর মূল বেতন</th>
    </tr>
    <?php
    $sl = 0;
    while ($dataRow = mysqli_fetch_array($getMyIncrementDataQ)) {
        $sl++;
    ?>
    <tr>
        <td <?=$tc?>><?=$obj->engToBn($sl)?></td>
        <td <?=$td?>><?=$obj->engToBn($dataRow['incrementYear'])?></td>
        <td <?=$td?>><?=$obj->engToBn(number_format($dataRow['presentSalary'], 2))?></td>
        <td <?=$td?>><?=$obj->engToBn(number_format($dataRow['incrementAmount'], 2))?></td>
        <td <?=$td?>><?=$obj->engToBn(number_format($dataRow['incrementSalary'], 2))?></td>
    </tr>
    <?php } ?>
</table>

</body>
</html>
        <?php
        $html = ob_get_clean();

        $mpdf = new \Mpdf\Mpdf([
            'mode'             => 'utf-8',
            'default_font'     => 'kalpurush',
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
            'margin_left'      => 12,
            'margin_right'     => 12,
            'margin_top'       => 15,
            'margin_bottom'    => 15,
            'format'           => 'A4',
        ]);
        $mpdf->SetTitle('ইনক্রিমেন্ট রিপোর্ট - ' . $getEmployeeDetailsQW['employee_name']);
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', 'S');
        $base64     = base64_encode($pdfContent);

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => true,
            'pdfData'  => $base64,
            'filename' => 'increment_report_' . $getEmployeeDetailsQW['employee_id'] . '_' . date('Ymd') . '.pdf',
        ]);

    } catch (\Throwable $e) {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Show full-page PDF.js viewer
// ─────────────────────────────────────────────────────────────────────────────
function showViewer($employeeID) {
    while (ob_get_level() > 0) ob_end_clean();
    $generateUrl = '?action=generate&employeeID=' . urlencode($employeeID);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ইনক্রিমেন্ট রিপোর্ট</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #f5f5f5; overflow: hidden; }
        .toolbar {
            background: #ffffff;
            padding: 10px 18px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .toolbar-title { display: none; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #ffffff;
            color: #374151;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.15s ease;
            font-family: inherit;
            line-height: 1;
        }
        .btn svg { width: 15px; height: 15px; stroke-width: 2; }
        .btn:hover { background: #f9fafb; border-color: #d1d5db; color: #1f2937; }
        .btn:active { background: #f3f4f6; }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; background: #fafafa; color: #9ca3af; }
        .btn-primary  { background: #1e3a5f; color: #ffffff; border-color: #1e3a5f; }
        .btn-primary:hover { background: #142a48; border-color: #142a48; color: #ffffff; }
        .btn-group { display: inline-flex; gap: 4px; padding: 3px; background: #f3f4f6; border-radius: 8px; }
        .btn-group .btn { border: 0; background: transparent; padding: 6px 10px; }
        .btn-group .btn:hover { background: #ffffff; }
        .btn-icon-only { padding: 7px 9px; }

        .status {
            margin-left: auto;
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status::before { content:""; width:6px; height:6px; border-radius:50%; display:inline-block; }
        .status.loading { background: #f3f4f6; color: #6b7280; }
        .status.loading::before { background: #9ca3af; animation: pulse 1.4s ease-in-out infinite; }
        .status.ready   { background: #dcfce7; color: #166534; }
        .status.ready::before { background: #22c55e; }
        .status.error   { background: #fee2e2; color: #991b1b; }
        .status.error::before { background: #ef4444; }
        @keyframes pulse { 0%,100%{opacity:0.4} 50%{opacity:1} }
        .pdf-viewer {
            height: calc(100vh - 60px);
            background: #525252;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-y: auto;
            padding: 20px;
        }
        #pdfCanvas { max-width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); margin-bottom: 20px; }
        .page-controls {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: white; padding: 10px 20px; border-radius: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            display: none; align-items: center; gap: 15px;
        }
        .page-controls.active { display: flex; }
        .page-btn {
            background: #667eea; color: white; border: none;
            width: 36px; height: 36px; border-radius: 50%;
            cursor: pointer; font-size: 18px;
            display: flex; align-items: center; justify-content: center;
        }
        .page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .page-info { font-weight: 500; color: #2d3748; }
        .loading-screen, .error-screen {
            display: flex; justify-content: center; align-items: center;
            height: calc(100vh - 60px); flex-direction: column; gap: 20px;
        }
        .spinner {
            width: 50px; height: 50px;
            border: 4px solid #e2e8f0; border-top: 4px solid #667eea;
            border-radius: 50%; animation: spin 1s linear infinite;
        }
        @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
        .error-icon    { font-size: 64px; }
        .error-message { font-size: 16px; color: #e53e3e; max-width: 600px; text-align: center; padding: 20px; }
        .error-details {
            background: #f7fafc; padding: 15px; border-radius: 4px;
            margin-top: 10px; font-size: 12px; font-family: monospace;
            text-align: left; max-height: 200px; overflow-y: auto;
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn" onclick="loadPDF()" id="btnReload" title="রিলোড">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
        Reload
    </button>
    <button class="btn btn-primary" onclick="downloadPDF()" id="btnDownload" disabled title="ডাউনলোড">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Download
    </button>
    <button class="btn" onclick="printPDF()" id="btnPrint" disabled title="প্রিন্ট">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print
    </button>
    <div class="btn-group" style="margin-left:6px;">
        <button class="btn btn-icon-only" onclick="zoomOut()" title="জুম কমান">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
        </button>
        <button class="btn btn-icon-only" onclick="zoomIn()" title="জুম বাড়ান">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
        </button>
    </div>
    <div class="status loading" id="status">Loading</div>
</div>

<div id="loadingScreen" class="loading-screen">
    <div class="spinner"></div>
    <div>Generating PDF...</div>
</div>

<div id="errorScreen" class="error-screen" style="display:none">
    <div class="error-icon">⚠️</div>
    <div class="error-message" id="errorMessage"></div>
    <div class="error-details"  id="errorDetails"  style="display:none"></div>
    <button class="btn btn-primary" onclick="loadPDF()">Try Again</button>
</div>

<div id="pdfViewer" class="pdf-viewer" style="display:none">
    <canvas id="pdfCanvas"></canvas>
</div>

<div class="page-controls" id="pageControls">
    <button class="page-btn" onclick="prevPage()" id="btnPrev">‹</button>
    <span class="page-info">Page <span id="pageNum">1</span> of <span id="pageCount">1</span></span>
    <button class="page-btn" onclick="nextPage()" id="btnNext">›</button>
</div>

<script>
    const generateUrl = '<?= $generateUrl ?>';
    let pdfDoc = null, pageNum = 1, pageRendering = false, pageNumPending = null, scale = 1.5;
    let pdfDataBlob = null, downloadFilename = 'increment_report.pdf';

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    window.addEventListener('DOMContentLoaded', () => loadPDF());

    function setStatus(text, type = 'loading') {
        const el = document.getElementById('status');
        el.textContent = text;
        el.className = 'status ' + type;
    }

    function disableButtons(disabled) {
        document.getElementById('btnDownload').disabled = disabled;
        document.getElementById('btnPrint').disabled    = disabled;
    }

    function showLoading() {
        document.getElementById('loadingScreen').style.display = 'flex';
        document.getElementById('errorScreen').style.display   = 'none';
        document.getElementById('pdfViewer').style.display     = 'none';
        document.getElementById('pageControls').classList.remove('active');
        disableButtons(true);
    }

    function showError(message, details) {
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('errorScreen').style.display   = 'flex';
        document.getElementById('pdfViewer').style.display     = 'none';
        document.getElementById('errorMessage').textContent    = message;
        if (details) {
            document.getElementById('errorDetails').textContent    = details;
            document.getElementById('errorDetails').style.display  = 'block';
        }
        setStatus('Error', 'error');
        disableButtons(true);
    }

    function showPDF() {
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('errorScreen').style.display   = 'none';
        document.getElementById('pdfViewer').style.display     = 'flex';
        document.getElementById('pageControls').classList.add('active');
        disableButtons(false);
    }

    function loadPDF() {
        showLoading();
        setStatus('Generating...', 'loading');

        fetch(generateUrl)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.filename) downloadFilename = data.filename;
                    setStatus('✓ Ready', 'ready');

                    const binary = atob(data.pdfData);
                    const bytes  = new Uint8Array(binary.length);
                    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

                    pdfDataBlob = new Blob([bytes], { type: 'application/pdf' });
                    loadPDFDocument(bytes);
                } else {
                    showError(data.error || 'PDF generation failed', data.trace || null);
                }
            })
            .catch(e => showError('Failed to load: ' + e.message));
    }

    function loadPDFDocument(pdfData) {
        pdfjsLib.getDocument(pdfData).promise
            .then(pdf => {
                pdfDoc = pdf;
                document.getElementById('pageCount').textContent = pdf.numPages;
                renderPage(pageNum);
                showPDF();
            })
            .catch(e => showError('PDF render error: ' + e.message));
    }

    function renderPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then(page => {
            const canvas   = document.getElementById('pdfCanvas');
            const ctx      = canvas.getContext('2d');
            const viewport = page.getViewport({ scale: scale });
            canvas.height  = viewport.height;
            canvas.width   = viewport.width;

            page.render({ canvasContext: ctx, viewport: viewport }).promise.then(() => {
                pageRendering = false;
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
            });
        });
        document.getElementById('pageNum').textContent  = num;
        document.getElementById('btnPrev').disabled = num <= 1;
        document.getElementById('btnNext').disabled = num >= (pdfDoc ? pdfDoc.numPages : 1);
    }

    function queueRenderPage(num) {
        pageRendering ? (pageNumPending = num) : renderPage(num);
    }

    function prevPage() { if (pageNum > 1) { pageNum--; queueRenderPage(pageNum); } }
    function nextPage() { if (pdfDoc && pageNum < pdfDoc.numPages) { pageNum++; queueRenderPage(pageNum); } }
    function zoomIn()  { scale += 0.25; if (pdfDoc) renderPage(pageNum); }
    function zoomOut() { if (scale > 0.5) { scale -= 0.25; if (pdfDoc) renderPage(pageNum); } }

    function downloadPDF() {
        if (!pdfDataBlob) return;
        const url = URL.createObjectURL(pdfDataBlob);
        const a   = document.createElement('a');
        a.href = url; a.download = downloadFilename; a.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function printPDF() {
        if (!pdfDataBlob) return;
        const url = URL.createObjectURL(pdfDataBlob);
        window.open(url, '_blank');
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
</script>
</body>
</html>
<?php
}
