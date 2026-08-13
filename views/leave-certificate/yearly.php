<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$incrementYear = date('Y');

// Actor scope — Super Admin + HQ (org=4) see all centers; others restricted to
// own center, same rule the certificate generate page already applies.
$_actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_actorStmt, 's', $_un);
mysqli_stmt_execute($_actorStmt);
$_actor = mysqli_fetch_assoc(mysqli_stmt_get_result($_actorStmt)) ?: [];
mysqli_stmt_close($_actorStmt);
$_isSuperAdmin  = ((int)($_actor['user_group_id'] ?? 0) === 1);
$_myCenterID    = (int)($_actor['emp_org'] ?? 0);
$_seeAllCenters = ($_isSuperAdmin || $_myCenterID === 4);
$_orgScopeEL    = $_seeAllCenters ? '' : ' AND employee_list.organization_id = ' . $_myCenterID;

$getAllEmployeeListQ = mysqli_query($con, "SELECT employee_list.*, job_title.job_title_name
    FROM employee_list
    INNER JOIN job_title ON employee_list.designation = job_title.id
    WHERE (employment_status=1 OR employment_status=2)
      AND employee_list.pending_section_assignment = 0
      $_orgScopeEL
    ORDER BY employee_id ASC");

function Bengali_DTN($NRS){
    $englDTN = array('1','2','3','4','5','6','7','8','9','0');
    $bangDTN = array('১','২','৩','৪','৫','৬','৭','৮','৯','০');
    $converted = str_replace($bangDTN, $englDTN, $NRS);
    return $converted;
}
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">বার্ষিক ছুটি সনদ প্রিন্ট</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Yearly Leave Certificate Form Card -->
<div class="card">
    <div class="card-body">
        <form action="documents/yearly-certificate.php" method="get" target="_blank" data-turbo="false">
            <div class="row">
                <!-- Employee Selection -->
                <div class="col-12 mb-3">
                    <label class="form-label" for="employeeID">
                        কর্মকর্তা/ কর্মচারী সিলেক্ট করুন <span class="text-danger">*</span>
                    </label>
                    <select class="form-select employeeID" name="employeeID" id="employeeID" required>
                        <option value=''>-- সিলেক্ট করুন --</option>
                        <?php while($empRow = mysqli_fetch_array($getAllEmployeeListQ)){ ?>
                        <option value='<?php echo $empRow['id']; ?>'>
                            <?php echo Bengali_DTN($empRow['employee_id']).' - '.$empRow['employee_name'].', '.$empRow['job_title_name']; ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Year Selection -->
                <div class="col-12 mb-4">
                    <label class="form-label" for="incrementYear">
                        বছর <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" name="year" id="incrementYear" required>
                        <?php
                        $currentYear = date('Y');
                        for($y = $currentYear; $y >= $currentYear - 5; $y--){
                        ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Form Actions -->
                <div class="col-12">
                    <button type="submit" name="submit" id="submit" class="btn btn-primary me-2">
                        <i class="ti tabler-file-type-pdf me-1"></i>Generate PDF
                    </button>
                    <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                        <i class="ti tabler-x me-1"></i>Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
$(document).ready(function() {
    // Initialize Select2 for employee dropdown
    $('.employeeID').select2({
        placeholder: '-- সিলেক্ট করুন --',
        allowClear: true,
        width: '100%',
        theme: 'bootstrap-5'
    });
});
</script>
