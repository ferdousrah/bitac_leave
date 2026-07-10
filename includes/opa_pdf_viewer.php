<?php
/**
 * Shared PDF viewer shell for the ঐচ্ছিক পূর্বানুমোদন (optional pre-approval) PDFs.
 *
 * Called by the three OPA PDF endpoints:
 *   - api/reports/opa-application.php
 *   - api/reports/opa-forward-note.php
 *   - api/reports/opa-office-order.php
 *
 * Each caller sets $viewer_config and includes this file:
 *   $viewer_config = [
 *       'title'         => 'পূর্বানুমোদন আবেদন',   // page title
 *       'file_prefix'   => 'opa_application',      // download filename prefix
 *       'preApprovalID' => $preApprovalID,         // OPA row id
 *   ];
 *   include(__DIR__ . '/../../includes/opa_pdf_viewer.php');
 */

$vc_title    = htmlspecialchars($viewer_config['title']         ?? 'PDF');
$vc_prefix   = htmlspecialchars($viewer_config['file_prefix']   ?? 'document');
$vc_id       = (int)($viewer_config['preApprovalID']            ?? 0);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $vc_title ?> - <?= $vc_id ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        :root { --nav:#0e1e34; --nav2:#1e3a5f; --line:#e5e7eb; --muted:#6b7280; }
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { height:100%; }
        body { font-family:'Inter','Segoe UI',Arial,sans-serif; background:#f5f7fa; overflow:hidden; color:#111827; }
        .toolbar { background:#fff; padding:10px 16px; border-bottom:1px solid var(--line); display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; cursor:pointer; font-size:0.83rem; font-weight:500; border:1px solid var(--line); background:#fff; color:#374151; transition:all .15s; }
        .btn:hover { background:#f9fafb; }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .btn-primary { background:var(--nav); border-color:var(--nav); color:#fff; }
        .btn-primary:hover { background:var(--nav2); border-color:var(--nav2); }
        .btn svg { width:16px; height:16px; stroke-width:2; }
        .pill-group { display:inline-flex; border:1px solid var(--line); border-radius:8px; overflow:hidden; }
        .pill-group .btn { border-radius:0; border:none; border-right:1px solid var(--line); }
        .pill-group .btn:last-child { border-right:none; }
        .status-pill { margin-left:auto; display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:20px; font-size:.75rem; font-weight:500; background:#eef2ff; color:#4338ca; }
        .status-pill .dot { width:6px; height:6px; border-radius:50%; background:#4338ca; animation:pulse 1.2s infinite; }
        .status-pill.ready { background:#dcfce7; color:#166534; }
        .status-pill.ready .dot { background:#166534; animation:none; }
        .status-pill.error { background:#fee2e2; color:#991b1b; }
        .status-pill.error .dot { background:#991b1b; animation:none; }
        @keyframes pulse { 0%,100% {opacity:1;} 50% {opacity:.35;} }
        .pdf-viewer { height:calc(100vh - 55px); background:#525252; display:flex; flex-direction:column; align-items:center; overflow-y:auto; padding:16px; }
        #pdfCanvas { max-width:100%; box-shadow:0 4px 20px rgba(0,0,0,.3); margin-bottom:16px; }
        .center-screen { display:flex; justify-content:center; align-items:center; height:calc(100vh - 55px); flex-direction:column; gap:14px; }
        .spinner { width:44px; height:44px; border:4px solid #e2e8f0; border-top:4px solid var(--nav); border-radius:50%; animation:spin 1s linear infinite; }
        @keyframes spin { 0% {transform:rotate(0deg);} 100% {transform:rotate(360deg);} }
        .error-icon { font-size:52px; }
        .error-message { font-size:15px; color:#dc2626; max-width:600px; text-align:center; padding:16px; }
        .error-details { background:#f7fafc; padding:12px; border-radius:6px; margin-top:8px; font-size:12px; font-family:monospace; text-align:left; max-height:200px; overflow-y:auto; max-width:80%; }
        .page-controls { position:fixed; bottom:16px; left:50%; transform:translateX(-50%); background:#fff; padding:8px 16px; border-radius:24px; box-shadow:0 4px 20px rgba(0,0,0,.2); display:none; align-items:center; gap:12px; }
        .page-controls.active { display:flex; }
        .page-btn { background:var(--nav); color:#fff; border:none; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; }
        .page-btn:disabled { opacity:.3; cursor:not-allowed; }
        .page-info { font-weight:500; color:#111827; font-size:.85rem; }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn" id="btnReload" onclick="loadPDF()" title="Reload">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
        Reload
    </button>
    <button class="btn btn-primary" id="btnDownload" onclick="downloadPDF()" title="Download">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Download
    </button>
    <button class="btn" id="btnPrint" onclick="printPDF()" title="Print">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print
    </button>
    <div class="pill-group">
        <button class="btn" onclick="zoomOut()" title="Zoom out"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM13 10H7"/></svg></button>
        <button class="btn" onclick="zoomIn()" title="Zoom in"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg></button>
    </div>
    <div class="status-pill" id="status"><span class="dot"></span><span id="statusText">Loading...</span></div>
</div>

<div id="loadingScreen" class="center-screen">
    <div class="spinner"></div>
    <div style="color:#374151;font-size:.9rem;">PDF তৈরি হচ্ছে...</div>
</div>

<div id="errorScreen" class="center-screen" style="display:none;">
    <div class="error-icon">⚠️</div>
    <div class="error-message" id="errorMessage"></div>
    <div class="error-details" id="errorDetails" style="display:none;"></div>
    <button class="btn btn-primary" onclick="loadPDF()">Try Again</button>
</div>

<div id="pdfViewer" class="pdf-viewer" style="display:none;">
    <canvas id="pdfCanvas"></canvas>
</div>

<div class="page-controls" id="pageControls">
    <button class="page-btn" onclick="prevPage()" id="btnPrev">‹</button>
    <span class="page-info">Page <span id="pageNum">1</span> of <span id="pageCount">1</span></span>
    <button class="page-btn" onclick="nextPage()" id="btnNext">›</button>
</div>

<script>
    const preApprovalID = <?= $vc_id ?>;
    const filePrefix    = <?= json_encode($vc_prefix) ?>;
    let pdfDoc = null, pageNum = 1, pageRendering = false, pageNumPending = null, scale = 1.5;
    let pdfDataBlob = null;

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    window.addEventListener('DOMContentLoaded', loadPDF);

    function setStatus(text, type='loading') {
        document.getElementById('statusText').textContent = text;
        const el = document.getElementById('status');
        el.className = 'status-pill' + (type === 'loading' ? '' : ' ' + type);
    }
    function disableBtns(d) { ['btnReload','btnDownload','btnPrint'].forEach(id => document.getElementById(id).disabled = d); }
    function showLoading() {
        document.getElementById('loadingScreen').style.display = 'flex';
        document.getElementById('errorScreen').style.display = 'none';
        document.getElementById('pdfViewer').style.display = 'none';
        document.getElementById('pageControls').classList.remove('active');
        disableBtns(true);
    }
    function showError(msg, details=null) {
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('errorScreen').style.display = 'flex';
        document.getElementById('pdfViewer').style.display = 'none';
        document.getElementById('errorMessage').textContent = msg;
        if (details) { document.getElementById('errorDetails').textContent = details; document.getElementById('errorDetails').style.display = 'block'; }
        setStatus('Error', 'error');
        disableBtns(false);
    }
    function showPDF() {
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('errorScreen').style.display = 'none';
        document.getElementById('pdfViewer').style.display = 'flex';
        document.getElementById('pageControls').classList.add('active');
        disableBtns(false);
    }
    function loadPDF() {
        showLoading(); setStatus('Generating...', 'loading');
        fetch(`?action=generate&id=${preApprovalID}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    setStatus('Ready', 'ready');
                    const bin = atob(data.pdfData);
                    const bytes = new Uint8Array(bin.length);
                    for (let i=0; i<bin.length; i++) bytes[i] = bin.charCodeAt(i);
                    pdfDataBlob = new Blob([bytes], { type:'application/pdf' });
                    loadPDFDocument(bytes);
                } else {
                    showError(data.error || 'Unknown error', data.trace || null);
                }
            })
            .catch(e => showError('Failed to load: ' + e.message));
    }
    function loadPDFDocument(data) {
        pdfjsLib.getDocument(data).promise.then(pdf => {
            pdfDoc = pdf;
            document.getElementById('pageCount').textContent = pdf.numPages;
            renderPage(pageNum); showPDF();
        }).catch(e => showError('PDF Error: ' + e.message));
    }
    function renderPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then(page => {
            const canvas = document.getElementById('pdfCanvas');
            const ctx = canvas.getContext('2d');
            const vp = page.getViewport({ scale });
            canvas.height = vp.height; canvas.width = vp.width;
            page.render({ canvasContext:ctx, viewport:vp }).promise.then(() => {
                pageRendering = false;
                if (pageNumPending !== null) { renderPage(pageNumPending); pageNumPending = null; }
            });
        });
        document.getElementById('pageNum').textContent = num;
        document.getElementById('btnPrev').disabled = num <= 1;
        document.getElementById('btnNext').disabled = num >= pdfDoc.numPages;
    }
    function queueRenderPage(num) { pageRendering ? pageNumPending = num : renderPage(num); }
    function prevPage() { if (pageNum > 1) { pageNum--; queueRenderPage(pageNum); } }
    function nextPage() { if (pageNum < pdfDoc.numPages) { pageNum++; queueRenderPage(pageNum); } }
    function zoomIn() { scale += 0.25; renderPage(pageNum); }
    function zoomOut() { if (scale > 0.5) { scale -= 0.25; renderPage(pageNum); } }
    function downloadPDF() {
        if (!pdfDataBlob) return;
        const url = URL.createObjectURL(pdfDataBlob);
        const a = document.createElement('a');
        a.href = url; a.download = `${filePrefix}_${preApprovalID}.pdf`; a.click();
        URL.revokeObjectURL(url);
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
