<?php
include(__DIR__ . '/includes/header_vuexy.php');
include_once('function.php');

$dateStart = date('Y-m-01');
$enddate = date('Y-m-t');
$todayDate = ShowBangladeshDate();

// Get total employees
$getTotalEmpQ = mysqli_query($con, "select count(*) as totalEmp from employee_list where employment_status=1");
$getTotalEmpQRW = mysqli_fetch_assoc($getTotalEmpQ);

// Get today's leave count
$getTodayOnLeaveEMPQ = mysqli_query($con, "select count(*) as todayOnLeave from leave_applications where status=1 and ('$todayDate' between `dateFrom` and `dateTo`)");
$getTodayOnLeaveEMPQRW = mysqli_fetch_assoc($getTodayOnLeaveEMPQ);

$employeeID = $getUserInfoQRW['employee_id'];

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$getPrevLeaveHistory = mysqli_query($con, "select * from previous_leave_deduction where employeeID='$employeeID' and isApproved=1");
$getPrevLeaveHistoryRW = mysqli_fetch_assoc($getPrevLeaveHistory);

// Set default values if no record found
if (!$getPrevLeaveHistoryRW) {
    $getPrevLeaveHistoryRW = [
        'undeductibleLeave' => 0,
        'leaveWithoutPay' => 0,
        'extraOrdinaryLeave' => 0,
        'avgSalary' => 0,
        'halfAvgSalary' => 0
    ];
}

// কর্তনহীন ছুটি
$undeductibleLeaveQ = mysqli_query($con, "select sum(leaveDeduction) as totalUndeductDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='6' and isApproved=1");
$undeductibleLeaveQRW = mysqli_fetch_assoc($undeductibleLeaveQ);

$getTotalLWioutPayLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLUnDeductL from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='6'");
$getTotalLWioutPayLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLWioutPayLeaveInCurrentYearQ);

$totalUndeductibleLeave = ($getPrevLeaveHistoryRW['undeductibleLeave'] ?? 0) + ($undeductibleLeaveQRW['totalUndeductDeduction'] ?? 0) + ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLUnDeductL'] ?? 0);

// calculate leave deduction
$calculateFullAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalFullLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='1' and isApproved=1");
$calculateFullAvgQRW = mysqli_fetch_assoc($calculateFullAvgQ);

$calculateHalfAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalLHalfDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='2' and isApproved=1");
$calculateHalfAvgQRW = mysqli_fetch_assoc($calculateHalfAvgQ);

$calculateCLAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalCLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='3' and isApproved=1 and (createDate between '$casualStart' and '$casualEnd')");
$calculateCLAvgQRW = mysqli_fetch_assoc($calculateCLAvgQ);

// optional leave
$optionalLHistoryQ = mysqli_query($con, "select sum(leaveDeduction) as totalOLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='5' and isApproved=1 and (createDate between '$casualStart' and '$casualEnd')");
$optionalLHistoryQRW = mysqli_fetch_assoc($optionalLHistoryQ);

$calculateWPAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalWPDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='4' and isApproved=1");
$calculateWPAvgQRW = mysqli_fetch_assoc($calculateWPAvgQ);

// extraordinary leave
$calculateExOrLManualQ = mysqli_query($con, "select sum(leaveDeduction) as totalExODeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='10' and isApproved=1");
$calculateExOrLManualQRW = mysqli_fetch_assoc($calculateExOrLManualQ);

$getTotalLWioutPayLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLWithoutPay from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='4'");
$getTotalLWioutPayLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLWioutPayLeaveInCurrentYearQ);

$getTotalLExtraOrdinaryLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalExorLeave from leave_applications where status=1 and applicantID='$employeeID' and (leaveTypeInTwo='10')");
$getTotalLExtraOrdinaryLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLExtraOrdinaryLeaveInCurrentYearQ);

$getTotalLCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLCasualSpent from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='3' and (`approvedDateFrom`>='$casualStart' and `approvedDateTo`<='$casualEnd')");
$getTotalLCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLCasualLeaveInCurrentYearQ);

// optional leave
$getTotalOptionalLeaveQ = mysqli_query($con, "select sum(approvedDays) as totalOLSpent from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='5' and (`approvedDateFrom`>='$casualStart' and `approvedDateTo`<='$casualEnd')");
$getTotalOptionalLeaveQRW = mysqli_fetch_assoc($getTotalOptionalLeaveQ);

// total without pay to display
$totalWithoutPay = (($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] ?? 0) + ($getPrevLeaveHistoryRW['leaveWithoutPay'] ?? 0) + ($calculateWPAvgQRW['totalWPDeduction'] ?? 0));

$totalWithoutPayyears = floor($totalWithoutPay / 360);
$totalWithoutPaymonths = floor(($totalWithoutPay - ($totalWithoutPayyears * 360))/30);
$totalWithoutPaydays = round($totalWithoutPay - ($totalWithoutPayyears * 360) - ($totalWithoutPaymonths * 30));

// total extra ordinary leave to display
$totalExtraOrdinaryLeave = (($getTotalLExtraOrdinaryLeaveInCurrentYearQRW['totalExorLeave'] ?? 0) + ($getPrevLeaveHistoryRW['extraOrdinaryLeave'] ?? 0) + ($calculateExOrLManualQRW['totalExODeduction'] ?? 0));

$totalExtraOrdinaryLeaveYears = floor($totalExtraOrdinaryLeave / 360);
$totalExtraOrdinaryLeaveMonths = floor(($totalExtraOrdinaryLeave - ($totalExtraOrdinaryLeaveYears * 360))/30);
$totalExtraOrdinaryLeaveDays = round($totalExtraOrdinaryLeave - ($totalExtraOrdinaryLeaveYears * 360) - ($totalExtraOrdinaryLeaveMonths * 30));

$diff = abs(strtotime($todayDate)-strtotime($getEmployeeInfoQRW['joining_date']));
$days = round($diff/(60*60*24));

$getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
$getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);

$totalAvgSalVugkrito = ($getPrevLeaveHistoryRW['avgSalary'] ?? 0) + ($getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'] ?? 0) + ($calculateFullAvgQRW['totalFullLDeduction'] ?? 0);

$fullAvgVugkritoSalLeaveyears = floor($totalAvgSalVugkrito / 360);
$fullAvgVugkritoSalLeavemonths = floor(($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 360))/30);
$fullAvgVugkritoSalLeavedays = round($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 360) - ($fullAvgVugkritoSalLeavemonths * 30));

// get voghkrito chuti
$getTotalhalfAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays)*2 as totalHalfAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='2'");
$getTotalhalfAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalhalfAvgSalLeaveUsedQ);

$totalHalfAvgSalVugkrito = ($getPrevLeaveHistoryRW['halfAvgSalary'] ?? 0) + ($getTotalhalfAvgSalLeaveUsedQRW['totalHalfAvgSal'] ?? 0) + ($calculateHalfAvgQRW['totalLHalfDeduction'] ?? 0);

$halfAvgVugkritoSalLeaveyears = floor($totalHalfAvgSalVugkrito / 360);
$halfAvgVugkritoSalLeavemonths = floor(($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 360))/30);
$halfAvgVugkritoSalLeavedays = round($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 360) - ($halfAvgVugkritoSalLeavemonths * 30));

// job duration
$days = $days - 1;

$employmentyears = floor($days / 365);
$employmentmonths = floor(($days - ($employmentyears * 365))/30.416667);
$employmentdays = round($days - ($employmentyears * 365) - ($employmentmonths * 30.416667));

$actualJobDuration = $days;
$fullAvgSalLeave = floor($actualJobDuration/11);
$fullAvgSalLeaveRemainder = fmod($actualJobDuration, 11);

if($fullAvgSalLeaveRemainder >= 6){
    $fullAvgSalLeave = $fullAvgSalLeave + 1;
}

// in year month day
$fullAvgSalLeaveyears = floor($fullAvgSalLeave / 360);
$fullAvgSalLeavemonths = floor(($fullAvgSalLeave - ($fullAvgSalLeaveyears * 360))/30);
$fullAvgSalLeavedays = round($fullAvgSalLeave - ($fullAvgSalLeaveyears * 360) - ($fullAvgSalLeavemonths * 30));

// get rest avg salary leave
$restAvgSalLeave = $fullAvgSalLeave - $totalAvgSalVugkrito;

// rest in year month day
$fullAvgRestSalLeaveyears = floor($restAvgSalLeave / 360);
$fullAvgRestSalLeavemonths = floor(($restAvgSalLeave - ($fullAvgRestSalLeaveyears * 360))/30);
$fullAvgRestSalLeavedays = round($restAvgSalLeave - ($fullAvgRestSalLeaveyears * 360) - ($fullAvgRestSalLeavemonths * 30));

$halfAvgSalLeave = floor($actualJobDuration/12);
$halfAvgSalLeaveRemainder = fmod($actualJobDuration, 12);

if($halfAvgSalLeaveRemainder >= 6){
    $halfAvgSalLeave = $halfAvgSalLeave + 1;
}

// in year month day
$halfAvgSalLeaveyears = floor($halfAvgSalLeave / 360);
$halfAvgSalLeavemonths = floor(($halfAvgSalLeave - ($halfAvgSalLeaveyears * 360))/30);
$halfAvgSalLeavedays = round($halfAvgSalLeave - ($halfAvgSalLeaveyears * 360) - ($halfAvgSalLeavemonths * 30));

// get rest avg salary leave
$restHalfAvgSalLeave = $halfAvgSalLeave - $totalHalfAvgSalVugkrito;

// rest in year month day
$halfAvgRestSalLeaveyears = floor($restHalfAvgSalLeave / 360);
$halfAvgRestSalLeavemonths = floor(($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 360))/30);
$halfAvgRestSalLeavedays = round($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 360) - ($halfAvgRestSalLeavemonths * 30));

$casualCurrentBalance = $casualBalance - (($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] ?? 0) + ($calculateCLAvgQRW['totalCLDeduction'] ?? 0));
$casualSpent = (($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] ?? 0) + ($calculateCLAvgQRW['totalCLDeduction'] ?? 0));

// optional leave
$optionalLeaveCurrentBalance = $optionalLBalance - (($getTotalOptionalLeaveQRW['totalOLSpent'] ?? 0) + ($optionalLHistoryQRW['totalOLDeduction'] ?? 0));
$optionalLeaveSpent = (($getTotalOptionalLeaveQRW['totalOLSpent'] ?? 0) + ($optionalLHistoryQRW['totalOLDeduction'] ?? 0));

$actualJobDurationAfterDeduct = $days - ($totalAvgSalVugkrito + $totalHalfAvgSalVugkrito + $totalWithoutPay + $totalExtraOrdinaryLeave + $totalUndeductibleLeave);

$actualJobDurationInYears = floor($actualJobDurationAfterDeduct / 360);
$actualJobDurationInMonths = floor(($actualJobDurationAfterDeduct - ($actualJobDurationInYears * 360))/30);
$actualJobDurationInDays = round($actualJobDurationAfterDeduct - ($actualJobDurationInYears * 360) - ($actualJobDurationInMonths * 30));
?>

<style>
.stat-card {
    text-align: center;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
}

.countdown span {
    display: inline-block;
    margin: 0 5px;
    padding: 10px 15px;
    background: #f0f0f0;
    border-radius: 8px;
    font-weight: bold;
    color: #696cff;
}

.prl-date {
    font-size: 0.875rem;
    color: #8592a3;
}
</style>

<!-- Dashboard Statistics -->
<div class="row g-3 mb-4">
    <!-- Service Duration -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-white">
                <img src="dashboard-icons/halfleave.png" class="stat-icon" alt="Duration" />
                <h6 class="text-white mb-1">চাকরিকাল</h6>
                <h5 class="text-white fw-bold">
                    <?php echo $obj->engToBn($employmentyears).' বছর '.$obj->engToBn($employmentmonths).' মাস '.$obj->engToBn($employmentdays).' দিন'; ?>
                </h5>
            </div>
        </div>
    </div>

    <!-- Full Average Leave Taken -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="card-body text-white">
                <img src="dashboard-icons/halfleave.png" class="stat-icon" alt="Full Avg" />
                <h6 class="text-white mb-1">গড়-বেতনে ভোগকৃত ছুটি</h6>
                <h5 class="text-white fw-bold">
                    <?php echo $obj->engToBn($fullAvgVugkritoSalLeaveyears); ?> বছর <?php echo $obj->engToBn($fullAvgVugkritoSalLeavemonths); ?> মাস <?php echo $obj->engToBn($fullAvgVugkritoSalLeavedays); ?> দিন
                </h5>
            </div>
        </div>
    </div>

    <!-- Half Average Leave Taken -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="card-body text-white">
                <img src="dashboard-icons/sunbed.png" class="stat-icon" alt="Half Avg" />
                <h6 class="text-white mb-1">অর্ধ-গড় বেতনে ভোগকৃত ছুটি</h6>
                <h5 class="text-white fw-bold">
                    <?php echo $obj->engToBn($halfAvgVugkritoSalLeaveyears); ?> বছর <?php echo $obj->engToBn($halfAvgVugkritoSalLeavemonths); ?> মাস <?php echo $obj->engToBn($halfAvgVugkritoSalLeavedays); ?> দিন
                </h5>
            </div>
        </div>
    </div>

    <!-- Leave Without Pay -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card stat-card" style="background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);">
            <div class="card-body text-white">
                <img src="dashboard-icons/no-money.png" class="stat-icon" alt="Without Pay" />
                <h6 class="text-white mb-1">বিনা বেতনে ভোগকৃত ছুটি</h6>
                <h5 class="text-white fw-bold">
                    <?php echo $obj->engToBn($totalWithoutPayyears); ?> বছর <?php echo $obj->engToBn($totalWithoutPaymonths); ?> মাস <?php echo $obj->engToBn($totalWithoutPaydays); ?> দিন
                </h5>
            </div>
        </div>
    </div>

    <!-- Extraordinary Leave -->
    <div class="col-xl-1 col-md-4 col-sm-6">
        <div class="card stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
            <div class="card-body text-white">
                <img src="dashboard-icons/no-money.png" class="stat-icon" alt="Extraordinary" />
                <h6 class="text-white mb-1">অসাধারণ ভোগকৃত ছুটি</h6>
                <h5 class="text-white fw-bold">
                    <?php echo $obj->engToBn($totalExtraOrdinaryLeaveYears); ?> বছর <?php echo $obj->engToBn($totalExtraOrdinaryLeaveMonths); ?> মাস <?php echo $obj->engToBn($totalExtraOrdinaryLeaveDays); ?> দিন
                </h5>
            </div>
        </div>
    </div>

    <!-- Non-deductible Leave -->
    <div class="col-xl-1 col-md-4 col-sm-6">
        <div class="card stat-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
            <div class="card-body text-white">
                <img src="dashboard-icons/no-money.png" class="stat-icon" alt="Non-deductible" />
                <h6 class="text-white mb-1">কর্তনহীন ছুটি</h6>
                <h5 class="text-white fw-bold">
                    <?php echo $obj->engToBn($totalUndeductibleLeave); ?> দিন
                </h5>
            </div>
        </div>
    </div>

    <!-- Actual Service Duration -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <div class="card-body text-white">
                <img src="dashboard-icons/sunbed.png" class="stat-icon" alt="Actual Duration" />
                <h6 class="text-white mb-1">প্রকৃত চাকরিকাল</h6>
                <h5 class="text-white fw-bold">
                    <?php echo $obj->engToBn($actualJobDurationInYears); ?> বছর <?php echo $obj->engToBn($actualJobDurationInMonths); ?> মাস <?php echo $obj->engToBn($actualJobDurationInDays); ?> দিন
                </h5>
            </div>
        </div>
    </div>
</div>

<!-- Leave Balance and PRL Countdown -->
<div class="row g-3 mb-4">
    <!-- Leave Balance Table -->
    <div class="col-xl-10">
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h4 class="text-white mb-0 text-center">অবশিষ্ট পাওনা ছুটি</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th class="text-center">গড় বেতনে</th>
                                <th class="text-center">অর্ধ-গড় বেতনে</th>
                                <th class="text-center">নৈমিত্তিক</th>
                                <th class="text-center">বিনা বেতনে ছুটি</th>
                                <th class="text-center">ঐচ্ছিক ছুটি</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center fw-bold">
                                    <?php echo $obj->engToBn($fullAvgRestSalLeaveyears); ?> বছর
                                    <?php echo $obj->engToBn($fullAvgRestSalLeavemonths); ?> মাস
                                    <?php echo $obj->engToBn($fullAvgRestSalLeavedays); ?> দিন
                                </td>
                                <td class="text-center fw-bold">
                                    <?php echo $obj->engToBn($halfAvgRestSalLeaveyears); ?> বছর
                                    <?php echo $obj->engToBn($halfAvgRestSalLeavemonths); ?> মাস
                                    <?php echo $obj->engToBn($halfAvgRestSalLeavedays); ?> দিন
                                </td>
                                <td class="text-center fw-bold">
                                    <?php echo $obj->engToBn($casualCurrentBalance); ?> দিন
                                </td>
                                <td class="text-center fw-bold">
                                    <?php echo $obj->engToBn($totalWithoutPayyears); ?> বছর
                                    <?php echo $obj->engToBn($totalWithoutPaymonths); ?> মাস
                                    <?php echo $obj->engToBn($totalWithoutPaydays); ?> দিন
                                </td>
                                <td class="text-center fw-bold">
                                    <?php echo $obj->engToBn($optionalLeaveCurrentBalance); ?> দিন
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PRL Countdown -->
    <div class="col-xl-2">
        <div class="card h-100">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h6 class="text-white mb-1 text-center">অবসর গ্রহণের তারিখ</h6>
                <p class="text-white-50 mb-0 text-center prl-date" id="prlDateDisplay">--/--/--</p>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div class="countdown text-center">
                    <div><span id="years">0</span> বছর</div>
                    <div><span id="months">0</span> মাস</div>
                    <div><span id="days">0</span> দিন</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Absent Employees Table (if dashboardType == 2) -->
<?php if($getUserInfoQRW['dashboardType'] == 2){ ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">ছুটি হইতে কর্মস্থলে যোগদান করেননি</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="absent_employees" class="table table-hover" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>কর্মকর্তা/কর্মচারীর নাম</th>
                                <th>আইডি</th>
                                <th>পদবী</th>
                                <th>শাখা</th>
                                <th>যোগদানের তারিখ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be inserted here dynamically by DataTables -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php
include(__DIR__ . '/includes/footer_vuexy.php');
?>

<script>
$(document).ready(function() {
    <?php if($getUserInfoQRW['dashboardType'] == 2){ ?>
    // Destroy the DataTable if it's already initialized
    if ($.fn.dataTable.isDataTable('#absent_employees')) {
        $('#absent_employees').DataTable().destroy();
    }

    // Reinitialize the DataTable
    $('#absent_employees').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "get_absent_employees.php",
            "type": "POST"
        },
        "columns": [
            { "data": "serial" },
            { "data": "employee_name" },
            { "data": "employee_id" },
            { "data": "job_title" },
            { "data": "section_name" },
            { "data": "joining_date" }
        ],
        "language": {
            "search": "খুঁজুন:",
            "lengthMenu": "প্রদর্শন করুন _MENU_ এন্ট্রি",
            "info": "মোট _TOTAL_ এন্ট্রির মধ্যে _START_ থেকে _END_ প্রদর্শন করা হচ্ছে",
            "infoEmpty": "কোন এন্ট্রি পাওয়া যায়নি",
            "paginate": {
                "first": "প্রথম",
                "last": "শেষ",
                "next": "পরবর্তী",
                "previous": "পূর্ববর্তী"
            },
            "processing": "প্রক্রিয়াকরণ..."
        }
    });
    <?php } ?>
});

function fetchCountdown() {
    fetch('prlcountdown.php')
        .then(response => response.json())
        .then(data => updateCountdown(data))
        .catch(error => console.error('Error fetching countdown:', error));
}

function updateCountdown(data) {
    document.getElementById('years').textContent = data.years;
    document.getElementById('months').textContent = data.months;
    document.getElementById('days').textContent = data.days;
    document.getElementById('prlDateDisplay').textContent = data.prl_date;
}

// Initial load
fetchCountdown();

// Update countdown every day
setInterval(fetchCountdown, 24 * 60 * 60 * 1000); // 24 hours in milliseconds
</script>
