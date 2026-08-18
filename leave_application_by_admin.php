<?php
/**
 * Leave Approval Note Viewer - In-Memory Version
 * Shows approval process with multiple signatories
 * 
 * Usage: leave_approval_note_viewer.php?leaveApplicationID=5516
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
            a { color: #667eea; text-decoration: none; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Missing Parameter</h1>
            <p>You need to provide a leave application ID.</p>
            <div class="info">
                <strong>Usage:</strong>
                <div class="example">leave_approval_note_viewer.php?leaveApplicationID=<strong>5516</strong></div>
            </div>
            <p><strong>From PHP:</strong></p>
            <div class="example">
                &lt;a href="leave_approval_note_viewer.php?leaveApplicationID=&lt;?= $dataID ?&gt;"&gt;<br>
                &nbsp;&nbsp;View Approval Note<br>
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

function generatePDFData($leaveApplicationID) {
    try {
        require_once('connection.php');
        require_once('library/number_converter.php');
        
        // Load mPDF
        $autoload_paths = [
            __DIR__ . '/vendor/autoload.php',
            __DIR__ . '/mpdf/vendor/autoload.php',
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
        
        // Get employee data
        $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
        $stmt->bind_param("i", $leaveData['applicantID']);
        $stmt->execute();
        $empData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get designation
        $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
        $stmt->bind_param("i", $leaveData['designation_id']);
        $stmt->execute();
        $designationData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get section
        $stmt = $con->prepare("SELECT * FROM sections WHERE id = ?");
        $stmt->bind_param("i", $leaveData['section_id']);
        $stmt->execute();
        $sectionData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get organization
        $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
        $stmt->bind_param("i", $leaveData['organization_id']);
        $stmt->execute();
        $orgData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get leave type
        $stmt = $con->prepare("SELECT * FROM leave_types WHERE leaveID = ?");
        $stmt->bind_param("s", $leaveData['leaveType']);
        $stmt->execute();
        $leaveTypeData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Calculate dates
        $dateDiff = dateDiffInDays($leaveData['dateFrom'], $leaveData['dateTo']) + 1;
        $dateF = date_create($leaveData['dateFrom']);
        $dateT = date_create($leaveData['dateTo']);
        $submitDate = date_create($leaveData['submitDate']);

        // ── Fetch requested segments (frozen, applicant's original ask) ──
        $hasKindCol = false;
        $colC = $con->query("SHOW COLUMNS FROM leave_application_segments LIKE 'kind'");
        if ($colC && $colC->num_rows > 0) $hasKindCol = true;
        $kindFilter = $hasKindCol ? " AND s.kind = 'requested' " : "";
        $segS = $con->prepare("SELECT s.*, lt.leaveTitle FROM leave_application_segments s
            LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
            WHERE s.applicationID = ? $kindFilter
            ORDER BY s.serial ASC, s.dateFrom ASC");
        $segS->bind_param("i", $leaveApplicationID);
        $segS->execute();
        $reqSegRows = $segS->get_result()->fetch_all(MYSQLI_ASSOC);
        $segS->close();
        $isMultiSeg = count($reqSegRows) > 1;
        $totalSegDays = $isMultiSeg ? array_sum(array_column($reqSegRows, 'days')) : $dateDiff;
        
        // Get supervisor comment
        $stmt = $con->prepare("SELECT * FROM leave_data_for_approval WHERE leaveApplicationID = ? AND isSupervisor = 1");
        $stmt->bind_param("i", $leaveApplicationID);
        $stmt->execute();
        $supervisorComment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get initiator details
        $stmt = $con->prepare("SELECT * FROM user_list WHERE dataID = ?");
        $stmt->bind_param("i", $leaveData['adminInitiator']);
        $stmt->execute();
        $initiatorUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $initiatorData = null;
        $initiatorDesignation = null;
        
        if ($initiatorUser && !empty($initiatorUser['employee_id'])) {
            $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
            $stmt->bind_param("i", $initiatorUser['employee_id']);
            $stmt->execute();
            $initiatorData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($initiatorData) {
                $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
                $stmt->bind_param("i", $initiatorData['adminInitiatorDesignation']);
                $stmt->execute();
                $initiatorDesignation = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // admin initiator organization
                $orgData = null;
                if (!empty($initiatorData['adminInitiatorOrganization'])) {
                    $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
                    $stmt->bind_param("i", $initiatorData['adminInitiatorOrganization']);
                    $stmt->execute();
                    $orgData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
            }
        }
        
        // Get all signatories
        $stmt = $con->prepare("SELECT * FROM leave_data_for_approval WHERE leaveApplicationID = ? AND isSupervisor = 0");
        $stmt->bind_param("i", $leaveApplicationID);
        $stmt->execute();
        $signatories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Build HTML
        $html = '<style>
            body { font-family: "Kalpurush", "SolaimanLipi", "Nikosh", Arial, sans-serif; font-size: 14px; line-height: 1.6; }
            p { margin: 10px 0; text-align: justify; }
            .text-center { text-align: center; }
            .heading { font-family: "Kalpurush", "SolaimanLipi", "Nikosh", Arial, sans-serif; text-align: center; margin: 20px 0;  font-size: 16px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            table td { border: 1px solid #000; padding: 8px; vertical-align: top; }
            .signature-img { height: 60px; }
            .small-text { font-size: 11px; }
            .role-cell { text-align: center; vertical-align: middle; }
            .qr-block { margin-top: 18px; text-align: center; }
            .qr-caption { font-size: 10px; text-align: center; }
            .date-line { margin-bottom: 2px; }
        </style>';
        
        // Header
        $html .= '<p class="text-center">';
        $html .= '<span style="font-size: 20px;">বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)</span><br>';
        $html .= '<span style="font-size: 14px;">' . htmlspecialchars($orgData['address']) . '</span>';
        $html .= '</p>';
        
        $html .= '<p>&nbsp;</p>';
        
        // Title (multi-segment combined types)
        $subjectTypes = $isMultiSeg
            ? implode(' + ', array_unique(array_column($reqSegRows, 'leaveTitle')))
            : ($leaveTypeData['leaveTitle'] ?? '');
        $html .= '<p class="text-center">';
        $html .= '<span style="font-size: 16px;">বিষয়:&nbsp;</span>';
        $html .= '<span style="font-size: 16px;">' . htmlspecialchars($subjectTypes) . '</span>';
		$html .= '<span style="font-size: 16px;">&nbsp;ছুটির নোট অনুমোদন</span>';
        $html .= '</p>';

        // Main paragraph
        $html .= '<p>';
        $html .= htmlspecialchars($empData['employee_name']) . ', ';
        $html .= htmlspecialchars($designationData['job_title_name']) . ', ';
        $html .= htmlspecialchars($sectionData['section_name']) . ', ';
        $html .= 'বিটাক এর ' . banglaNumber(date_format($submitDate, "d/m/Y")) . ' খ্রি: তারিখের আবেদন অনুযায়ী ';
        $html .= htmlspecialchars($leaveData['leaveApplication']) . ' ';

        if ($isMultiSeg) {
            // Build inline phrase: "৩ দিনের পূর্ণ গড় বেতনে (০৩/০৫ হতে ০৫/০৫) এবং ১ দিনের অর্ধ-গড় বেতনে (০৬/০৫ হতে ০৬/০৫)"
            $segParts = [];
            foreach ($reqSegRows as $sg) {
                $segParts[] = banglaNumber((int)$sg['days']) . ' দিনের ' . htmlspecialchars($sg['leaveTitle'] ?? 'অজানা') . 'র ছুটি ('
                    . banglaNumber(date('d/m/Y', strtotime($sg['dateFrom']))) . ' হতে '
                    . banglaNumber(date('d/m/Y', strtotime($sg['dateTo']))) . ')';
            }
            if (count($segParts) === 2) {
                $segPhrase = $segParts[0] . ' এবং ' . $segParts[1];
            } else {
                $last = array_pop($segParts);
                $segPhrase = implode(', ', $segParts) . ' এবং ' . $last;
            }
            $html .= $segPhrase . ' — সর্বমোট ' . banglaNumber($totalSegDays) . ' দিনের ছুটির জন্য আবেদন করেছেন এবং তাতে বিভাগীয় প্রধান \'';
        } else {
            $html .= banglaNumber(date_format($dateF, "d/m/Y")) . ' হতে ';
            $html .= banglaNumber(date_format($dateT, "d/m/Y")) . ' তারিখ পর্যন্ত ';
            $html .= banglaNumber($dateDiff) . ' দিনের ';
            $html .= htmlspecialchars($leaveTypeData['leaveTitle'] ?? '') . 'র ছুটির জন্য আবেদন করেছেন এবং তাতে বিভাগীয় প্রধান \'';
        }
        $html .= htmlspecialchars($supervisorComment['note'] ?? '') . '\' মর্মে মন্তব্য করেছেন । ';
        
        // Leave balance info
        $html .= 'তার ছুটির তালিকায় ';
        
        if ($leaveData['leaveType'] == 8 && !empty($leaveData['casual'])) {
            $html .= banglaNumber($leaveData['casual']) . ' দিনের নৈমিত্তিক (Casual Leave) ছুটি, ';
        }
        
        if ($leaveData['leaveType'] == 18 && !empty($leaveData['optionalLBalance'])) {
            $html .= banglaNumber($leaveData['optionalLBalance']) . ' দিনের ঐচ্ছিক ছুটি, ';
        }
        
        $html .= banglaNumber($leaveData['fullSalaryYear']) . ' বছর ';
        $html .= banglaNumber($leaveData['fullSalaryMonth']) . ' মাস ';
        $html .= banglaNumber($leaveData['fullSalaryDay']) . ' দিনের গড় বেতনে ছুটি এবং ';
        $html .= banglaNumber($leaveData['halfSalaryYear']) . ' বছর ';
        $html .= banglaNumber($leaveData['halfSalaryMonth']) . ' মাস ';
        $html .= banglaNumber($leaveData['halfSalaryDay']) . ' দিনের অর্ধ-গড় বেতনে ছুটি জমা আছে ।';
        $html .= '</p>';
        
        $html .= '<p>&nbsp;</p>';
        
        // Approval table — same layout as the joining note and the printed BITAC
        // form: a role column grouping the desks, then name and post, the leave
        // being proposed, remarks and signature.

        // Supervisor (বিভাগীয় প্রধান) — fetched above as $supervisorComment for the
        // paragraph; the form needs their name, post and signature as well.
        $supEmp = null; $supDesig = null; $supOrg = null;
        if ($supervisorComment) {
            $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
            $stmt->bind_param("i", $supervisorComment['signatory']);
            $stmt->execute();
            $supEmp = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($supEmp) {
                $__desigId = !empty($supervisorComment['designation_id'])
                    ? $supervisorComment['designation_id'] : $supEmp['designation'];
                $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
                $stmt->bind_param("i", $__desigId);
                $stmt->execute();
                $supDesig = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $__orgId = !empty($supervisorComment['organization_id'])
                    ? $supervisorComment['organization_id'] : $supEmp['organization_id'];
                if (!empty($__orgId)) {
                    $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
                    $stmt->bind_param("i", $__orgId);
                    $stmt->execute();
                    $supOrg = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
            }
        }

        // One signature renderer for all three row kinds. The stored blob is
        // sometimes raw bytes and sometimes already base64, and the type is
        // sniffed from the decoded bytes — the signatory rows used to hard-code
        // image/png, so a JPEG signature rendered as a broken image.
        $renderSignature = function ($blob, $dateObj) {
            if (empty($blob)) return '';
            $imageType = 'image/png';
            $decoded   = @base64_decode($blob, true);
            if ($decoded !== false) {
                $sigBase64 = $blob;
                $probe     = $decoded;
            } else {
                $sigBase64 = base64_encode($blob);
                $probe     = $blob;
            }
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = @$finfo->buffer($probe);
            if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') $imageType = 'image/jpeg';
            if (empty($sigBase64)) return '';
            $out = '<img src="data:' . $imageType . ';base64,' . $sigBase64 . '" style="height: 60px;" /><br>';
            if ($dateObj) {
                $out .= '<span class="small-text">' . banglaNumber(date_format($dateObj, "d.m.Y")) . '</span>';
            }
            return $out;
        };

        // ছুটির প্রস্তাবনা — the leave this note asks approval for, taken from the
        // frozen requested segments so a multi-type application names each part
        // rather than collapsing to one figure.
        $proposalHtml = '<div class="date-line">' . banglaNumber(date_format($dateF, "d/m/Y"))
                      . ' হতে ' . banglaNumber(date_format($dateT, "d/m/Y")) . ' তারিখ পর্যন্ত</div>'
                      . banglaNumber($totalSegDays) . ' দিন';
        if ($isMultiSeg) {
            foreach ($reqSegRows as $sg) {
                $proposalHtml .= '<br><span class="small-text">' . banglaNumber((int)$sg['days']) . ' দিন '
                               . htmlspecialchars($sg['leaveTitle'] ?? '') . '</span>';
            }
        } else {
            $__t = trim((string)($reqSegRows[0]['leaveTitle'] ?? ($leaveTypeData['leaveTitle'] ?? '')));
            if ($__t !== '') $proposalHtml .= '<br><span class="small-text">' . htmlspecialchars($__t) . '</span>';
        }

        // Renders one name cell: name, post and centre, as the form has them.
        $nameCell = function ($emp, $desig, $org) {
            $out = '<div style="padding: 10px;">';
            if ($emp) {
                $out .= htmlspecialchars($emp['employee_name']) . '<br>';
                if ($desig) $out .= htmlspecialchars($desig['job_title_name']);
                if ($org)   $out .= '<br>' . htmlspecialchars($org['organization_name']);
            }
            return $out . '</div>';
        };

        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<td style="width: 15%;"></td>';
        $html .= '<td style="width: 22%;">কর্মকর্তা/ কর্মচারীর নাম ও পদবী</td>';
        $html .= '<td style="width: 25%;">ছুটির প্রস্তাবনা</td>';
        $html .= '<td style="width: 23%;">মন্তব্য</td>';
        $html .= '<td style="width: 15%;">স্বাক্ষর</td>';
        $html .= '</tr>';

        // বিভাগীয় প্রধান — the supervisor, whose desk proposes the leave.
        $html .= '<tr>';
        $html .= '<td class="role-cell">বিভাগীয় প্রধান</td>';
        $html .= '<td>' . $nameCell($supEmp, $supDesig, $supOrg) . '</td>';
        $html .= '<td><div style="padding: 10px;">' . $proposalHtml . '</div></td>';
        $html .= '<td><div style="padding: 10px;">' . htmlspecialchars($supervisorComment['note'] ?? '') . '</div></td>';
        $html .= '<td>' . ($supervisorComment
            ? $renderSignature($supervisorComment['signature'] ?? '',
                !empty($supervisorComment['approvedDate']) ? date_create($supervisorComment['approvedDate']) : null)
            : '') . '</td>';
        $html .= '</tr>';

        // নথি উপস্থাপক — the desk that put the note up for approval.
        $html .= '<tr>';
        $html .= '<td class="role-cell">নথি উপস্থাপক</td>';
        $html .= '<td>' . $nameCell($initiatorData, $initiatorDesignation, $orgData) . '</td>';
        $html .= '<td></td>';
        $html .= '<td><div style="padding: 10px;">' . htmlspecialchars($leaveData['adminNote'] ?? '') . '</div></td>';
        $html .= '<td>' . $renderSignature($initiatorUser['signature'] ?? '',
            !empty($leaveData['adminNoteDate']) ? date_create($leaveData['adminNoteDate']) : null) . '</td>';
        $html .= '</tr>';

        // নথি অনুমোদনকারী গণ — one label cell spanning every signatory row.
        $sigCount = count($signatories);
        $rowIndex = 0;
        foreach ($signatories as $signatory) {
            $stmt = $con->prepare("SELECT * FROM employee_list WHERE id = ?");
            $stmt->bind_param("i", $signatory['signatory']);
            $stmt->execute();
            $sigEmpData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $sigDesignation = null;
            $sigOrg = null;
            if ($sigEmpData) {
                $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
                $stmt->bind_param("i", $signatory['designation_id']);
                $stmt->execute();
                $sigDesignation = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!empty($signatory['organization_id'])) {
                    $stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
                    $stmt->bind_param("i", $signatory['organization_id']);
                    $stmt->execute();
                    $sigOrg = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
            }

            $html .= '<tr>';
            if ($rowIndex === 0) {
                $html .= '<td rowspan="' . $sigCount . '" class="role-cell">নথি অনুমোদনকারী গণ</td>';
            }
            $html .= '<td>' . $nameCell($sigEmpData, $sigDesignation, $sigOrg) . '</td>';
            $html .= '<td></td>';
            $html .= '<td><div style="padding: 10px;">' . htmlspecialchars($signatory['note'] ?? '') . '</div></td>';
            $html .= '<td>' . $renderSignature($signatory['signature'] ?? '',
                !empty($signatory['approvedDate']) ? date_create($signatory['approvedDate']) : null) . '</td>';
            $html .= '</tr>';
            $rowIndex++;
        }
        if ($rowIndex === 0) {
            $html .= '<tr><td class="role-cell">নথি অনুমোদনকারী গণ</td><td></td><td></td><td></td><td></td></tr>';
        }

        $html .= '</table>';

        // অফিস আদেশ QR — only once the leave is approved, because before that there
        // is no office order to open. This file sits at the project root, so the
        // documents directory is addressed relative to the request path; with no
        // host (CLI) the QR is left out rather than baked with a broken URL.
        if ((int)($leaveData['status'] ?? 0) === 1 && !empty($_SERVER['HTTP_HOST'])) {
            $__scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
            $__base   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            $noticeUrl = $__scheme . '://' . $_SERVER['HTTP_HOST'] . $__base
                       . '/views/leave/documents/office-notice.php?leaveApplicationID=' . (int)$leaveApplicationID;

            $html .= '<div class="qr-block">';
            $html .= '<barcode code="' . htmlspecialchars($noticeUrl, ENT_QUOTES) . '" type="QR" class="barcode" size="0.8" error="M" />';
            $html .= '<div class="qr-caption">ছুটির অফিস আদেশ দেখতে<br>স্ক্যান করুন</div>';
            $html .= '</div>';
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
        
        $mpdf->SetTitle('Leave Approval Note - ' . $empData['employee_name']);
        $mpdf->WriteHTML($html);
        
        $pdfContent = $mpdf->Output('', 'S');
        $base64 = base64_encode($pdfContent);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'pdfData' => $base64,
            'title' => 'Leave Approval Note - ' . $empData['employee_name']
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
    <title>Leave Approval Note - ID: <?= $leaveApplicationID ?></title>
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
                a.download = `leave_approval_note_${leaveApplicationID}.pdf`;
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