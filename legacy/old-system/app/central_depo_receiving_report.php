<?php
include('header.php');

$todayTaskDate = ShowBangladeshDate();

$getAllProductCodeQ = mysqli_query($con,"select * from row_materials");

$getAllStoreQ = mysqli_query($con, "SELECT * FROM `store_list` where dataID!=1");

$getAllRawMaterialGroupQ = mysqli_query($con, "select * from item_category");

$formDate = ShowBangladeshDate2();


if(isset($_POST['filter']))
{
						
						if(isset($_POST['categoryID']) && $_POST['categoryID']!=0)
						{
                          $catID = $_POST['categoryID'];  
						  $catSQL = " and groupID='$catID'";
						}
						else
						{
							$catSQL = "";
						}


						if(isset($_POST['productCode']) && $_POST['productCode']!=0)
						{
                          $itemCode = $_POST['productCode'];  
						  $itemSQL = " and itemCode='$itemCode'";
						}
						else
						{
							$itemSQL = "";
						}
                        


						if(isset($_POST['transferTo']) && $_POST['transferTo']!='')
						{
							$transferTo = $_POST['transferTo'];

							//echo $itemCode;

							$transferSQL = " and `storeTo`='$transferTo'";
						}
						else
						{
							$transferSQL = "";
						}
                        

						
						$dateF = date_create($_POST['dateFrom']);

						$dateFrom = date_format($dateF,"Y-m-d");


						$dateT = date_create($_POST['dateTo']);

						$dateTo = date_format($dateT,"Y-m-d");

						$dateSQL = " and (`arrivalDate` between '$dateFrom' and '$dateTo')";



						$getDataQuery = mysqli_query($con,"select * from item_arrival_history where 1=1 $catSQL $itemSQL and storeTo=1 $dateSQL order by `arrivalDate` asc");

						//echo "select * from item_arrival_history where 1=1 $itemSQL $dateSQL order by `arrivalDate` asc";
						//echo $_POST['dateTo'];


						


}



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
        <h4>Receiving Report</h4>
    </div>
	<div class="col-md-2">
        
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">

<!--  onkeyup="search(this.value)" -->
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" name="form" id="form" method="POST" class="form-horizontal row-border" enctype="multipart/form-data">

<input type="hidden" name="menuslug" value="<?php echo $menuSlug; ?>" />

				<div class="form-group row">

				    <div class="col-sm-4">

					<select class="form-control categoryID" name="categoryID" id="categoryID_0" onChange="getSubcategories(0,this.value)" required>
					<option value="0">-Category-</option>

					<?php
                     while($gRow=mysqli_fetch_array($getAllRawMaterialGroupQ))
					 {
					?>
					<option value="<?php echo $gRow['dataID']; ?>"><?php echo $gRow['category_name']; ?></option>
                    <?php
					 }
						?>

					</select>
						
					</div>

					<div class="col-sm-4">

					<select class="form-control productCode js-example-basic-single" name="productCode" id="productCode_0" required>
					<option value="0">-Select Material-</option>

					</select>

					</div>

					<div class="col-sm-4">
						<select class="form-control" name="transferTo">

						<option value="">-Receive From-</option>

						<?php
                          while($sRow=mysqli_fetch_array($getAllStoreQ))
						  {
						?>

							<option value="<?php echo $sRow['dataID']; ?>"><?php echo $sRow['store_name']; ?></option>

							<?php
						  }
								?>
						
						</select>
					</div>

					</div>


					<div class="form-group row">
					
					<div class="col-sm-4">
						<input type="date" class="form-control" id="datepicker2" autocomplete="off" name="dateFrom" placeholder="date from" value="">
					</div>
					
					<div class="col-sm-4">
						<input type="date" class="form-control" id="datepicker2" autocomplete="off" name="dateTo" placeholder="" value="">
					</div>

					<div class="col-sm-4">

					<button type="submit" name="filter" id="filter" class="btn-raised btn-primary btn">Generate Report</button>

					</div>
					
					
				</div>


				

									



</form>


<br>

<hr>

                <p style="text-align:right"><i onClick="printData()" style="cursor:help; font-size: 40px;" class="fa fa-print"></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
<hr>

<div id="print_div1" style="padding:0px; margin:0; width:100%;">

<p style="text-align:center;font-size:18px;padding:0px;">
RECEIVING REPORT<br>
CENTRAL STORE<br>
<?php
if(isset($_POST['filter']))
{
?>
<label style="font-size:14px;color:#000;line-height: 16px;">Item : 
<?php 
if(isset($_POST['productCode']) && $_POST['productCode']!=0)
{
	$getItemInfoQ = mysqli_query($con, "select * from row_materials where itemCode='$_POST[productCode]'");
	$getItemInfoQRW = mysqli_fetch_assoc($getItemInfoQ);
    echo $getItemInfoQRW['material_name']; 
}
else
{
echo "All";
}
?>
</label><br>
<label style="font-size:14px;color:#000;line-height: 16px;">Date: <?php $fdate=date_create($_POST['dateFrom']); echo date_format($fdate,"d/m/Y"); ?> - <?php $tdate=date_create($_POST['dateTo']); echo date_format($tdate,"d/m/Y"); ?></label>

</p>



					<table width="100%" align="center" border="1" style="border: 1px solid #4d4f55; border-collapse:collapse;font-size:14px;">
						<thead>
							<tr height="30">
								
								<th style="font-size:14px;width:20px;text-align:center">Sl</th>
								<th style="font-size:14px;text-align:center">Date</th>
								<th style="font-size:14px;text-align:center">Code</th>
								<th style="font-size:14px;">&nbsp;&nbsp;Material Name</th>
								<th style="font-size:14px;text-align:center">UOM</th>
								<th width="140" style="text-align:center;font-size:14px;">Quantity</th>
								
								
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

							  $getItemInfoQ = mysqli_query($con, "select * from row_materials where itemCode='$pRow[itemCode]'");
							  $getItemInfoQRW = mysqli_fetch_assoc($getItemInfoQ);

							  $getSizeInfoQ = mysqli_query($con,"select * from packsize_list where sizeID='$getItemInfoQRW[uom]'");
							  $getSizeInfoQRW = mysqli_fetch_assoc($getSizeInfoQ);

							  $odate=date_create($pRow['arrivalDate']); 
							  
							  $outDate = date_format($odate,"d/m/Y");


						?>


						<tr>
								
								<td style="font-size:12px;text-align:center">&nbsp;<?php echo $sl; ?></td>
								<td style="font-size:12px;text-align:center" width="80">&nbsp;<?php echo $outDate; ?></td>
								<td style="font-size:12px;text-align:center" width="80">&nbsp;<?php echo $pRow['itemCode']; ?></td>
								<td style="font-size:12px;" width="180">&nbsp;&nbsp;<?php echo $getItemInfoQRW['material_name']; ?></td>
								<td style="font-size:12px;text-align:center" width="80">&nbsp;<?php echo $getSizeInfoQRW['packsize']; ?></td>
								

								<td width="140" style="text-align:right;font-size:12px;"><?php echo $pRow['quantity']; ?>&nbsp;&nbsp;</td>
								
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

$('body').on('focus',"#datepicker2", function(){
        $(this).datepicker({todayHighlight: true});
    });

function printData()

{

   var divToPrint=document.getElementById("print_div1");

   newWin= window.open("");

   newWin.document.write(divToPrint.outerHTML);

   newWin.print();

   newWin.close();

}


	function getSubcategories(sl,categoryID)
{

	var dataString = 'categoryID='+ categoryID;

 
 
  $.ajax
  ({
   type: "POST",
   url: "get_subcategories.php",
   data: dataString,
   cache: false,
   success: function(html)
   {
      $("#productCode_"+sl).html(html);
	  $('.js-example-basic-single').select2();
   } 
   });

}

</script>