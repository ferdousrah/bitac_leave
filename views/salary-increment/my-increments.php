<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$employeeID = isset($getUserInfoQRW['employee_id']) ? (int)$getUserInfoQRW['employee_id'] : 0;
$getMyIncrementDataQ = mysqli_query($con, "SELECT * FROM yearly_salary_increment WHERE employeeID='$employeeID' AND status=1 ORDER BY incrementYear DESC");

$rows = [];
while ($getMyIncrementDataQ && $r = mysqli_fetch_assoc($getMyIncrementDataQ)) $rows[] = $r;

$totalRecords    = count($rows);
$latestSalary    = $totalRecords > 0 ? (float)$rows[0]['incrementSalary'] : 0;
$totalIncrement  = array_sum(array_map(fn($r) => (float)$r['incrementAmount'], $rows));
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center no-print">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-trending-up me-2 text-primary"></i>বাৎসরিক বেতন বৃদ্ধির সার্ভিস বুক</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary me-2">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
        <button type="button" onclick="printContent()" class="btn btn-primary">
            <i class="ti tabler-printer me-1"></i>প্রিন্ট করুন
        </button>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2 no-print">
    <div class="col-12 col-md-4">
        <div class="stat-card stat-info"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="মোট বছরের বেতন বৃদ্ধি">
            <div class="stat-icon"><i class="ti tabler-calendar-stats"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($totalRecords); ?></div>
                <div class="stat-label">মোট বৎসর</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card stat-approved"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="বর্তমান (সর্বশেষ বৃদ্ধির পর) মূল বেতন">
            <div class="stat-icon"><i class="ti tabler-currency-taka"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber(number_format($latestSalary, 0)); ?></div>
                <div class="stat-label">বর্তমান মূল বেতন</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card stat-total"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="সকল বছরের সমষ্টি বেতন বৃদ্ধি">
            <div class="stat-icon"><i class="ti tabler-arrow-up-right"></i></div>
            <div class="stat-body">
                <div class="stat-num">+<?php echo banglaNumber(number_format($totalIncrement, 0)); ?></div>
                <div class="stat-label">মোট বৃদ্ধি</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0" id="printableArea">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table modern-leave-table align-middle salary-increment-table" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center" style="width:80px;">ক্রমিক</th>
                        <th class="text-center" style="width:120px;">বৎসর</th>
                        <th class="text-end">মূল বেতন</th>
                        <th class="text-end">বৃদ্ধির হার</th>
                        <th class="text-end">নতুন মূল বেতন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sl = 0; foreach ($rows as $dataRow): $sl++; ?>
                    <tr>
                        <td class="text-center"><span class="serial-num"><?php echo banglaNumber($sl); ?></span></td>
                        <td class="text-center"><span class="year-chip"><?php echo banglaNumber($dataRow['incrementYear']); ?></span></td>
                        <td class="text-end"><span class="salary-amount"><?php echo banglaNumber(number_format((float)$dataRow['presentSalary'], 0)); ?> ৳</span></td>
                        <td class="text-end"><span class="increment-amount"><i class="ti tabler-arrow-up-right me-1"></i>+<?php echo banglaNumber(number_format((float)$dataRow['incrementAmount'], 0)); ?> ৳</span></td>
                        <td class="text-end"><span class="salary-new"><?php echo banglaNumber(number_format((float)$dataRow['incrementSalary'], 0)); ?> ৳</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalRecords === 0): ?>
            <div class="empty-state-rich py-5">
                <i class="ti tabler-file-invoice"></i>
                <div class="empty-title">কোন তথ্য পাওয়া যায়নি</div>
                <div class="empty-subtitle">এখনো কোনো বেতন বৃদ্ধির রেকর্ড নেই</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<style>
.year-chip {
    display: inline-block;
    background: #efeaff;
    color: #5648c4;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.88rem;
    font-weight: 600;
    border: 1px solid #ddd5f6;
}
.salary-amount {
    font-family: var(--bs-font-monospace, monospace);
    font-size: 0.9rem;
    color: #4a4f6f;
    font-weight: 500;
}
.increment-amount {
    display: inline-flex;
    align-items: center;
    background: #e6f7ee;
    color: #1a7e44;
    padding: 4px 10px;
    border-radius: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    font-family: var(--bs-font-monospace, monospace);
}
.salary-new {
    font-family: var(--bs-font-monospace, monospace);
    font-size: 0.95rem;
    color: #1a7e44;
    font-weight: 700;
}

/* Print styles */
@media print {
    body { background: #fff !important; }
    .no-print { display: none !important; }
    .card, .leave-apps-card { border: none !important; box-shadow: none !important; }
    .modern-leave-table thead th {
        background: #f0f1f5 !important;
        color: #3a3d53 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .year-chip, .increment-amount {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<script type="text/javascript">
function printContent() { window.print(); }

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printContent();
    }
});

$(document).ready(function() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
    }
});
</script>
