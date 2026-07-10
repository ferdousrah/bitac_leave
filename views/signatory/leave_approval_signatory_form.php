<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$centerId  = intval($_GET['center_id'] ?? 0);
$menuslug  = htmlspecialchars($_GET['menuslug'] ?? 'leave-settings');

if (!$centerId) {
    echo '<div class="alert alert-danger">কেন্দ্রের তথ্য পাওয়া যায়নি।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$centerQ = mysqli_query($con, "SELECT organization_name FROM organization WHERE id = '$centerId'");
$centerRow = mysqli_fetch_assoc($centerQ);
$centerName = $centerRow['organization_name'] ?? '';

// Org filter options for the modal: current center + HQ (id=4) if different
$orgFilterOpts = [];
if ($centerId != 4) {
    $orgFilterQ = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE id IN ($centerId, 4) ORDER BY FIELD(id, $centerId, 4)");
    while ($o = mysqli_fetch_assoc($orgFilterQ)) $orgFilterOpts[] = $o;
}

ob_start();
?>
<style>
#signatoryTable thead th { color: #fff !important; background-color: #435971 !important; }
.ui-sortable-helper { display: table; background-color: #f8f9fa; opacity: 0.8; }
.ui-sortable-placeholder { background-color: #e9ecef; visibility: visible !important; }
#signatoryTable tbody tr { cursor: move; }
#signatoryTable tbody tr:hover { background-color: #f8f9fa; }
</style>
<?php
define('PAGE_SCRIPTS', ob_get_clean());
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">সিগনেটরি ব্যবস্থাপনা</h4>
        <p class="text-muted mb-0"><?= htmlspecialchars($centerName) ?></p>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addSignatoryModal"
            onclick="openAddModal();">
            <i class="ti tabler-plus me-1"></i>নতুন সিগনেটরি যোগ করুন
        </button>
        <a href="recommend_approval_revision_main.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary">
            <i class="menu-icon icon-base ti tabler-arrow-narrow-left-dashed icon-22px"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table id="signatoryTable" class="table table-bordered w-100">
            <thead>
                <tr>
                    <th>ক্রম</th>
                    <th>পদবী</th>
                    <th>সিগনেটরি (কর্মকর্তা/কর্মচারী)</th>
                    <th>অনুমোদন ক্রম</th>
                    <th>আবশ্যিক</th>
                    <th>অ্যাকশন</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Signatory Modal -->
<div class="modal fade" id="addSignatoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">নতুন সিগনেটরি যোগ করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="signatoryForm">
                <div class="modal-body">
                    <input type="hidden" name="center_id" value="<?= $centerId ?>">
                    <input type="hidden" name="record_id" id="recordId" value="">

                    <?php if (count($orgFilterOpts) > 1): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">কর্মকর্তার প্রতিষ্ঠান</label>
                        <div class="btn-group w-100" role="group">
                            <?php foreach ($orgFilterOpts as $org): ?>
                            <input type="radio" class="btn-check" name="orgFilter"
                                id="orgFilter_<?= $org['id'] ?>"
                                value="<?= $org['id'] ?>"
                                <?= $org['id'] == $centerId ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary btn-sm" for="orgFilter_<?= $org['id'] ?>">
                                <?= htmlspecialchars($org['organization_name']) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">সিগনেটরি কোন প্রতিষ্ঠানের কর্মকর্তা তা নির্বাচন করুন।</div>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">কর্মকর্তা/কর্মচারী <span class="text-danger">*</span></label>
                        <select name="employeeID" id="employeeID" class="form-select select2" required>
                            <option value="0">-- লোড হচ্ছে... --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">আবশ্যিক <span class="text-danger">*</span></label>
                        <select name="isMandatory" id="isMandatory" class="form-select">
                            <option value="1">হ্যাঁ (আবশ্যিক)</option>
                            <option value="0">না (ঐচ্ছিক)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
var centerId = <?= $centerId ?>;

// Load employees for whichever org is currently selected in the filter (defaults to centerId)
function loadCenterEmployees(callback) {
    var orgId = $('input[name="orgFilter"]:checked').val() || centerId;
    $('#employeeID').html('<option value="0">-- লোড হচ্ছে... --</option>');
    $.get('../../api/employees/get-by-center.php', { organization_id: orgId }, function (html) {
        $('#employeeID').html(html);
        if ($.fn.select2) $('#employeeID').trigger('change');
        if (typeof callback === 'function') callback();
    });
}

function openAddModal() {
    $('#modalTitle').text('নতুন সিগনেটরি যোগ করুন');
    $('#recordId').val('');
    $('#isMandatory').val('1');
    // Reset org filter to current center
    $('input[name="orgFilter"][value="' + centerId + '"]').prop('checked', true);
    loadCenterEmployees();
    $('#addSignatoryModal').modal('show');
}

function removeSignatory(dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: 'এই সিগনেটরি মুছে ফেলতে চান?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'হ্যাঁ, মুছে ফেলুন!',
        cancelButtonText: 'বাতিল',
        confirmButtonColor: '#ff3e1d',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
    }).then(result => {
        if (result.isConfirmed) {
            $.post('../../api/signatory/delete-signatory.php', { dataID: dataID }, function(res) {
                if (res.status == 1) {
                    signatoryTable.ajax.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'ত্রুটি!', text: res.message || 'মুছতে সমস্যা হয়েছে।' });
                }
            }, 'json');
        }
    });
}

function editSignatory(dataID, employeeID, isMandatory, empOrgId) {
    $('#modalTitle').text('সিগনেটরি সম্পাদনা করুন');
    $('#recordId').val(dataID);
    $('#isMandatory').val(isMandatory);
    // Pre-select the org filter that matches the employee's actual org
    var orgId = empOrgId || centerId;
    var $radio = $('input[name="orgFilter"][value="' + orgId + '"]');
    if ($radio.length) {
        $radio.prop('checked', true);
    } else {
        $('input[name="orgFilter"][value="' + centerId + '"]').prop('checked', true);
    }
    loadCenterEmployees(function () {
        $('#employeeID').val(employeeID).trigger('change');
    });
    $('#addSignatoryModal').modal('show');
}

$(document).ready(function () {
    // Init Select2
    if ($.fn.select2) {
        $('#employeeID').select2({ dropdownParent: $('#addSignatoryModal') });
    }

    // When org filter changes, reload employee list for that org
    $(document).on('change', 'input[name="orgFilter"]', function () {
        loadCenterEmployees();
    });

    // DataTable
    var signatoryTable = $('#signatoryTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '../../api/signatory/fetch-by-center.php', type: 'POST', data: { center_id: centerId } },
        columns: [
            { data: 'serial',      orderable: false },
            { data: 'designation', orderable: false },
            { data: 'signatory',   orderable: false },
            { data: 'approvalSL',  orderable: false },
            { data: 'isMandatory', orderable: false },
            { data: 'action',      orderable: false, searchable: false }
        ],
        pageLength: 25,
        language: {
            processing: '<div class="spinner-border text-primary" role="status"></div>',
            search: 'খুঁজুন:', lengthMenu: 'দেখান _MENU_',
            info: '_START_–_END_ / মোট _TOTAL_', infoEmpty: 'কোন রেকর্ড নেই',
            zeroRecords: 'কোন মিল নেই', emptyTable: 'কোন ডেটা নেই',
            paginate: { previous: 'পূর্ববর্তী', next: 'পরবর্তী' }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        initComplete: function () {
            $('#signatoryTable_filter').append(`
                <button class="btn btn-primary btn-sm ms-2" onclick="openAddModal();">
                    <i class="ti tabler-plus me-1"></i>নতুন যোগ করুন
                </button>`);
        },
        drawCallback: function () {
            $('#signatoryTable tbody tr').each(function () {
                var dataId = $(this).find('[data-id]').data('id');
                if (dataId) $(this).attr('data-id', dataId);
            });
        }
    });

    // Make signatoryTable rows globally accessible for drag-to-reorder
    window.signatoryTable = signatoryTable;

    // Drag-to-reorder
    $('#signatoryTable tbody').sortable({
        helper: 'clone', axis: 'y',
        update: function () {
            var order = [];
            $('#signatoryTable tbody tr').each(function (idx) {
                var id = $(this).data('id');
                if (id) order.push({ id: id, approvalSL: idx + 2 });
            });
            $.ajax({
                url: '../../api/signatory/update-order.php',
                method: 'POST',
                data: { order: order },
                success: function () { signatoryTable.ajax.reload(); }
            });
        }
    }).disableSelection();

    // Toggle isMandatory directly from table — also reshuffles approvalSL
    $(document).on('change', '.toggle-mandatory', function () {
        var $toggle = $(this);
        var dataID  = $toggle.data('id');
        var value   = $toggle.is(':checked') ? 1 : 0;
        $toggle.prop('disabled', true);
        $.post('../../api/signatory/toggle-mandatory.php', { dataID: dataID, value: value }, function (res) {
            $toggle.prop('disabled', false);
            if (res.status == 1) {
                signatoryTable.ajax.reload(null, false); // reload without resetting page
            } else {
                $toggle.prop('checked', !value); // revert
                Swal.fire({ icon: 'error', title: 'ত্রুটি!', text: res.message || 'আপডেট ব্যর্থ হয়েছে।' });
            }
        }, 'json').fail(function () {
            $toggle.prop('disabled', false);
            $toggle.prop('checked', !value);
        });
    });

    // Form submit: add or update
    $('#signatoryForm').on('submit', function (e) {
        e.preventDefault();
        var empVal = parseInt($('#employeeID').val());
        if (!empVal || empVal == 0) {
            Swal.fire({ icon: 'warning', title: '', text: 'কর্মকর্তা/কর্মচারী নির্বাচন করুন।' });
            return;
        }
        $('#saveBtn').attr('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>সংরক্ষণ...');
        $.ajax({
            type: 'POST',
            url: '../../api/signatory/save-signatory.php',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                $('#saveBtn').removeAttr('disabled').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
                if (res.status == 1) {
                    $('#addSignatoryModal').modal('hide');
                    signatoryTable.ajax.reload();
                    Swal.fire({ icon: 'success', title: 'সফল!', text: res.message, timer: 2000, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'ত্রুটি!', text: res.message, confirmButtonColor: '#ff3e1d', buttonsStyling: false, customClass: { confirmButton: 'btn btn-danger' } });
                }
            },
            error: function () {
                $('#saveBtn').removeAttr('disabled').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
                Swal.fire({ icon: 'error', title: 'ত্রুটি!', text: 'সমস্যা হয়েছে। আবার চেষ্টা করুন।' });
            }
        });
    });
});
</script>
