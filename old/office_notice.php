<?php
include(__DIR__ . '/includes/header_vuexy.php');
include('library/number_converter.php');

$getAllEmployeeListQ = mysqli_query($con, "SELECT employee_list.*, job_title.job_title_name
    FROM employee_list
    INNER JOIN job_title ON employee_list.designation = job_title.id
    WHERE employment_status=1 OR employment_status=2
    ORDER BY employee_id ASC");

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
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">অফিস আদেশ</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Search Form Card -->
<div class="card">
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <!-- Employee Selection -->
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label" for="employeeID">
                        কর্মকর্তা/ কর্মচারী নির্বাচন করুন
                    </label>
                    <select class="form-select employeeID" name="employeeID" id="employeeID">
                        <option value=''>সকল</option>
                        <?php
                        $getAllEmployeeListQ2 = mysqli_query($con, "SELECT employee_list.*, job_title.job_title_name
                            FROM employee_list
                            INNER JOIN job_title ON employee_list.designation = job_title.id
                            WHERE employment_status=1 OR employment_status=2
                            ORDER BY employee_id ASC");
                        while($empRow = mysqli_fetch_array($getAllEmployeeListQ2)){
                        ?>
                        <option value='<?php echo $empRow['id']; ?>' <?php echo (isset($_POST['employeeID']) && $_POST['employeeID']==$empRow['id']) ? 'selected' : ''; ?>>
                            <?php echo Bengali_DTN($empRow['employee_id']).' - '.$empRow['employee_name'].', '.$empRow['job_title_name']; ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Leave Type Selection -->
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label" for="leaveTypeInTwo">
                        ডিডাক্ট ফ্রম
                    </label>
                    <select class="form-select" name="leaveTypeInTwo" id="leaveTypeInTwo">
                        <option value=''>সকল</option>
                        <option value="1" <?php echo (isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo']=='1') ? 'selected' : ''; ?>>গড় বেতন</option>
                        <option value="2" <?php echo (isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo']=='2') ? 'selected' : ''; ?>>অর্ধ-গড় বেতন</option>
                        <option value="3" <?php echo (isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo']=='3') ? 'selected' : ''; ?>>নৈমিত্তিক (Casual Leave)</option>
                        <option value="4" <?php echo (isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo']=='4') ? 'selected' : ''; ?>>বিনা বেতনে ছুটি</option>
                        <option value="5" <?php echo (isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo']=='5') ? 'selected' : ''; ?>>ঐচ্ছিক ছুটি</option>
                        <option value="6" <?php echo (isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo']=='6') ? 'selected' : ''; ?>>কর্তনহীন ছুটি</option>
                        <option value="10" <?php echo (isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo']=='10') ? 'selected' : ''; ?>>অসাধারণ ছুটি</option>
                    </select>
                </div>

                <!-- Date From -->
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label" for="dateFrom">
                        Date From <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="dateFrom" name="dateFrom" placeholder="dd/mm/yyyy" value="<?php echo isset($_POST['dateFrom']) ? htmlspecialchars($_POST['dateFrom']) : ''; ?>" required autocomplete="off" />
                </div>

                <!-- Date To -->
                <div class="col-12 col-md-6 mb-4">
                    <label class="form-label" for="dateTo">
                        Date To <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="dateTo" name="dateTo" placeholder="dd/mm/yyyy" value="<?php echo isset($_POST['dateTo']) ? htmlspecialchars($_POST['dateTo']) : ''; ?>" required autocomplete="off" />
                </div>

                <!-- Form Actions -->
                <div class="col-12">
                    <button type="submit" name="submit" id="submit" class="btn btn-primary me-2">
                        <i class="ti tabler-search me-1"></i>Search
                    </button>
                    <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                        <i class="ti tabler-x me-1"></i>Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if(isset($_POST['submit'])){ ?>
<!-- Results Card -->
<div class="card mt-4">
    <div class="card-body">
        <div class="table-responsive">
            <table id="officeNoticeTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr style="background-color: #435971 !important; color: #ffffff !important;">
                        <th>ক্রমিক</th>
                        <th>আদেশ নং</th>
                        <th>নাম ও পদবী</th>
                        <th>ডিডাক্ট ফ্রম</th>
                        <th>তারিখ</th>
                        <th>অফিস আদেশ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sl = 1;
                    while($dataRow = mysqli_fetch_array($getOfficeNoticesQ)){
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

                        if($dataRow['leaveTypeInTwo'] == 1){
                            $leaveType = "গড় বেতন";
                        }else if($dataRow['leaveTypeInTwo'] == 2){
                            $leaveType = "অর্ধ-গড় বেতন";
                        }else if($dataRow['leaveTypeInTwo'] == 3){
                            $leaveType = "নৈমিত্তিক (Casual Leave)";
                        }else if($dataRow['leaveTypeInTwo'] == 4){
                            $leaveType = "বিনা বেতনে ছুটি";
                        }else if($dataRow['leaveTypeInTwo'] == 10){
                            $leaveType = "অসাধারণ ছুটি";
                        }else if($dataRow['leaveTypeInTwo'] == 5){
                            $leaveType = "ঐচ্ছিক ছুটি";
                        }else if($dataRow['leaveTypeInTwo'] == 6){
                            $leaveType = "কর্তনহীন ছুটি";
                        }
                    ?>
                    <tr>
                        <td><?php echo $sl; ?></td>
                        <td><?php echo htmlspecialchars($dataRow['officeNoticeNumber']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($getEmployeeDetailsQW['employee_name']).', '.htmlspecialchars($getDesignationDetailsQRW['job_title_name']).', '.htmlspecialchars($getSectionDetailsQRW['section_name']).', '.htmlspecialchars($getApplicgDetailsDesigQRW['organization_name']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($leaveType); ?></td>
                        <td><?php echo htmlspecialchars($officeNoticeDate); ?></td>
                        <td>
                            <a href="leave_office_notice.php?menuslug=allowed-leave-applications&leaveApplicationID=<?php echo $dataRow['dataID']; ?>" target="_blank" class="btn btn-sm btn-info">
                                <i class="ti tabler-file-description me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    <?php $sl++; } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<?php include(__DIR__ . '/includes/footer_vuexy.php'); ?>

<style>
#officeNoticeTable thead th {
    color: #ffffff !important;
    background-color: #435971 !important;
}
</style>

<script>
$(document).ready(function() {
    // Initialize Select2 for employee dropdown
    $('.employeeID').select2({
        placeholder: 'সকল',
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

    <?php if(isset($_POST['submit'])){ ?>
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
    <?php } ?>
});
</script>
