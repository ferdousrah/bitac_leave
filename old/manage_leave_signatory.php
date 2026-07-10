<?php
include(__DIR__ . '/includes/header_vuexy.php');
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">ছুটির সুপারিশ ও অনুমোদন</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Tabs Card -->
<div class="card">
    <div class="card-body">
        <!-- Nav Tabs -->
        <ul class="nav nav-tabs" id="signatoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="center-tab" data-bs-toggle="tab" data-bs-target="#center-content" type="button" role="tab" aria-controls="center-content" aria-selected="true">
                    <i class="ti tabler-building me-1"></i>প্রধান কার্যালয়
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="dhaka-tab" data-bs-toggle="tab" data-bs-target="#dhaka-content" type="button" role="tab" aria-controls="dhaka-content" aria-selected="false">
                    <i class="ti tabler-building-bank me-1"></i>বিটাক, ঢাকা
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content mt-4" id="signatoryTabContent">
            <!-- প্রধান কার্যালয় Tab -->
            <div class="tab-pane fade show active" id="center-content" role="tabpanel" aria-labelledby="center-tab">
                <div class="table-responsive">
                    <table id="leaveTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্রমিক</th>
                                <th>পদবী</th>
                                <th>বর্তমানে কর্মরত</th>
                                <th>অনুমতির ক্রমিক নং</th>
                                <th>বাধ্যতামূলক</th>
                                <th width="220">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- বিটাক, ঢাকা Tab -->
            <div class="tab-pane fade" id="dhaka-content" role="tabpanel" aria-labelledby="dhaka-tab">
                <div class="table-responsive">
                    <table id="leaveApproveTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্রমিক</th>
                                <th>পদবী</th>
                                <th>বর্তমানে কর্মরত</th>
                                <th>অনুমতির ক্রমিক নং</th>
                                <th>বাধ্যতামূলক</th>
                                <th width="220">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/includes/footer_vuexy.php'); ?>

<style>
#leaveTable thead th,
#leaveApproveTable thead th {
    color: #ffffff !important;
    background-color: #435971 !important;
}

/* Sortable row styling */
.ui-sortable-helper {
    display: table;
    background-color: #f8f9fa;
    opacity: 0.8;
}

.ui-sortable-placeholder {
    background-color: #e9ecef;
    visibility: visible !important;
}

tbody tr {
    cursor: move;
}

tbody tr:hover {
    background-color: #f8f9fa;
}
</style>

<script type="text/javascript">
function removeData(sl, dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই ডেটা মুছে ফেলতে চান?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28c76f',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, মুছে ফেলুন!',
        cancelButtonText: 'বাতিল',
        customClass: {
            confirmButton: 'btn btn-success me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'POST',
                url: 'delete_signatory.php',
                data: { dataID: dataID, tableName: 'leave_approval_signatory' },
                success: function(response) {
                    Swal.fire({
                        title: 'মুছে ফেলা হয়েছে!',
                        text: 'ডেটা সফলভাবে মুছে ফেলা হয়েছে',
                        icon: 'success',
                        confirmButtonColor: '#28c76f',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    }).then(() => {
                        // Reload DataTables
                        if ($.fn.DataTable.isDataTable('#leaveTable')) {
                            $('#leaveTable').DataTable().ajax.reload();
                        }
                        if ($.fn.DataTable.isDataTable('#leaveApproveTable')) {
                            $('#leaveApproveTable').DataTable().ajax.reload();
                        }
                    });
                },
                error: function() {
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'কিছু ভুল হয়েছে',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
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

$(document).ready(function() {
    // Initialize প্রধান কার্যালয় DataTable
    let leaveTable = $('#leaveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "fetch_bitac_center_approval_setting_data.php",
            type: "POST"
        },
        columns: [
            { data: "serial", orderable: false },
            { data: "designation", orderable: false },
            { data: "signatory", orderable: false },
            { data: "approvalSL", orderable: false },
            { data: "isMandatory", orderable: false },
            { data: "action", orderable: false, searchable: false }
        ],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
            search: "খুঁজুন:",
            lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
            info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
            infoEmpty: "কোন এন্ট্রি নেই",
            infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
            emptyTable: "টেবিলে কোন ডেটা নেই",
            paginate: {
                first: "প্রথম",
                previous: "পূর্ববর্তী",
                next: "পরবর্তী",
                last: "শেষ"
            }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        initComplete: function() {
            const addButton = `
                <button class="btn btn-primary btn-sm ms-2" onclick="location.href='new_signatory_form?id=4&menuslug=leave-settings'">
                    <i class="ti tabler-plus me-1"></i>Add New
                </button>`;
            $('#leaveTable_filter').append(addButton);
        },
        drawCallback: function() {
            // Add data-id attribute to rows
            $('#leaveTable tbody tr').each(function() {
                const actionCell = $(this).find('td:last');
                const editLink = actionCell.find('a').attr('href');
                const dataIDMatch = editLink ? editLink.match(/dataID=(\d+)/) : null;

                if (dataIDMatch && dataIDMatch[1]) {
                    $(this).attr('data-id', dataIDMatch[1]);
                }
            });
        }
    });

    // Enable drag-and-drop sorting for প্রধান কার্যালয়
    $('#leaveTable tbody').sortable({
        helper: "clone",
        axis: "y",
        update: function(event, ui) {
            let order = [];
            $('#leaveTable tbody tr').each(function(index) {
                const dataId = $(this).data("id");
                const position = index + 2;
                order.push({ id: dataId, approvalSL: position });
            });

            // Send the updated order to the server
            $.ajax({
                url: "update_leave_approval_order.php",
                method: "POST",
                data: { order: order },
                success: function(response) {
                    console.log(response);
                    leaveTable.ajax.reload();
                },
                error: function(xhr, status, error) {
                    console.error("Error updating order:", error);
                }
            });
        }
    }).disableSelection();

    // Initialize বিটাক, ঢাকা DataTable
    let leaveApproveTable = $('#leaveApproveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "fetch_bitac_dhaka_approval_setting_data.php",
            type: "POST"
        },
        columns: [
            { data: "serial", orderable: false },
            { data: "designation", orderable: false },
            { data: "signatory", orderable: false },
            { data: "approvalSL", orderable: false },
            { data: "isMandatory", orderable: false },
            { data: "action", orderable: false, searchable: false }
        ],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
            search: "খুঁজুন:",
            lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
            info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
            infoEmpty: "কোন এন্ট্রি নেই",
            infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
            emptyTable: "টেবিলে কোন ডেটা নেই",
            paginate: {
                first: "প্রথম",
                previous: "পূর্ববর্তী",
                next: "পরবর্তী",
                last: "শেষ"
            }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        initComplete: function() {
            const addButton = `
                <button class="btn btn-primary btn-sm ms-2" onclick="location.href='new_signatory_form?id=5&menuslug=leave-settings'">
                    <i class="ti tabler-plus me-1"></i>Add New
                </button>`;
            $('#leaveApproveTable_filter').append(addButton);
        },
        drawCallback: function() {
            // Add data-id attribute to rows
            $('#leaveApproveTable tbody tr').each(function() {
                const actionCell = $(this).find('td:last');
                const editLink = actionCell.find('a').attr('href');
                const dataIDMatch = editLink ? editLink.match(/dataID=(\d+)/) : null;

                if (dataIDMatch && dataIDMatch[1]) {
                    $(this).attr('data-id', dataIDMatch[1]);
                }
            });
        }
    });

    // Enable drag-and-drop sorting for বিটাক, ঢাকা
    $('#leaveApproveTable tbody').sortable({
        helper: "clone",
        axis: "y",
        update: function(event, ui) {
            let order = [];
            $('#leaveApproveTable tbody tr').each(function(index) {
                const dataId = $(this).data("id");
                const position = index + 2;
                order.push({ id: dataId, approvalSL: position });
            });

            // Send the updated order to the server
            $.ajax({
                url: "update_leave_approval_order.php",
                method: "POST",
                data: { order: order },
                success: function(response) {
                    console.log(response);
                    leaveApproveTable.ajax.reload();
                },
                error: function(xhr, status, error) {
                    console.error("Error updating order:", error);
                }
            });
        }
    }).disableSelection();
});
</script>
