<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$getAllEmployeeListQ = mysqli_query($con, "SELECT employee_list.*, job_title.job_title_name
    FROM employee_list
    INNER JOIN job_title ON employee_list.designation = job_title.id
    WHERE employment_status=1 OR employment_status=2
    ORDER BY employee_id ASC");

// Pull the real leave-type list from DB. The old hardcoded dropdown used
// values (1..10) that did NOT match leave_types.leaveID, so filtering by
// leave type (e.g. Casual Leave) matched zero rows even when data existed.
$leaveTypeOptions = [];
$_ltRes = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types ORDER BY leaveID");
while ($_ltRow = mysqli_fetch_assoc($_ltRes)) {
    $leaveTypeOptions[(int)$_ltRow['leaveID']] = $_ltRow['leaveTitle'];
}

function Bengali_DTN($NRS){
    $englDTN = array('1','2','3','4','5','6','7','8','9','0');
    $bangDTN = array('১','২','৩','৪','৫','৬','৭','৮','৯','০');
    $converted = str_replace($bangDTN, $englDTN, $NRS);
    return $converted;
}

if(isset($_POST['submit'])){
    if(isset($_POST['employeeID']) && $_POST['employeeID']!=''){
        $empSQL = " and applicantID='".mysqli_real_escape_string($con, $_POST['employeeID'])."'";
    }else{
        $empSQL = "";
    }

    if(isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo']!=''){
        $leaveTypeInTwoSQL = " and leaveTypeInTwo='".mysqli_real_escape_string($con, $_POST['leaveTypeInTwo'])."'";
    }else{
        $leaveTypeInTwoSQL = "";
    }

    $dateFromArray = explode('/', $_POST['dateFrom']);
    $dateFrom = $dateFromArray[2].'-'.$dateFromArray[1].'-'.$dateFromArray[0];

    $dateToArray = explode('/', $_POST['dateTo']);
    $dateTo = $dateToArray[2].'-'.$dateToArray[1].'-'.$dateToArray[0];

    $getOfficeNoticesQ = mysqli_query($con, "SELECT * FROM leave_applications WHERE 1=1 $empSQL $leaveTypeInTwoSQL AND (officeNoticeDate BETWEEN '$dateFrom' AND '$dateTo') AND officeNoticeDate!='' ORDER BY officeNoticeNumber ASC");
}

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'office-notice');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-file-description me-2 text-primary"></i>অফিস আদেশ</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>তারিখের পরিসরে জারিকৃত অফিস আদেশের তালিকা খুঁজুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<style>
.simple-form-card { border-radius: 0.75rem; }
.simple-form-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .simple-form-card .card-body { padding: 1rem; }
}
.simple-form-card .form-section-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #eef0f5;
}
.simple-form-card .section-icon-tile {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: #f0edff;
    color: #5648c4;
    border-radius: 0.5rem;
    font-size: 1.05rem;
}
.simple-form-card .section-title {
    margin: 0;
    color: #2c2e3a;
    font-size: 1rem;
    font-weight: 600;
}
.simple-form-card .form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.simple-form-card .form-control:focus,
.simple-form-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.simple-form-card .input-group-text {
    background: #fafbfd;
    border-color: #e0e4ee;
    color: #5d6580;
}
.simple-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}

/* Results table */
.notice-results-card { border-radius: 0.75rem; }
#officeNoticeTable {
    border: 1px solid #eef0f5;
    border-radius: 0.6rem;
    overflow: hidden;
}
#officeNoticeTable thead th {
    background: #fafbfd !important;
    color: #5d6580 !important;
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #eef0f5 !important;
    padding: 0.85rem 1rem;
}
#officeNoticeTable tbody td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: #2c2e3a;
}
.notice-results-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #8a90a6;
}
.notice-results-empty i { font-size: 2rem; color: #b9b0f4; }
</style>

<!-- Search Form Card -->
<div class="card simple-form-card shadow-sm border-0">
    <div class="card-body">
        <form action="" method="post">
            <!-- Section header -->
            <div class="form-section-header">
                <span class="section-icon-tile"><i class="ti tabler-filter"></i></span>
                <h6 class="section-title">খোঁজার ফিল্টার</h6>
            </div>

            <div class="row g-3">
                <!-- Employee Selection -->
                <div class="col-12 col-md-6">
                    <label class="form-label" for="employeeID">কর্মকর্তা / কর্মচারী</label>
                    <select class="form-select employeeID" name="employeeID" id="employeeID">
                        <option value=''>সকল কর্মচারী</option>
                        <?php
                        $getAllEmployeeListQ2 = mysqli_query($con, "SELECT employee_list.*, job_title.job_title_name
                            FROM employee_list
                            INNER JOIN job_title ON employee_list.designation = job_title.id
                            WHERE employment_status=1 OR employment_status=2
                            ORDER BY employee_id ASC");
                        while($empRow = mysqli_fetch_array($getAllEmployeeListQ2)):
                        ?>
                        <option value='<?= $empRow['id'] ?>' <?= (isset($_POST['employeeID']) && $_POST['employeeID'] == $empRow['id']) ? 'selected' : '' ?>>
                            <?= Bengali_DTN($empRow['employee_id']) . ' - ' . htmlspecialchars($empRow['employee_name']) . ', ' . htmlspecialchars($empRow['job_title_name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Leave Type Selection -->
                <div class="col-12 col-md-6">
                    <label class="form-label" for="leaveTypeInTwo">ডিডাক্ট ফ্রম</label>
                    <select class="form-select" name="leaveTypeInTwo" id="leaveTypeInTwo">
                        <option value=''>সকল ধরন</option>
                        <?php foreach ($leaveTypeOptions as $ltID => $ltTitle): ?>
                            <option value="<?= $ltID ?>" <?= (isset($_POST['leaveTypeInTwo']) && (string)$_POST['leaveTypeInTwo'] === (string)$ltID) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ltTitle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date From -->
                <div class="col-12 col-md-6">
                    <label class="form-label" for="dateFrom">শুরুর তারিখ <span class="text-danger">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-calendar-event"></i></span>
                        <input type="text" class="form-control" id="dateFrom" name="dateFrom" placeholder="dd/mm/yyyy" value="<?= isset($_POST['dateFrom']) ? htmlspecialchars($_POST['dateFrom']) : '' ?>" required autocomplete="off">
                    </div>
                </div>

                <!-- Date To -->
                <div class="col-12 col-md-6">
                    <label class="form-label" for="dateTo">শেষ তারিখ <span class="text-danger">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-calendar-event"></i></span>
                        <input type="text" class="form-control" id="dateTo" name="dateTo" placeholder="dd/mm/yyyy" value="<?= isset($_POST['dateTo']) ? htmlspecialchars($_POST['dateTo']) : '' ?>" required autocomplete="off">
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="simple-form-actions d-flex gap-2 justify-content-end">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </button>
                <button type="submit" name="submit" id="submit" class="btn btn-primary px-4">
                    <i class="ti tabler-search me-1"></i>খুঁজুন
                </button>
            </div>
        </form>
    </div>
</div>

<?php if(isset($_POST['submit'])): ?>
<!-- Results Card -->
<div class="card notice-results-card shadow-sm border-0 mt-4">
    <div class="card-body">
        <h6 class="mb-3 fw-bold" style="color:#2c2e3a;font-size:0.95rem;">
            <i class="ti tabler-list-search me-1 text-primary"></i>খোঁজার ফলাফল
        </h6>
        <div class="table-responsive">
            <table id="officeNoticeTable" class="table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:60px;">ক্রমিক</th>
                        <th>আদেশ নং</th>
                        <th>নাম ও পদবী</th>
                        <th>ডিডাক্ট ফ্রম</th>
                        <th>তারিখ</th>
                        <th class="text-center" style="width:100px;">অফিস আদেশ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sl = 1;
                    while($dataRow = mysqli_fetch_array($getOfficeNoticesQ)):
                        $officeNoticeDateArray = explode('-', $dataRow['officeNoticeDate']);
                        $officeNoticeDate = $officeNoticeDateArray['2'].'/'.$officeNoticeDateArray[1].'/'.$officeNoticeDateArray[0];

                        $getEmployeeDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$dataRow[applicantID]'");
                        $getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

                        $getDesignationDetailsQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='$getEmployeeDetailsQW[designation]'");
                        $getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

                        $getApplicgDetailsDesigQ = mysqli_query($con, "SELECT * FROM organization WHERE id='$getEmployeeDetailsQW[organization_id]'");
                        $getApplicgDetailsDesigQRW = mysqli_fetch_assoc($getApplicgDetailsDesigQ);

                        $getSectionDetailsQ = mysqli_query($con, "SELECT * FROM sections WHERE id='$getEmployeeDetailsQW[section_id]'");
                        $getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

                        // Use the DB-backed map built up-top so the label always matches leave_types
                        $leaveType = $leaveTypeOptions[(int)$dataRow['leaveTypeInTwo']] ?? '';
                    ?>
                    <tr>
                        <td class="text-muted"><?= $sl ?></td>
                        <td><strong><?= htmlspecialchars($dataRow['officeNoticeNumber']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($getEmployeeDetailsQW['employee_name']) ?>,
                            <?= htmlspecialchars($getDesignationDetailsQRW['job_title_name']) ?>,
                            <?= htmlspecialchars($getSectionDetailsQRW['section_name']) ?>,
                            <?= htmlspecialchars($getApplicgDetailsDesigQRW['organization_name']) ?>
                        </td>
                        <td><?= htmlspecialchars($leaveType) ?></td>
                        <td><?= htmlspecialchars($officeNoticeDate) ?></td>
                        <td class="text-center">
                            <a href="../../api/reports/leave-notice.php?menuslug=allowed-leave-applications&leaveApplicationID=<?= $dataRow['dataID'] ?>" target="_blank" class="btn btn-sm btn-label-primary">
                                <i class="ti tabler-eye me-1"></i>দেখুন
                            </a>
                        </td>
                    </tr>
                    <?php $sl++; endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
$(document).ready(function() {
    // Initialize Select2 for employee dropdown
    $('.employeeID').select2({
        placeholder: 'সকল কর্মচারী',
        allowClear: true,
        width: '100%',
        theme: 'bootstrap-5'
    });

    // Initialize datepickers
    $("#dateFrom").datepicker({
        dateFormat: "dd/mm/yy",
        changeMonth: true,
        changeYear: true,
        yearRange: "2020:2030"
    });

    $("#dateTo").datepicker({
        dateFormat: "dd/mm/yy",
        changeMonth: true,
        changeYear: true,
        yearRange: "2020:2030"
    });

    <?php if(isset($_POST['submit'])): ?>
    // Initialize DataTable for results (client-side)
    $('#officeNoticeTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: {
            search: "খুঁজুন:",
            lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
            info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
            infoEmpty: "কোন এন্ট্রি নেই",
            infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
            emptyTable: "টেবিলে কোন ডেটা নেই",
            paginate: {
                first: "প্রথম",
                previous: "পূর্ববর্তী",
                next: "পরবর্তী",
                last: "শেষ"
            }
        },
        order: [[0, 'asc']]
    });
    <?php endif; ?>
});
</script>
