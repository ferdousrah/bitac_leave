<?php
include(__DIR__ . '/includes/header_vuexy.php');
include('library/number_converter.php');

// Fetch all organizations/centers
$getOrganizationsQ = mysqli_query($con, "SELECT * FROM organization WHERE deleted = 0 ORDER BY display_order ASC");
$organizations = [];
while($org = mysqli_fetch_assoc($getOrganizationsQ)) {
    $organizations[] = $org;
}
?>

<style>
/* Action button styling */
.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-icon i {
    font-size: 1.125rem;
}

.column-search input,
.column-search select {
    width: 100%;
    padding: 8px;
    box-sizing: border-box;
    border: 1px solid #d9dee3;
    border-radius: 4px;
}

.column-search {
    background-color: #f8f9fa;
}

.column-search th {
    padding: 8px;
    vertical-align: top;
}

/* Table header styling - Dark theme */
.table thead tr:first-child th {
    background-color: #435971;
    color: #ffffff;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.4px;
    border-bottom: 1px solid #435971;
}

/* Search row styling */
.table thead tr.column-search th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #d9dee3;
}
</style>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">কর্মকর্তা/কর্মচারীদের তালিকা</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="new_employee_form.php?menuslug=manage-employee" class="btn btn-primary">
            <i class="menu-icon menu-icon icon-base ti tabler-plus"></i>নতুন সংযোজন
        </a>
    </div>
</div>

<!-- Employee Management Card -->
<div class="card">
    <div class="card-body">
        <!-- Tab Navigation - Filled Tabs Style -->
        <div class="nav-align-top nav-tabs-shadow">
            <ul class="nav nav-tabs nav-fill" role="tablist">
                <li class="nav-item">
                    <button type="button"
                            class="nav-link active"
                            role="tab"
                            data-bs-toggle="tab"
                            data-bs-target="#active_employees"
                            aria-controls="active_employees"
                            aria-selected="true">
                        <span class="d-none d-sm-inline-flex align-items-center">
                            <i class="ti tabler-users me-2"></i>চাকরিরত কর্মকর্তা/কর্মচারী
                        </span>
                        <i class="ti tabler-users d-sm-none"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button"
                            class="nav-link"
                            role="tab"
                            data-bs-toggle="tab"
                            data-bs-target="#inactive_employees"
                            aria-controls="inactive_employees"
                            aria-selected="false">
                        <span class="d-none d-sm-inline-flex align-items-center">
                            <i class="ti tabler-user-off me-2"></i>অবসরপ্রাপ্ত/চাকরিরত নন
                        </span>
                        <i class="ti tabler-user-off d-sm-none"></i>
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
            <!-- Active Employees Tab -->
            <div class="tab-pane fade show active" id="active_employees" role="tabpanel">
                <div class="table-responsive">
                    <table id="activeEmployeeTable" class="table table-hover" style="width: 100%;">
                        <thead>
                            <tr>
                                <th width="60">ক্রমিক নং</th>
                                <th width="80">ফটো</th>
                                <th>নাম ও পদবি</th>
                                <th width="100">আইডি</th>
                                <th>কেন্দ্র</th>
                                <th>শাখা</th>
                                <th width="140">Action</th>
                            </tr>
                            <tr class="column-search">
                                <th></th>
                                <th></th>
                                <th><input type="text" placeholder="নাম খুঁজুন" class="form-control form-control-sm" /></th>
                                <th><input type="text" placeholder="আইডি খুঁজুন" class="form-control form-control-sm" /></th>
                                <th>
                                    <select class="form-select form-select-sm">
                                        <option value="">সকল কেন্দ্র</option>
                                        <?php foreach($organizations as $org): ?>
                                            <option value="<?php echo htmlspecialchars($org['organization_name']); ?>">
                                                <?php echo htmlspecialchars($org['organization_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th><input type="text" placeholder="শাখা খুঁজুন" class="form-control form-control-sm" /></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Inactive Employees Tab -->
            <div class="tab-pane fade" id="inactive_employees" role="tabpanel">
                <div class="table-responsive">
                    <table id="inactiveEmployeeTable" class="table table-hover" style="width: 100%;">
                        <thead>
                            <tr>
                                <th width="60">ক্রমিক নং</th>
                                <th width="80">ফটো</th>
                                <th>নাম ও পদবি</th>
                                <th width="100">আইডি</th>
                                <th>কেন্দ্র</th>
                                <th>শাখা</th>
                                <th width="100">Action</th>
                            </tr>
                            <tr class="column-search">
                                <th></th>
                                <th></th>
                                <th><input type="text" placeholder="নাম খুঁজুন" class="form-control form-control-sm" /></th>
                                <th><input type="text" placeholder="আইডি খুঁজুন" class="form-control form-control-sm" /></th>
                                <th>
                                    <select class="form-select form-select-sm">
                                        <option value="">সকল কেন্দ্র</option>
                                        <?php foreach($organizations as $org): ?>
                                            <option value="<?php echo htmlspecialchars($org['organization_name']); ?>">
                                                <?php echo htmlspecialchars($org['organization_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th><input type="text" placeholder="শাখা খুঁজুন" class="form-control form-control-sm" /></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<?php
include(__DIR__ . '/includes/footer_vuexy.php');
?>

<script>
$(document).ready(function() {
    // Initialize DataTable for active employees
    var activeTable = $('#activeEmployeeTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "fetch_active_employees_data.php",
            "type": "POST",
            "data": function(d) {
                d.center_id = 0; // 0 means show all centers
            }
        },
        "columns": [
            { "data": "sl", "orderable": false, "searchable": false },
            { "data": "photo", "orderable": false, "searchable": false },
            { "data": "employee_name" },
            { "data": "employee_id" },
            { "data": "organization_name" },
            { "data": "section_name" },
            { "data": "action", "orderable": false, "searchable": false }
        ],
        "language": {
            "search": "সার্চ:",
            "lengthMenu": "প্রদর্শন করুন _MENU_ এন্ট্রি",
            "info": "মোট _TOTAL_ এন্ট্রির মধ্যে _START_ থেকে _END_ প্রদর্শন করা হচ্ছে",
            "infoEmpty": "কোন এন্ট্রি পাওয়া যায়নি",
            "infoFiltered": "(মোট _MAX_ এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            "paginate": {
                "first": "প্রথম",
                "last": "শেষ",
                "next": "পরবর্তী",
                "previous": "পূর্ববর্তী"
            },
            "processing": "প্রক্রিয়াকরণ..."
        },
        "initComplete": function() {
            var api = this.api();

            // Column 2: Name search
            $('#activeEmployeeTable thead .column-search th:eq(2) input').on('keyup change clear', function() {
                api.column(2).search(this.value).draw();
            });

            // Column 3: ID search
            $('#activeEmployeeTable thead .column-search th:eq(3) input').on('keyup change clear', function() {
                api.column(3).search(this.value).draw();
            });

            // Column 4: Center/Organization dropdown search
            $('#activeEmployeeTable thead .column-search th:eq(4) select').on('change', function() {
                var val = this.value;
                api.column(4).search(val ? val : '', false, false).draw();
            });

            // Column 5: Section search
            $('#activeEmployeeTable thead .column-search th:eq(5) input').on('keyup change clear', function() {
                api.column(5).search(this.value).draw();
            });
        },
        "drawCallback": function() {
            // Dispose existing tooltips first to prevent duplicates
            var existingTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            existingTooltips.forEach(function(element) {
                var tooltip = bootstrap.Tooltip.getInstance(element);
                if (tooltip) {
                    tooltip.dispose();
                }
            });

            // Reinitialize tooltips after each draw
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });

    // Initialize DataTable for inactive employees
    var inactiveTable = $('#inactiveEmployeeTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "fetch_inactive_employees_data.php",
            "type": "POST",
            "data": function(d) {
                d.center_id = 0; // 0 means show all centers
            }
        },
        "columns": [
            { "data": "sl", "orderable": false, "searchable": false },
            { "data": "photo", "orderable": false, "searchable": false },
            { "data": "employee_name" },
            { "data": "employee_id" },
            { "data": "organization_name" },
            { "data": "section_name" },
            { "data": "action", "orderable": false, "searchable": false }
        ],
        "language": {
            "search": "সার্চ:",
            "lengthMenu": "প্রদর্শন করুন _MENU_ এন্ট্রি",
            "info": "মোট _TOTAL_ এন্ট্রির মধ্যে _START_ থেকে _END_ প্রদর্শন করা হচ্ছে",
            "infoEmpty": "কোন এন্ট্রি পাওয়া যায়নি",
            "infoFiltered": "(মোট _MAX_ এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            "paginate": {
                "first": "প্রথম",
                "last": "শেষ",
                "next": "পরবর্তী",
                "previous": "পূর্ববর্তী"
            },
            "processing": "প্রক্রিয়াকরণ..."
        },
        "initComplete": function() {
            var api = this.api();

            // Column 2: Name search
            $('#inactiveEmployeeTable thead .column-search th:eq(2) input').on('keyup change clear', function() {
                api.column(2).search(this.value).draw();
            });

            // Column 3: ID search
            $('#inactiveEmployeeTable thead .column-search th:eq(3) input').on('keyup change clear', function() {
                api.column(3).search(this.value).draw();
            });

            // Column 4: Center/Organization dropdown search
            $('#inactiveEmployeeTable thead .column-search th:eq(4) select').on('change', function() {
                var val = this.value;
                api.column(4).search(val ? val : '', false, false).draw();
            });

            // Column 5: Section search
            $('#inactiveEmployeeTable thead .column-search th:eq(5) input').on('keyup change clear', function() {
                api.column(5).search(this.value).draw();
            });
        },
        "drawCallback": function() {
            // Dispose existing tooltips first to prevent duplicates
            var existingTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            existingTooltips.forEach(function(element) {
                var tooltip = bootstrap.Tooltip.getInstance(element);
                if (tooltip) {
                    tooltip.dispose();
                }
            });

            // Reinitialize tooltips after each draw
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });

    // Prevent sorting when clicking on search inputs/selects
    $('#activeEmployeeTable thead .column-search th, #inactiveEmployeeTable thead .column-search th').on('click', function(e) {
        e.stopPropagation();
    });
});

function removeData(sl, dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই ডেটা মুছে ফেলতে চান?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#696cff',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, মুছে ফেলুন!',
        cancelButtonText: 'বাতিল করুন'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'post',
                url: 'delete_emp_data.php',
                data: 'dataID=' + dataID + '&tableName=students',
                success: function(data) {
                    Swal.fire({
                        title: 'মুছে ফেলা হয়েছে!',
                        text: 'ডেটা সফলভাবে মুছে ফেলা হয়েছে।',
                        icon: 'success',
                        confirmButtonColor: '#696cff'
                    }).then(() => {
                        // Reload both tables
                        if ($.fn.DataTable.isDataTable('#activeEmployeeTable')) {
                            $('#activeEmployeeTable').DataTable().ajax.reload();
                        }
                        if ($.fn.DataTable.isDataTable('#inactiveEmployeeTable')) {
                            $('#inactiveEmployeeTable').DataTable().ajax.reload();
                        }
                    });
                }
            });
        }
    });
}
</script>
