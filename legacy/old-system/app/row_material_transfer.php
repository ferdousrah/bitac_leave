<?php
include('header.php');

$getAllProductCodeQ = mysqli_query($con,"select * from row_materials where storeID=1");

$getAllProductCodeQ2 = mysqli_query($con,"select * from row_materials where storeID=1");

$getAllStoreQ = mysqli_query($con, "SELECT * FROM `store_list` where dataID!=1");

$getRawMaterialCategoryQ = mysqli_query($con, "select * from item_category");

$getRawMaterialCategoryQ2 = mysqli_query($con, "select * from item_category");


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
        <h4>Raw material Transfer</h4>
    </div>
	<div class="col-md-2">
        
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">




<form action="item_transfer_action.php" name="form" id="form" class="form-horizontal row-border" method="POST" enctype="multipart/form-data">


                

				<div class="form-group">
					<label class="col-sm-2 control-label">Ref. No.&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-3">
						<input type="text" class="form-control" name="ref_no" required />
					</div>
				</div>


				<div class="form-group">
					<label class="col-sm-2 control-label">Transfer Date&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-3">
						<input type="date" class="form-control" id="datepicker2" autocomplete="off" name="receiveDate" placeholder="" required>
					</div>
				</div>


				<div class="form-group">
					<label class="col-sm-2 control-label">Transfer To&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-3">
						<select class="form-control" name="transferTo" required>

						<option value=""></option>

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
                
                
           	   


           <table id="tbl" border="1" width="100%">
				<thead>
					<tr >

					   <th class="text-center" height="50" width="200">
							CATEGORY
						</th>
					    
						<th class="text-center" height="50">
							MATERIAL NAME
						</th>
						
						
						<th class="text-center" width="120">
							QTY
						</th>
						
						
					</tr>
				</thead>
				<tbody>
					<tr id='addr0'>

					    <td height='40' style="text-align:center">
						

                        <select class="js-example-basic-single" style="width:90%;" onChange="getSubcategories(0,this.value)" name="categoryID[]" id="categoryID_0">
                        <option value=""></option>
						<?php
                        while($pcRow=mysqli_fetch_array($getRawMaterialCategoryQ))
						{
						?>
						  <option value="<?php echo $pcRow['dataID']; ?>"><?php echo htmlentities($pcRow['category_name']); ?></option>
                        <?php
						}
						?>
						</select>

						</td>


					    
						<td height='40' style="text-align:center">
						

                        <select class="js-example-basic-single-0" style="width:90%;" name="productCode[]" id="productCode_0">
                        <option value=""></option>
						
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



<div class="form-actions">
	                            <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Cancel
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> Save
	                            </button>
	                        </div>


</form>



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

$('.js-example-basic-single-0').select2();

//adds extra table rows
var i=$('table tr').length;
$("#add_row").on('click',function(){

	i++;
	
	html = '<tr>';

	html += "<td height='40' style='text-align:center'><select class='js-example-basic-single-"+i+"' style='width:90%;' name='categoryID[]' id='categoryID_"+i+"' onChange='getSubcategories("+i+",this.value)' required><option value=''></option><?php while($cRow2=mysqli_fetch_array($getRawMaterialCategoryQ2)){?><option value='<?php echo $cRow2['dataID']; ?>'><?php echo htmlentities($cRow2['category_name']); ?></option><?php } ?></select></td>";
	
	html += "<td height='40' style='text-align:center'><select class='js-example-basic-single"+i+"' style='width:90%;' name='productCode[]' id='productCode_"+i+"' required><option value=''></option></select></td>";
	//html += "<td style='text-align:center;display:none'><input type='text' name='productName[]' id='productName_"+i+"' placeholder=''  style='width:90%;'  /> </td>";
	
	html += "<td style='text-align:center'><input type='text' name='quantity[]' id='quantity_"+i+"' value='1' class='changesNo' onkeypress='return IsNumeric(event);' ondrop='return false;' onpaste='return false;' placeholder=''  style='width:90%;' required /></td>";
	
	html += '</tr>';
	$('table').append(html);
	
	$('.js-example-basic-single'+i).select2();

	
});


$("#delete_row").click(function(){
	var i=$('table tr').length;
    	 
		 $("#tbl tr:last").remove();

           /*if(i>1){
		 $("#add_row"+(i-1)).html('');
		 i--;
		 }*/


	 });



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
   } 
   });

}






//price change


    </script>



