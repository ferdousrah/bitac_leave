<?php
include('header.php');

$studentID = base64_decode($_GET['studentID']);


$getStudentInfoQ = mysqli_query($con, "select * from `students` where `dataID`='$studentID'");
$getStudentInfoQRW = mysqli_fetch_assoc($getStudentInfoQ);

$getStudentCoursesQ = mysqli_query($con, "select * from `courses_by_students` where `studentID`='$studentID'");

?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->


<!--About section starts-->
<section id="about">
    <div class="row">
        <div class="col-12">
            <!--<div class="content-header">About</div>-->
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <p style="text-align: right;"><i id="doPrint" style="font-size: 32px;" class="fa fa-print"></i></p>
                </div>
                <div class="card-body">
                    <div class="card-block">

					<div id="printDiv">
                        
                        <div class="row">

							<div class="col-sm-3" style="text-align: center;">
								<img class="img-responsive" src="uploads/students/<?php echo $getStudentInfoQRW['photo']; ?>" height="160" />
							</div>
                            <div class="col-sm-9">
                                <table width="100%" border="1" style="border-collapse: collapse;border-color: #ccc;">
								<tr><td><span style="padding: 10px;font-size: 14px;">Full Name</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['full_name']."</span>"; ?></span></td></tr>
								<tr><td><span style="padding: 10px;font-size: 14px;">Father's/Spouse Name</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['father_name']; ?></span></td></tr>
								<tr><td><span style="padding: 10px;font-size: 14px;">Mother's Name</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['mother_name']; ?></span></td></tr>
								
								<tr><td><span style="padding: 10px;font-size: 14px;">Date of Birth</span></td><td><span style="padding: 10px;font-size: 14px;"><?php $date=date_create($getStudentInfoQRW['dob']); echo date_format($date,"F d, Y"); ?></span></td><td><span style="padding: 10px;font-size: 14px;">Gender</span></td><td><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['gender']; ?></span></td></tr>

								<tr><td><span style="padding: 10px;font-size: 14px;">Nationality</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['nationality']; ?></span></td></tr>

								<tr><td><span style="padding: 10px;font-size: 14px;">Academic Qualification</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['academic_qualification']; ?></span></td></tr>

								<tr><td><span style="padding: 10px;font-size: 14px;">Academic Institute</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['academic_institute']; ?></span></td></tr>

								<tr><td><span style="padding: 10px;font-size: 14px;">Present Address</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['present_address']; ?></span></td></tr>

								<tr><td><span style="padding: 10px;font-size: 14px;">Gurdian Cell</span></td><td><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['guardians_cell']; ?></span></td><td><span style="padding: 10px;font-size: 14px;">Residence Cell</span></td><td><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['residence_tele']; ?></span></td></tr>

								<tr><td><span style="padding: 10px;font-size: 14px;">Student Cell</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['students_cell']; ?></span></td></tr>

								<tr><td><span style="padding: 10px;font-size: 14px;">Passport No.</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['passport_no']; ?></span></td></tr>

								<tr><td><span style="padding: 10px;font-size: 14px;">Study Abroad</span></td><td colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['study_abroad']; ?></span></td></tr>

								<tr><td><span style="padding: 10px;font-size: 14px;">Email</span></td><td  colspan="3"><span style="padding: 10px;font-size: 14px;"><?php echo $getStudentInfoQRW['email']; ?></span></td></tr>
								</table>
                            </div>
                        
						    
                            
                        <hr>

						<div class="col-sm-12" style="text-align: center;">
							&nbsp;
						</div>
						<div class="col-sm-3" style="text-align: center;">
								
						</div>

						<div class="col-sm-9">

							<table width="100%" border="1" style="border-collapse: collapse;border-color: #ccc;">
								<tr><th colspan="3">&nbsp;Courses Entitled</th></tr>
								<tr><th style="text-align: center;">Sl</th><th style="text-align: center;">Course Name</th><th style="text-align: center;">Enroll date</th></tr>

								<?php
									$sl = 0;
									while($cRow = mysqli_fetch_array($getStudentCoursesQ)) {	

										$sl++;

										$getCourseDetailsQ = mysqli_query($con, "select * from `courses` where `dataID`='$cRow[courseID]'");
										$getCourseDetailsQRW = mysqli_fetch_assoc($getCourseDetailsQ);
								?>


								<tr><td style="text-align: center"><?php echo $sl; ?></td><td style="text-align: center"><?php echo $getCourseDetailsQRW['courseTitle']; ?></td><td style="text-align: center"><?php $enrolldate=date_create($cRow['date']); echo date_format($enrolldate,"F d, Y"); ?></td></tr>


								<?php } ?>

							</table>



						</div>


						<div class="col-sm-12" style="text-align: center;">
							&nbsp;
						</div>


<!--
						<div class="col-sm-3" style="text-align: center;">
								&nbsp;
						</div>

						<div class="col-sm-9">

							<table width="100%" border="1" style="border-collapse: collapse;border-color: #ccc;">
								<tr><th colspan="3">&nbsp;Medicine Consumption</th></tr>
								<tr><th style="text-align: center;">Sl</th><th style="text-align: center;">Receiving Date</th><th style="text-align: center;">Prescription ID</th></tr>

							</table>



						</div>
                        
-->




						</div>
                    </div>
                </div>
            </div>
        </div>
        



    </div>
</section>
<!--About section ends-->
<!--Invoice template ends-->
          </div>
        </div>

        
		
</div>







<?php
include('footer.php');

?>




<script>


document.getElementById("doPrint").addEventListener("click", function() {
     var printContents = document.getElementById('printDiv').innerHTML;
     var originalContents = document.body.innerHTML;
     document.body.innerHTML = printContents;
     window.print();
     document.body.innerHTML = originalContents;
});







</script>