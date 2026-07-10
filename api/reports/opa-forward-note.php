<?php
/**
 * ঐচ্ছিক পূর্বানুমোদন — Forward Note (সম্পাদনার নোট) PDF
 *
 * Renders the center admin's forward memo to the signatory chain, including:
 *   - Applicant identity + request summary
 *   - Supervisor's recommendation
 *   - Admin's approved days + note
 *   - Signature block (initiating admin)
 *
 * Usage: api/reports/opa-forward-note.php?id=<preApprovalID>
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
        'title'         => 'ঐচ্ছিক ছুটির সম্পাদনার নোট',
        'file_prefix'   => 'opa_forward_note',
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

        // Applicant + org
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
        if (empty($opa['admin_forward_date'])) throw new Exception('এই আবেদন এখনো কেন্দ্র প্রশাসন কর্তৃক forward করা হয়নি');

        // Supervisor row (recommendation)
        $supStmt = $con->prepare("
            SELECT s.approvedDate,
                   el.employee_name AS sup_name,
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

        // Initiating admin
        $adminName = '—'; $adminTitle = ''; $adminSigBase64 = '';
        if (!empty($opa['admin_initiator'])) {
            $adStmt = $con->prepare("
                SELECT ul.signature, el.employee_name, jt.job_title_name
                FROM user_list ul
                LEFT JOIN employee_list el ON ul.employee_id = el.id
                LEFT JOIN job_title jt ON el.designation = jt.id
                WHERE ul.dataID = ? LIMIT 1
            ");
            $adStmt->bind_param('i', $opa['admin_initiator']);
            $adStmt->execute();
            $ad = $adStmt->get_result()->fetch_assoc() ?: [];
            $adStmt->close();
            $adminName  = $ad['employee_name']  ?? '—';
            $adminTitle = $ad['job_title_name'] ?? '';
            if (!empty($ad['signature'])) {
                $sigRaw = $ad['signature'];
                $decoded = @base64_decode($sigRaw, true);
                $adminSigBase64 = ($decoded !== false) ? $sigRaw : base64_encode($sigRaw);
            }
        }

        $forwardDate = date_create($opa['admin_forward_date']);
        $submitDate  = $opa['submit_date'] ? date_create($opa['submit_date']) : date_create();

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
            .info-tbl td { padding:6px 8px; border:1px solid #d0d0d0; font-size:13px; vertical-align:top; }
            .info-tbl .lbl { background:#f5f5f5; width:35%; color:#000; }
            .indent      { text-indent:32px; }
            .note-box    { border:1px solid #d0d0d0; padding:10px 12px; margin-top:10px; background:#fafafa; font-size:13px; }
            .note-label  { font-weight:bold; margin-bottom:6px; display:block; color:#111; }
        </style>';

        // Header
        $html .= '<p class="text-center heading">বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)<br>';
        $html .= htmlspecialchars($opa['organization_name'] ?? '') . '</p>';
        if (!empty($opa['org_address'])) {
            $html .= '<p class="text-center">' . htmlspecialchars($opa['org_address']) . '</p>';
        }

        $memoNo = trim($opa['memorialNo'] ?? '');
        $memoDisplay = $memoNo !== '' ? banglaNumber(htmlspecialchars($memoNo)) : '—';
        $html .= '<table style="margin-top:12px;"><tr>';
        $html .= '<td style="width:50%;"><p>স্মারক নং ঃ ' . $memoDisplay . '</p></td>';
        $html .= '<td style="width:50%;" class="text-right"><p>তারিখঃ ' . banglaNumber(date_format($forwardDate, 'd/m/Y')) . ' খ্রিস্টাব্দ</p></td>';
        $html .= '</tr></table>';

        $html .= '<p class="text-center heading" style="margin-top:10px;"><span class="underline">প্রস্তাবিত ঐচ্ছিক ছুটি সম্পাদনার নোট</span></p>';

        // Applicant info table — inline styles on label cell for maximum mPDF compatibility
        $lblStyle = 'background:#f5f5f5; width:35%; padding:6px 8px; border:1px solid #d0d0d0; font-size:13px; vertical-align:top; color:#000;';
        $valStyle = 'padding:6px 8px; border:1px solid #d0d0d0; font-size:13px; vertical-align:top;';
        $html .= '<table style="width:100%; border-collapse:collapse; margin-top:10px;">';
        $html .= '<tr><td style="' . $lblStyle . '">কর্মচারীর নাম</td><td style="' . $valStyle . '">' . htmlspecialchars($opa['employee_name']) . '</td></tr>';
        $empCodeDisplay = !empty($opa['emp_code']) ? banglaNumber(htmlspecialchars($opa['emp_code'])) : '—';
        $html .= '<tr><td style="' . $lblStyle . '">আইডি</td><td style="' . $valStyle . '">' . $empCodeDisplay . '</td></tr>';
        $html .= '<tr><td style="' . $lblStyle . '">পদবী</td><td style="' . $valStyle . '">' . htmlspecialchars($opa['job_title_name'] ?? '—') . '</td></tr>';
        if (!empty($opa['section_name'])) {
            $html .= '<tr><td style="' . $lblStyle . '">শাখা</td><td style="' . $valStyle . '">' . htmlspecialchars($opa['section_name']) . '</td></tr>';
        }
        $html .= '<tr><td style="' . $lblStyle . '">বছর</td><td style="' . $valStyle . '">' . banglaNumber((int)$opa['year']) . '</td></tr>';
        $html .= '<tr><td style="' . $lblStyle . '">আবেদনের তারিখ</td><td style="' . $valStyle . '">' . banglaNumber(date_format($submitDate, 'd/m/Y')) . '</td></tr>';
        $html .= '<tr><td style="' . $lblStyle . '">চাহিত দিন</td><td style="' . $valStyle . '">' . banglaNumber((float)$opa['requested_days']) . ' দিন</td></tr>';
        if (!empty($opa['approved_days'])) {
            $html .= '<tr><td style="' . $lblStyle . '">প্রশাসন কর্তৃক অনুমোদিত দিন</td><td style="' . $valStyle . '"><span class="bold">' . banglaNumber((float)$opa['approved_days']) . ' দিন</span></td></tr>';
        }
        if (!empty($opa['festival_notes'])) {
            $html .= '<tr><td style="' . $lblStyle . '">উৎসব / নোট</td><td style="' . $valStyle . '">' . htmlspecialchars($opa['festival_notes']) . '</td></tr>';
        }
        $html .= '</table>';

        $boxStyle = 'border:1px solid #d0d0d0; padding:10px 12px; margin-top:10px; background:#fafafa; font-size:13px;';
        $lblLine  = 'margin-bottom:6px; color:#000;';

        // Supervisor recommendation
        if (!empty($sup['sup_name'])) {
            $supDate = $sup['approvedDate'] ? banglaNumber(date('d/m/Y', strtotime($sup['approvedDate']))) : '—';
            $html .= '<div style="' . $boxStyle . '">';
            $html .= '<div style="' . $lblLine . '">সুপারভাইজারের সুপারিশ ঃ</div>';
            $html .= 'বিভাগীয় প্রধান জনাব ' . htmlspecialchars($sup['sup_name'])
                  . (!empty($sup['sup_title']) ? ', ' . htmlspecialchars($sup['sup_title']) : '')
                  . ' ' . $supDate . ' তারিখে এই আবেদন সুপারিশ করেছেন।';
            $html .= '</div>';
        }

        // Admin note
        if (!empty($opa['admin_note'])) {
            $html .= '<div style="' . $boxStyle . '">';
            $html .= '<div style="' . $lblLine . '">প্রশাসনিক মন্তব্য ঃ</div>';
            $html .= nl2br(htmlspecialchars($opa['admin_note']));
            $html .= '</div>';
        }

        $html .= '<p style="margin-top:14px;">উপরোক্ত বিষয়ে সদয় অনুমোদনের জন্য পেশ করা হলো।</p>';

        // Signature block — left aligned
        $html .= '<div style="margin-top:36px; text-align:left;">';
        if ($adminSigBase64) {
            $html .= '<img src="data:image/png;base64,' . $adminSigBase64 . '" style="height:56px;" /><br>';
        }
        $html .= '<span style="border-top:1px solid #000;display:inline-block;padding-top:2px;min-width:220px;">'
              . htmlspecialchars($adminName);
        if ($adminTitle) $html .= '<br>' . htmlspecialchars($adminTitle);
        $html .= '<br>' . htmlspecialchars($opa['organization_name'] ?? '');
        $html .= '<br>তারিখ: ' . banglaNumber(date_format($forwardDate, 'd/m/Y'));
        $html .= '</span>';
        $html .= '</div>';

        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 18,
            'margin_right'  => 18,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'default_font'  => 'kalpurush',
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
        ]);
        $mpdf->SetTitle('OPA Forward Note - ' . ($opa['employee_name'] ?? ''));
        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'pdfData' => base64_encode($pdfContent),
            'title'   => 'OPA Forward Note - ' . ($opa['employee_name'] ?? ''),
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
