<?php
include('top.php');

$todayTaskDate = ShowBangladeshDate();

$getAllProductCodeQ = mysqli_query($con,"select * from row_materials");

$formDate = ShowBangladeshDate2();

$storeID = 5;

$getStoreDetailsQ = mysqli_query($con, "SELECT * FROM `store_list` where dataID='$storeID'");
$getStoreDetailsQRW = mysqli_fetch_assoc($getStoreDetailsQ);

if(isset($_POST['filter']))
{
						
						if(isset($_POST['itemCode']) && $_POST['itemCode']!='')
						{
							$itemCode = $_POST['itemCode'];

							//echo $itemCode;

							$itemSQL = " and `itemCode`='$itemCode'";
						}
						else
						{
							$itemSQL = "";
						}
                        

						
						$dateF = date_create($_POST['dateFrom']);

						$dateFrom = date_format($dateF,"Y-m-d");


						


						$getDataQuery = mysqli_query($con,"select * from component_stock where storeID='$storeID'");

						//echo "select * from item_arrival_history where 1=1 $itemSQL $dateSQL order by `arrivalDate` asc";
						//echo $_POST['dateTo'];


						


}
else
{

$getDataQuery = mysqli_query($con,"select * from component_stock where storeID='$storeID'");

$dateF = date_create(date('Y-m-d'));

$dateFrom = date_format($dateF,"Y-m-d");

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


				<div class="form-group row">
					<label class="col-sm-1 control-label">Date&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-2">
						<input type="date" class="form-control" id="datepicker2" autocomplete="off" name="dateFrom" placeholder="date from" value="<?php echo $formDate; ?>">
					</div>
					<label class="col-sm-1 control-label">&nbsp;</label>
					<div class="col-sm-2">
						<button type="submit" name="filter" id="filter" class="btn-raised btn-primary btn">Search</button>
					</div>
					
				</div>

								



</form>


<br>

<hr>

                <p style="text-align:right"><i onClick="printData()" style="cursor:help; font-size: 40px;" class="fas fa-print"></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
<hr>

<div id="print_div1" style="padding:0px; margin:0; width:100%;">

<p style="text-align:center;font-size:18px;padding:0px;">
<?php echo $getStoreDetailsQRW['store_name']; ?><br>Stock Report<br>


<label style="font-size:14px;color:#000;line-height: 16px;">Date: <?php $fdate=date_create($_POST['dateFrom']); echo date_format($fdate,"d/m/Y"); ?></label>

</p>



					<table width="100%" align="center" border="1" style="border: 1px solid #4d4f55; border-collapse:collapse;font-size:14px;">
						<thead>
							<tr height="30">
								
								<th style="font-size:14px;width:20px;text-align:center">Sl</th>
								
								<th style="font-size:14px;text-align:center">Code</th>
								<th style="font-size:14px;">&nbsp;&nbsp;Component Name</th>
								<th style="font-size:14px;text-align:center">UOM</th>
								<th width="140" style="text-align:center;font-size:14px;">Quantity</th>
								
								
							</tr>
						</thead>
						<tbody>


						<?php
                        
						
                       
						$sl = 0;
						while($pRow=mysqli_fetch_array($getDataQuery))
							{
                              $sl = $sl + 1;


							  $getComponentInfoQ = mysqli_query($con, "select * from component_list where componentCode='$pRow[componentCode]'");
							  $getComponentInfoQRW = mysqli_fetch_assoc($getComponentInfoQ);

							  // get UOM itemCode

							  
							  $getSizeInfoQ = mysqli_query($con,"select * from packsize_list where sizeID='$getComponentInfoQRW[uom]'");
							  $getSizeInfoQRW = mysqli_fetch_assoc($getSizeInfoQ);

							  


						?>


						<tr>
								
								<td style="font-size:12px;text-align:center">&nbsp;<?php echo $sl; ?></td>
								
								<td style="font-size:12px;text-align:center" width="80">&nbsp;<?php echo $pRow['componentCode']; ?></td>
								<td style="font-size:12px;" width="180">&nbsp;&nbsp;<?php echo $getComponentInfoQRW['componentTitle']; ?></td>
								<td style="font-size:12px;text-align:center" width="80">&nbsp;<?php echo $getSizeInfoQRW['packsize']; ?></td>
								

								<td width="140" style="text-align:right;font-size:12px;"><?php echo $pRow['stockQuantity']; ?>&nbsp;&nbsp;</td>
								
							</tr>


							<?php
							} // end of while

						
						
					
							?>

							
						</tbody>
					</table>

                    <p>&nbsp;</p>
  
                    

					</div>
                        </div>
                    </div> <!-- end col -->
                </div> <!-- end row -->






<?php
include('footer.php');
?>







<script type="text/javascript">


function printData()

{

   var divToPrint=document.getElementById("print_div1");

   newWin= window.open("");

   newWin.document.write(divToPrint.outerHTML);

   newWin.print();

   newWin.close();

}

</script>