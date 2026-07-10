<?php
include(__DIR__ . '/includes/header_vuexy.php');
include('library/number_converter.php');
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">ছুটি সংশোধন অনুমোদন</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Leave Edit Approval Card -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="leaveEditApprovalTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr style="background-color: #435971 !important; color: #ffffff !important;">
                        <th>ক্রমিক</th>
                        <th>কর্মচারীর নাম ও পদবি</th>
                        <th>আইডি</th>
                        <th>অনুমোদিত ছুটি</th>
                        <th>পূর্বের অফিস আদেশ</th>
                        <th>সংশোধনের জন্য<br>অনুমোদিত ছুটি</th>
                        <th>সংশোধিত ছুটির অফিস আদেশ</th>
                        <th>ডিডাক্ট ফ্রম</th>
                        <th>সাবমিশন ডেট টাইম</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include(__DIR__ . '/includes/footer_vuexy.php');
?>

<style>
#leaveEditApprovalTable thead th {
    color: #ffffff !important;
    background-color: #435971 !important;
}
</style>

<script type="text/javascript">
$(document).ready(function() {
    // Initialize DataTable with server-side processing
    $('#leaveEditApprovalTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "fetch_leave_edit_approval_data.php",
            type: "POST",
            dataSrc: function (json) {
                console.log(json);
                return json.data;
            }
        },
        columns: [
            { data: "serial", orderable: false },
            { data: "employee_name", orderable: false },
            { data: "employee_id", orderable: false },
            { data: "approved_leave", orderable: false },
            { data: "previous_office_order", orderable: false, searchable: false },
            { data: "revised_leave", orderable: false },
            { data: "revised_office_order", orderable: false, searchable: false },
            { data: "deduct_from", orderable: false },
            { data: "submit_datetime", orderable: false },
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
        drawCallback: function() {
            // Re-initialize tooltips after each draw
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });
});

function processapplication(leaveApplicationID, ptype, sl, dataID) {
    let title, icon, confirmText;

    if(ptype == 1) {
        title = "আপনি কি এই আবেদন অনুমোদন করতে চান?";
        icon = "success";
        confirmText = "হ্যাঁ, অনুমোদন করুন!";
    } else if(ptype == 2) {
        title = "আপনি কি এই আবেদন প্রত্যাখ্যান করতে চান?";
        icon = "warning";
        confirmText = "হ্যাঁ, প্রত্যাখ্যান করুন!";
    } else if(ptype == 3) {
        title = "আপনি কি এই ডেটা মুছে ফেলতে চান?";
        icon = "error";
        confirmText = "হ্যাঁ, মুছে ফেলুন!";
    }

    Swal.fire({
        title: title,
        text: "",
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: ptype == 1 ? '#28c76f' : (ptype == 2 ? '#ff9f43' : '#ff3e1d'),
        cancelButtonColor: '#8592a3',
        confirmButtonText: confirmText,
        cancelButtonText: 'বাতিল',
        customClass: {
            confirmButton: 'btn ' + (ptype == 1 ? 'btn-success' : (ptype == 2 ? 'btn-warning' : 'btn-danger')) + ' me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'post',
                url: 'leave_edit_approval_action.php',
                data: 'leaveApplicationID=' + leaveApplicationID + '&ptype=' + ptype + '&dataID=' + dataID,
                success: function(data) {
                    console.log(data);

                    if(data == 1) {
                        $("#tr_" + sl).fadeOut(1000);

                        Swal.fire({
                            title: ptype == 1 ? 'অনুমোদিত!' : 'প্রত্যাখ্যাত!',
                            text: ptype == 1 ? 'আবেদন সফলভাবে অনুমোদন করা হয়েছে' : 'আবেদন সফলভাবে প্রত্যাখ্যান করা হয়েছে',
                            icon: 'success',
                            confirmButtonColor: '#28c76f',
                            customClass: {
                                confirmButton: 'btn btn-success'
                            },
                            buttonsStyling: false
                        }).then(() => {
                            // Reload datatable
                            $('#leaveEditApprovalTable').DataTable().ajax.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'ত্রুটি!',
                            text: 'প্রক্রিয়াকরণে সমস্যা হয়েছে',
                            icon: 'error',
                            confirmButtonColor: '#ff3e1d',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    }
                },
                error: function(e) {
                    console.log(e);
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'প্রক্রিয়াকরণে সমস্যা হয়েছে',
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
</script>
