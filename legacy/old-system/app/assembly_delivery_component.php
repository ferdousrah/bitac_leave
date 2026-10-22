<?php
include('top.php');

$storeID = 6;

$getAllProductCodeQ = mysqli_query($con,"select component_stock.componentCode,component_stock.stockQuantity,component_list.componentTitle,component_list.componentCode from component_stock inner join component_list on component_stock.componentCode=component_list.componentCode where component_stock.storeID='$storeID'");

$getAllProductCodeQ2 = mysqli_query($con,"select component_stock.componentCode,component_stock.stockQuantity,component_list.componentTitle,component_list.componentCode from component_stock inner join component_list on component_stock.componentCode=component_list.componentCode where component_stock.storeID='$storeID'");

$getAllStoreQ = mysqli_query($con, "SELECT * FROM `store_list` where dataID!=1 and dataID!=2 and dataID!=3 and dataID!=4  and dataID!=5");



$getLastTransferIDQ = mysqli_query($con,"select distinct ref_no from component_transfer_data");
$totalTrData = mysqli_num_rows($getLastTransferIDQ);

$newTRID = "6000"+($totalTrData + 1);

$newTRID = "TR-".$newTRID;

?>



    
<link href="assets/plugins/form-select2/select2.css" type="text/css" rel="stylesheet">                        <!-- Select2 -->
<link href="assets/plugins/form-fseditor/fseditor.css" type="text/css" rel="stylesheet">                      <!-- FullScreen Editor -->
<link href="assets/plugins/bootstrap-tokenfield/css/bootstrap-tokenfield.css" type="text/css" rel="stylesheet">   <!-- Tokenfield -->

<link href="assets/plugins/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.min.css" type="text/css" rel="stylesheet"> <!-- Touchspin -->

<link href="assets/plugins/card/lib/css/card.css" type="text/css" rel="stylesheet"> 	

<style type="text/css">

.btn {
	border-radius: 40px;
  box-sizing: border-box;
	
}



</style>






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

<form action="component_transfer_from_assembly_action.php" name="form" id="form" class="form-horizontal row-border" method="POST" enctype="multipart/form-data">

<input type="hidden" name="storeID" value="<?php echo $storeID; ?>" />


                

				<div class="form-group">
					<label class="col-sm-2 control-label">Ref. No.&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-3">
						<input type="text" class="form-control" name="ref_no" value="<?php echo $newTRID; ?>" required />
					</div>
				</div>


				<div class="form-group">
					<label class="col-sm-2 control-label">Consumption Date&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-3">
						<input type="date" class="form-control" id="datepicker2" autocomplete="off" name="transferDate" placeholder="" required>
					</div>
				</div>


				                
                
           	   


           <table id="tbl" border="1" width="100%">
				<thead>
					<tr >

					    
						<th class="text-center" height="50">
							COMPONENT NAME
						</th>
						
						
						<th class="text-center">
							QTY
						</th>
						
						
					</tr>
				</thead>
				<tbody>
					<tr id='addr0'>
					    
						<td height='40' style="text-align:center">
						

                        <select class="js-example-basic-single" style="width:90%;" name="productCode[]" id="productCode_0">
                        <option value=""></option>
						<?php
                        while($pcRow=mysqli_fetch_array($getAllProductCodeQ))
						{
						?>
						  <option value="<?php echo $pcRow['componentCode']; ?>"><?php echo $pcRow['componentCode']." - ".htmlentities($pcRow['componentTitle']); ?></option>
                        <?php
						}
						?>
						</select>

						</td>
						
						
						<td style="text-align:center">
						<input type="text" name='quantity[]' id="quantity_0" class="changesNo" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" placeholder=''  style="width:90%;" value="1" required />
						</td>
						
						
					</tr>
                    <tr id='addr1'></tr>
				</tbody>
			</table>

<p>&nbsp;</p>
<div class="row">
<div class="col-md-4">
&nbsp;
</div>
<div class="col-md-6">
&nbsp;</div>

<div class="col-md-2">
<a id="add_row" class="btn btn-success  btn-fab"><i class="material-icons">add</i></a>&nbsp;&nbsp;&nbsp;&nbsp;<a id='delete_row' class="btn btn-danger  btn-fab"><i class="material-icons">clear</i></a>
</div>
</div>



<div class="row">
<div class="col-md-4">
&nbsp;
</div>
<div class="col-md-4">
<button type="submit" class="form-control btn btn-success btn-raised">Submit</button>
</div>
<div class="col-md-4">
&nbsp;
</div>
</div>


</form>



</div>
                        </div>
                    </div> <!-- end col -->
                </div> <!-- end row -->

<?php
include('footer.php');
?>

<script type="text/javascript">

//adds extra table rows
var i=$('table tr').length;
$("#add_row").on('click',function(){
	
	html = '<tr>';
	
	html += "<td height='40' style='text-align:center'><select class='js-example-basic-single"+i+"' style='width:90%;' name='productCode[]' id='productCode_"+i+"' required><option value=''></option><?php while($uRow2=mysqli_fetch_array($getAllProductCodeQ2)){?><option value='<?php echo $uRow2['componentCode']; ?>'><?php echo $uRow2['componentCode'].' - '.htmlentities($uRow2['componentTitle']); ?></option><?php } ?></select></td>";
	//html += "<td style='text-align:center;display:none'><input type='text' name='productName[]' id='productName_"+i+"' placeholder=''  style='width:90%;'  /> </td>";
	
	html += "<td style='text-align:center'><input type='text' name='quantity[]' id='quantity_"+i+"' value='1' class='changesNo' onkeypress='return IsNumeric(event);' ondrop='return false;' onpaste='return false;' placeholder=''  style='width:90%;' required /></td>";
	
	html += '</tr>';
	$('table').append(html);
	
	$('.js-example-basic-single'+i).select2();

	i++;
});


$("#delete_row").click(function(){
	var i=$('table tr').length;
    	 
		 $("#tbl tr:last").remove();

           /*if(i>1){
		 $("#add_row"+(i-1)).html('');
		 i--;
		 }*/


	 });









//price change


    </script>



