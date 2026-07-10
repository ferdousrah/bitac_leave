<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Fetch all user groups
$groupQuery = "SELECT * FROM user_group WHERE deleted = 0 ORDER BY display_order ASC";
$groupResult = mysqli_query($con, $groupQuery);

// Get group_id from URL if provided
$selectedGroupId = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
$menuslug        = htmlspecialchars($_GET['menuslug'] ?? 'manage-user-group');
?>

<style>
.access-card { border-radius: 0.75rem; }
.access-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .access-card .card-body { padding: 1rem; }
}
.access-card .form-section-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #eef0f5;
}
.access-card .section-icon-tile {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: #f0edff;
    color: #5648c4;
    border-radius: 0.5rem;
    font-size: 1.05rem;
}
.access-card .section-title {
    margin: 0;
    color: #2c2e3a;
    font-size: 1rem;
    font-weight: 600;
}
.access-card .col-form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.access-card .form-control:focus,
.access-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}

/* ── Module cards ────────────────────────────────────── */
.module-card {
    border: 1px solid #eef0f5;
    border-radius: 0.6rem;
    padding: 14px 16px;
    margin-bottom: 12px;
    background: #fafbfd;
    transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}
.module-card:hover {
    border-color: #ddd5f6;
    background: #fdfcff;
    box-shadow: 0 2px 8px rgba(108, 92, 231, 0.06);
}
.module-checkbox-container {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
}
.module-header {
    font-weight: 600;
    font-size: 0.92rem;
    color: #2c2e3a;
    cursor: pointer;
}
.module-header .ti {
    color: #5648c4;
    margin-right: 0.4rem;
}

.submodule-list {
    margin-left: 26px;
    margin-top: 10px;
    border-left: 2px solid #eef0f5;
    padding-left: 16px;
}
.submodule-checkbox-container {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
    padding: 4px 0;
}
.submodule-checkbox-container label {
    font-size: 0.86rem;
    color: #3a3d53;
    cursor: pointer;
}

.permission-checkbox { margin-right: 10px; }

/* Vuexy override — selected color matches app purple */
#modulesList .form-check-input:checked {
    background-color: #6c5ce7;
    border-color: #6c5ce7;
}
#modulesList .form-check-input:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 0.2rem rgba(108, 92, 231, 0.2);
}

.permissions-section-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2c2e3a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.permissions-section-title .ti-tile {
    width: 30px; height: 30px;
    background: #f0edff;
    color: #5648c4;
    border-radius: 0.45rem;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem;
}

.access-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}

/* Empty / loading states */
#modulesList .empty-loading {
    text-align: center;
    padding: 2rem 1rem;
    color: #8a90a6;
    font-size: 0.88rem;
}
</style>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-shield-lock me-2 text-primary"></i>গ্রুপ অনুযায়ী এক্সেস পারমিশন</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>প্রতিটি গ্রুপের জন্য মডিউল ও সাবমডিউলের অনুমতি নির্ধারণ করুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage-groups.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<!-- Group Access Card -->
<div class="card access-card shadow-sm border-0">
    <div class="card-body">
        <div class="statusMsg"></div>

        <!-- Section header -->
        <div class="form-section-header">
            <span class="section-icon-tile"><i class="ti tabler-users-group"></i></span>
            <h6 class="section-title">গ্রুপ নির্বাচন</h6>
        </div>

        <!-- Group Selection -->
        <div class="row mb-3">
            <label class="col-md-3 col-form-label">
                ব্যবহারকারী গ্রুপ <span class="text-danger">*</span>
            </label>
            <div class="col-md-9">
                <select id="user_group_id" class="form-select select2" required>
                    <option value="">-- গ্রুপ নির্বাচন করুন --</option>
                    <?php while($group = mysqli_fetch_assoc($groupResult)): ?>
                        <option value="<?= $group['id'] ?>" <?= ($selectedGroupId == $group['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($group['group_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>একটি গ্রুপ নির্বাচন করলে নিচে অনুমতিগুলো দেখানো হবে</small>
            </div>
        </div>

        <!-- Modules and Submodules List -->
        <div id="permissionsContainer" style="display: none;">
            <h6 class="permissions-section-title mt-4">
                <span class="ti-tile"><i class="ti tabler-list-check"></i></span>
                মডিউল এবং সাবমডিউল পারমিশন
            </h6>
            <div id="modulesList"></div>

            <div class="access-form-actions d-flex justify-content-end">
                <button type="button" id="savePermissions" class="btn btn-primary px-4">
                    <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন
                </button>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>

<script>
function initializeGroupAccessPage() {
    console.log('Initializing group access page...');

    // Select2 is auto-initialized by footer for .select2 elements
    // Just bind event handlers and handle pre-selection

    // Auto-load permissions if group is preselected from URL
    const initialGroupId = $('#user_group_id').val();
    if (initialGroupId) {
        console.log('Auto-loading permissions for group:', initialGroupId);
        loadGroupPermissions(initialGroupId);
    }

    // When group is selected, load permissions
    $('#user_group_id').off('change').on('change', function() {
        const groupId = $(this).val();
        if (groupId) {
            loadGroupPermissions(groupId);
        } else {
            $('#permissionsContainer').hide();
        }
    });

    // Save permissions
    $('#savePermissions').off('click').on('click', function() {
        saveGroupPermissions();
    });
}

// Initialize after a short delay to let footer's Select2 auto-init complete
$(document).ready(function(){
    setTimeout(function() {
        if ($('#user_group_id').length) {
            initializeGroupAccessPage();
        }
    }, 200);
});

// Reinitialize on Turbo navigation
document.addEventListener('turbo:load', function() {
    setTimeout(function() {
        if ($('#user_group_id').length) {
            initializeGroupAccessPage();
        }
    }, 200);
});

function loadGroupPermissions(groupId) {
    $.ajax({
        type: 'POST',
        url: '../../api/users/fetch-group-permissions.php',
        data: { group_id: groupId },
        dataType: 'json',
        beforeSend: function() {
            $('#modulesList').html('<div class="empty-loading"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">লোড হচ্ছে...</p></div>');
            $('#permissionsContainer').show();
        },
        success: function(response) {
            if (response.status == 1) {
                displayModulesAndPermissions(response.modules, response.permissions);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ত্রুটি',
                    text: response.message,
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'ত্রুটি',
                text: 'ডেটা লোড করতে ব্যর্থ হয়েছে!',
                confirmButtonColor: '#ff3e1d',
                customClass: { confirmButton: 'btn btn-danger' },
                buttonsStyling: false
            });
        }
    });
}

function displayModulesAndPermissions(modules, existingPermissions) {
    let html = '';

    if (!modules || modules.length === 0) {
        html = '<div class="empty-loading"><i class="ti tabler-folder-off" style="font-size:2rem;color:#8a90a6;"></i><p class="mt-2 mb-0">কোনো মডিউল পাওয়া যায়নি</p></div>';
        $('#modulesList').html(html);
        return;
    }

    modules.forEach(function(module) {
        const moduleChecked = existingPermissions.some(p =>
            p.module_id == module.dataID && p.submodule_id == null
        );

        html += `
            <div class="module-card">
                <div class="module-checkbox-container">
                    <div class="form-check">
                        <input type="checkbox"
                               class="form-check-input permission-checkbox module-checkbox"
                               id="module_${module.dataID}"
                               value="${module.dataID}"
                               data-module-id="${module.dataID}"
                               ${moduleChecked ? 'checked' : ''}>
                        <label for="module_${module.dataID}" class="form-check-label module-header">
                            <i class="${module.icon}"></i>${module.module_name}
                        </label>
                    </div>
                </div>
        `;

        if (module.submodules && module.submodules.length > 0) {
            html += '<div class="submodule-list">';
            module.submodules.forEach(function(submodule) {
                const submoduleChecked = existingPermissions.some(p =>
                    p.module_id == module.dataID && p.submodule_id == submodule.dataID
                );

                html += `
                    <div class="submodule-checkbox-container">
                        <div class="form-check">
                            <input type="checkbox"
                                   class="form-check-input permission-checkbox submodule-checkbox"
                                   id="submodule_${submodule.dataID}"
                                   value="${submodule.dataID}"
                                   data-module-id="${module.dataID}"
                                   data-submodule-id="${submodule.dataID}"
                                   ${submoduleChecked ? 'checked' : ''}>
                            <label for="submodule_${submodule.dataID}" class="form-check-label">
                                ${submodule.submodule_name}
                            </label>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }

        html += '</div>';
    });

    $('#modulesList').html(html);

    // Add event listeners for module checkboxes to auto-select/deselect submodules
    $('.module-checkbox').on('change', function() {
        const moduleId = $(this).data('module-id');
        const isChecked = $(this).is(':checked');
        $(`.submodule-checkbox[data-module-id="${moduleId}"]`).prop('checked', isChecked);
    });
}

function saveGroupPermissions() {
    const groupId = $('#user_group_id').val();

    if (!groupId) {
        Swal.fire({
            icon: 'error',
            title: 'ত্রুটি',
            text: 'দয়া করে একটি গ্রুপ নির্বাচন করুন!',
            confirmButtonColor: '#ff3e1d',
            customClass: { confirmButton: 'btn btn-danger' },
            buttonsStyling: false
        });
        return;
    }

    // Collect all checked permissions
    const permissions = [];

    // Collect module permissions (modules without submodules or parent modules)
    $('.module-checkbox:checked').each(function() {
        const moduleId = $(this).data('module-id');
        permissions.push({
            module_id: moduleId,
            submodule_id: null
        });
    });

    // Collect submodule permissions
    $('.submodule-checkbox:checked').each(function() {
        const moduleId = $(this).data('module-id');
        const submoduleId = $(this).data('submodule-id');
        permissions.push({
            module_id: moduleId,
            submodule_id: submoduleId
        });
    });

    $.ajax({
        type: 'POST',
        url: '../../api/users/save-group-permissions.php',
        data: JSON.stringify({
            group_id: groupId,
            permissions: permissions
        }),
        contentType: 'application/json',
        dataType: 'json',
        beforeSend: function() {
            $('#savePermissions').attr('disabled', 'disabled').html('<span class="spinner-border spinner-border-sm me-1" role="status"></span>সংরক্ষণ হচ্ছে...');
        },
        success: function(response) {
            if (response.status == 1) {
                Swal.fire({
                    icon: 'success',
                    title: 'সম্পন্ন',
                    text: response.message,
                    confirmButtonColor: '#6c5ce7',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
                // Reload permissions to show updated state
                loadGroupPermissions(groupId);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ত্রুটি',
                    text: response.message,
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
            $('#savePermissions').removeAttr('disabled').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'ত্রুটি',
                text: 'পারমিশন সংরক্ষণ করতে ব্যর্থ হয়েছে!',
                confirmButtonColor: '#ff3e1d',
                customClass: { confirmButton: 'btn btn-danger' },
                buttonsStyling: false
            });
            $('#savePermissions').removeAttr('disabled').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
        }
    });
}
</script>
