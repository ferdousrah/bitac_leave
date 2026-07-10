<?php
// Filter-panel partial for employees/manage.php — included once per tab.
// Expects: $organizations, $scope from parent.
if (!isset($scope)) $scope = 'act';
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
                <label class="filter-label"><i class="ti tabler-user"></i>নাম</label>
                <input type="text" id="<?= $scope ?>NameSearch" class="form-control form-control-sm filter-input" data-scope="<?= $scope ?>" placeholder="নাম দিয়ে খুঁজুন...">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="filter-label"><i class="ti tabler-id"></i>আইডি</label>
                <input type="text" id="<?= $scope ?>IdSearch" class="form-control form-control-sm filter-input" data-scope="<?= $scope ?>" placeholder="আইডি দিয়ে খুঁজুন...">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="filter-label"><i class="ti tabler-map-pin"></i>কেন্দ্র</label>
                <select id="<?= $scope ?>CenterFilter" class="form-select form-select-sm filter-input" data-scope="<?= $scope ?>">
                    <option value="">সকল কেন্দ্র</option>
                    <?php foreach ($organizations as $org): ?>
                        <option value="<?= htmlspecialchars($org['organization_name']) ?>"><?= htmlspecialchars($org['organization_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="filter-label"><i class="ti tabler-building"></i>শাখা</label>
                <select id="<?= $scope ?>SectionSearch" class="form-select form-select-sm filter-input" data-scope="<?= $scope ?>">
                    <option value="">সকল শাখা</option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?= htmlspecialchars($sec['section_name']) ?>"><?= htmlspecialchars($sec['section_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>
