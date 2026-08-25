<?php
// Super admin only — this page rewrites the sidebar for everyone.
$pageTitle    = 'মেনু ব্যবস্থাপনা';
$pageSubtitle = 'সাইডবারের সাবমেনু যোগ, সম্পাদনা ও স্তরবিন্যাস';

require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

if ((int)($getUserInfoQRW['user_group_id'] ?? 0) !== 1) {
    echo '<div class="alert alert-danger m-4">অ্যাক্সেস নিষিদ্ধ — এই পেজটি শুধুমাত্র সুপার অ্যাডমিনের জন্য।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// The nesting column is what this page exists to edit, so unlike the sidebar —
// which degrades to a flat menu — here its absence is worth saying out loud.
$hasParent = false;
$__c = mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
if ($__c && mysqli_num_rows($__c) > 0) $hasParent = true;

$modules = [];
$mq = mysqli_query($con, "SELECT dataID, module_name, slug, icon, display_order
                          FROM modules WHERE deleted = 0
                          ORDER BY display_order ASC, dataID ASC");
while ($m = mysqli_fetch_assoc($mq)) {
    $m['submodules'] = [];
    $modules[(int)$m['dataID']] = $m;
}

$parentSel = $hasParent ? 'parent_id' : '0 AS parent_id';
$sq = mysqli_query($con, "SELECT dataID, module_id, $parentSel, submodule_name, page_link, slug, display_order
                          FROM submodules WHERE deleted = 0
                          ORDER BY display_order ASC, dataID ASC");
$flat = [];
while ($s = mysqli_fetch_assoc($sq)) $flat[(int)$s['dataID']] = $s + ['children' => []];

// Two passes: attach children first, then place the remaining rows as the
// module's own second level, so a child never shows up twice.
foreach ($flat as $id => $s) {
    $pid = (int)$s['parent_id'];
    if ($pid > 0 && isset($flat[$pid])) $flat[$pid]['children'][] = $id;
}
foreach ($flat as $id => $s) {
    $pid = (int)$s['parent_id'];
    if ($pid > 0 && isset($flat[$pid])) continue;
    $mid = (int)$s['module_id'];
    if (isset($modules[$mid])) $modules[$mid]['submodules'][] = $id;
}

$totalSub    = count($flat);
$totalNested = 0;
foreach ($flat as $s) if ((int)$s['parent_id'] > 0 && isset($flat[(int)$s['parent_id']])) $totalNested++;
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-list-tree me-2 text-primary"></i>মেনু ব্যবস্থাপনা</h4>
        <div class="text-muted small mt-1">সাবমেনু যোগ করুন, নাম ও লিংক বদলান, বা একটির নিচে আরেকটি বসিয়ে তৃতীয় স্তর বানান।</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-primary" id="btnAddSub">
            <i class="ti tabler-plus me-1"></i>নতুন সাবমেনু
        </button>
    </div>
</div>

<?php if (!$hasParent): ?>
<div class="alert alert-warning d-flex align-items-start gap-2">
    <i class="ti tabler-alert-triangle mt-1"></i>
    <div>
        <strong>স্তরবিন্যাস এখনো চালু হয়নি।</strong>
        <div class="small mt-1">
            <code>submodules.parent_id</code> কলামটি নেই, তাই সাবমেনুর নিচে সাবমেনু বসানো যাবে না।
            <code>sql/2026-08-25-submenu-third-level.sql</code> চালিয়ে নিন — বাকি সম্পাদনা এখনই কাজ করবে।
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row stats-strip mb-3 g-2">
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-info">
            <div class="stat-icon"><i class="ti tabler-folder"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber(count($modules)) ?></div>
                <div class="stat-label">মূল মেনু</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="ti tabler-list"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber($totalSub) ?></div>
                <div class="stat-label">সাবমেনু</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="ti tabler-corner-down-right"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber($totalNested) ?></div>
                <div class="stat-label">তৃতীয় স্তরে</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="text-muted small mb-3">
            <i class="ti tabler-info-circle me-1"></i>
            পাতাবিহীন সাবমেনু (লিংক <code>#</code>) কেবল দলবদ্ধ করার জন্য — তার নিচের কোনো একটিতে অনুমতি থাকলেই সাইডবারে দেখা যাবে, আলাদা অনুমতি লাগবে না।
        </div>

        <?php foreach ($modules as $mid => $mod): ?>
        <div class="menu-module">
            <div class="menu-module-head">
                <i class="<?= htmlspecialchars($mod['icon'] ?: 'ti tabler-folder') ?> me-2"></i>
                <span class="fw-semibold"><?= htmlspecialchars($mod['module_name']) ?></span>
                <span class="badge bg-label-secondary ms-2"><?= htmlspecialchars($mod['slug']) ?></span>
                <span class="ms-auto text-muted small"><?= banglaNumber(count($mod['submodules'])) ?> টি সাবমেনু</span>
            </div>

            <?php if (empty($mod['submodules'])): ?>
                <div class="menu-empty">কোনো সাবমেনু নেই</div>
            <?php else: ?>
                <?php foreach ($mod['submodules'] as $sid): $s = $flat[$sid]; ?>
                <div class="menu-row" data-id="<?= (int)$s['dataID'] ?>">
                    <div class="menu-row-main">
                        <span class="menu-row-name"><?= htmlspecialchars($s['submodule_name']) ?></span>
                        <?php if (trim((string)$s['page_link']) === '' || $s['page_link'] === '#'): ?>
                            <span class="badge bg-label-warning ms-2">গ্রুপ</span>
                        <?php endif; ?>
                        <span class="menu-row-slug"><?= htmlspecialchars($s['slug']) ?></span>
                    </div>
                    <div class="menu-row-actions">
                        <?php if ($hasParent): ?>
                        <button type="button" class="btn btn-sm btn-icon btn-label-secondary js-add-child"
                                data-parent="<?= (int)$s['dataID'] ?>" data-module="<?= (int)$mid ?>"
                                title="এর নিচে সাবমেনু যোগ করুন"><i class="ti tabler-corner-down-right"></i></button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-icon btn-label-primary js-edit"
                                data-id="<?= (int)$s['dataID'] ?>" title="সম্পাদনা"><i class="ti tabler-pencil"></i></button>
                        <button type="button" class="btn btn-sm btn-icon btn-label-danger js-del"
                                data-id="<?= (int)$s['dataID'] ?>"
                                data-name="<?= htmlspecialchars($s['submodule_name'], ENT_QUOTES) ?>"
                                title="মুছুন"><i class="ti tabler-trash"></i></button>
                    </div>
                </div>

                    <?php foreach ($s['children'] as $cid): $c = $flat[$cid]; ?>
                    <div class="menu-row menu-row-child" data-id="<?= (int)$c['dataID'] ?>">
                        <div class="menu-row-main">
                            <i class="ti tabler-corner-down-right text-muted me-2"></i>
                            <span class="menu-row-name"><?= htmlspecialchars($c['submodule_name']) ?></span>
                            <span class="menu-row-slug"><?= htmlspecialchars($c['slug']) ?></span>
                        </div>
                        <div class="menu-row-actions">
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary js-edit"
                                    data-id="<?= (int)$c['dataID'] ?>" title="সম্পাদনা"><i class="ti tabler-pencil"></i></button>
                            <button type="button" class="btn btn-sm btn-icon btn-label-danger js-del"
                                    data-id="<?= (int)$c['dataID'] ?>"
                                    data-name="<?= htmlspecialchars($c['submodule_name'], ENT_QUOTES) ?>"
                                    title="মুছুন"><i class="ti tabler-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add / edit -->
<div class="modal fade" id="subModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="subForm">
            <div class="modal-header">
                <h5 class="modal-title" id="subModalTitle">নতুন সাবমেনু</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="dataID" id="f_dataID" value="0">

                <div class="mb-3">
                    <label class="form-label">মূল মেনু <span class="text-danger">*</span></label>
                    <select class="form-select" name="module_id" id="f_module" required>
                        <?php foreach ($modules as $mid => $mod): ?>
                            <option value="<?= (int)$mid ?>"><?= htmlspecialchars($mod['module_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($hasParent): ?>
                <div class="mb-3">
                    <label class="form-label">কার নিচে বসবে</label>
                    <select class="form-select" name="parent_id" id="f_parent">
                        <option value="0">— সরাসরি মূল মেনুর নিচে —</option>
                    </select>
                    <div class="form-text">অন্য একটি সাবমেনু বাছলে এটি তৃতীয় স্তরে যাবে এবং সাইডবারে hover করলে পাশে খুলবে।</div>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">নাম <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="submodule_name" id="f_name" maxlength="200" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">পাতার লিংক</label>
                    <input type="text" class="form-control" name="page_link" id="f_link"
                           maxlength="80" placeholder="views/…/manage.php  অথবা  #">
                    <div class="form-text">শুধু দলবদ্ধ করার জন্য হলে <code>#</code> দিন — তখন এটি ক্লিক করা যাবে না, কেবল খুলবে।</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">স্লাগ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="slug" id="f_slug" maxlength="200" required
                           placeholder="manage-something">
                    <div class="form-text">পাতার <code>?menuslug=</code>-এর সঙ্গে হুবহু মিলতে হবে, নইলে মেনুটি সক্রিয় দেখাবে না।</div>
                </div>

                <div class="mb-0">
                    <label class="form-label">ক্রম</label>
                    <input type="number" class="form-control" name="display_order" id="f_order" min="0" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="submit" class="btn btn-primary" id="subSaveBtn">সংরক্ষণ</button>
            </div>
        </form>
    </div>
</div>

<style>
.menu-module { border: 1px solid #e6e6ef; border-radius: 12px; margin-bottom: 14px; overflow: hidden; }
.menu-module-head { display: flex; align-items: center; padding: 10px 14px; background: #f7f7fb; border-bottom: 1px solid #ececf4; }
.menu-empty { padding: 12px 16px; color: #8a90a6; font-size: 0.85rem; }
.menu-row { display: flex; align-items: center; gap: 12px; padding: 9px 14px; border-bottom: 1px solid #f2f2f8; }
.menu-row:last-child { border-bottom: none; }
.menu-row-child { padding-left: 40px; background: #fcfcff; }
.menu-row-main { display: flex; align-items: center; min-width: 0; flex: 1 1 auto; }
.menu-row-name { font-weight: 600; color: #2c2e3a; }
.menu-row-slug { margin-left: 10px; font-size: 0.72rem; color: #8a90a6; font-family: monospace; }
.menu-row-actions { display: flex; gap: 6px; flex: 0 0 auto; }
</style>

<script type="text/javascript">
(function bootMenuManage() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || typeof Swal === 'undefined' || typeof bootstrap === 'undefined') {
        return setTimeout(bootMenuManage, 20);
    }
    var $ = jQuery;
    if ($('#subForm').data('bound')) return;
    $('#subForm').data('bound', true);

    // Every submodule, so the parent picker can be filtered by module without a
    // round trip. Children are excluded as parents: the sidebar renders exactly
    // three levels, and a deeper tree would silently lose rows.
    var SUBS = <?= json_encode(array_values(array_map(function ($s) {
        return [
            'id'     => (int)$s['dataID'],
            'module' => (int)$s['module_id'],
            'parent' => (int)$s['parent_id'],
            'name'   => $s['submodule_name'],
        ];
    })), JSON_UNESCAPED_UNICODE) ?>;

    var modal = new bootstrap.Modal(document.getElementById('subModal'));

    function fillParents(moduleId, selected, excludeId) {
        var $p = $('#f_parent');
        if (!$p.length) return;
        $p.empty().append($('<option>').val(0).text('— সরাসরি মূল মেনুর নিচে —'));
        SUBS.forEach(function (s) {
            if (s.module !== moduleId) return;
            if (s.parent !== 0) return;            // no fourth level
            if (excludeId && s.id === excludeId) return;   // cannot parent itself
            $p.append($('<option>').val(s.id).text(s.name));
        });
        $p.val(selected || 0);
    }

    $('#f_module').on('change', function () {
        fillParents(parseInt($(this).val(), 10), 0, parseInt($('#f_dataID').val(), 10) || 0);
    });

    function openNew(moduleId, parentId) {
        $('#subModalTitle').text('নতুন সাবমেনু');
        $('#f_dataID').val(0);
        $('#f_name, #f_link, #f_slug').val('');
        $('#f_order').val(0);
        if (moduleId) $('#f_module').val(moduleId);
        fillParents(parseInt($('#f_module').val(), 10), parentId || 0, 0);
        modal.show();
    }

    $('#btnAddSub').on('click', function () { openNew(null, 0); });

    $(document).on('click', '.js-add-child', function () {
        openNew(parseInt($(this).data('module'), 10), parseInt($(this).data('parent'), 10));
    });

    $(document).on('click', '.js-edit', function () {
        var id = parseInt($(this).data('id'), 10);
        $.getJSON('../../api/menu/get.php', { dataID: id })
            .done(function (r) {
                if (!r || r.status !== 1) {
                    Swal.fire({ icon: 'error', title: 'ত্রুটি', text: (r && r.message) || 'তথ্য আনা যায়নি',
                                customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                    return;
                }
                $('#subModalTitle').text('সাবমেনু সম্পাদনা');
                $('#f_dataID').val(r.data.dataID);
                $('#f_module').val(r.data.module_id);
                $('#f_name').val(r.data.submodule_name);
                $('#f_link').val(r.data.page_link);
                $('#f_slug').val(r.data.slug);
                $('#f_order').val(r.data.display_order);
                fillParents(parseInt(r.data.module_id, 10), parseInt(r.data.parent_id, 10), parseInt(r.data.dataID, 10));
                modal.show();
            })
            .fail(function () {
                Swal.fire({ icon: 'error', title: 'ত্রুটি', text: 'সার্ভারে পৌঁছানো যায়নি',
                            customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
            });
    });

    $('#subForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#subSaveBtn').prop('disabled', true);
        $.post('../../api/menu/save.php', $(this).serialize(), null, 'json')
            .done(function (r) {
                if (r && r.status === 1) {
                    modal.hide();
                    Swal.fire({ icon: 'success', title: 'সংরক্ষিত', text: r.message || 'মেনু হালনাগাদ হয়েছে',
                                timer: 1200, showConfirmButton: false })
                        .then(function () { window.location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'সংরক্ষণ হয়নি', text: (r && r.message) || 'অজানা ত্রুটি',
                                customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                }
            })
            .fail(function () {
                Swal.fire({ icon: 'error', title: 'ত্রুটি', text: 'সার্ভারে পৌঁছানো যায়নি',
                            customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
            })
            .always(function () { $btn.prop('disabled', false); });
    });

    $(document).on('click', '.js-del', function () {
        var id = parseInt($(this).data('id'), 10), name = $(this).data('name');
        Swal.fire({
            title: 'মুছে ফেলবেন?',
            html: '<strong>' + $('<div>').text(name).html() + '</strong> সাইডবার থেকে সরে যাবে।',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'হ্যাঁ, মুছুন', cancelButtonText: 'বাতিল',
            customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false
        }).then(function (res) {
            if (!res.isConfirmed) return;
            $.post('../../api/menu/delete.php', { dataID: id }, null, 'json')
                .done(function (r) {
                    if (r && r.status === 1) {
                        Swal.fire({ icon: 'success', title: 'মুছে ফেলা হয়েছে', timer: 1100, showConfirmButton: false })
                            .then(function () { window.location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'মুছতে পারিনি', text: (r && r.message) || 'অজানা ত্রুটি',
                                    customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                    }
                })
                .fail(function () {
                    Swal.fire({ icon: 'error', title: 'ত্রুটি', text: 'সার্ভারে পৌঁছানো যায়নি',
                                customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                });
        });
    });
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
