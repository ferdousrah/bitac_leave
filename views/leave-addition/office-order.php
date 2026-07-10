<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Resolve user's org (re-query — sidebar overwrites $getUserInfoQRW)
$orgStmt = $con->prepare("SELECT isCenterAdmin, organization_id, employee_id FROM user_list WHERE user_id = ?");
$orgStmt->bind_param("s", $_SESSION['username']);
$orgStmt->execute();
$orgUserRow = $orgStmt->get_result()->fetch_assoc();
$orgStmt->close();
if (!empty($orgUserRow['isCenterAdmin'])) {
    $userOrgID = intval($orgUserRow['organization_id']);
} elseif (!empty($orgUserRow['employee_id'])) {
    $empOrgStmt = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $empOrgStmt->bind_param("i", $orgUserRow['employee_id']);
    $empOrgStmt->execute();
    $empOrgRow = $empOrgStmt->get_result()->fetch_assoc();
    $empOrgStmt->close();
    $userOrgID = intval($empOrgRow['organization_id'] ?? 0);
} else {
    $userOrgID = 0;
}
$orgFilter = $userOrgID > 0 ? "AND organization_id = '$userOrgID'" : "";
$getEmployeeListQ = mysqli_query($con, "SELECT * FROM employee_list WHERE employment_status=1 $orgFilter ORDER BY employee_name");

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'leave-addition-on-office-order');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-calendar-plus me-2 text-primary"></i>অফিস আদেশে ছুটি যোগ</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>অফিস আদেশের ভিত্তিতে কর্মচারীর ছুটি যোগ এন্ট্রি করুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<style>
.deduct-form-card { border-radius: 0.75rem; }
.deduct-form-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .deduct-form-card .card-body { padding: 1rem; }
}

.deduct-section-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    margin: 28px 0 20px;
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-left: 3px solid var(--sec-accent, #6c5ce7);
    border-radius: 0.6rem;
}
.deduct-section-header:first-of-type { margin-top: 0; }
.deduct-section-header[data-color="indigo"] { --sec-bg: #f0edff; --sec-accent: #6c5ce7; }
.deduct-section-header[data-color="green"]  { --sec-bg: #e6f7ee; --sec-accent: #1a7e44; }

.deduct-section-header .section-num {
    width: 30px; height: 30px;
    border-radius: 0.5rem;
    background: var(--sec-bg, #f0edff);
    color: var(--sec-accent, #6c5ce7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.deduct-section-header .section-text { flex: 1; min-width: 0; }
.deduct-section-header .section-title {
    font-size: 0.98rem;
    font-weight: 600;
    color: #2c2e3a;
    margin: 0;
    line-height: 1.3;
}
.deduct-section-header .section-sub {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 2px;
    display: block;
}
.deduct-section-header .section-icon {
    width: 38px; height: 38px;
    border-radius: 0.55rem;
    background: var(--sec-bg, #f0edff);
    color: var(--sec-accent, #6c5ce7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.deduct-form-card .col-form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.deduct-form-card .form-control:focus,
.deduct-form-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.deduct-form-card .input-group-text {
    background: #fafbfd;
    border-color: #e0e4ee;
    color: #5d6580;
}

.deduct-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}

@media (max-width: 575px) {
    .deduct-section-header { padding: 12px 14px; gap: 10px; }
    .deduct-section-header .section-icon { display: none; }
    .deduct-section-header .section-num { width: 26px; height: 26px; font-size: 0.8rem; }
    .deduct-section-header .section-title { font-size: 0.92rem; }
}
</style>

<!-- Leave Addition Form Card -->
<div class="card deduct-form-card shadow-sm border-0">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-horizontal" name="form" id="form" enctype="multipart/form-data">
            <!-- ───── Section 1: Employee ───── -->
            <div class="deduct-section-header" data-color="indigo">
                <div class="section-num">১</div>
                <div class="section-text">
                    <h6 class="section-title">কর্মচারী তথ্য</h6>
                    <span class="section-sub">যে কর্মচারীর ছুটি যোগ করা হবে তাকে নির্বাচন করুন</span>
                </div>
                <span class="section-icon"><i class="ti tabler-user"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="employeeID">
                    কর্মকর্তা / কর্মচারী <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="select2" style="width: 100%;" name="employeeID" id="employeeID" data-allow-clear="true" required>
                        <option value=''>-- কর্মচারী নির্বাচন করুন --</option>
                        <?php while($empRow = mysqli_fetch_assoc($getEmployeeListQ)): ?>
                            <option value='<?= $empRow['id'] ?>'>
                                <?= htmlspecialchars($empRow['employee_id'] . ' - ' . $empRow['employee_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="signatoryID">
                    স্বাক্ষরকারী <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="select2" style="width: 100%;" name="signatory_id" id="signatoryID" data-allow-clear="true" required>
                        <option value=''>-- আগে কর্মচারী নির্বাচন করুন --</option>
                    </select>
                    <div id="sigInfoBox" class="mt-2 d-none">
                        <div class="alert alert-info py-2 mb-0 small">
                            <i class="ti tabler-info-circle me-1"></i>
                            <span id="sigInfoText"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ───── Section 2: Addition details ───── -->
            <div class="deduct-section-header" data-color="green">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">ছুটি যোগ তথ্য</h6>
                    <span class="section-sub">ছুটির ধরন, যোগের দিন, মন্তব্য ও অফিস আদেশ সংযুক্তি</span>
                </div>
                <span class="section-icon"><i class="ti tabler-calendar-plus"></i></span>
            </div>

            <!-- Multi-row leave entries -->
            <div class="row mb-3">
                <label class="col-md-3 col-form-label">
                    ছুটির এন্ট্রি <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-2" id="leaveRowsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:32%;">ছুটির ধরন</th>
                                    <th style="width:15%;">দিন</th>
                                    <th style="width:43%;">মন্তব্য</th>
                                    <th style="width:60px;" class="text-center">—</th>
                                </tr>
                            </thead>
                            <tbody id="leaveRowsBody">
                                <!-- initial row inserted by JS -->
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-label-primary" id="addLeaveRowBtn">
                        <i class="ti tabler-plus me-1"></i>নতুন সারি যোগ করুন
                    </button>
                    <small class="text-muted d-block mt-1"><i class="ti tabler-info-circle me-1"></i>এই অফিস আদেশে যতগুলো ছুটি যোগ করতে চান, ততগুলো সারি যোগ করুন</small>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="officeAdesh">
                    অফিস আদেশ
                </label>
                <div class="col-md-9">
                    <input type="file" class="form-control" id="officeAdesh" name="officeAdesh" accept=".pdf,.jpg,.jpeg,.png">
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>PDF, JPG বা PNG ফরম্যাট সমর্থিত (ঐচ্ছিক)। সব ছুটির জন্য একই আদেশ প্রযোজ্য হবে।</small>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="deduct-form-actions d-flex gap-2 justify-content-end">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </button>
                <button type="button" name="submit" id="submit" class="btn btn-primary submitBtn px-4">
                    <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন
                </button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
(function bootAdditionPage() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || typeof Swal === 'undefined') {
        return setTimeout(bootAdditionPage, 30);
    }

    var LEAVE_TYPES = [
        { v: '1',  t: 'গড় বেতন' },
        { v: '2',  t: 'অর্ধ-গড় বেতন' },
        { v: '3',  t: 'নৈমিত্তিক (Casual Leave)' },
        { v: '4',  t: 'বিনা বেতনে ছুটি' },
        { v: '10', t: 'অসাধারণ ছুটি' },
        { v: '5',  t: 'ঐচ্ছিক ছুটি' },
        { v: '6',  t: 'কর্তনহীন ছুটি' }
    ];

    function typeOptions() {
        var s = '<option value="">-- ছুটির ধরন --</option>';
        LEAVE_TYPES.forEach(function(o) { s += '<option value="' + o.v + '">' + o.t + '</option>'; });
        return s;
    }

    function newLeaveRow() {
        return '<tr>'
            + '<td><select class="form-select form-select-sm" name="leaveType[]" required>' + typeOptions() + '</select></td>'
            + '<td><input type="number" step="0.5" min="0" class="form-control form-control-sm text-center" name="leaveAdd[]" placeholder="দিন" required></td>'
            + '<td><textarea class="form-control form-control-sm" name="note[]" rows="1" placeholder="মন্তব্য" required></textarea></td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-icon btn-label-danger removeLeaveRow" title="মুছুন"><i class="ti tabler-trash"></i></button></td>'
            + '</tr>';
    }

    function loadSignatoryOptions(empId) {
        var $sig = $('#signatoryID');
        var $box = $('#sigInfoBox');
        var $txt = $('#sigInfoText');
        if (!empId) {
            $sig.html('<option value="">-- আগে কর্মচারী নির্বাচন করুন --</option>').val('').trigger('change');
            $box.addClass('d-none');
            return;
        }
        $sig.html('<option value="">লোড হচ্ছে...</option>');
        $.get('../../api/office-order/signatory-info.php', { employee_id: empId }, function(resp) {
            if (!resp || resp.status !== 1) {
                $sig.html('<option value="">-- ডেটা লোড ব্যর্থ --</option>');
                return;
            }
            var opts = '<option value="">-- স্বাক্ষরকারী নির্বাচন করুন --</option>';
            resp.org_employees.forEach(function(e) {
                var label = e.name + (e.code ? ' (' + e.code + ')' : '') + (e.title ? ' — ' + e.title : '');
                opts += '<option value="' + e.id + '">' + $('<div>').text(label).html() + '</option>';
            });
            $sig.html(opts);

            // Default routing logic:
            // - If default signatory != selected employee → auto-pick default
            // - If default signatory == selected employee → leave blank + show info
            if (resp.default_signatory && !resp.default_sig_conflict) {
                $sig.val(String(resp.default_signatory.id)).trigger('change');
                $box.removeClass('d-none');
                $txt.html('ডিফল্ট স্বাক্ষরকারী auto-নির্বাচিত: <b>' + $('<div>').text(resp.default_signatory.name).html() + '</b> — চাইলে পরিবর্তন করা যাবে');
            } else if (resp.default_sig_conflict) {
                $sig.val('').trigger('change');
                $box.removeClass('d-none');
                $txt.html('<b>এই কর্মচারীই কেন্দ্রের ডিফল্ট স্বাক্ষরকারী</b> — নিজে অনুমোদন দিতে পারবেন না। এখান থেকে তাঁর বসকে (বা অন্য কাউকে) select করুন।');
            } else {
                $sig.val('').trigger('change');
                $box.removeClass('d-none');
                $txt.html('এই কেন্দ্রে কোনো ডিফল্ট স্বাক্ষরকারী কনফিগার করা নেই — manually select করুন।');
            }
        }, 'json').fail(function() {
            $sig.html('<option value="">-- সংযোগ ব্যর্থ --</option>');
        });
    }

    function init() {
        // Employee Select2 uses class="select2" — footer_vuexy auto-initializes it
        // with Bootstrap 5 theme, so no custom init needed here.

        // Load signatory options when employee changes
        $(document).off('change.empSigAdd', '#employeeID').on('change.empSigAdd', '#employeeID', function() {
            loadSignatoryOptions($(this).val());
        });

        // Seed with one row if empty
        if ($('#leaveRowsBody tr').length === 0) {
            $('#leaveRowsBody').html(newLeaveRow());
        }

        // Row add
        $(document).off('click.addLeave', '#addLeaveRowBtn').on('click.addLeave', '#addLeaveRowBtn', function() {
            $('#leaveRowsBody').append(newLeaveRow());
        });

        // Row remove — keep at least one row
        $(document).off('click.rmLeave', '.removeLeaveRow').on('click.rmLeave', '.removeLeaveRow', function() {
            if ($('#leaveRowsBody tr').length <= 1) {
                Swal.fire({ title: 'সতর্কতা', text: 'কমপক্ষে একটি সারি থাকতে হবে', icon: 'warning', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-warning' }, buttonsStyling: false });
                return;
            }
            $(this).closest('tr').remove();
        });

        // Submit via button click (Turbo-safe pattern)
        $('#submit').off('click.submitAdd').on('click.submitAdd', function(e) {
            e.preventDefault();
            var formEl = document.getElementById('form');
            if (formEl && typeof formEl.checkValidity === 'function' && !formEl.checkValidity()) {
                formEl.reportValidity();
                return;
            }
            var $btn = $('.submitBtn');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>প্রক্রিয়াকরণ হচ্ছে...');
            $('#form').css("opacity", ".5");

            $.ajax({
                url: '../../api/leave-addition/save.php',
                type: 'POST',
                dataType: 'json',
                data: new FormData(formEl),
                contentType: false,
                cache: false,
                processData: false,
                success: function(response) {
                    if (response && response.status == 1) {
                        Swal.fire({ title: 'সম্পন্ন', text: response.message, icon: 'success', confirmButtonColor: '#6c5ce7', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false }).then(function() {
                            formEl.reset();
                            $('#employeeID').val(null).trigger('change');
                            $('#leaveRowsBody').html(newLeaveRow());
                        });
                    } else {
                        Swal.fire({ title: 'ত্রুটি', text: (response && response.message) || 'ব্যর্থ', icon: 'error', confirmButtonColor: '#ff3e1d', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'ত্রুটি', text: 'ছুটি যোগ সংরক্ষণ করতে ব্যর্থ হয়েছে!', icon: 'error', confirmButtonColor: '#ff3e1d', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                },
                complete: function() {
                    $('#form').css("opacity", "");
                    $btn.prop('disabled', false).html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
                }
            });
        });
    }

    $(document).ready(init);
    document.addEventListener('turbo:load', init);
})();
</script>

<?php
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>
