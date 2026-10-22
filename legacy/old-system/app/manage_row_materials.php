<?php
include('top.php');

$getAllOrgList = mysqli_query($con,"select * from `user_list`");


?>


<!-- DataTables -->
        <link href="plugins/datatables/dataTables.bootstrap4.min.css" rel="stylesheet" type="text/css" />
        <link href="plugins/datatables/buttons.bootstrap4.min.css" rel="stylesheet" type="text/css" />

        <!-- Responsive datatable examples -->
        <link href="plugins/datatables/responsive.bootstrap4.min.css" rel="stylesheet" type="text/css" />


<!-- Page-Title -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="page-title m-0"><?php echo $pageTitle; ?></h4>
                                </div>
                                <div class="col-md-4">
                                    <div class="float-right d-none d-md-block">
                                        <div class="dropdown">
                                            <button onClick="window.location='add_new_user_form?slug=manage-user'" class="btn btn-primary" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="fas fa-plus"></i> New User
                                            </button>
                                            
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
                <!-- end page title end breadcrumb -->




                <div class="row">
                    <div class="col-12">
                        <div class="card m-b-30">
                            <div class="card-body">

                                

                                <table id="datatable" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                    <thead>
                                    <tr>
                                        <th width="4%">SL</th>
										<th width="10%">&nbsp;</th>
										<th width="20%">Full Name</th>
										<th width="10%">Designation</th>
										<th width="10%">Email</th>
										
										<th width="10%">Username</th>
										<th width="10%">User Type</th>

										<th width="10%">Action</th>
                                    </tr>
                                    </thead>


                                    <tbody>

                                    <?php
										$sl = 0;
										while($orgRow=mysqli_fetch_array($getAllOrgList))
										{
											$sl = $sl + 1;
											
																																	
										?>
                                        <tr id="tr_<?php echo $sl; ?>">
				                            <td><?php echo $sl; ?></td>
				                            <td><img src="uploads/<?php echo $orgRow['photo']; ?>" width="80" /></td>
                                            <td><?php echo $orgRow['full_name']; ?></td>
                                            
                                            <td><?php echo $orgRow['designation']; ?></td>
                                            
                                            <td><?php echo $orgRow['email']; ?></td>
                                            
                                                                                        
                                            <td><?php echo $orgRow['user_id']; ?></td>

											<td>
											<?php
											if($orgRow['user_type']==1)
											{
											echo "Admin"; 
											}
											else
											{
											echo "General User";
											}
											?>
											</td>

				                            
                                            <td><a class="btn btn-warning waves-effect waves-light" href="edit_user_form?id=<?php echo $orgRow['id']; ?>&slug=manage-user" id="custId" data-toggle="modal" data-id="<?php echo $orgRow['id']; ?>" title="Edit"><i class="material-icons">edit</i></a>&nbsp;&nbsp;&nbsp;<a class="btn btn-danger waves-effect waves-light" href="#" title="Delete" onClick="removeData(<?php echo $sl; ?>,<?php echo $orgRow['id']; ?>)"><i class="material-icons">delete</i></a>&nbsp;&nbsp;&nbsp;<a class="btn btn-info waves-effect waves-light" href="fetch_user_access_record?slug=manage-user&rowid=<?php echo $orgRow['id']; ?>"><i class="material-icons">access</i></a></td>
				                        </tr>
				                        
                                        <?php
										}
										?>
                                    
                                    </tbody>
                                </table>

                            </div>
                        </div>
                    </div> <!-- end col -->
                </div> <!-- end row -->









<?php
include('footer.php');
?>


<!-- Required datatable js -->
        <script src="plugins/datatables/jquery.dataTables.min.js"></script>
        <script src="plugins/datatables/dataTables.bootstrap4.min.js"></script>
        <!-- Buttons examples -->
        <script src="plugins/datatables/dataTables.buttons.min.js"></script>
        <script src="plugins/datatables/buttons.bootstrap4.min.js"></script>
        <script src="plugins/datatables/jszip.min.js"></script>
        <script src="plugins/datatables/pdfmake.min.js"></script>
        <script src="plugins/datatables/vfs_fonts.js"></script>
        <script src="plugins/datatables/buttons.html5.min.js"></script>
        <script src="plugins/datatables/buttons.print.min.js"></script>
        <script src="plugins/datatables/buttons.colVis.min.js"></script>
        <!-- Responsive examples -->
        <script src="plugins/datatables/dataTables.responsive.min.js"></script>
        <script src="plugins/datatables/responsive.bootstrap4.min.js"></script>

        <!-- Datatable init js -->
        <script src="assets/pages/datatables.init.js"></script>









		<script>
		
		function removeData(sl,dataID)
{
	


Swal.fire({
  title: 'Are you sure?',
  text: "You won't be able to revert this!",
  icon: 'warning',
  showCancelButton: true,
  confirmButtonColor: '#3085d6',
  cancelButtonColor: '#d33',
  confirmButtonText: 'Yes, Delete!'
}).then((result) => {
  if (result.value) {

    $.ajax({
            type : 'post',
            url : 'delete_user.php', //Here you will fetch records 
            data :  'dataID='+ dataID, //Pass $id
            success : function(data){
            $("#tr_"+sl).hide(1000);
            }
        });

    Swal.fire(
      '',
      'Deleted!',
      'success'
    )
		
  }
})
	
	

	
	
		
	
	
}
		
		</script>