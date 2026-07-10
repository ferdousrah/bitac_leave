<?php
// Filter-panel partial for allowed-applications.php — included once per tab.
// Expects: $secOptions, $leaveTypeOptions, $scope from parent.
if (!isset($scope)) $scope = 'pend';
?>
<div class="filter-panel mb-3 is-collapsed" data-scope="<?= $scope ?>">
    <div class="filter-panel-header">
        <button type="button" class="filter-panel-toggle" data-scope="<?= $scope ?>" aria-expanded="false" aria-controls="filterBody-<?= $scope ?>">
            <i class="ti tabler-filter me-1"></i>
            <span class="filter-panel-title">ফিল্টার</span>
            <span class="filter-active-count" data-scope="<?= $scope ?>"></span>
            <i class="ti tabler-chevron-down filter-chevron ms-2"></i>
        </button>
        <div class="filter-panel-actions">
            <button type="button" class="btn btn-sm btn-icon btn-label-primary table-refresh" data-scope="<?= $scope ?>" title="টেবিল রিফ্রেশ" data-bs-toggle="tooltip">
                <i class="ti tabler-refresh"></i>
            </button>
            <button type="button" class="btn btn-sm btn-label-secondary filter-reset" data-scope="<?= $scope ?>">
                <i class="ti tabler-x me-1"></i>রিসেট
            </button>
        </div>
    </div>
    <div class="filter-panel-body" id="filterBody-<?= $scope ?>">
        <div class="row g-2">
            <div class="col-12 col-md-6 col-lg-3">
                <label class="filter-label"><i class="ti tabler-building"></i>শাখা</label>
                <select id="<?= $scope ?>SectionFilter" class="form-select form-select-sm filter-input" data-scope="<?= $scope ?>">
                    <option value="">সকল শাখা</option>
                    <?= $secOptions ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="filter-label"><i class="ti tabler-user"></i>আবেদনকারী</label>
                <select id="<?= $scope ?>EmployeeFilter" class="form-select form-select-sm filter-input" data-scope="<?= $scope ?>">
                    <option value="">সকল</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="filter-label"><i class="ti tabler-clipboard-list"></i>ছুটির ধরণ</label>
                <select id="<?= $scope ?>LeaveTypeFilter" class="form-select form-select-sm filter-input" data-scope="<?= $scope ?>">
                    <option value="">সকল ধরণ</option>
                    <?= $leaveTypeOptions ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="filter-label"><i class="ti tabler-calendar-event"></i>ছুটির শুরু তারিখ</label>
                <div class="field-shell">
                    <i class="ti tabler-calendar field-icon"></i>
                    <input type="text" id="<?= $scope ?>DateRange" class="form-control form-control-sm filter-input filter-daterange" data-scope="<?= $scope ?>" placeholder="তারিখ নির্বাচন...">
                </div>
            </div>
        </div>
    </div>
</div>
