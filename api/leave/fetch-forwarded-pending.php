<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php'); // Database connection file
require_once(LIBRARY_PATH . '/number_converter.php');

// Disable error display for clean JSON response
error_reporting(0);
ini_set('display_errors', 0);

function dateDiffInDays($date1, $date2) 
  {
      // Calculating the difference in timestamps
      $diff = strtotime($date2) - strtotime($date1);
  
      // 1 day = 24 hours
      // 24 * 60 * 60 = 86400 seconds
      return abs(round($diff / 86400));
  }

// Hoisted existence check for the lazily-created return-history table.
$hasReturnHistory = false;
$_rrChk = mysqli_query($con, "SHOW TABLES LIKE 'leave_return_history'");
if ($_rrChk && mysqli_num_rows($_rrChk) > 0) $hasReturnHistory = true;

// Resolve organization_id for the current user
if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = intval($_SESSION['centerAdminOrgID']);
} else {
    $empID = intval($_SESSION['employeeID'] ?? 0);
    $stmt_org = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $stmt_org->bind_param("i", $empID);
    $stmt_org->execute();
    $orgRow = $stmt_org->get_result()->fetch_assoc();
    $stmt_org->close();
    $orgID = intval($orgRow['organization_id'] ?? 0);
}

// Column map for sorting (frontend index → DB column). New layout: 0=sl, 1=applicant, 2=date, 3=requested, 4=proposed, 5=status, 6=action
$sortableColumns = [
    0 => 'leave_data_for_approval.leaveApplicationID',
    1 => 'employee_list.employee_name',
    2 => 'leave_applications.submitDate',
    3 => 'leave_applications.dateFrom',
    4 => 'leave_applications.approvedDateFrom',
    5 => 'leave_applications.status',
    6 => 'leave_data_for_approval.leaveApplicationID',
];

// Filter params
$sectionFilter   = (int)($_POST['sectionFilter']   ?? 0);
$employeeFilter  = (int)($_POST['employeeFilter']  ?? 0);
$leaveTypeFilter = (int)($_POST['leaveTypeFilter'] ?? 0);
$dateFromF       = trim($_POST['dateFrom'] ?? '');
$dateToF         = trim($_POST['dateTo']   ?? '');

$filterClause = '';
if ($sectionFilter   > 0) $filterClause .= " AND employee_list.section_id = $sectionFilter";
if ($employeeFilter  > 0) $filterClause .= " AND employee_list.id = $employeeFilter";
if ($leaveTypeFilter > 0) $filterClause .= " AND leave_applications.leaveType = $leaveTypeFilter";
if ($dateFromF !== '' && $dateToF !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromF) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToF)) {
    $filterClause .= " AND leave_applications.dateFrom BETWEEN '$dateFromF' AND '$dateToF'";
}

// Get request parameters with sanitization
$limit  = isset($_POST['length']) ? (int) $_POST['length'] : 10;
$start  = isset($_POST['start']) ? (int) $_POST['start'] : 0;
$search = isset($_POST['search']['value']) ? mysqli_real_escape_string($con, $_POST['search']['value']) : '';

$orderColumn    = isset($_POST['order'][0]['column']) ? (int) $_POST['order'][0]['column'] : 2;
$orderDirection = (isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
$sortColumn     = $sortableColumns[$orderColumn] ?? 'leave_applications.submitDate';

$baseFrom = "FROM `leave_data_for_approval`
             INNER JOIN leave_applications ON leave_data_for_approval.leaveApplicationID = leave_applications.dataID
             INNER JOIN employee_list ON leave_applications.applicantID = employee_list.id
             WHERE leave_data_for_approval.isSupervisor = 1
               AND leave_data_for_approval.isApproved   = 1
               AND leave_applications.organization_id   = '$orgID'
               AND leave_data_for_approval.isSentbyAdmin = 0
               $filterClause";

$selectFields = "employee_list.employee_name AS applicant_name, employee_list.employee_id, employee_list.photo, employee_list.designation, employee_list.section_id, leave_data_for_approval.leaveApplicationID, leave_data_for_approval.isSentbyAdmin";

$sql = "SELECT $selectFields $baseFrom";

if ($search) {
    $sql .= " AND (employee_list.employee_name LIKE ? OR employee_list.employee_id LIKE ?)";
}
$sql .= " ORDER BY $sortColumn $orderDirection LIMIT ?, ?";

$stmt = mysqli_prepare($con, $sql);
if ($search) {
    $searchTerm = "%$search%";
    mysqli_stmt_bind_param($stmt, 'ssii', $searchTerm, $searchTerm, $start, $limit);
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $start, $limit);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Total records (with filters applied for accurate count)
$totalRecordsQuery = mysqli_query($con, "SELECT leave_data_for_approval.dataID $baseFrom");
$totalRecords = mysqli_num_rows($totalRecordsQuery);

$data = [];
$sl = $start + 1;

// Process the results
while ($row = mysqli_fetch_array($result)) {

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$row[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$row[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$row[leaveApplicationID]'");
$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

$getOrgQ = mysqli_query($con, "select organization_name from organization where id='" . intval($getLeaveApplicationDetailsQRW['organization_id']) . "'");
$orgName = ($getOrgQRW = mysqli_fetch_assoc($getOrgQ)) ? $getOrgQRW['organization_name'] : '';

//.....

$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveType]'");
$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

$getApprovedLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[approvedLeaveType]'");
$getApprovedLeaveTypeQRW = mysqli_fetch_assoc($getApprovedLeaveTypeQ);

if($getLeaveApplicationDetailsQRW['dateFrom']!=NULL && $getLeaveApplicationDetailsQRW['dateTo']!=NULL){

	$dateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;

	$dateF=date_create($getLeaveApplicationDetailsQRW['dateFrom']);
	//echo date_format($dateF,"d/m/Y");
	$dateT=date_create($getLeaveApplicationDetailsQRW['dateTo']);

}

// proposed

if($getLeaveApplicationDetailsQRW['approvedDateFrom']!=NULL && $getLeaveApplicationDetailsQRW['approvedDateTo']!=NULL){

	$adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
	//echo date_format($dateF,"d/m/Y");
	$adateT=date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

	$adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

}

//$getALeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveTypeInTwo]'");
//$getALeaveTypeQRW = mysqli_fetch_assoc($getALeaveTypeQ);


$sdate = date_create($getLeaveApplicationDetailsQRW['submitDate']);

												
$checkIsReadQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$row[leaveApplicationID]' and isSupervisor=0 and isRead=1");
$checkIsReadQNumRows = mysqli_num_rows($checkIsReadQ);

$application_date_time = $sdate->format('d/m/Y');

if($getLeaveApplicationDetailsQRW['submitTime'] != NULL){ $application_date_time = $application_date_time. " ".$getLeaveApplicationDetailsQRW['submitTime']; }

$requested_leave_days = "";

if ($dateF && $dateT) {
// Format the DateTime objects
	$formatted_dateF = $dateF->format('d/m/Y');
	$formatted_dateT = $dateT->format('d/m/Y');
													
	// Echo the formatted dates
	$requested_leave_days = $formatted_dateF . ' হইতে ' . $formatted_dateT.", ".banglaNumber($dateDiff)."দিন";

} else {
	// Handle the case where date_create() failed
	$requested_leave_days = "Error: Unable to create DateTime object";
}

$proposed_leave_days = "";

if ($adateF && $adateT) {
// Format the DateTime objects
	$formatted_adateF = $adateF->format('d/m/Y');
	$formatted_adateT = $adateT->format('d/m/Y');
														
	// Echo the formatted dates
	$proposed_leave_days = $formatted_adateF . ' হইতে ' . $formatted_adateT.", ".banglaNumber($adateDiff)."দিন";

} else {
// Handle the case where date_create() failed
	$proposed_leave_days = "Error: Unable to create DateTime object";
}



if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){
	$proposed_leave_type = "গড় বেতন ";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){
	$proposed_leave_type = "অর্ধ-গড় বেতন ";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){
	$proposed_leave_type = "নৈমিত্তিক (Casual Leave)";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){
	$proposed_leave_type = "বিনা বেতনে ছুটি";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){
	$proposed_leave_type = "ঐচ্ছিক ছুটি";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){

	$proposed_leave_type = "সংগনিরোধ ছুটি";

}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 7){

	$proposed_leave_type = "প্রসূতি ছুটি";

}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 8){

	$proposed_leave_type = "অক্ষমতাজনিত বিশেষ ছুটি";

}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 9){

	$proposed_leave_type = "অধ্যয়ন ছুটি";

}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){

	$proposed_leave_type = "অসাধারণ ছুটি";

}else{

	$proposed_leave_type = "";

}


if ($getLeaveApplicationDetailsQRW['status'] == 1) {
    $status = '<span class="status-pill status-approved"><i class="ti tabler-check me-1"></i>অনুমোদিত</span>';
} else if ($getLeaveApplicationDetailsQRW['status'] == 2) {
    $status = '<span class="status-pill status-rejected"><i class="ti tabler-x me-1"></i>অনুমোদিত হয়নি</span>';
} else if ($getLeaveApplicationDetailsQRW['status'] == 3) {
    $status = '<span class="status-pill" style="background:#fff3cd;color:#7a5400;"><i class="ti tabler-corner-up-left me-1"></i>ফেরত — পুনঃফরওয়ার্ড করুন</span>';
} else if ($getLeaveApplicationDetailsQRW['status'] == 0) {
    $status = '<span class="status-pill status-pending"><i class="ti tabler-hourglass me-1"></i>অপেক্ষমান</span>';
} else {
    $status = '';
}

// Has this application ever been returned via ফেরত পাঠান? Hoisted before
// the actions HTML because the "ফেরতকৃত আবেদন" label below depends on it,
// and further reused for the applicant-cell "পুনঃ যাচাইয়ের পর জমা" chip.
$_wasReturned = false;
if ($hasReturnHistory) {
    $_lid = (int)($getLeaveApplicationDetailsQRW['dataID'] ?? 0);
    if ($_lid > 0) {
        $_rrq = mysqli_query($con, "SELECT COUNT(*) c FROM leave_return_history WHERE leaveApplicationID = $_lid");
        if ($_rrq && (int)(mysqli_fetch_assoc($_rrq)['c'] ?? 0) > 0) {
            $_wasReturned = true;
        }
    }
}

$html = '
<div class="btn-group">
    <button type="button" class="btn btn-icon btn-outline-primary btn-sm rounded-circle action-btn" data-bs-toggle="dropdown" aria-expanded="false" title="কার্যাবলী">
        <i class="ti tabler-dots-vertical"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><a class="dropdown-item app-doc-view" href="javascript:void(0);"
               data-url="../../views/leave/application-details.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $row['leaveApplicationID'] . '"
               data-title="' . ($_wasReturned ? 'ফেরতকৃত আবেদন' : 'আবেদনপত্র') . '">
            <i class="ti ' . ($_wasReturned ? 'tabler-file-alert' : 'tabler-file-text') . ' me-2"></i>' . ($_wasReturned ? 'ফেরতকৃত আবেদন' : 'আবেদনপত্র') . '
        </a></li>

        ' . ($getLeaveApplicationDetailsQRW['attachment'] != '' ?
            '<li><a class="dropdown-item app-doc-view" href="javascript:void(0);"
                   data-url="uploads/' . htmlspecialchars($getLeaveApplicationDetailsQRW['attachment']) . '"
                   data-title="সংযুক্তি">
                <i class="ti tabler-paperclip me-2"></i>সংযুক্তি
            </a></li>' : '') . '

        ' . ($row['isSentbyAdmin'] == 1 ? '
            <li><a class="dropdown-item app-doc-view" href="javascript:void(0);"
                   data-url="../../leave_application_by_admin.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $row['leaveApplicationID'] . '"
                   data-title="সম্পাদনার নোট">
                <i class="ti tabler-notes me-2"></i>সম্পাদনার নোট
            </a></li>

            ' . ($checkIsReadQNumRows <= 0 ?
                '<li><a class="dropdown-item" href="forward-to-approval.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $getLeaveApplicationDetailsQRW['dataID'] . '">
                    <i class="ti tabler-edit me-2"></i>নোট এডিট করুন
                </a></li>' : '') . '

            ' . ($getLeaveApplicationDetailsQRW['status'] == 1 ? '
                <li><a class="dropdown-item app-doc-view" href="javascript:void(0);"
                       data-url="../../api/reports/leave-notice.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $row['leaveApplicationID'] . '"
                       data-title="অফিস আদেশ">
                    <i class="ti tabler-file-description me-2"></i>অফিস আদেশ
                </a></li>
                <li><a class="dropdown-item" href="new_leave_edit_form?menuslug=allowed-leave-applications&leaveApplicationID=' . $row['leaveApplicationID'] . '">
                    <i class="ti tabler-pencil me-2"></i>সংশোধন
                </a></li>
            ' : '') . '
        ' : '') . '

        ' . ($row['isSentbyAdmin'] == 0 ? '
            <li><a class="dropdown-item" href="forward-to-approval.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $getLeaveApplicationDetailsQRW['dataID'] . '">
                <i class="ti tabler-arrow-right me-2"></i>ফরওয়ার্ড করুন
            </a></li>
        ' : '') . '
    </ul>
</div>';




    // Application number (BITAC/{year}/{dataID})
    $appNoVal = !empty($getLeaveApplicationDetailsQRW['application_no'])
        ? $getLeaveApplicationDetailsQRW['application_no']
        : (function_exists('generateApplicationNo')
            ? generateApplicationNo($getLeaveApplicationDetailsQRW['dataID'], $getLeaveApplicationDetailsQRW['submitDate'] ?? '')
            : 'BITAC/' . date('Y', strtotime($getLeaveApplicationDetailsQRW['submitDate'] ?? 'now')) . '/' . $getLeaveApplicationDetailsQRW['dataID']);

    // Avatar-based applicant cell
    $empName_  = trim($row['applicant_name'] ?? '');
    $empJob_   = trim($getDesignationDetailsQRW['job_title_name'] ?? '');
    $empPhoto_ = trim($row['photo'] ?? '');
    $empCode_  = trim($row['employee_id'] ?? '');
    $initials_ = mb_substr($empName_, 0, 1, 'UTF-8');
    $parts_ = preg_split('/\s+/u', $empName_);
    if (count($parts_) > 1) {
        $initials_ = mb_substr($parts_[0], 0, 1, 'UTF-8') . mb_substr(end($parts_), 0, 1, 'UTF-8');
    }
    if (!empty($empPhoto_)) {
        $photoUrl_ = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto_);
        $avatarHtml_ = '<div class="emp-avatar"><img src="' . $photoUrl_ . '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials_) . '</span></div>';
    } else {
        $avatarHtml_ = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials_) . '</span></div>';
    }
    $secOrg_ = trim(($getSectionDetailsQRW['section_name'] ?? '') . (!empty($orgName) ? ' • ' . $orgName : ''));

    // Resubmit-after-return chip — reuses $_wasReturned flag hoisted above.
    $_resubmitChip = $_wasReturned
        ? '<div class="mt-1"><span style="display:inline-block;background:#fff3e1;color:#b8651a;font-size:0.68rem;padding:2px 8px;border-radius:999px;border:1px solid #f0d9a8;line-height:1.3;"><i class="ti tabler-refresh me-1"></i>পুনঃ যাচাইয়ের পর জমা</span></div>'
        : '';

    $applicant_info = '<div class="emp-cell">' . $avatarHtml_
                    . '<div class="emp-meta">'
                    . '<div class="appno-chip"><i class="ti tabler-hash"></i> ' . htmlspecialchars($appNoVal) . '</div>'
                    . '<div class="emp-name">' . htmlspecialchars($empName_) . ($empCode_ ? ' <span class="emp-sub-light">(' . banglaNumber($empCode_) . ')</span>' : '') . '</div>'
                    . ($empJob_ ? '<div class="emp-sub">' . htmlspecialchars($empJob_) . '</div>' : '')
                    . ($secOrg_ ? '<div class="emp-sub-light">' . htmlspecialchars($secOrg_) . '</div>' : '')
                    . $_resubmitChip
                    . '</div></div>';

    // ── Multi-segment override for both চাহিত and প্রস্তাবিত ──
    $appID = (int)$row['leaveApplicationID'];
    $segQ = mysqli_query($con, "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
        LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
        WHERE s.applicationID = $appID ORDER BY s.kind ASC, s.serial ASC, s.dataID ASC");
    $reqSegs = []; $propSegs = [];
    while ($sr = mysqli_fetch_assoc($segQ)) {
        $k = $sr['kind'] ?? 'requested';
        if ($k === 'requested') $reqSegs[] = $sr;
        else                    $propSegs[] = $sr;
    }
    if (empty($reqSegs) && empty($propSegs)) { /* no segments — keep single fields */ }
    else {
        if (empty($reqSegs))  $reqSegs  = $propSegs;
        if (empty($propSegs)) $propSegs = $reqSegs;

        // Requested
        if (count($reqSegs) > 1) {
            $reqTotal = array_sum(array_column($reqSegs, 'days'));
            $titles = array_unique(array_column($reqSegs, 'leaveTitle'));
            $minF = min(array_column($reqSegs, 'dateFrom'));
            $maxT = max(array_column($reqSegs, 'dateTo'));
            $partsR = [];
            foreach ($reqSegs as $sg) {
                $partsR[] = banglaNumber((int)$sg['days']) . ' দিন ' . htmlspecialchars($sg['leaveTitle'] ?? 'অজানা');
            }
            $getLeaveTypeQRW['leaveTitle'] = implode(' + ', array_unique(array_column($reqSegs, 'leaveTitle')));
            $requested_leave_days = banglaNumber(date('d/m/Y', strtotime($minF))) . ' হইতে '
                . banglaNumber(date('d/m/Y', strtotime($maxT)))
                . ', মোট ' . banglaNumber($reqTotal) . ' দিন<br>'
                . '<small class="text-muted">(' . implode(' + ', $partsR) . ')</small>';
        } else if (count($reqSegs) === 1) {
            $sg = $reqSegs[0];
            $getLeaveTypeQRW['leaveTitle'] = $sg['leaveTitle'] ?? '';
            $requested_leave_days = banglaNumber(date('d/m/Y', strtotime($sg['dateFrom']))) . ' হইতে '
                . banglaNumber(date('d/m/Y', strtotime($sg['dateTo']))) . ', '
                . banglaNumber((int)$sg['days']) . ' দিন';
        }

        // Proposed
        if (count($propSegs) > 1) {
            $propTotal = array_sum(array_column($propSegs, 'days'));
            $minF = min(array_column($propSegs, 'dateFrom'));
            $maxT = max(array_column($propSegs, 'dateTo'));
            $partsP = [];
            foreach ($propSegs as $sg) {
                $partsP[] = banglaNumber((int)$sg['days']) . ' দিন ' . htmlspecialchars($sg['leaveTitle'] ?? 'অজানা');
            }
            $getApprovedLeaveTypeQRW['leaveTitle'] = implode(' + ', array_unique(array_column($propSegs, 'leaveTitle')));
            $proposed_leave_type = '';
            $proposed_leave_days = banglaNumber(date('d/m/Y', strtotime($minF))) . ' হইতে '
                . banglaNumber(date('d/m/Y', strtotime($maxT)))
                . ', মোট ' . banglaNumber($propTotal) . ' দিন<br>'
                . '<small class="text-muted">(' . implode(' + ', $partsP) . ')</small>';
        } else if (count($propSegs) === 1) {
            $sg = $propSegs[0];
            $getApprovedLeaveTypeQRW['leaveTitle'] = $sg['leaveTitle'] ?? '';
            $proposed_leave_type = '';
            $proposed_leave_days = banglaNumber(date('d/m/Y', strtotime($sg['dateFrom']))) . ' হইতে '
                . banglaNumber(date('d/m/Y', strtotime($sg['dateTo']))) . ', '
                . banglaNumber((int)$sg['days']) . ' দিন';
        }
    }

    // Build requested + proposed pill cells from $requested_leave_days etc.
    $requestedHtml = '';
    if ($dateF && $dateT) {
        $requestedHtml .= '<div class="date-range"><i class="ti tabler-calendar"></i><span>' . banglaNumber(date_format($dateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($dateT, "d/m/Y")) . '</span></div>';
        $requestedHtml .= '<div class="leave-meta"><span class="days-pill">' . banglaNumber((int)$dateDiff) . ' দিন</span> <span class="leave-type-chip">' . htmlspecialchars($getLeaveTypeQRW['leaveTitle'] ?? '') . '</span></div>';
    } else {
        $requestedHtml = '<span class="text-muted small">—</span>';
    }
    $proposedHtml = '';
    if ($adateF && $adateT) {
        $proposedHtml .= '<div class="date-range"><i class="ti tabler-calendar-check"></i><span>' . banglaNumber(date_format($adateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($adateT, "d/m/Y")) . '</span></div>';
        $proposedHtml .= '<div class="leave-meta"><span class="days-pill days-pill-success">' . banglaNumber((int)$adateDiff) . ' দিন</span>';
        $propTypeText = trim(($getApprovedLeaveTypeQRW['leaveTitle'] ?? '') . ($proposed_leave_type ? ' - ' . $proposed_leave_type : ''));
        if ($propTypeText) $proposedHtml .= ' <span class="leave-type-chip">' . htmlspecialchars($propTypeText) . '</span>';
        $proposedHtml .= '</div>';
    } else {
        $proposedHtml = '<span class="text-muted small">—</span>';
    }
    $dateBadge = '<div class="date-range"><i class="ti tabler-clock"></i><span>' . htmlspecialchars(banglaNumber($application_date_time)) . '</span></div>';

    $data[] = [
        "sl" => '<span class="serial-num">' . $sl . '</span>',
        "applicant_info" => $applicant_info,
        "application_date_time" => $dateBadge,
        "requested_leave" => $requestedHtml,
        "proposed_leave" => $proposedHtml,
        "status" => $status,
        "action" => $html
/*        "action" => '<button data-toggle="tooltip" data-placement="top" data-original-title="Edit" onclick="window.location=\'edit_employee_info_form?dataID=' . base64_encode($row['id']) . '&menuslug=manage-employee\'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-edit"></i></button>
                    <button data-toggle="tooltip" data-placement="top" data-original-title="Delete" onClick="removeData(' . $sl . ',' . $row['id'] . ')" type="button" class="btn btn-raised btn-icon btn-danger mr-1"><i class="fa fa-trash-o"></i></button>
                    <button data-toggle="tooltip" data-placement="top" data-original-title="Previous Leave" onclick="window.location=\'previous_leave_info_form?dataID=' . base64_encode($row['id']) . '&menuslug=manage-employee\'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-sort-amount-asc"></i></button>' */
    ];
    $sl++;
}

// Send response in JSON format
$response = [
    "draw" => $_POST['draw'],
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "data" => $data
];

// Output the response
echo json_encode($response);

// Close statement and connection
mysqli_stmt_close($stmt);
mysqli_close($con);
?>
