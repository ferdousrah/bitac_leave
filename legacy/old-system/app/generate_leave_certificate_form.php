<?php
include('header.php');

$incrementYear = date('Y');

$getAllEmployeeQ = mysqli_query($con, "select * from employee_list where employment_status=1 or employment_status=2");

$getAllCopytoListQ = mysqli_query($con,"select * from employee_list");

$getAllCopytoListQ2 = mysqli_query($con,"select * from employee_list");

// For signatory dropdown - typically officers/managers
$getSignatoryListQ = mysqli_query($con,"select employee_list.*, job_title.job_title_name from employee_list 
    inner join job_title on employee_list.designation = job_title.id 
    where employment_status=1 or employment_status=2 order by employee_id asc");

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

<div class="main-panel">
    <div class="main-content" style="clear: left;">
        <div class="content-wrapper">
            <div class="row">
                <div class="col-md-12">
                    <h4>Generate Leave Certificate</h4>
                </div>
            </div>
            <section class="invoice-template">
                <div class="card">
                    <div class="card-body p-3">
                        <div id="invoice-template" class="card-block">
                            <div id="invoice-footer">
                                <div class="row">
                                    <div class="col-md-12 col-sm-12">
                                        <div class="px-3">
                                            <form class="form form-horizontal" name="form" id="form" enctype="multipart/form-data">
                                                <div class="form-body">

                                                    <div class="form-group row">
                                                        <label class="col-md-3 label-control" for="incrementYear">বছর: </label>
                                                        <div class="col-md-9">
                                                            <select class="form-control" name="incrementYear">
                                                                <option value="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="form-group row">
                                                        <label class="col-md-3 label-control" for="certificateDate">সার্টিফিকেট ডেট :</label>
                                                        <div class="col-md-9">
                                                            <input type="text" class="form-control" id="certificateDate" name="certificateDate" required autocomplete="off" />
                                                        </div>
                                                    </div>


													<div class="form-group row">
                                                        <label class="col-md-3 label-control" for="noticeDate">নোটিশ ডেট :</label>
                                                        <div class="col-md-9">
                                                            <input type="text" class="form-control" id="noticeDate" name="noticeDate" required autocomplete="off" />
                                                        </div>
                                                    </div>

                                                    <div class="form-group row">
                                                        <label class="col-md-3 label-control" for="employeeID">কর্মচারী </label>
                                                        <div class="col-md-9">
                                                            <select class="js-example-basic-single2" style="width: 100%;" name="employeeID" id="employeeID" required>
                                                                <option value=''></option>
                                                                <?php
                                                                while($empRow = mysqli_fetch_array($getAllEmployeeQ)){
                                                                    $getSupervisorDesigQ = mysqli_query($con, "select * from job_title where id='$empRow[designation]'");
                                                                    $getSupervisorDesigQRW = mysqli_fetch_assoc($getSupervisorDesigQ);
                                                                ?>
                                                                <option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).'-'.$empRow['employee_name'].', '.$getSupervisorDesigQRW['job_title_name']; ?></option>
                                                                <?php } ?>
                                                                <option value='0'>সকল</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Signatory Selection -->
                                                    <div class="form-group row">
                                                        <label class="col-md-3 label-control" for="signatoryID">স্বাক্ষরকারী কর্মকর্তা </label>
                                                        <div class="col-md-9">
                                                            <select class="js-example-basic-single3" style="width: 100%;" name="signatoryID" id="signatoryID" required>
                                                                <option value=''>-- স্বাক্ষরকারী নির্বাচন করুন --</option>
                                                                <?php
                                                                while($sigRow = mysqli_fetch_array($getSignatoryListQ)){
                                                                ?>
                                                                <option value='<?php echo $sigRow['id']; ?>'><?php echo Bengali_DTN($sigRow['employee_id']).' - '.$sigRow['employee_name'].', '.$sigRow['job_title_name']; ?></option>
                                                                <?php } ?>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <h4 class="form-section"><i class="ft-file-text"></i>অনুলিপি:</h4>

                                                    <table id="tbl" border="1" width="100%">
                                                        <thead>
                                                            <tr>
                                                                <th class="text-center" height="50" width="20">ক্রমিক নং</th>
                                                                <th class="text-center" height="50" width="150">কর্মকর্তার নাম ও পদবী</th>
                                                                <th class="text-center" height="50" width="150">ক্রমিক নং</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr id='addr0'>
                                                                <td height='40' style="text-align:center">1</td>
                                                                <td height='40' style="text-align:center">
                                                                    <select class="js-example-basic-single" style="width: 100%;" name="copyTo[]">
                                                                        <option value=''></option>
                                                                        <?php while($empRow = mysqli_fetch_array($getAllCopytoListQ)){ 
                                                                            $getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$empRow[id]'");
                                                                            $getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);
                                                                        ?>
                                                                        <option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($getEmployeeDetailsQRW['employee_id']).' - '.$getEmployeeDetailsQRW['employee_name']; ?></option>
                                                                        <?php } ?>
                                                                    </select>
                                                                </td>
                                                                <td height='40' style="text-align:center">
                                                                    <input type="number" class="form-control" name="serial[]" value="1" />
                                                                </td>
                                                            </tr>
                                                            <tr id='addr2'></tr>
                                                        </tbody>
                                                    </table>

                                                    <div class="row" style="margin-top: 20px;">
                                                        <div class="col-md-4">&nbsp;</div>
                                                        <div class="col-md-6">&nbsp;</div>
                                                        <div class="col-md-2" style="text-align: right;">
                                                            <a id="add_row" class="btn btn-success btn-fab"><i class="material-icons">add</i></a>&nbsp;&nbsp;&nbsp;&nbsp;
                                                            <a id='delete_row' class="btn btn-danger btn-fab"><i class="material-icons">clear</i></a>
                                                        </div>
                                                    </div>

                                                    <div class="form-actions">
                                                        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
                                                            <i class="ft-x"></i> Cancel
                                                        </button>
                                                        <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
                                                            <i class="fa fa-check-square-o"></i> Generate Now
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script type="text/javascript">
$(document).ready(function() {
    $("#certificateDate").datepicker({
        dateFormat: "dd/mm/yy"
    });

	$("#noticeDate").datepicker({
        dateFormat: "dd/mm/yy"
    });

});

$('.employeeID').select2();
$('.js-example-basic-single').select2();
$('.js-example-basic-single2').select2();
$('.js-example-basic-single3').select2();
$('.copyTo').select2();

var i = $('table tr').length;
$("#add_row").on('click', function() {
    i++;
    html = '<tr>';
    html += "<td style='text-align:center;'>" + (i - 2) + "</td>";
    html += "<td height='40' style='text-align:center'><select class='js-example-basic-single" + i + "' style='width:100%;' name='copyTo[]' id='copyTo_" + i + "' required><option value=''></option><?php while($cRow2 = mysqli_fetch_array($getAllCopytoListQ2)){ ?><option value='<?php echo $cRow2['id']; ?>'><?php echo Bengali_DTN($cRow2['employee_id']).' - '.$cRow2['employee_name']; ?></option><?php } ?></select></td>";
    html += "<td style='text-align:center'><input type='number' name='serial[]' id='serial_" + i + "' placeholder='' class='form-control' value='" + (i - 2) + "' required /></td>";
    html += '</tr>';
    $('table').append(html);
    $('.js-example-basic-single' + i).select2();
});

$("#delete_row").click(function() {
    var i = $('table tr').length;
    $("#tbl tr:last").remove();
});

$(document).ready(function() {
    var form = $('#form');
    var submit = $('#submit');

    form.on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'auto_generate_leave_cert_action.php',
            type: 'POST',
            dataType: 'html',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                submit.html('<i class="fa fa-spinner fa-spin"></i> Processing, please wait');
            },
            success: function(data) {
                if (data == 1) {
                    toastr.success('Data Generated Successfully');
                } else {
                    toastr.error('Error!!');
                }
                submit.html('Generate Again');
            },
            error: function(e) {
                console.log(e);
            }
        });
    });
});
</script>