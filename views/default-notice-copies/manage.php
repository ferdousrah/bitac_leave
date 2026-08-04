<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Auto-migrate table + seed with the three existing hardcoded defaults
// on first visit. `{center}` is a placeholder that gets replaced with
// the applicant's org name at form-render time in forward-to-approval.
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS default_notice_copies (
        dataID    INT AUTO_INCREMENT PRIMARY KEY,
        label     VARCHAR(255) NOT NULL,
        serial    INT NOT NULL DEFAULT 0,
        isActive  TINYINT(1) NOT NULL DEFAULT 1,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$__seedChk = mysqli_query($con, "SELECT COUNT(*) c FROM default_notice_copies");
if ($__seedChk && (int)(mysqli_fetch_assoc($__seedChk)['c'] ?? 0) === 0) {
    $__seeds = [
        'প্রশাসন বিভাগ, বিটাক, {center}',
        'ব্যক্তিগত নথির কপি',
        'অফিস কপি',
    ];
    $__ins = mysqli_prepare($con,
        "INSERT INTO default_notice_copies (label, serial, isActive) VALUES (?, ?, 1)");
    foreach ($__seeds as $__i => $__lbl) {
        $__sn = $__i + 1;
        mysqli_stmt_bind_param($__ins, 'si', $__lbl, $__sn);
        mysqli_stmt_execute($__ins);
    }
    mysqli_stmt_close($__ins);
}

$rows = [];
$q = mysqli_query($con, "SELECT * FROM default_notice_copies ORDER BY serial ASC, dataID ASC");
while ($q && $r = mysqli_fetch_assoc($q)) $rows[] = $r;
$total = count($rows);
$activeCount = 0;
foreach ($rows as $r) if ((int)$r['isActive'] === 1) $activeCount++;
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-copy me-2 text-primary"></i>ডিফল্ট অনুলিপি প্রাপক</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>প্রতিটি অফিস আদেশে স্বয়ংক্রিয়ভাবে যোগ হওয়া অনুলিপি প্রাপকদের তালিকা</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" class="btn btn-primary" id="btnNew">
            <i class="ti tabler-plus me-1"></i>নতুন যোগ করুন
        </button>
    </div>
</div>

<!-- Stats -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6">
        <div class="stat-card stat-info">
            <div class="stat-icon"><i class="ti tabler-list-details"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber($total) ?></div>
                <div class="stat-label">মোট অনুলিপি প্রাপক</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="stat-card stat-approved">
            <div class="stat-icon"><i class="ti tabler-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber($activeCount) ?></div>
                <div class="stat-label">সক্রিয়</div>
            </div>
        </div>
    </div>
</div>

<!-- Info banner -->
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert" style="border-radius:0.5rem;">
    <i class="ti tabler-bulb mt-1"></i>
    <div style="font-size:0.86rem;">
        <strong>টিপ:</strong> লেবেলে <code style="background:#fff3e1;padding:0 4px;border-radius:3px;">{center}</code>
        placeholder ব্যবহার করলে অফিস আদেশ তৈরির সময় সেটা আবেদনকারীর কেন্দ্রের নাম দিয়ে স্বয়ংক্রিয়ভাবে প্রতিস্থাপিত হবে।
        উদাহরণ: <em>"প্রশাসন বিভাগ, বিটাক, {center}"</em>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="defaultsTable">
                <thead>
                    <tr>
                        <th width="40" class="text-center">—</th>
                        <th width="70" class="text-center">ক্রম</th>
                        <th>লেবেল</th>
                        <th width="100" class="text-center">অবস্থা</th>
                        <th width="120" class="text-center">কার্যাবলী</th>
                    </tr>
                </thead>
                <tbody id="defaultsBody">
                    <?php foreach ($rows as $i => $r): ?>
                    <tr data-id="<?= (int)$r['dataID'] ?>">
                        <td class="text-center drag-handle" style="cursor:grab;color:#8a90a6;" title="টেনে সরান">
                            <i class="ti tabler-grip-vertical"></i>
                        </td>
                        <td class="text-center row-serial"><?= banglaNumber($i + 1) ?></td>
                        <td><?= htmlspecialchars($r['label']) ?></td>
                        <td class="text-center">
                            <?php if ((int)$r['isActive'] === 1): ?>
                                <span class="badge bg-label-success"><i class="ti tabler-check me-1"></i>সক্রিয়</span>
                            <?php else: ?>
                                <span class="badge bg-label-secondary"><i class="ti tabler-x me-1"></i>নিষ্ক্রিয়</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary btn-edit"
                                    data-id="<?= (int)$r['dataID'] ?>"
                                    data-label="<?= htmlspecialchars($r['label']) ?>"
                                    data-active="<?= (int)$r['isActive'] ?>"
                                    title="সম্পাদনা"><i class="ti tabler-edit"></i></button>
                            <button type="button" class="btn btn-sm btn-icon btn-label-danger btn-delete"
                                    data-id="<?= (int)$r['dataID'] ?>"
                                    data-label="<?= htmlspecialchars($r['label']) ?>"
                                    title="মুছুন"><i class="ti tabler-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="text-center text-muted p-4">
                        <i class="ti tabler-inbox" style="font-size:2rem;color:#b9b0f4;"></i>
                        <div class="mt-2">কোনো ডিফল্ট অনুলিপি নেই — উপরে "নতুন যোগ করুন" চাপুন</div>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalTitle"><i class="ti tabler-copy me-1"></i>নতুন অনুলিপি প্রাপক</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" name="dataID" id="fld_id" value="0">
                    <div class="mb-3">
                        <label class="form-label">লেবেল <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="fld_label" name="label" required
                               placeholder="যেমন: প্রশাসন বিভাগ, বিটাক, {center}">
                        <div class="form-text">
                            <code>{center}</code> লিখলে আবেদনকারীর কেন্দ্রের নাম বসবে।
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="fld_active" name="isActive" value="1" checked>
                        <label class="form-check-label" for="fld_active">সক্রিয় (অফিস আদেশে যোগ হবে)</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn btn-primary" id="btnSave"><i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ</button>
            </div>
        </div>
    </div>
</div>

<style>
    #defaultsBody tr.ui-sortable-helper {
        background: #fff !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    #defaultsBody .drag-handle:hover { color: #6c5ce7 !important; }
    #defaultsBody .drag-handle:active { cursor: grabbing !important; }
    .dnc-drop-placeholder { background: #eef0f8; height: 48px; border: 2px dashed #b9b0f4; }
</style>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script type="text/javascript">
(function boot() {
    if (typeof jQuery === 'undefined' || !$.fn.sortable || typeof Swal === 'undefined' || typeof bootstrap === 'undefined') {
        return setTimeout(boot, 20);
    }

    var editModal = new bootstrap.Modal(document.getElementById('editModal'));

    // Open modal for new
    $(document).on('click', '#btnNew', function () {
        $('#fld_id').val(0);
        $('#fld_label').val('');
        $('#fld_active').prop('checked', true);
        $('#editModalTitle').html('<i class="ti tabler-copy me-1"></i>নতুন অনুলিপি প্রাপক');
        editModal.show();
    });

    // Open modal for edit
    $(document).on('click', '.btn-edit', function () {
        var $btn = $(this);
        $('#fld_id').val($btn.data('id'));
        $('#fld_label').val($btn.data('label'));
        $('#fld_active').prop('checked', String($btn.data('active')) === '1');
        $('#editModalTitle').html('<i class="ti tabler-edit me-1"></i>ডিফল্ট অনুলিপি সম্পাদনা');
        editModal.show();
    });

    // Save
    $('#btnSave').on('click', function () {
        var label  = $.trim($('#fld_label').val());
        var active = $('#fld_active').is(':checked') ? 1 : 0;
        var id     = parseInt($('#fld_id').val(), 10) || 0;
        if (label === '') {
            Swal.fire({ icon: 'warning', title: 'লেবেল প্রয়োজন', text: 'একটি লেবেল লিখুন', confirmButtonColor: '#ff9f43',
                        customClass: { confirmButton: 'btn btn-warning' }, buttonsStyling: false });
            return;
        }
        $.post('../../api/default-notice-copies/save.php', { dataID: id, label: label, isActive: active }, function (res) {
            if (res && res.status == 1) {
                editModal.hide();
                Swal.fire({ icon: 'success', title: 'সংরক্ষিত', timer: 1200, showConfirmButton: false })
                    .then(function () { window.location.reload(); });
            } else {
                Swal.fire({ icon: 'error', title: 'ত্রুটি', text: (res && res.message) ? res.message : 'সংরক্ষণ ব্যর্থ',
                            confirmButtonColor: '#ff3e1d', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
            }
        }, 'json').fail(function () {
            Swal.fire({ icon: 'error', title: 'ত্রুটি', text: 'সার্ভার সাড়া দিচ্ছে না',
                        confirmButtonColor: '#ff3e1d', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
        });
    });

    // Delete
    $(document).on('click', '.btn-delete', function () {
        var id    = $(this).data('id');
        var label = $(this).data('label');
        Swal.fire({
            title: 'মুছে ফেলবেন?',
            html: '<strong>' + $('<div>').text(label).html() + '</strong> — এই ডিফল্ট অনুলিপিটি মুছে ফেলা হবে।',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'হ্যাঁ, মুছুন',
            cancelButtonText: 'বাতিল',
            customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $.post('../../api/default-notice-copies/delete.php', { dataID: id }, function (res) {
                if (res && res.status == 1) {
                    window.location.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'ত্রুটি', text: (res && res.message) ? res.message : 'মুছতে ব্যর্থ',
                                confirmButtonColor: '#ff3e1d', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                }
            }, 'json');
        });
    });

    // Drag reorder
    $('#defaultsBody').sortable({
        handle: '.drag-handle',
        axis: 'y',
        placeholder: 'dnc-drop-placeholder',
        forcePlaceholderSize: true,
        helper: function (e, tr) {
            var $originals = tr.children();
            var $helper = tr.clone();
            $helper.children().each(function (i) { $(this).width($originals.eq(i).outerWidth()); });
            return $helper;
        },
        update: function () {
            var ids = $('#defaultsBody tr[data-id]').map(function () { return $(this).data('id'); }).get();
            $.post('../../api/default-notice-copies/reorder.php', { order: ids }, function (res) {
                if (res && res.status == 1) {
                    // Re-number visual ক্রম column
                    var toBn = function (n) { return String(n).replace(/[0-9]/g, function (d) { return '০১২৩৪৫৬৭৮৯'[+d]; }); };
                    $('#defaultsBody tr[data-id]').each(function (i) { $(this).find('.row-serial').text(toBn(i + 1)); });
                }
            }, 'json');
        }
    }).disableSelection();

})();
</script>
