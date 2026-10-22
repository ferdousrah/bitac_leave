<?php
include('top.php');

$storeID = 5;

$todayTaskDate = ShowBangladeshDate();

$getAllRefQ = mysqli_query($con,"select distinct ref_no from component_transfer_data where transferTo='$storeID' and isReceived=0");

$formDate = ShowBangladeshDate2();


if(isset($_POST['filter']))
{
						
						
                        $ref_no = $_POST['ref_no'];

						$getDataQuery = mysqli_query($con,"select * from component_transfer_data where transferTo='$storeID' and ref_no='$ref_no' and isReceived=0 order by `transferDate` asc");

						//echo "select * from item_arrival_history where 1=1 $itemSQL $dateSQL order by `arrivalDate` asc";
						//echo $_POST['dateTo'];


						


}



?>







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

<!--  onkeyup="search(this.value)" -->
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" name="form" id="form" method="POST" class="form-horizontal row-border" enctype="multipart/form-data">

<input type="hidden" name="storeID" value="<?php echo $storeID; ?>" />


				<div class="form-group row">
					
					<label class="col-sm-1 control-label">Reference No.&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-2">
						<select name="ref_no" id="ref_no" class="form-control">
                                        <option value="">&nbsp;</option>

										<?php
										while($pcRow=mysqli_fetch_array($getAllRefQ))
										{
										?>
										<option value="<?php echo $pcRow['ref_no']; ?>"><?php echo $pcRow['ref_no']; ?></option>
										<?php
										}
										?>

					   </select>
					</div>
				</div>


				

				<div class="form-group row">
					<label class="col-sm-4 control-label">&nbsp;</label>
					<div class="col-sm-3">
						<button type="submit" name="filter" id="filter" class="btn-raised btn-primary btn">Search</button>
					</div>
				</div>


								



</form>


<br>


<hr>

<div id="print_div1" style="padding:0px; margin:0; width:100%;">

<p style="text-align:center;font-size:18px;padding:0px;">
COMPONENT RECEIVING<br>
<?php
if(isset($_POST['filter']))
{
?>
Reference No. <?php echo $_POST['ref_no']; ?>

</p>



					<table width="100%" align="center" border="1" style="border: 1px solid #4d4f55; border-collapse:collapse;font-size:14px;">
						<thead>
							<tr height="30">
								
								<th style="font-size:14px;width:20px;text-align:center">Sl</th>								
								<th style="font-size:14px;text-align:center">Code</th>
								<th style="font-size:14px;">&nbsp;&nbsp;Component Name</th>
								<th style="font-size:14px;text-align:center">UOM</th>
								<th width="140" style="text-align:center;font-size:14px;">Quantity</th>
								<th width="80" style="font-size:14px;text-align:center">
								
								&nbsp;
								
								</th>
								
								
							</tr>
						</thead>
						<tbody>


						<?php
                        
						
                        if(isset($_POST['filter']))
						{

                        

						
                        
						$sl = 0;
						while($pRow=mysqli_fetch_array($getDataQuery))
							{
                              $sl = $sl + 1;

							   

							  // get UOM itemCode

							  $getItemInfoQ = mysqli_query($con, "select * from component_list where componentCode='$pRow[componentCode]'");
							  $getItemInfoQRW = mysqli_fetch_assoc($getItemInfoQ);

							  $getSizeInfoQ = mysqli_query($con,"select * from packsize_list where sizeID='$getItemInfoQRW[uom]'");
							  $getSizeInfoQRW = mysqli_fetch_assoc($getSizeInfoQ);

							  $odate=date_create($pRow['transferDate']); 
							  
							  $outDate = date_format($odate,"d/m/Y");


						?>


						<tr>
								
								<td style="font-size:12px;text-align:center">&nbsp;<?php echo $sl; ?></td>
								
								<td style="font-size:12px;text-align:center" width="80">&nbsp;<?php echo $pRow['componentCode']; ?></td>
								<td style="font-size:12px;" width="180">&nbsp;&nbsp;<?php echo $getItemInfoQRW['componentTitle']; ?></td>
								<td style="font-size:12px;text-align:center" width="80">&nbsp;<?php echo $getSizeInfoQRW['packsize']; ?></td>
								

								<td width="140" style="text-align:right;font-size:12px;"><?php echo $pRow['quantity']; ?>&nbsp;&nbsp;</td>

								<td style="text-align:center;">
                                <span id="buttonArea_<?php echo $sl; ?>">
								<a class="btn btn-info waves-effect waves-light" onClick="receiveData(<?php echo $sl; ?>,<?php echo $pRow['dataID']; ?>)"><i class="material-icons">receive</i></a>
                                </span> 
								</td>
								
							</tr>


							<?php
							} // end of while

						}
						
					
							?>

							
						</tbody>
					</table>

                    <p>&nbsp;</p>
  
                    <?php
}
						?>


					</div>
                        </div>
                    </div> <!-- end col -->
                </div> <!-- end row -->






<?php
include('footer.php');
?>







<script>
		
		function receiveData(sl,dataID)
{
	


Swal.fire({
  title: 'Are you sure?',
  text: "",
  icon: 'warning',
  showCancelButton: true,
  confirmButtonColor: '#3085d6',
  cancelButtonColor: '#d33',
  confirmButtonText: 'Yes, Receive Confirm!'
}).then((result) => {
  if (result.value) {

    $.ajax({
            type : 'post',
            url : 'receive_component_action.php', //Here you will fetch records 
            data :  'dataID='+ dataID, //Pass $id
            success : function(data){
				alert(data);
            $("#buttonArea_"+sl).html('Received');
            }
        });

    Swal.fire(
      '',
      'Process Done!',
      'success'
    )
		
  }
})
	
	

	
	
		
	
	
}
		
		</script>