<?php
include(__DIR__ . '/includes/header_vuexy.php');
include('library/number_converter.php');

function Bengali_DTN($NRS){
	$englDTN = array
			('1','2','3','4','5','6','7','8','9','0',
			'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday',
			'Sat','Sun','Mon','Tue','Wed','Thu','Fri',
			'am','pm','at','st','nd','rd','th',
			'January','February','March','April','May','June','July','August','September','October','November','December',
			'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
			$bangDTN = array
			('১','২','৩','৪','৫','৬','৭','৮','৯','০',
			'শনিবার','রবিবার','সোমবার','মঙ্গলবার','বুধবার','বৃহস্পতিবার','শুক্রবার',
			'শনি','রবি','সোম','মঙ্গল','বুধ','বৃহঃ','শুক্র',
			'পূর্বাহ্ণ','অপরাহ্ণ','','','','','',
			'জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর',
			'জানু','ফেব্রু','মার্চ','এপ্রি','মে','জুন','জুলা','আগ','সেপ্টে','অক্টো','নভে','ডিসে');
			$converted = str_replace($bangDTN, $englDTN, $NRS);
			return $converted;
}
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">ছুটির ইতিহাস ও যোগদান</h4>
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
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#applicationBased" role="tab">
                    <i class="ti tabler-file-text me-2"></i>
                    <span class="d-none d-sm-inline">আবেদনের ভিত্তিতে</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#officeBased" role="tab">
                    <i class="ti tabler-file-description me-2"></i>
                    <span class="d-none d-sm-inline">অফিস আদেশের ভিত্তিতে</span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content pt-4">
            <!-- Tab 1: Application Based -->
            <div class="tab-pane fade show active" id="applicationBased" role="tabpanel">
                <div class="table-responsive">
                    <table id="applicationBasedTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্রমিক</th>
                                <th>আবেদনকারীর নাম ও পদবী</th>
                                <th>চাহিত ছুটি</th>
                                <th>প্রাথমিক অনুমোদিত ছুটি</th>
                                <th>ভোগকৃত ছুটি</th>
                                <th>যোগদানের প্রকার</th>
                                <th>সংশোধিত ছুটি</th>
                                <th>স্টেটাস</th>
                                <th>প্রয়োজনীয় পত্রসমূহ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Office Based -->
            <div class="tab-pane fade" id="officeBased" role="tabpanel">
                <div class="table-responsive">
                    <table id="officeBasedTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্রমিক</th>
                                <th>ছুটির ধরণ</th>
                                <th>কর্তন(দিন)</th>
                                <th>নোট</th>
                                <th>Submit Date</th>
                                <th>সংযুক্তি</th>
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
#applicationBasedTable thead th,
#officeBasedTable thead th {
    color: #ffffff !important;
    background-color: #435971 !important;
}
</style>

<script>
var applicationBasedTableInstance;
var officeBasedTableInstance;

$(document).ready(function() {
    // Initialize Application Based DataTable
    applicationBasedTableInstance = $('#applicationBasedTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'fetch_all_leave_application_data.php',
            type: 'POST'
        },
        columns: [
            { data: 'serial', orderable: false },
            { data: 'employee_info', orderable: false },
            { data: 'requested_leave', orderable: false },
            { data: 'approved_leave', orderable: false },
            { data: 'spent_leave', orderable: false },
            { data: 'joining_type', orderable: false },
            { data: 'corrected_leave', orderable: false },
            { data: 'status', orderable: false },
            { data: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
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

    // Initialize Office Based DataTable (lazy load on tab switch)
    $('button[data-bs-target="#officeBased"]').on('shown.bs.tab', function (e) {
        if (!officeBasedTableInstance) {
            officeBasedTableInstance = $('#officeBasedTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'fetch_leave_deduction_history_data.php',
                    type: 'POST'
                },
                columns: [
                    { data: 'serial', orderable: false },
                    { data: 'leave_type', orderable: false },
                    { data: 'deduction_days', orderable: false },
                    { data: 'note', orderable: false },
                    { data: 'submit_date' },
                    { data: 'attachment', orderable: false, searchable: false }
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

function cancelApplication(applicationID, rowId){
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই আবেদনটি মুছে ফেলতে চান?",
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
    }).then(function (result) {
        if (result.isConfirmed) {
            // AJAX request to cancel leave application
            $.ajax({
                type: 'post',
                url: 'cancelLeaveApplicationBySelf.php',
                data: 'applicationID='+ applicationID,
                success: function(data) {
                    if (data == 0) {
                        Swal.fire({
                            title: 'ত্রুটি!',
                            text: 'একটি ত্রুটি হয়েছে',
                            icon: 'error',
                            confirmButtonColor: '#ff3e1d',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    } else {
                        // Reload the DataTable
                        applicationBasedTableInstance.ajax.reload();

                        Swal.fire({
                            title: 'সফল!',
                            text: 'আবেদনটি মুছে ফেলা হয়েছে',
                            icon: 'success',
                            confirmButtonColor: '#28c76f',
                            customClass: {
                                confirmButton: 'btn btn-success'
                            },
                            buttonsStyling: false
                        });
                    }
                },
                error: function(e) {
                    console.log(e);
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'অনুরোধ প্রক্রিয়া করতে ব্যর্থ হয়েছে',
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
