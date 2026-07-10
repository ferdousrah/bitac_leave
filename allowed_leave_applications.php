<?php
include(__DIR__ . '/includes/header_vuexy.php');
include('library/number_converter.php');
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">ছুটি সম্পাদনা</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Leave Edit Card -->
<div class="card">
    <div class="card-body">
        <!-- Nav Tabs -->
        <ul class="nav nav-tabs nav-fill" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#pendingLeaves" role="tab">
                    <i class="ti tabler-clock me-2"></i>
                    <span class="d-none d-sm-inline">প্রক্রিয়াধীন ছুটি</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#editedLeaves" role="tab">
                    <i class="ti tabler-edit-circle me-2"></i>
                    <span class="d-none d-sm-inline">সম্পাদিত ছুটি</span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content pt-4">
            <!-- Tab 1: Pending Leaves -->
            <div class="tab-pane fade show active" id="pendingLeaves" role="tabpanel">
                <div class="table-responsive">
                    <table id="employeeTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্রমিক</th>
                                <th>আবেদনকারীর নাম ও পদবী</th>
                                <th>আইডি</th>
                                <th>শাখা</th>
                                <th>আবেদনের তারিখ ও সময়</th>
                                <th>চাহিত ছুটির ধরণ</th>
                                <th>চাহিত ছুটি(দিন)</th>
                                <th>প্রস্তাবিত ছুটি(দিন)</th>
                                <th>প্রস্তাবিত ছুটির ধরণ</th>
                                <th>স্টেটাস</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Edited Leaves -->
            <div class="tab-pane fade" id="editedLeaves" role="tabpanel">
                <div class="table-responsive">
                    <table id="inactiveEmployeeTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্রমিক</th>
                                <th>আবেদনকারীর নাম ও পদবী</th>
                                <th>আইডি</th>
                                <th>শাখা</th>
                                <th>আবেদনের তারিখ ও সময়</th>
                                <th>চাহিত ছুটির ধরণ</th>
                                <th>চাহিত ছুটি(দিন)</th>
                                <th>প্রস্তাবিত ছুটি(দিন)</th>
                                <th>প্রস্তাবিত ছুটির ধরণ</th>
                                <th>স্টেটাস</th>
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
#employeeTable thead th,
#inactiveEmployeeTable thead th {
    color: #ffffff !important;
    background-color: #435971 !important;
}
</style>

<script>
var employeeTableInstance;
var inactiveEmployeeTableInstance;

$(document).ready(function() {
    // Initialize Pending Leaves DataTable
    employeeTableInstance = $('#employeeTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "fetch_forwardbyadmin_pending_data.php",
            type: "POST"
        },
        columns: [
            { data: "sl", orderable: false },
            { data: "applicant_name", orderable: false },
            { data: "employee_id", orderable: false },
            { data: "section_name", orderable: false },
            { data: "application_date_time" },
            { data: "requested_leave_type", orderable: false },
            { data: "requested_leave_days", orderable: false },
            { data: "proposed_leave_days", orderable: false },
            { data: "proposed_leave_type", orderable: false },
            { data: "status", orderable: false },
            { data: "action", orderable: false, searchable: false }
        ],
        order: [[4, 'desc']],
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

    // Initialize Edited Leaves DataTable (lazy load on tab switch)
    $('button[data-bs-target="#editedLeaves"]').on('shown.bs.tab', function (e) {
        if (!inactiveEmployeeTableInstance) {
            inactiveEmployeeTableInstance = $('#inactiveEmployeeTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "fetch_forwardedbyadmin_data.php",
                    type: "POST"
                },
                columns: [
                    { data: "sl", orderable: false },
                    { data: "applicant_name", orderable: false },
                    { data: "employee_id", orderable: false },
                    { data: "section_name", orderable: false },
                    { data: "application_date_time" },
                    { data: "requested_leave_type", orderable: false },
                    { data: "requested_leave_days", orderable: false },
                    { data: "proposed_leave_days", orderable: false },
                    { data: "proposed_leave_type", orderable: false },
                    { data: "status", orderable: false },
                    { data: "action", orderable: false, searchable: false }
                ],
                order: [[4, 'desc']],
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
</script>
