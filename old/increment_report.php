<?php
include(__DIR__ . '/includes/header_vuexy.php');

$incrementYear = date('Y');

$getAllEmployeeListQ = mysqli_query($con, "SELECT employee_list.*, job_title.job_title_name
    FROM employee_list
    INNER JOIN job_title ON employee_list.designation = job_title.id
    WHERE employment_status=1 OR employment_status=2
    ORDER BY employee_id ASC");

function Bengali_DTN($NRS){
    $englDTN = array('1','2','3','4','5','6','7','8','9','0');
    $bangDTN = array('১','২','৩','৪','৫','৬','৭','৮','৯','০');
    $converted = str_replace($bangDTN, $englDTN, $NRS);
    return $converted;
}
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">ইনক্রিমেন্ট রিপোর্ট</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Increment Report Form Card -->
<div class="card">
    <div class="card-body">
        <form action="increment_report_view.php" onsubmit="target_popup(this)" method="post">
            <div class="row">
                <!-- Employee Selection -->
                <div class="col-12 mb-4">
                    <label class="form-label" for="employeeID">
                        কর্মকর্তা/ কর্মচারী সিলেক্ট করুন <span class="text-danger">*</span>
                    </label>
                    <select class="form-select employeeID" name="employeeID" id="employeeID" required>
                        <option value=''>-- সিলেক্ট করুন --</option>
                        <?php while($empRow = mysqli_fetch_array($getAllEmployeeListQ)){ ?>
                        <option value='<?php echo $empRow['id']; ?>'>
                            <?php echo Bengali_DTN($empRow['employee_id']).' - '.$empRow['employee_name'].', '.$empRow['job_title_name']; ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Form Actions -->
                <div class="col-12">
                    <button type="submit" name="submit" id="submit" class="btn btn-primary me-2">
                        <i class="ti tabler-file-export me-1"></i>Generate
                    </button>
                    <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                        <i class="ti tabler-x me-1"></i>Cancel
                    </button>
                </div>
            </div>
        </form>

        <!-- Result Container -->
        <div id="formresult" class="mt-4"></div>
    </div>
</div>

<?php include(__DIR__ . '/includes/footer_vuexy.php'); ?>

<script>
$(document).ready(function() {
    // Initialize Select2 for employee dropdown
    $('.employeeID').select2({
        placeholder: '-- সিলেক্ট করুন --',
        allowClear: true,
        width: '100%',
        theme: 'bootstrap-5'
    });

    var form = $('form');
    var submit = $('#submit');

    // form submit event
    form.on('submit', function(e) {
        e.preventDefault(); // prevent default form submit

        // sending ajax request through jQuery
        $.ajax({
            url: 'increment_report_view.php',
            type: 'POST',
            dataType: 'html',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                submit.html('<i class="spinner-border spinner-border-sm me-1"></i>Generating, please wait');
                submit.prop('disabled', true);
            },
            success: function(data) {
                if(data == 0) {
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'রিপোর্ট তৈরি করতে ব্যর্থ হয়েছে',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                } else {
                    $('#formresult').html(data);
                }

                submit.html('<i class="ti tabler-file-export me-1"></i>Generate');
                submit.prop('disabled', false);
            },
            error: function(e) {
                console.log(e);
                Swal.fire({
                    title: 'ত্রুটি!',
                    text: 'রিপোর্ট তৈরি করতে ব্যর্থ হয়েছে',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: {
                        confirmButton: 'btn btn-danger'
                    },
                    buttonsStyling: false
                });
                submit.html('<i class="ti tabler-file-export me-1"></i>Generate');
                submit.prop('disabled', false);
            }
        });
    });
});

function target_popup(form) {
    window.open('', 'formpopup', 'width=1000,height=1000,resizeable,scrollbars');
    form.target = 'formpopup';
}
</script>
