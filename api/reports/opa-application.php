<?php
/**
 * ঐচ্ছিক পূর্বানুমোদন — Application PDF
 *
 * Renders the employee's optional-leave pre-approval application as a formal
 * PDF letter to the Director-General (director/authority hierarchy).
 *
 * Usage: api/reports/opa-application.php?id=<preApprovalID>
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');
set_time_limit(120);

$action        = $_GET['action'] ?? 'view';
$preApprovalID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($preApprovalID <= 0) {
    http_response_code(400);
    echo '<div style="font-family:Arial;padding:40px;color:#dc2626;">Missing pre-approval ID.</div>';
    exit;
}

if ($action === 'generate') {
    generatePDFData($preApprovalID);
} else {
    $viewer_config = [
        'title'         => 'ঐচ্ছিক ছুটির পূর্বানুমোদন আবেদন',
        'file_prefix'   => 'opa_application',
        'preApprovalID' => $preApprovalID,
    ];
    include(__DIR__ . '/../../includes/opa_pdf_viewer.php');
}

function generatePDFData($preApprovalID) {
    try {
        require_once(__DIR__ . '/../../config/connection.php');
        require_once(LIBRARY_PATH . '/number_converter.php');

        foreach ([__DIR__ . '/../../vendor/autoload.php'] as $p) {
            if (file_exists($p)) { require_once($p); break; }
        }
        if (!class_exists('\Mpdf\Mpdf')) throw new Exception('mPDF not found');
        if (!$con) throw new Exception('Database connection failed');

        // Load pre-approval + applicant snapshots
        $stmt = $con->prepare("
            SELECT opa.*,
                   el.employee_name, el.employee_id AS emp_code, el.memorialNo,
                   jt.job_title_name,
                   o.organization_name, o.address AS org_address,
                   s.section_name
            FROM optional_leave_pre_approval opa
            INNER JOIN employee_list el ON opa.employee_id = el.id
            LEFT JOIN job_title jt ON el.designation = jt.id
            LEFT JOIN organization o ON opa.organization_id = o.id
            LEFT JOIN sections s ON opa.section_id = s.id
            WHERE opa.id = ? LIMIT 1
        ");
        $stmt->bind_param('i', $preApprovalID);
        $stmt->execute();
        $opa = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$opa) throw new Exception('আবেদন পাওয়া যায়নি');

        // Load first supervisor row (top of the chain) to derive the recipient hierarchy
        $supStmt = $con->prepare("
            SELECT s.signatory, el.employee_name AS sup_name,
                   jt.job_title_name AS sup_title
            FROM optional_leave_pre_approval_signatory s
            LEFT JOIN employee_list el ON s.signatory = el.id
            LEFT JOIN job_title jt ON el.designation = jt.id
            WHERE s.preApprovalID = ? AND s.isSupervisor = 1 LIMIT 1
        ");
        $supStmt->bind_param('i', $preApprovalID);
        $supStmt->execute();
        $sup = $supStmt->get_result()->fetch_assoc() ?: [];
        $supStmt->close();

        // Load top signatory in chain (last non-supervisor, non-DG row) as addressee
        $topSigStmt = $con->prepare("
            SELECT el.employee_name AS sig_name, jt.job_title_name AS sig_title,
                   o.organization_name AS sig_org
            FROM optional_leave_pre_approval_signatory s
            LEFT JOIN employee_list el ON s.signatory = el.id
            LEFT JOIN job_title jt ON el.designation = jt.id
            LEFT JOIN organization o ON el.organization_id = o.id
            WHERE s.preApprovalID = ? AND s.isSupervisor = 0
            ORDER BY s.serial DESC LIMIT 1
        ");
        $topSigStmt->bind_param('i', $preApprovalID);
        $topSigStmt->execute();
        $topSig = $topSigStmt->get_result()->fetch_assoc() ?: [];
        $topSigStmt->close();

        $submitDate = $opa['submit_date'] ? date_create($opa['submit_date']) : date_create();

        // Build HTML
        $html = '<style>
            body { font-family:"Kalpurush","SolaimanLipi","Nikosh",Arial,sans-serif; font-size:14px; line-height:1.7; color:#000; }
            p { margin:6px 0; }
            .text-center { text-align:center; }
            .text-right  { text-align:right; }
            .text-justify{ text-align:justify; }
            .heading     { text-align:center; font-size:17px; margin:8px 0; }
            .underline   { text-decoration:underline; }
            .bold        { font-weight:bold; }
            table        { width:100%; border-collapse:collapse; }
            .indent      { text-indent:32px; }
        </style>';

        // Header — center + org
        $html .= '<p class="text-center heading">বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)<br>';
        $html .= htmlspecialchars($opa['organization_name'] ?? '') . '</p>';
        if (!empty($opa['org_address'])) {
            $html .= '<p class="text-center">' . htmlspecialchars($opa['org_address']) . '</p>';
        }

        // Date / applicant reference — employee's memorialNo (Bangla digits)
        $memoNo = trim($opa['memorialNo'] ?? '');
        $memoDisplay = $memoNo !== '' ? banglaNumber(htmlspecialchars($memoNo)) : '—';
        $html .= '<table style="margin-top:14px;"><tr>';
        $html .= '<td style="width:50%;"><p>স্মারক নং ঃ ' . $memoDisplay . '</p></td>';
        $html .= '<td style="width:50%;" class="text-right"><p>তারিখঃ ' . banglaNumber(date_format($submitDate, 'd/m/Y')) . ' খ্রিস্টাব্দ</p></td>';
        $html .= '</tr></table>';

        // Recipient — address by designation only (formal Bangla application convention;
        // the person holding that position may change, so name is intentionally omitted).
        $html .= '<p style="margin-top:10px;">বরাবর,</p>';
        if (!empty($topSig['sig_title'])) {
            $html .= '<p style="margin-left:24px;">' . htmlspecialchars($topSig['sig_title']) . '<br>';
            if (!empty($topSig['sig_org'])) $html .= htmlspecialchars($topSig['sig_org']);
            $html .= '</p>';
        } else {
            $html .= '<p style="margin-left:24px;">মহাপরিচালক<br>বিটাক, ঢাকা</p>';
        }

        // Subject — inline style avoids the mPDF + Bangla font + bold-class rendering quirk
        $html .= '<p class="text-center" style="margin-top:18px;"><span style="text-decoration:underline;">বিষয়ঃ ঐচ্ছিক ছুটির পূর্বানুমোদনের জন্য আবেদন।</span></p>';
        $html .= '<p>জনাব,</p>';

        // Body
        $festival = trim($opa['festival_notes'] ?? '');
        $festivalTxt = $festival !== '' ? htmlspecialchars($festival) : 'উৎসব উপলক্ষে';

        $html .= '<p class="text-justify indent">';
        $html .= 'সবিনয় নিবেদন এই যে, আমি ' . htmlspecialchars($opa['employee_name']) . ', ';
        $html .= htmlspecialchars($opa['job_title_name'] ?? '');
        if (!empty($opa['section_name'])) $html .= ', ' . htmlspecialchars($opa['section_name']);
        $html .= ', ' . htmlspecialchars($opa['organization_name'] ?? '') . '-এর একজন কর্মচারী। ';
        $html .= banglaNumber((int)$opa['year']) . ' সালের জন্য ' . $festivalTxt . ' উপলক্ষে ';
        $html .= banglaNumber((float)$opa['requested_days']) . ' দিনের ঐচ্ছিক ছুটির পূর্বানুমোদনের জন্য আমি অনুরোধ করছি। ';
        $html .= 'সরকারি চাকরি বিধিমালা অনুযায়ী প্রতি বছর সর্বোচ্চ ৩ দিন ঐচ্ছিক ছুটির অনুমোদন প্রদান করা হয়ে থাকে।';
        $html .= '</p>';

        $html .= '<p class="text-justify indent">';
        $html .= 'অতএব, বিনীত অনুরোধ, উক্ত ছুটির পূর্বানুমোদন প্রদান করে বাধিত করবেন।';
        $html .= '</p>';

        // Signature block
        $html .= '<p style="margin-top:30px;">নিবেদক,</p>';
        $html .= '<p style="margin-top:36px;">';
        $html .= htmlspecialchars($opa['employee_name']) . '<br>';
        if (!empty($opa['job_title_name'])) $html .= htmlspecialchars($opa['job_title_name']) . '<br>';
        if (!empty($opa['section_name']))   $html .= htmlspecialchars($opa['section_name']);
        $html .= '</p>';

        // Attachment note
        if (!empty($opa['attachment'])) {
            $html .= '<p style="margin-top:20px;">সংযুক্তি: প্রমাণাদি (আলাদা)</p>';
        }

        // Generate PDF
        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 20,
            'margin_right'  => 20,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'default_font'  => 'kalpurush',
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
        ]);
        $mpdf->SetTitle('OPA Application - ' . ($opa['employee_name'] ?? ''));
        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'pdfData' => base64_encode($pdfContent),
            'title'   => 'OPA Application - ' . ($opa['employee_name'] ?? ''),
        ]);
    } catch (\Throwable $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);
    }
}
