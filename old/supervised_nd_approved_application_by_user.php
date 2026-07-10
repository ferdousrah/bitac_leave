<?php
include(__DIR__ . '/includes/header_vuexy.php');
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">সুপারিশপ্রাপ্ত ও অনুমোদিত আবেদন</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Leave Applications Card -->
<div class="card">
    <div class="card-body">
        <!-- Nav Tabs -->
        <ul class="nav nav-tabs nav-fill" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#supervised" role="tab">
                    <i class="ti tabler-clipboard-check me-2"></i>
                    <span class="d-none d-sm-inline">সুপারিশপ্রাপ্ত</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#approved" role="tab">
                    <i class="ti tabler-circle-check me-2"></i>
                    <span class="d-none d-sm-inline">অনুমোদিত</span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content pt-4">
            <!-- Tab 1: Supervised -->
            <div class="tab-pane fade show active" id="supervised" role="tabpanel">
                <div class="table-responsive">
                    <table id="supleaveTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্রমিক</th>
                                <th>আবেদনকারীর নাম ও পদবী</th>
                                <th>শাখা</th>
                                <th>চাহিত ছুটির ধরণ</th>
                                <th>চাহিত ছুটি(দিন)</th>
                                <th>প্রস্তাবিত ছুটি(দিন)</th>
                                <th>প্রস্তাবিত ছুটির ধরণ</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Approved -->
            <div class="tab-pane fade" id="approved" role="tabpanel">
                <div class="table-responsive">
                    <table id="approvedLeaveTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্রমিক</th>
                                <th>আবেদনকারীর নাম ও পদবী</th>
                                <th>শাখা</th>
                                <th>চাহিত ছুটির ধরণ</th>
                                <th>চাহিত ছুটি(দিন)</th>
                                <th>প্রস্তাবিত ছুটি(দিন)</th>
                                <th>প্রস্তাবিত ছুটির ধরণ</th>
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
    </div>
</div>

<?php
include(__DIR__ . '/includes/footer_vuexy.php');
?>

<style>
#supleaveTable thead th,
#approvedLeaveTable thead th {
    color: #ffffff !important;
    background-color: #435971 !important;
}
</style>

<script type="text/javascript">
var supleaveTableInstance;
var approvedLeaveTableInstance;

$(document).ready(function() {
    // Initialize Supervised Leaves DataTable
    supleaveTableInstance = $('#supleaveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "fetch_supervised_leave_data.php",
            type: "POST",
            dataSrc: function (json) {
                console.log(json);
                return json.data;
            }
        },
        columns: [
            { data: "serial", orderable: false },
            { data: "applicant_name", orderable: false },
            { data: "section", orderable: false },
            { data: "leave_type", orderable: false },
            { data: "requested_leave_days", orderable: false },
            { data: "proposed_leave_days", orderable: false },
            { data: "proposed_leave_type", orderable: false },
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
        }
    });

    // Initialize Approved Leaves DataTable (lazy load on tab switch)
    $('button[data-bs-target="#approved"]').on('shown.bs.tab', function (e) {
        if (!approvedLeaveTableInstance) {
            approvedLeaveTableInstance = $('#approvedLeaveTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "fetch_approved_leave_data.php",
                    type: "POST",
                    dataSrc: function (json) {
                        console.log(json);
                        return json.data;
                    }
                },
                columns: [
                    { data: "serial", orderable: false },
                    { data: "applicant_name", orderable: false },
                    { data: "section", orderable: false },
                    { data: "leave_type", orderable: false },
                    { data: "requested_leave_days", orderable: false },
                    { data: "proposed_leave_days", orderable: false },
                    { data: "proposed_leave_type", orderable: false },
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
                }
            });
        }
    });
});

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
                type: 'post',
                url: 'delete_data.php',
                data: 'dataID=' + dataID + '&tableName=modules',
                success: function(data) {
                    $("#tr_" + sl).fadeOut(1000);

                    Swal.fire({
                        title: 'মুছে ফেলা হয়েছে!',
                        text: 'ডেটা সফলভাবে মুছে ফেলা হয়েছে',
                        icon: 'success',
                        confirmButtonColor: '#28c76f',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    });
                },
                error: function(e) {
                    console.log(e);
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'ডেটা মুছতে ব্যর্থ হয়েছে',
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
