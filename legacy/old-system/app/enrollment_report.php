<?php
include('header.php');

$getAllDepotList = mysqli_query($con,"select * from `money_receipt`");


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
        <h4>All Transactions</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='add_new_instructor_form?menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                
					



							                               

                                <table class="table table-striped table-bordered scroll-vertical">
                                    <thead>
                                    <tr>
                                        <th width="4%">SL</th>

										<th width="20%">Date</th>

										<th width="20%">Receipt No.</th>

										<th width="20%">From</th>

										<th width="20%">Amount</th>

										<th width="20%">On Account Of</th>
																		
										<th width="18%">Action</th>
                                    </tr>
                                    </thead>


                                    <tbody>

                                    <?php
										$sl = 0;
										while($orgRow=mysqli_fetch_array($getAllDepotList))
										{
											$sl = $sl + 1;

											
											
																																	
										?>
                                        <tr id="tr_<?php echo $sl; ?>">

				                            <td><?php echo $sl; ?></td>		
											
											<td><?php echo $orgRow['date']; ?></td>
											
											<td><?php echo $orgRow['receipt_no']; ?></td>

											<td><?php echo $orgRow['received_from']; ?></td>
											
											<td><?php echo $orgRow['received_amount']; ?></td>

											<td><?php echo $orgRow['on_account_of']; ?></td>

											

                                            <td>


											<div class="form-group">
											<!-- Simple Icon Button -->
											<button data-toggle="tooltip" data-placement="top" data-original-title="View Details" onclick="window.location='print_receipt?receiptno=<?php echo $orgRow['receipt_no']; ?>&menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-file"></i></button>
																						
											
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
            data :  'dataID='+ dataID +'&tableName=students', //Pass $id
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