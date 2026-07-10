<?php
include('header.php');
include_once('function.php');

$employeeID = base64_decode($_GET['employeeID']);

$getEmployeeInfoQ = mysqli_query($con, "select * from `employee_list` where `id`='$employeeID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeInfoQ);

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeInfoQRW[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeInfoQRW[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);


$birthDateArray = explode('-', $getEmployeeInfoQRW['date_of_birth']);

$birthDate = $birthDateArray['2'].'/'.$birthDateArray[1].'/'.$birthDateArray[0];


$joiningDateArray = explode('-', $getEmployeeInfoQRW['joining_date']);

$joiningDate = $joiningDateArray['2'].'/'.$joiningDateArray[1].'/'.$joiningDateArray[0];


$getEmpIncrementDataQ = mysqli_query($con, "select * from yearly_salary_increment where employeeID='$employeeID' and status=1");


$todayDate = ShowBangladeshDate();

/*
$datediff = abs(strtotime($todayDate)-strtotime($getEmployeeInfoQRW['joining_date']));

echo "Date diff ".$datediff;

$employmentyears = floor($datediff / (365*60*60*24));

$employmentmonths = floor(($datediff - $employmentyears * 365*60*60*24) / (30*60*60*24));

$employmentdays = floor(($datediff - $employmentyears * 365*60*60*24 - $employmentmonths*30*60*60*24)/ (60*60*24));
*/

$getLeaveTypesQ = mysqli_query($con, "SELECT * FROM `leave_types`");


//.....

$resultSTR = calculateLeave($getUserInfoQRW['employee_id']);

$resultSTRArray = explode('_', $resultSTR);

$employmentyears = floor($resultSTRArray[0] / 365); // Calculate the number of years
$employmentmonths = floor(($resultSTRArray[0] % 365) / 30); // Calculate the number of months
$employmentdays = ($resultSTRArray[0] % 365) % 30; // Calculate the remaining days


?>






<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>কর্মকর্তা/কর্মচারীর তথ্য </h4>
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                <!-- Invoice Footer -->
                <div id="invoice-footer">
                    <div class="row">
                        <div class="col-md-12 col-sm-12">


						<p style="text-align:right"><button class="print-link no-print" onclick="jQuery('#ele1').print()">
                Print
                </button></p>


						<div id="ele1" style="padding:0px; margin:0; width:100%;">

							<h4 class="form-section" style="font-size: 16px;"> ব্যক্তিগত তথ্য</h4>
							<hr>

							<table width="100%" border="1" style="border-collapse: collapse; border: 1px solid #ccc;">

								<tr>
									<td height="40"><span style="margin-left: 10px;margin-right: 10px;">নাম</span></td>
									<td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getEmployeeInfoQRW['employee_name']; ?></span></td>
									<td rowspan="5" width="150" align="center"><span style="padding-top: 10px;padding-bottom: 10px;"><img height="150" src="uploads/<?php if($getEmployeeInfoQRW['photo'] != ''){ echo $getEmployeeInfoQRW['photo']; }else{ echo 'default-avatar.png'; } ?>" /></span></td>
								</tr>

								<tr><td height="30"><span style="margin-left: 10px;margin-right: 10px;">জাতীয় পরিচয়পত্র নং</span></td><td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getEmployeeInfoQRW['nationalID']; ?></span></td></tr>


								<tr><td height="30"><span style="margin-left: 10px;margin-right: 10px;">শাখা</span></td><td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getSectionDetailsQRW['section_name']; ?></span></td></tr>


								<tr><td height="30"><span style="margin-left: 10px;margin-right: 10px;">পদবি</span></td><td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getDesignationDetailsQRW['job_title_name']; ?></span></td></tr>


								<tr>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">আইডি</span></td>
									<td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $obj->engToBn($getEmployeeInfoQRW['employee_id']); ?></span></td>
								</tr>

								
								<tr>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">জন্ম তারিখ</span></td>
									<td><span style="margin-left: 10px;margin-right: 10px;"><?php echo $obj->engToBn($birthDate); ?></span></td>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">চাকরিতে যোগদানের তারিখ</span></td>
									<td colspan="2"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $obj->engToBn($joiningDate); ?></span></td>
								</tr>


								<tr>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">ইমেইল</span></td>
									<td><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getEmployeeInfoQRW['email']; ?></span></td>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">মোবাইল নম্বর</span></td>
									<td colspan="2"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $obj->engToBn($getEmployeeInfoQRW['mobileNo']); ?></span></td>
								</tr>
								


							
							</table>

							<p>&nbsp;</p>

							<h4 class="form-section" style="font-size: 16px;"> বেতন বৃদ্ধি সংক্রান্ত তথ্য</h4>
							<hr>


							<table border="1" width="100%">
								<tr>
								 <th>&nbsp;ক্রমিক নং</th>
								 <th>&nbsp;বৎসর</th>
								 <th>&nbsp;মূল বেতন</th>
								 <th>&nbsp;বেতন বৃদ্ধির হার</th>
								 <th>&nbsp;বেতন বৃদ্ধির পর মূল বেতন</th>
								</tr>

								<?php
								  $sl = 0;
								  while($dataRow = mysqli_fetch_array($getEmpIncrementDataQ)){
									$sl++;
								?>

								<tr>
								 <td>&nbsp;<?php echo $obj->engToBn($sl); ?></td>
								 <td>&nbsp;<?php echo $obj->engToBn(number_format($dataRow['incrementYear'],2)); ?></td>
								 <td>&nbsp;<?php echo $obj->engToBn(number_format($dataRow['presentSalary'],2)); ?></td>
								 <td>&nbsp;<?php echo $obj->engToBn(number_format($dataRow['incrementAmount'],2)); ?></td>
								 <td>&nbsp;<?php echo $obj->engToBn(number_format($dataRow['incrementSalary'],2)); ?></td>
								</tr>
					

								<?php } ?>


							</table>


							<p>&nbsp;</p>
							<h4 class="form-section" style="font-size: 16px;"> ছুটির হিসাব</h4>
							<hr>

							
							
							<div class="row">
							  <div class="col-sm-2">
							  	<div style="border: 1px solid #000;text-align: center;padding: 10px;">
									চাকরির সময়কাল <br>
									<?php echo $obj->engToBn($employmentyears).' বছর '.$obj->engToBn($employmentmonths).' মাস '.$obj->engToBn($employmentdays).' দিন'; ?>
								</div>
							  </div>
							  <div class="col-sm-2">
							  	<div style="border: 1px solid #000;text-align: center;padding: 10px;">
									নৈমিত্তিক পাওনা ছুটি<br>
									<?php echo $obj->engToBn(($resultSTRArray[1])); ?>&nbsp;দিন
								</div>
							  </div>
							  <div class="col-sm-2">
							  	<div style="border: 1px solid #000;text-align: center;padding: 10px;">
									গড়-বেতনে পাওনা ছুটি<br>
									<?php echo $obj->engToBn($resultSTRArray[2]); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($resultSTRArray[3]); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($resultSTRArray[4]); ?>&nbsp;দিন
								</div>
							  </div>
							  <div class="col-sm-2">
							  	<div style="border: 1px solid #000;text-align: center;padding: 10px;">
									অর্ধ-গড় বেতনে পাওনা ছুটি <br>
									<?php echo $obj->engToBn($resultSTRArray[5]); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($resultSTRArray[6]); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($resultSTRArray[7]); ?>&nbsp;দিন
								</div>
							  </div>



							</div>

							<p>&nbsp;</p>


							<table border="1" width="100%">
								<tr>
								 <th style="text-align: center;">ক্রমিক নং</th>
								 <th>&nbsp;ছুটির ধরণ</th>
								 <th style="text-align: center;">ভোগকৃত ছুটি(দিন)</th>
								 <th style="text-align: center;">গড় বেতনে (দিন)</th>
								 <th style="text-align: center;">অর্ধ-গড় বেতনে(দিন) </th>
								</tr>

								<?php
								  $ltSl = 0;
								  while($ltRow = mysqli_fetch_array($getLeaveTypesQ)){
									  $ltSl++;

									  if($ltRow['leaveID'] == 8){

										  $getTotalCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalCasual from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='3' and (approvedDateFrom >='$casualStart' and approvedDateTo<='$casualEnd')");
										  $getTotalCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalCasualLeaveInCurrentYearQ);

										  $usedLeave = number_format($getTotalCasualLeaveInCurrentYearQRW['totalCasual']);

										  $totalFullSLLeave = 0;

										  $totalHalfSLLeave = 0;


									  
									  }else if($ltRow['leaveID'] == 3){

										  $getTotalCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalCasual from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='4' and (approvedDateFrom >='$casualStart' and approvedDateTo<='$casualEnd')");
										  $getTotalCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalCasualLeaveInCurrentYearQ);

										  $usedLeave = number_format($getTotalCasualLeaveInCurrentYearQRW['totalCasual']);

										  $totalFullSLLeave = 0;

										  $totalHalfSLLeave = 0;


									  
									  }
									  else{
									  
									  	  $getTotalCasualLeaveInCurrentYearQ2 = mysqli_query($con, "select sum(approvedDays) as totalUsedLeave from leave_applications where status=1 and applicantID='$employeeID' and approvedLeaveType='$ltRow[leaveID]'");
										  $getTotalCasualLeaveInCurrentYearQ2RW = mysqli_fetch_assoc($getTotalCasualLeaveInCurrentYearQ2);

										  $usedLeave = number_format($getTotalCasualLeaveInCurrentYearQ2RW['totalUsedLeave']);

										  // calculate fullsal
										  $getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and approvedLeaveType='$ltRow[leaveID]' and leaveTypeInTwo='1'");
										  $getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);

										  $totalFullSLLeave = $getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'];


										  $getTotalfullAvgSalLeaveUsedQ2 = mysqli_query($con, "select sum(approvedDays) as totalhalfAvgSal from leave_applications where status=1 and applicantID='$employeeID' and approvedLeaveType='$ltRow[leaveID]' and leaveTypeInTwo='2'");
										  $getTotalfullAvgSalLeaveUsedQ2RW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ2);

										  $totalHalfSLLeave = $getTotalfullAvgSalLeaveUsedQ2RW['totalhalfAvgSal'];
									  
									  }
								?>



								<tr>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn($ltSl); ?></td>
								 <td>&nbsp;<?php echo $ltRow['leaveTitle']; ?></td>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn(number_format($usedLeave)); ?></td>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn(number_format($totalFullSLLeave)); ?></td>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn(number_format($totalHalfSLLeave)); ?></td>
								</tr>




								<?php } ?>


							</table>



						</div>
                            




                        




                        </div>
                        
                    </div>
                </div>
                <!--/ Invoice Footer -->
            </div>
        </div>
    </div>
</section>
<!--Invoice template ends-->
          </div>
        </div>

        
		
</div>







<?php
include('footer.php');

?>




<script>


$(".departmentID").change(function()
 {
  var id=$(this).val();
  var dataString = 'departmentID='+ id;
  
  $.ajax
  ({
   type: "POST",
   url: "get_sections.php",
   data: dataString,
   cache: false,
   success: function(html)
   {
      $(".sectionID").html(html);

   } 
   });
  });


$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_officer_data.php', // form action url
			type: 'POST', // form submit method get/post
			dataType: 'html', // request type html/json/xml
			data: new FormData(this), // serialize form data
			contentType: false,
            cache: false,
            processData:false,
			beforeSend: function() {
				
				submit.html('<i class="fa fa-spinner fa-spin"></i> processing, please wait'); // change submit button text
				setTimeout(200000000000000000);


			},
			success: function(data) {

				//alert(data);
				

                if(data==0)
				{
				
				    		
					toastr.error('à¦¤à§à¦°à§à¦Ÿà¦¿ à¦¦à§à¦ƒà¦–à¦¿à¦¤!!');

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else
				{
				
				    toastr.success('à¦¡à§‡à¦Ÿà¦¾ à¦¸à¦«à¦²à¦­à¦¾à¦¬à§‡ à¦¸à¦‚à¦°à¦•à§à¦·à¦¿à¦¤ à¦¹à¦¯à¦¼à§‡à¦›à§‡');

					window.location='fetch_user_access_record?menuslug=user_management&rowid='+data;
				
				
				}




				//form.trigger('reset'); // reset form
				submit.html('à¦¸à¦‚à¦°à¦•à§à¦·à¦£ à¦•à¦°à§à¦¨'); // reset submit button text
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});



</script>