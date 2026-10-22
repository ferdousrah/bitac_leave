<?php
include('header.php');

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

<div class="main-panel">
    <div class="main-content">
        <div class="content-wrapper">
            <div class="row">
                <div class="col-md-10">
                    <h4>বার্ষিক ছুটি সনদ প্রিন্ট</h4>
                </div>
                <div class="col-md-2"></div>
            </div>
            <section class="invoice-template">
                <div class="card">
                    <div class="card-body p-3">
                        <div id="invoice-template" class="card-block">
                            <form action="yearly_leave_certificate_print_view.php" method="get" target="_blank">
                                <div class="form-body">
                                    
                                    <div class="form-group row">
                                        <label class="col-md-3 label-control" for="employeeID">কর্মকর্তা/ কর্মচারী সিলেক্ট করুন:</label>
                                        <div class="col-md-9">
                                            <select class="employeeID" style="width: 100%;" name="employeeID" id="employeeID" required>
                                                <option value=''>-- সিলেক্ট করুন --</option>
                                                <?php while($empRow = mysqli_fetch_array($getAllEmployeeListQ)){ ?>
                                                <option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).' - '.$empRow['employee_name'].', '.$empRow['job_title_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-md-3 label-control" for="incrementYear">বছর:</label>
                                        <div class="col-md-9">
                                            <select class="form-control" name="year" id="incrementYear" required>
                                                <?php 
                                                $currentYear = date('Y');
                                                for($y = $currentYear; $y >= $currentYear - 5; $y--){ 
                                                ?>
                                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
                                            <i class="ft-x"></i> Cancel
                                        </button>
                                        <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
                                            <i class="fa fa-file-pdf-o"></i> Generate PDF
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
$(document).ready(function() {
    $('.employeeID').select2();
});
</script>