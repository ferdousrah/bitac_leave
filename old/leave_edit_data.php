<?php
include('header.php');
include('library/number_converter.php');

$leaveApplicationID = $_GET['leaveApplicationID'];


$getLeaveApplicationsQ = mysqli_query($con,"select * from leave_edit_data where leaveApplicationID='$leaveApplicationID'");


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
        <h4>ছুটি সংশোধন</h4>
    </div>
	<div class="col-md-2">
        <button onClick="window.location='new_leave_edit_form?menuslug=allowed-leave-applications&leaveApplicationID=<?php echo $leaveApplicationID; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button>
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
								  <th rowspan="2">ক্রমিক</th>
								  <th colspan="3">সংশোধিত ছুটি</th>
								  <th rowspan="2">ডিডাক্ট ফ্রম</th>
								  <th rowspan="2">অফিস আদেশ(সংযুক্তি)</th>
								  <th rowspan="2">সাবমিশন ডেট-টাইম</th>
								  <th rowspan="2" width="80">স্টেটাস</th>

								</tr>
								<tr>
								  <th>হইতে</th>
								  <th>পর্যন্ত</th>
								  <th>দিন</th>
								</tr>
							</thead>
							<tbody>

							<?php 
							$sl = 0;	
							while($lRow = mysqli_fetch_array($getLeaveApplicationsQ)){ 
								$sl = $sl + 1;

								if($lRow['approvedLeaveType'] == 1){
								
									$approvedLType = "গড় বেতন";
								
								}else if($lRow['approvedLeaveType'] == 2){
								
									$approvedLType = "অর্ধ-গড় বেতন";
								
								}else if($lRow['approvedLeaveType'] == 3){
								
									$approvedLType = "নৈমিত্তিক (Casual Leave)";
								
								}else if($lRow['approvedLeaveType'] == 4){
								
									$approvedLType = "বিনা বেতনে ছুটি";
								
								}else if($lRow['approvedLeaveType'] == 5){

									$approvedLType = "ঐচ্ছিক ছুটি";

								}else if($lRow['approvedLeaveType'] == 6){

									$approvedLType = "কর্তনহীন ছুটি ";

								}else if($lRow['approvedLeaveType'] == 10){

									$approvedLType = "অসাধারণ ছুটি";

								}


								
								$getApplicationTypeQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$lRow[dataID]'");
								$getApplicationTypeQRW = mysqli_fetch_assoc($getApplicationTypeQ);

								$getLeaveJoiningApplicationQNumRows = mysqli_num_rows($getApplicationTypeQ);

								// চাহিত ছুটি 

								$leaveApplicationDateF=date_create($lRow['approvedFrom']);
								//echo date_format($dateF,"d/m/Y");
								$leaveApplicationDateT=date_create($lRow['approvedTo']);

								$totalReqDays = dateDiffInDays($lRow['approvedFrom'], $lRow['approvedTo']) + 1;


								


																				

							?>


							<tr>
									<td><?php echo $sl; ?></td>

									<td><?php echo banglaNumber(date_format($leaveApplicationDateF,"d/m/Y")); ?></td>

									<td><?php echo banglaNumber(date_format($leaveApplicationDateT,"d/m/Y")); ?></td>

									<td><?php echo banglaNumber($totalReqDays); ?></td>

									<td>
										
										<?php echo $approvedLType; ?>

									</td>

									<td>
										
										<a href="uploads/<?php echo $lRow['attachment']; ?>" target="_blank">View</a>

									</td>


									<td>
										
										<?php echo $lRow['submitDateTime']; ?>

									</td>

									<td>
										
										<?php if($lRow['isApproved'] == 1){ echo "Approved"; }else if($lRow['isApproved'] == 0){ echo "Pending"; }else{ echo "Not Approved"; } ?>

									</td>



									

								</tr>


							<?php } ?>

							
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
            data :  'dataID='+ dataID+'&tableName=modules', //Pass $id
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