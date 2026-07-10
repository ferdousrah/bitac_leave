<?php
include(__DIR__ . '/includes/header_vuexy.php');
?>

<style>
/* Action button styling */
.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.375rem 0.75rem;
}

.btn-icon i {
    font-size: 1.125rem;
}

/* Table header styling - Dark theme */
#userGroupListTable thead tr th {
    background-color: #435971 !important;
    color: #ffffff !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.4px;
    border-bottom: 1px solid #435971 !important;
}

/* DataTable wrapper styling */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-bottom: 1rem;
}

/* Responsive table */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
</style>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">ব্যবহারকারী গ্রুপের তালিকা</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="new_user_group_form.php?menuslug=<?php echo $_GET['menuslug'] ?? 'manage-user-group'; ?>" class="btn btn-primary" data-turbo="true">
            <i class="menu-icon menu-icon icon-base ti tabler-plus"></i>নতুন যোগ করুন
        </a>
    </div>
</div>

<!-- User Group List Card -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="userGroupListTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th width="100">ক্রমিক</th>
                        <th>গ্রুপের নাম</th>
                        <th width="200">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be loaded via DataTables AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include(__DIR__ . '/includes/footer_vuexy.php');
?>

<script>
// Initialize DataTable - works with both initial load and Turbo navigation
function initUserGroupListTable() {
    console.log('Initializing user group list table...');

    // Wait a bit for DOM to be ready
    setTimeout(function() {
        // Destroy existing instance if it exists
        if ($.fn.DataTable.isDataTable('#userGroupListTable')) {
            $('#userGroupListTable').DataTable().destroy();
        }

        // Get menuslug from URL
        const urlParams = new URLSearchParams(window.location.search);
        const menuslug = urlParams.get('menuslug') || 'manage-user-group';

        $('#userGroupListTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "fetch_user_group_data.php?menuslug=" + menuslug,
                "type": "POST",
                "dataSrc": function (json) {
                    console.log('User group data loaded:', json.data.length, 'rows');
                    return json.data;
                }
            },
            "columns": [
                { "data": "sl" },
                { "data": "group_name" },
                { "data": "action", "orderable": false }
            ],
            "language": {
                "processing": "প্রক্রিয়াকরণ হচ্ছে...",
                "search": "অনুসন্ধান:",
                "lengthMenu": "প্রদর্শন _MENU_ এন্ট্রি",
                "info": "দেখানো হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ এন্ট্রি",
                "infoEmpty": "কোন এন্ট্রি নেই",
                "infoFiltered": "(মোট _MAX_ এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
                "zeroRecords": "কোন মিল খুঁজে পাওয়া যায়নি",
                "emptyTable": "টেবিলে কোন ডেটা উপলব্ধ নেই",
                "paginate": {
                    "first": "প্রথম",
                    "previous": "পূর্ববর্তী",
                    "next": "পরবর্তী",
                    "last": "শেষ"
                }
            },
            "drawCallback": function() {
                // Reinitialize tooltips after each DataTable draw
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                        new bootstrap.Tooltip(el);
                    });
                }
            }
        });
    }, 100);
}

// Initialize on document ready (first load)
$(document).ready(function() {
    if ($('#userGroupListTable').length) {
        initUserGroupListTable();
    }
});

// Reinitialize on Turbo navigation
document.addEventListener('turbo:load', function() {
    console.log('Turbo load event - checking for user group table');
    if ($('#userGroupListTable').length) {
        console.log('User group table found, initializing...');
        initUserGroupListTable();
    }
});

// Delete function using SweetAlert2
function removeData(sl, dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত যে এই তথ্য মুছে ফেলতে চান?',
        text: "এই কাজটি পূর্বাবস্থায় ফিরিয়ে আনা যাবে না!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#696cff',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, মুছে ফেলুন!',
        cancelButtonText: 'বাতিল',
        customClass: {
            confirmButton: 'btn btn-primary me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'post',
                url: 'delete_user_group.php',
                data: 'dataID=' + dataID,
                dataType: 'json',
                success: function(response) {
                    if (response.status == 1) {
                        // Refresh the DataTable
                        $('#userGroupListTable').DataTable().ajax.reload();
                        Swal.fire({
                            title: 'মুছে ফেলা হয়েছে!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#696cff',
                            customClass: {
                                confirmButton: 'btn btn-success'
                            },
                            buttonsStyling: false
                        });
                    } else {
                        Swal.fire({
                            title: 'ত্রুটি!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonColor: '#696cff',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'ব্যবহারকারী গ্রুপ মুছে ফেলতে ব্যর্থ হয়েছে!',
                        icon: 'error',
                        confirmButtonColor: '#696cff',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}
</script>
