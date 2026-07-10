<?php
/**
 * ঐচ্ছিক পূর্বানুমোদন — Office Order (অফিস আদেশ) PDF
 *
 * Final approval office order for optional-leave pre-approval, mirroring
 * the leave-notice.php pattern. Only meaningful once opa.status = 1 (approved).
 *
 * Usage: api/reports/opa-office-order.php?id=<preApprovalID>
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
        'title'         => 'ঐচ্ছিক ছুটির অফিস আদেশ',
        'file_prefix'   => 'opa_office_order',
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

        // Load pre-approval + applicant
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
        if ((int)$opa['status'] !== 1) throw new Exception('আবেদন এখনো চূড়ান্তভাবে অনুমোদিত হয়নি');

        // Full chain — supervisor + all approved signatories, top-most last (highest serial)
        // Rendered as signature grid at the bottom of the order.
        $allStmt = $con->prepare("
            SELECT s.signatory AS sig_emp_id, s.serial, s.isSupervisor, s.approvedDate,
                   el.employee_name AS sig_name,
                   jt.job_title_name AS sig_title,
                   o.organization_name AS sig_org,
                   ul.signature AS sig_signature
            FROM optional_leave_pre_approval_signatory s
            LEFT JOIN employee_list el ON s.signatory = el.id
            LEFT JOIN job_title jt ON el.designation = jt.id
            LEFT JOIN organization o ON el.organization_id = o.id
            LEFT JOIN user_list ul ON ul.employee_id = el.id
            WHERE s.preApprovalID = ? AND s.isApproved = 1
            ORDER BY s.serial ASC
        ");
        $allStmt->bind_param('i', $preApprovalID);
        $allStmt->execute();
        $rawSigs = $allStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $allStmt->close();

        // De-duplicate: if the same person appears as both supervisor and later
        // signatory, keep ONLY their highest-serial row (final role). Iterate
        // ASC and overwrite by employee id — the later row wins.
        $uniqueSigs = [];
        foreach ($rawSigs as $s) {
            $uniqueSigs[(int)$s['sig_emp_id']] = $s;
        }
        // Re-index by ascending serial to preserve chain order
        $allSigs = array_values($uniqueSigs);
        usort($allSigs, function($a, $b) { return (int)$a['serial'] <=> (int)$b['serial']; });

        // Prep base64 for each signature image
        foreach ($allSigs as &$sig) {
            $sig['sig_base64'] = '';
            if (!empty($sig['sig_signature'])) {
                $sigRaw = $sig['sig_signature'];
                $decoded = @base64_decode($sigRaw, true);
                $sig['sig_base64'] = ($decoded !== false) ? $sigRaw : base64_encode($sigRaw);
            }
        }
        unset($sig);

        // The top-most signatory (final approver) — used in the main body sentence
        $topSig = !empty($allSigs) ? end($allSigs) : [];

        $approvedDate = !empty($opa['final_approved_date']) ? date_create($opa['final_approved_date']) : date_create();
        $submitDate   = $opa['submit_date'] ? date_create($opa['submit_date']) : date_create();
        $approvedDays = !empty($opa['approved_days']) ? (float)$opa['approved_days'] : (float)$opa['requested_days'];

        // Build HTML (mirroring leave-notice.php structure)
        $html = '<style>
            body { font-family:"Kalpurush","SolaimanLipi","Nikosh",Arial,sans-serif; font-size:15px; line-height:1.7; color:#000; }
            p { margin:8px 0; }
            .text-center { text-align:center; }
            .text-right  { text-align:right; }
            .text-justify{ text-align:justify; }
            .heading     { text-align:center; font-size:18px; margin:10px 0; }
            .underline   { text-decoration:underline; }
            .bold        { font-weight:bold; }
            table        { width:100%; border-collapse:collapse; }
            .small-text  { font-size:11px; }
        </style>';

        // Header
        $html .= '<p class="text-center heading">বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)<br>';
        $html .= htmlspecialchars($opa['organization_name'] ?? '') . '</p>';
        if (!empty($opa['org_address'])) {
            $html .= '<p class="text-center">' . htmlspecialchars($opa['org_address']) . '</p>';
        }

        // Notice number + date — employee's memorialNo (Bangla digits)
        $memorialNo = trim($opa['memorialNo'] ?? '') !== '' ? banglaNumber(htmlspecialchars($opa['memorialNo'])) : '—';
        $html .= '<table style="margin-top:12px;"><tr>';
        $html .= '<td style="width:50%;"><p>নং- ' . $memorialNo . '</p></td>';
        $html .= '<td style="width:50%;" class="text-right"><p class="text-right">তারিখঃ ' . banglaNumber(date_format($approvedDate, 'd/m/Y')) . ' খ্রিস্টাব্দ</p></td>';
        $html .= '</tr></table>';

        // Title
        $html .= '<p class="text-center heading"><span class="underline">অফিস আদেশ</span></p>';
        $html .= '<p>&nbsp;</p>';

        // Main body
        $html .= '<p class="text-justify">&nbsp;&nbsp;&nbsp;';
        $html .= htmlspecialchars($opa['employee_name']) . ', ';
        $html .= htmlspecialchars($opa['job_title_name'] ?? '') . ', ';
        if (!empty($opa['section_name'])) $html .= htmlspecialchars($opa['section_name']) . ', ';
        $html .= htmlspecialchars($opa['organization_name'] ?? '') . '-এর ';
        $html .= banglaNumber(date_format($submitDate, 'd/m/Y')) . ' ইং তারিখের আবেদনের প্রেক্ষিতে ';
        $html .= 'বিভাগীয় প্রধানের সুপারিশের আলোকে ' . banglaNumber((int)$opa['year']) . ' সালের জন্য ';
        $html .= banglaNumber($approvedDays) . ' দিনের ঐচ্ছিক ছুটির পূর্বানুমোদন প্রদান করা হলো';
        if (!empty($opa['festival_notes'])) {
            $html .= ' (উৎসব: ' . htmlspecialchars($opa['festival_notes']) . ')';
        }
        $html .= '।';
        $html .= '</p>';

        $html .= '<p>&nbsp;</p>';
        $html .= '<p>০২। কর্তৃপক্ষের অনুমোদনক্রমে এ আদেশ জারি করা হলো।</p>';
        $html .= '<p>&nbsp;</p>';

        // Applicant block (top of the signature area) + full chain signature grid.
        // Chain rendered lowest-serial → highest-serial (supervisor first, final
        // approver last), 2 per row, so all approvers' signatures appear.
        $html .= '<p>' . htmlspecialchars($opa['employee_name']) . '<br>';
        if (!empty($opa['job_title_name'])) $html .= htmlspecialchars($opa['job_title_name']) . '<br>';
        if (!empty($opa['section_name']))   $html .= htmlspecialchars($opa['section_name']) . ', ';
        $html .= htmlspecialchars($opa['organization_name'] ?? '');
        $html .= '</p>';

        if (!empty($allSigs)) {
            // Fixed 4-column grid — signatures flow left-to-right. Layout gives
            // a uniform spread across the page width even when only 2-3 sigs
            // are present. Rows wrap after 4 sigs.
            $slotsPerRow = 4;
            $slotWidth   = 25; // percent
            $html .= '<p>&nbsp;</p>';
            $html .= '<table style="margin-top:12px; width:100%; border-collapse:collapse;">';

            $totalSigs = count($allSigs);
            $rowCount  = (int)ceil($totalSigs / $slotsPerRow);
            for ($r = 0; $r < $rowCount; $r++) {
                $html .= '<tr>';
                for ($c = 0; $c < $slotsPerRow; $c++) {
                    $idx = $r * $slotsPerRow + $c;
                    $html .= '<td style="width:' . $slotWidth . '%; vertical-align:top; padding-right:10px;">';
                    if ($idx < $totalSigs) {
                        $sig = $allSigs[$idx];
                        if (!empty($sig['sig_base64'])) {
                            $html .= '<img src="data:image/png;base64,' . $sig['sig_base64'] . '" style="height:50px;" /><br>';
                        }
                        $sigDate = !empty($sig['approvedDate']) ? banglaNumber(date('d.m.Y', strtotime($sig['approvedDate']))) : '';
                        if ($sigDate) $html .= '<span class="small-text">' . $sigDate . '</span><br>';
                        if (!empty($sig['sig_name'])) {
                            $html .= htmlspecialchars(str_replace('জনাব ', '', $sig['sig_name'])) . '<br>';
                        }
                        if (!empty($sig['sig_title'])) $html .= htmlspecialchars($sig['sig_title']) . '<br>';
                        if (!empty($sig['sig_org']))   $html .= htmlspecialchars($sig['sig_org']);
                    } else {
                        $html .= '&nbsp;';
                    }
                    $html .= '</td>';
                }
                $html .= '</tr>';
                if ($r < $rowCount - 1) {
                    $html .= '<tr><td colspan="' . $slotsPerRow . '" style="height:14px;">&nbsp;</td></tr>';
                }
            }
            $html .= '</table>';
        }

        // Copy-to list — for OPA we send it to the applicant and their center admin/hr
        $html .= '<p>&nbsp;</p><p>অনুলিপি :</p>';
        $html .= '<p>১। ' . htmlspecialchars($opa['employee_name']);
        if (!empty($opa['job_title_name'])) $html .= ', ' . htmlspecialchars($opa['job_title_name']);
        if (!empty($opa['section_name']))   $html .= ', ' . htmlspecialchars($opa['section_name']);
        $html .= ', ' . htmlspecialchars($opa['organization_name'] ?? '');
        $html .= ' — অবগতি ও প্রয়োজনীয় ব্যবস্থার জন্য।</p>';
        $html .= '<p>২। প্রশাসন শাখা, ' . htmlspecialchars($opa['organization_name'] ?? '') . ' — রেকর্ডে সংরক্ষণের জন্য।</p>';

        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'default_font'  => 'kalpurush',
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
        ]);
        $mpdf->SetTitle('OPA Office Order - ' . ($opa['employee_name'] ?? ''));
        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', 'S');

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'pdfData' => base64_encode($pdfContent),
            'title'   => 'OPA Office Order - ' . ($opa['employee_name'] ?? ''),
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
