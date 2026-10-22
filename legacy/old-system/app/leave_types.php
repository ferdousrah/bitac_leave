<?php
include('header.php');

$getLeaveTypesQ = mysqli_query($con,"select * from leave_types");


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
        <h4>ছুটির প্রকারভেদ </h4>
    </div>
	<div class="col-md-2">
        <button onClick="window.location='new_leave_type_form?slug=manage-module&menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button>
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
									<th>SL</th>
									<th>Leave Type</th>
									
									
									<th width="80">Action</th>
								</tr>
							</thead>
							<tbody>
							<?php
							$sl = 0;
								while($dataRow=mysqli_fetch_array($getLeaveTypesQ))
								{
									$sl = $sl + 1;
							?>
								<tr id="tr_<?php echo $sl; ?>">
									<td><?php echo $sl; ?></td>
									<td><?php echo $dataRow['leaveTitle']; ?></td>
																		
									<td>
									<div class="form-group">
                                    <!-- Simple Icon Button -->
                                    <button data-toggle="tooltip" data-placement="top" data-original-title="Edit" onclick="window.location='edit_leave_type_form?leaveID=<?php echo $dataRow['leaveID']; ?>&menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-edit"></i></button>
                                    <button data-toggle="tooltip" data-placement="top" data-original-title="Delete" onClick="removeData(<?php echo $sl; ?>,<?php echo $dataRow['leaveID']; ?>)" type="button" class="btn btn-raised btn-icon btn-danger mr-1"><i class="fa fa-trash-o"></i></button>
                                    
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
            url : 'delete_leave_type.php', //Here you will fetch records 
            data :  'dataID='+ dataID, //Pass $id
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