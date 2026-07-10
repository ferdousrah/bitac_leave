<?php
// Filter-panel partial for joining-approval.php — included once per tab.
// Expects: $orgOptions, $secOptions, $joiningTypeMap from the parent scope.
// $scope may be set by the parent (defaults to 'supervise' for the first include).
if (!isset($scope)) $scope = 'supervise';
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
            <button type="button" class="btn btn-sm btn-icon btn-label-primary table-refresh" data-scope="<?= $scope ?>" title="পেজ রিফ্রেশ" data-bs-toggle="tooltip">
                <i class="ti tabler-refresh"></i>
            </button>
            <button type="button" class="btn btn-sm btn-label-secondary filter-reset" data-scope="<?= $scope ?>">
                <i class="ti tabler-x me-1"></i>রিসেট
            </button>
        </div>
    </div>
    <div class="filter-panel-body" id="filterBody-<?= $scope ?>">
        <div class="row g-2">
            <div class="col-12 col-md-6 col-lg-4">
                <label class="filter-label"><i class="ti tabler-map-pin"></i>কেন্দ্র</label>
                <select id="<?= $scope ?>OrgFilter" class="form-select form-select-sm filter-input" data-scope="<?= $scope ?>">
                    <option value="">সকল কেন্দ্র</option>
                    <?= $orgOptions ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <label class="filter-label"><i class="ti tabler-building"></i>শাখা</label>
                <select id="<?= $scope ?>SectionFilter" class="form-select form-select-sm filter-input" data-scope="<?= $scope ?>">
                    <option value="">সকল শাখা</option>
                    <?= $secOptions ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <label class="filter-label"><i class="ti tabler-arrow-back"></i>যোগদানের প্রকার</label>
                <select id="<?= $scope ?>JoiningTypeFilter" class="form-select form-select-sm filter-input" data-scope="<?= $scope ?>">
                    <option value="">সকল ধরণ</option>
                    <?php foreach ($joiningTypeMap as $k => $v): ?>
                        <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>
