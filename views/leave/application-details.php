<?php
/**
 * Leave Application PDF Viewer - In-Memory Version
 * NO FILES SAVED - Everything in memory!
 * 
 * Usage: leave_pdf_viewer.php?leaveApplicationID=5516
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');
set_time_limit(120);

// Get parameters
$action = $_GET['action'] ?? 'view';
$leaveApplicationID = isset($_GET['leaveApplicationID']) ? (int)$_GET['leaveApplicationID'] : 0;

// Better error handling
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
            a { color: #667eea; text-decoration: none; font-weight: bold; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Missing Parameter</h1>
            <p>You need to provide a leave application ID in the URL.</p>
            
            <div class="info">
                <strong>How to use:</strong>
                <div class="example">leave_pdf_viewer.php?leaveApplicationID=<strong>5516</strong></div>
            </div>
            
            <p><strong>Examples:</strong></p>
            <ul>
                <li><a href="?leaveApplicationID=5516">View Leave ID 5516</a></li>
                <li><a href="?leaveApplicationID=5517">View Leave ID 5517</a></li>
                <li><a href="?leaveApplicationID=5518">View Leave ID 5518</a></li>
            </ul>
            
            <p><strong>Debug Info:</strong></p>
            <div class="example">
                Current URL: <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?><br>
                leaveApplicationID parameter: <?= var_export($_GET['leaveApplicationID'] ?? 'NOT SET', true) ?><br>
                Value received: <?= $leaveApplicationID ?>
            </div>
            
            <p><strong>From PHP code:</strong></p>
            <div class="example">
                &lt;a href="leave_pdf_viewer.php?leaveApplicationID=&lt;?= $dataID ?&gt;"&gt;<br>
                &nbsp;&nbsp;View Leave Application<br>
                &lt;/a&gt;
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

/**
 * Legacy signature blobs were stored via real_escape_string concatenation, which double-
 * escaped binary bytes — e.g. CR (0x0D) became literal "\\r" (2 chars 0x5C 0x72) in the
 * BLOB. Detect that pattern via the PNG magic header and unescape back to true binary.
 * Properly-stored signatures (from prepared statements + send_long_data) pass through
 * untouched.
 */
function bitac_unescape_legacy_signature($sig) {
    if (!is_string($sig) || strlen($sig) < 8) return $sig;
    // Valid PNG: \x89 P N G \r \n \x1a \n  (bytes 0..7)
    // Corrupt:    \x89 P N G \ r \ n        (bytes 5..6 = 0x5C 0x72)
    if (substr($sig, 0, 4) === "\x89PNG" && substr($sig, 4, 2) === '\\r') {
        return strtr($sig, [
            '\\0'  => "\0",
            '\\r'  => "\r",
            '\\n'  => "\n",
            '\\Z'  => "\x1a",
            "\\'"  => "'",
            '\\"'  => '"',
            '\\\\' => '\\',
        ]);
    }
    return $sig;
}

/**
 * Generate PDF and output as base64
 */
function generatePDFData($leaveApplicationID) {
    try {
        require_once(__DIR__ . '/../../config/connection.php');
        require_once(LIBRARY_PATH . '/number_converter.php');

        // Load mPDF
        $autoload_paths = [
            __DIR__ . '/../../vendor/autoload.php',
            '/home/ferdous/ftp/bitac.technocratsbd.com/vendor/autoload.php',
        ];
        
        $autoload_found = false;
        foreach ($autoload_paths as $path) {
            if (file_exists($path)) {
                require_once($path);
                $autoload_found = true;
                break;
            }
        }
        
        if (!$autoload_found) {
            throw new Exception("mPDF not found. Checked paths: " . implode(', ', $autoload_paths));
        }
        
        if (!class_exists('\Mpdf\Mpdf')) {
            throw new Exception("mPDF class not found. Make sure mPDF is properly installed.");
        }
        
        if (!isset($con) || !$con) {
            throw new Exception("Database connection failed. Check connection.php");
        }
        
        function dateDiffInDays($date1, $date2) {
            return abs(round((strtotime($date2) - strtotime($date1)) / 86400));
        }
        
        // Get all data
        $stmt = $con->prepare("SELECT * FROM leave_applications WHERE dataID = ?");
        if (!$stmt) {
            throw new Exception("Database error: " . $con->error);
        }
        
        $stmt->bind_param("i", $leaveApplicationID);
        $stmt->execute();
        $leaveData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$leaveData) {
            throw new Exception("Leave application not found with ID: " . $leaveApplicationID);
        }
        
        $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
        $stmt->bind_param("i", $leaveData['applicantID']);
        $stmt->execute();
        $empData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$empData) {
            throw new Exception("Employee not found");
        }
        
        $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
        $stmt->bind_param("i", $leaveData['designation_id']);
        $stmt->execute();
        $designationData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $stmt = $con->prepare("SELECT * FROM sections WHERE id = ?");
        $stmt->bind_param("i", $leaveData['section_id']);
        $stmt->execute();
        $sectionData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
        $stmt->bind_param("i", $leaveData['organization_id']);
        $stmt->execute();
        $orgData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $stmt = $con->prepare("SELECT * FROM leave_types WHERE leaveID = ?");
        $stmt->bind_param("s", $leaveData['leaveType']);
        $stmt->execute();
        $leaveTypeData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $dateDiff = dateDiffInDays($leaveData['dateFrom'], $leaveData['dateTo']) + 1;
        $dateF = date_create($leaveData['dateFrom']);
        $dateT = date_create($leaveData['dateTo']);
        $submitDate = date_create($leaveData['submitDate']);
        
        $stmt = $con->prepare("SELECT * FROM leave_data_for_approval WHERE leaveApplicationID = ? AND isSupervisor = 1 AND serial = '1'");
        $stmt->bind_param("i", $leaveApplicationID);
        $stmt->execute();
        $supervisorApproval = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $supervisorData = null;
        $supDesignationData = null;
        $supSectionData = null;
        $supOrgData = null;
        
        if ($supervisorApproval && !empty($supervisorApproval['signatory'])) {
            $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
            $stmt->bind_param("i", $supervisorApproval['signatory']);
            $stmt->execute();
            $supervisorData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($supervisorData) {
                $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
                $stmt->bind_param("i", $supervisorApproval['designation_id']);
                $stmt->execute();
                $supDesignationData = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $stmt = $con->prepare("SELECT * FROM sections WHERE id = ?");
                $stmt->bind_param("i", $supervisorApproval['section_id']);
                $stmt->execute();
                $supSectionData = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
                $stmt->bind_param("i", $supervisorApproval['organization_id']);
                $stmt->execute();
                $supOrgData = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
        
        // Build HTML
        $html = '<style>
            body { font-family: "Kalpurush", "SolaimanLipi", "Nikosh", Arial, sans-serif; font-size: 14px; line-height: 1.6; }
            p { margin: 10px 0; text-align: justify; }
            .text-right { text-align: right; }
            .underline { text-decoration: underline; }
            table { width: 100%; border-collapse: collapse; }
            .signature-section { text-align: center; margin-top: 20px; }
            .signature-img { height: 40px; }
            .small-text { font-size: 11px; }
        </style>';
        
        $html .= '<div class="text-right">তারিখ ও সময়: ' . banglaNumber(date_format($submitDate,"d/m/Y"));
        if (!empty($leaveData['submitTime'])) {
            $html .= " " . banglaNumber($leaveData['submitTime']);
        }
        $html .= '</div>';
        
        $html .= '<p>বরাবর<br>';
        
        $orgId = (int)$leaveData['organization_id'];
        $appTo = (int)$leaveData['applicationTo'];
        
        if ($orgId == 4) {
            $html .= ($appTo == 1) ? "পরিচালক (প্রশাসন ও অর্থ)" : "মহাপরিচালক";
        } else if (in_array($orgId, [5, 6, 7, 8, 9])) {
            if ($appTo == 1) {
                $html .= "অতিরিক্ত পরিচালক (কেন্দ্র প্রধান)";
            }
        }
        
        $html .= '<br>' . htmlspecialchars($orgData['organization_name']) . '<br>';
        $html .= htmlspecialchars($orgData['address']) . '</p>';
        
        $html .= '<p>মাধ্যম: যথাযথ কর্তৃপক্ষ । </p>';
        $html .= '<p>বিষয়: ' . htmlspecialchars($leaveData['subject']) . '</p><br>';
        $html .= '<p>মহোদয়,</p>';
        
        // ── Fetch segments (PDF = applicant's letter, so use 'requested' kind) ──
        // Falls back to all rows if 'kind' column doesn't exist yet (backward compat)
        $hasKindCol = false;
        $colCheck = $con->query("SHOW COLUMNS FROM leave_application_segments LIKE 'kind'");
        if ($colCheck && $colCheck->num_rows > 0) { $hasKindCol = true; }

        $kindFilter = $hasKindCol ? " AND s.kind = 'requested' " : "";
        $segStmt = $con->prepare(
            "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
             LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
             WHERE s.applicationID = ? $kindFilter
             ORDER BY s.serial ASC, s.dateFrom ASC");
        $segStmt->bind_param("i", $leaveApplicationID);
        $segStmt->execute();
        $appSegRows = $segStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $segStmt->close();
        $isMulti = count($appSegRows) > 1;
        $totalSegDays = $isMulti ? array_sum(array_column($appSegRows, 'days')) : $dateDiff;

        // Build the segments inline phrase: "X দিনের A (D1 হইতে D2) এবং Y দিনের B (D3 হইতে D4)"
        $segPhrase = '';
        if ($isMulti) {
            $parts = [];
            foreach ($appSegRows as $sg) {
                $parts[] = banglaNumber((int)$sg['days']) . ' দিনের ' . htmlspecialchars($sg['leaveTitle'] ?? 'অজানা')
                         . ' (ইং <span class="underline">' . banglaNumber(date('d/m/Y', strtotime($sg['dateFrom']))) . '</span>'
                         . ' হইতে <span class="underline">' . banglaNumber(date('d/m/Y', strtotime($sg['dateTo']))) . '</span> পর্যন্ত)';
            }
            // Join with "এবং" between last two; "," between others
            if (count($parts) === 2) {
                $segPhrase = $parts[0] . ' এবং ' . $parts[1];
            } else {
                $last = array_pop($parts);
                $segPhrase = implode(', ', $parts) . ' এবং ' . $last;
            }
        }

        if ($leaveData['applicationType'] == 1) {
            if ($isMulti) {
                $html .= '<p>&nbsp;&nbsp;&nbsp;যথাবিহীত সম্মান প্রদর্শনপূর্বক নিবেদন এই যে, আমি নিম্নস্বাক্ষরকারী  ' .
                         htmlspecialchars($leaveData['leaveApplication']) . '  ' . $segPhrase .
                         ' — সর্বমোট <span class="underline">' . banglaNumber($totalSegDays) . '</span> দিনের ছুটি প্রয়োজন ।</p>';

                $html .= '<p>&nbsp;&nbsp;&nbsp;অতএব উপর্যুক্ত প্রেক্ষিতে আমাকে উপরোল্লিখিত ' .
                         banglaNumber($totalSegDays) . ' দিনের ছুটি প্রদান করতে মহোদয়ের নিকট বিনীত প্রার্থনা করছি।</p>';
            } else {
                $html .= '<p>&nbsp;&nbsp;&nbsp;যথাবিহীত সম্মান প্রদর্শনপূর্বক নিবেদন এই যে,আমি নিম্নস্বাক্ষরকারী  ' .
                         htmlspecialchars($leaveData['leaveApplication']) . '  ইং <span class="underline">' .
                         banglaNumber(date_format($dateF,"d/m/Y")) . '</span> হইতে <span class="underline">' .
                         banglaNumber(date_format($dateT,"d/m/Y")) . '</span> পর্যন্ত <span class="underline">' .
                         banglaNumber($dateDiff) . '</span> দিনের ' . htmlspecialchars($leaveTypeData['leaveTitle']) . ' ছুটি প্রয়োজন ।</p>';

                $html .= '<p>&nbsp;&nbsp;&nbsp;অতএব উপর্যুক্ত প্রেক্ষিতে আমাকে উপরোল্লিখিত ' .
                         banglaNumber($dateDiff) . ' দিনের ' . htmlspecialchars($leaveTypeData['leaveTitle']) .
                         ' ছুটি প্রদান করতে মহোদয়ের নিকট বিনীত প্রার্থনা করছি।</p>';
            }

        } else if ($leaveData['applicationType'] == 2) {
            $html .= '<p>&nbsp;&nbsp;&nbsp;যথাবিহীত সম্মান প্রদর্শনপূর্বক নিবেদন এই যে,আমি নিম্নস্বাক্ষরকারী  ' .
                     htmlspecialchars($leaveData['leaveApplication']) . '  ইং <span class="underline">' .
                     banglaNumber(date_format($dateF,"d/m/Y")) . '</span> হইতে <span class="underline">' .
                     banglaNumber(date_format($dateT,"d/m/Y")) . '</span> পর্যন্ত অফিসে উপস্থিত হতে পারিনি। বিষয়টি কর্তৃপক্ষকে পূর্বে অবগত ';

            $html .= ($leaveData['isinformed'] == 1) ? 'করেছি' : 'করতে পারিনি';
            $html .= '।</p>';

            if ($isMulti) {
                $html .= '<p>&nbsp;&nbsp;&nbsp;অতএব উপর্যুক্ত বিষয়টি বিবেচনাপূর্বক আমাকে আমার অনুপস্থিতকালের জন্য ' .
                         $segPhrase . ' — সর্বমোট ' . banglaNumber($totalSegDays) .
                         ' দিনের ছুটি মঞ্জুরকরতঃ অফিসে যোগদানের অনুমতি প্রদান করতে মহোদয়ের নিকট বিনীত প্রার্থনা করছি।</p>';
            } else {
                $html .= '<p>&nbsp;&nbsp;&nbsp;অতএব উপর্যুক্ত বিষয়টি বিবেচনাপূর্বক আমাকে আমার অনুপস্থিতকালের জন্য ' .
                         banglaNumber($dateDiff) . ' দিনের ' . htmlspecialchars($leaveTypeData['leaveTitle']) .
                         ' ছুটি মঞ্জুরকরতঃ অফিসে যোগদানের অনুমতি প্রদান করতে মহোদয়ের নিকট বিনীত প্রার্থনা করছি।</p>';
            }
        }

        $html .= '<p>&nbsp;</p>';

        // Applicant signature
        $html .= '<table><tr><td width="60%">&nbsp;</td><td width="40%" class="signature-section">';
        $html .= 'নিবেদক<br>_________________________________<br>';
        
        if (!empty($leaveData['signature'])) {
            $sig = bitac_unescape_legacy_signature($leaveData['signature']);

            // Detect image type
            $imageType = 'image/png'; // default
            $decoded = @base64_decode($sig, true);
            
            if ($decoded !== false) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = @$finfo->buffer($decoded);
                
                if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
                    $imageType = 'image/jpeg';
                } else if ($mimeType === 'image/png') {
                    $imageType = 'image/png';
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
                $html .= '<img src="data:' . $imageType . ';base64,' . $sigBase64 . '" style="max-width:150px; max-height:80px; display:block; margin:5px 0;" /><br>';
            }
        }
        
        $html .= '<span class="small-text">' . banglaNumber(date_format($submitDate,"d.m.Y")) . '</span><br>';
        $html .= htmlspecialchars(str_replace("জনাব ", "", $empData['employee_name'])) . '<br>';
        $html .= htmlspecialchars($designationData['job_title_name']) . '<br>';
        $html .= htmlspecialchars($sectionData['section_name']) . ', ' . htmlspecialchars($orgData['organization_name']);
        $html .= '</td></tr></table>';
        
        $html .= '<p>&nbsp;</p>';
        
        // Supervisor signature
        $html .= '<table><tr><td width="60%">&nbsp;</td><td width="40%" class="signature-section">';
        $html .= 'বিভাগীয় প্রধান<br>_________________________________<br>';
        
        if ($supervisorApproval && !empty($supervisorApproval['signature'])) {
            $sig = bitac_unescape_legacy_signature($supervisorApproval['signature']);

            // Detect image type
            $imageType = 'image/png'; // default
            $decoded = @base64_decode($sig, true);
            
            if ($decoded !== false) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = @$finfo->buffer($decoded);
                
                if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
                    $imageType = 'image/jpeg';
                } else if ($mimeType === 'image/png') {
                    $imageType = 'image/png';
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
                $html .= '<img src="data:' . $imageType . ';base64,' . $sigBase64 . '" style="max-width:150px; max-height:80px; display:block; margin:5px 0;" /><br>';
            }
        }
        
        if ($supervisorApproval && !empty($supervisorApproval['approvedDate'])) { 
            $sapprovedDate = date_create($supervisorApproval['approvedDate']); 
            $html .= '<span class="small-text">' . banglaNumber(date_format($sapprovedDate,"d.m.Y")) . '</span><br>';
        }
        
        if ($supervisorData) {
            $html .= htmlspecialchars(str_replace("জনাব ", "", $supervisorData['employee_name'])) . '<br>';
            if ($supDesignationData) {
                $html .= htmlspecialchars($supDesignationData['job_title_name']) . '<br>';
            }
            if ($supSectionData && $supOrgData) {
                $html .= htmlspecialchars($supSectionData['section_name']) . ', ' . htmlspecialchars($supOrgData['organization_name']);
            }
        }
        
        $html .= '</td></tr></table>';

        // ── Return history callout ──────────────────────────────────
        // Rendered below the signatures so the letter + signatories
        // read cleanly first and the ফেরত note appears as a footer.
        // Table markup + color-only emphasis because mPDF + Bangla
        // fonts silently drop <strong>/<b> tags.
        $_rhCheck = $con->query("SHOW TABLES LIKE 'leave_return_history'");
        if ($_rhCheck && $_rhCheck->num_rows > 0) {
            // Every return event in chronological order — same application
            // can be sent back multiple times (supervisor → applicant,
            // signatory → previous signatory, etc.), and each event needs
            // to be visible so the reader can trace the full ফেরত history.
            $_rh = $con->query(
                "SELECT returnedByName, returnedByTitle, returnType,
                        returnedToName, note, createdAt
                 FROM leave_return_history
                 WHERE leaveApplicationID = " . (int)$leaveApplicationID . "
                 ORDER BY createdAt ASC, dataID ASC");
            $_rhRows = [];
            while ($_rh && $_row = $_rh->fetch_assoc()) $_rhRows[] = $_row;

            if (!empty($_rhRows)) {
                $_multi = count($_rhRows) > 1;
                $_headerLine = $_multi
                    ? 'পুনঃ যাচাই — ফেরত প্রেরণ (' . banglaNumber(count($_rhRows)) . ' বার)'
                    : 'পুনঃ যাচাই — ফেরত প্রেরণ';
                $_typeLabels = [
                    'to_applicant'          => 'আবেদনকারী',
                    'to_previous_signatory' => 'পূর্ববর্তী সিদ্ধান্তকারী',
                    'to_admin'              => 'প্রশাসনিক ডেস্ক',
                ];

                $html .= '<p>&nbsp;</p>'
                       . '<table cellpadding="8" cellspacing="0" style="width:100%;border:1px solid #d4a056;background:#fff8e6;margin-top:12px;">'
                       . '<tr><td style="color:#8b5a1a;font-size:13px;line-height:1.4;border-bottom:1px solid #f0d9a8;">'
                       . $_headerLine
                       . '</td></tr>';

                foreach ($_rhRows as $_i => $_rhRow) {
                    $_rBy    = trim($_rhRow['returnedByName']  ?? '');
                    $_rTitle = trim($_rhRow['returnedByTitle'] ?? '');
                    $_rNote  = trim($_rhRow['note']            ?? '');
                    $_rWhen  = !empty($_rhRow['createdAt'])
                        ? banglaNumber(date('d/m/Y', strtotime($_rhRow['createdAt'])))
                        : '';
                    $_rType  = $_rhRow['returnType'] ?? '';
                    $_rToLbl = $_typeLabels[$_rType] ?? '';
                    $_rToNm  = trim($_rhRow['returnedToName'] ?? '');
                    $_target = $_rToLbl !== ''
                        ? ($_rToLbl . ($_rToNm !== '' && $_rToNm !== $_rToLbl ? ' (' . htmlspecialchars($_rToNm) . ')' : ''))
                        : ($_rToNm !== '' ? htmlspecialchars($_rToNm) : '');

                    $_bodyLine = ($_multi ? banglaNumber($_i + 1) . '। ' : '')
                        . 'এই আবেদনটি '
                        . ($_rBy !== ''    ? htmlspecialchars($_rBy) : 'সংশ্লিষ্ট কর্তৃপক্ষ')
                        . ($_rTitle !== '' ? ' (' . htmlspecialchars($_rTitle) . ')' : '')
                        . ' কর্তৃক'
                        . ($_rWhen !== ''  ? ' ' . $_rWhen . ' তারিখে' : '')
                        . ($_target !== '' ? ' ' . $_target . '-এর কাছে' : '')
                        . ' সংশোধনের জন্য ফেরত পাঠানো হয়েছিল।';
                    $_noteLine = ($_rNote !== '') ? '<br>কারণ: ' . nl2br(htmlspecialchars($_rNote)) : '';

                    $_lastRow = ($_i === count($_rhRows) - 1);
                    $html .= '<tr><td style="color:#5d3f1c;font-size:12px;line-height:1.6;'
                          .  ($_lastRow ? '' : 'border-bottom:1px dashed #f0d9a8;')
                          .  '">'
                          .  $_bodyLine
                          .  $_noteLine
                          .  '</td></tr>';
                }
                $html .= '</table>';
            }
        }

        // Create PDF in memory
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
        
        $mpdf->SetTitle('Leave Application - ' . $empData['employee_name']);
        $mpdf->WriteHTML($html);
        
        // Output as base64
        $pdfContent = $mpdf->Output('', 'S');
        $base64 = base64_encode($pdfContent);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'pdfData' => $base64,
            'title' => 'Leave Application - ' . $empData['employee_name']
        ]);
        
    } catch (\Throwable $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Show viewer
 */
function showViewer($leaveApplicationID) {
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Application - ID: <?= $leaveApplicationID ?></title>
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
                        
                        // Convert base64 to binary
                        const binaryString = atob(data.pdfData);
                        const bytes = new Uint8Array(binaryString.length);
                        for (let i = 0; i < binaryString.length; i++) {
                            bytes[i] = binaryString.charCodeAt(i);
                        }
                        
                        // Store for download
                        pdfDataBlob = new Blob([bytes], { type: 'application/pdf' });
                        
                        // Load PDF
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
                a.download = `leave_${leaveApplicationID}.pdf`;
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