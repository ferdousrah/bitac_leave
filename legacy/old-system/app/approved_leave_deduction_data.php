<?php
include('header.php');

$getLeaveDeductionHQ = mysqli_query($con,"select * from leave_deduction_history order by createDate desc");


?>






<div class="main-panel">
        <div class="main-content">
          <div class="content-wrapper">
		  <!--Invoice template starts-->
    <div class="row">
    <div class="col-md-12">
	&nbsp;
	</div>
	</div>

    <div class="row">
    <div class="col-md-10">
        <h4>Leave Duduction Data</h4>
    </div>
	<div class="col-md-2">
        
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                <!-- Invoice Footer -->
                <div id="invoice-footer">
                    <div class="row">

                        

                    
					<table class="table table-striped table-bordered zero-configuration">
							<thead>
								<tr>
									<th>ক্রমিক</th>
									<th>কর্মকর্তা/কর্মচারীর নাম ও পদবি</th>

									<th>আইডি</th>

									<th>ছুটির ধরণ</th>

									<th>কর্তন(দিন)</th>

									<th>নোট </th>

									<th>Submit Date </th>

									<th>সংযুক্তি </th>


									<th>স্টেটাস </th>
									
									
									<th width="80">Action</th>
								</tr>
							</thead>
							<tbody>
							<?php
							$sl = 0;
								while($dataRow=mysqli_fetch_array($getLeaveDeductionHQ))
								{
									$sl = $sl + 1;

									$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$dataRow[employeeID]'");
									$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

									$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
									$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);


									if($dataRow['leaveID'] == 1){

									$approvedLeaveType = "গড় বেতন ";

									}else if($dataRow['leaveID'] == 2){

									$approvedLeaveType = "অর্ধ-গড় বেতন ";

									}else if($dataRow['leaveID'] == 3){

									$approvedLeaveType = "নৈমিত্তিক (Casual Leave)";

									}else if($dataRow['leaveID'] == 4){

									$approvedLeaveType = "অসাধারণ(বিনা বেতনে ছুটি)";

									}else if($dataRow['leaveID'] == 5){

									$approvedLeaveType = "ঐচ্ছিক ছুটি";

									}



							?>
								<tr id="tr_<?php echo $sl; ?>">
									<td><?php echo $sl; ?></td>
									<td><?php echo $getEmployeeDetailsQW['employee_name'].', '.$getDesignationDetailsQRW['job_title_name']; ?></td>

									<td><?php echo $getEmployeeDetailsQW['employee_id']; ?></td>

									<td><?php echo $approvedLeaveType; ?></td>

									<td><?php echo $dataRow['leaveDeduction']; ?></td>

									<td><?php echo $dataRow['note']; ?></td>

									<td><?php echo $dataRow['createDate']; ?></td>

									<td><?php if($dataRow['attachment'] != ''){ ?><a href="uploads/<?php echo $dataRow['attachment']; ?>" target="_blank">View</a><?php } ?></td>

									<td><?php if($dataRow['isApproved'] == 1){ echo "Approved"; }else if($dataRow['isApproved'] == 2){ echo "Cancelled"; }else if($dataRow['isApproved'] == 0){ echo "Pending"; } ?></td>

									
																		
									<td>
									<div class="form-group">
                                    <!-- Simple Icon Button -->
                                   <!-- <button data-toggle="tooltip" data-placement="top" data-original-title="Delete" onClick="removeData(<?php echo $sl; ?>,<?php echo $dataRow['dataID']; ?>)" type="button" class="btn btn-raised btn-icon btn-danger mr-1"><i class="fa fa-trash-o"></i></button> -->
                                    
                                    </div>
									</td>
								</tr>

								<?php
								}
									?>
                    </tbody>
							
						</table>
 

                        
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


<script type="text/javascript">





function removeData(sl,dataID)
{
	

	swal({
  title: "Are you sure to delete this data?",
  text: "",
  icon: "warning",
  buttons: true,
  dangerMode: true,
})
.then((willDelete) => {
  if (willDelete) {
	  
	$.ajax({
            type : 'post',
            url : 'delete_data.php', //Here you will fetch records 
            data :  'dataID='+ dataID+'&tableName=leave_deduction_history', //Pass $id
            success : function(data){
            $("#tr_"+sl).hide(1000);
            }
        });	  
	  
    swal("Deleted!", {
      icon: "success",
    });
	//window.location= 'index.html';
  } else {
    swal("Your data is safe!");
  }
});
	
	

}

</script>