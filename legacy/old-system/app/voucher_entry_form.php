<?php
include('header.php');

$getAllAccountQ = mysqli_query($con,"select * from accounts_list");

$getAllAccountQ1 = mysqli_query($con,"select * from accounts_list");

$getAllAccountQ2 = mysqli_query($con,"select * from accounts_list");


$getLastVoucherQ = mysqli_query($con, "select * from voucher_list order by dataID desc LIMIT 0,1");
$getLastVoucherQRW = mysqli_fetch_assoc($getLastVoucherQ);

$lastID = $getLastVoucherQRW['dataID'] + 1;

$formattedID = date('Y').date('m').str_pad($lastID, 6, '0', STR_PAD_LEFT);



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
        <h4>Voucher Entry</h4>
    </div>
	<div class="col-md-2">
        <!-- <button onClick="window.location='add_new_account?menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                
					

				<form class="form-login" name="form" id="form">


                

				<div class="form-group" style="margin-left: 0px;padding-left: 0px;">
					<label class="col-sm-5 control-label">Voucher No.&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-3">
						<input type="text" class="form-control" name="ref_no" value="<?php echo $formattedID; ?>" required />
					</div>
				</div>


				<div class="form-group" style="margin-left: 0px;padding-left: 0px;">
					<label class="col-sm-5 control-label">Voucher Date&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-3">
						<input type="date" class="form-control" name="voucher_date" required />
					</div>
				</div>

				<div class="form-group" style="margin-left: 0px;padding-left: 0px;">
					<label class="col-sm-5 control-label">Transaction Mode&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-3">
						<select class="form-control" name="transaction_mode" required>
							<option value='cash'>Cash</option>
							<option value='bank'>Bank A/C</option>
						</select>
					</div>
				</div>

				<!--

				<div class="form-group">
					<label class="col-sm-5 control-label">Receive Date&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-3">
						<input type="date" class="form-control" id="datepicker2" required autocomplete="off" name="receiveDate" placeholder="">
					</div>
				</div>
                
                
           	   -->


           <table id="tbl" border="1" width="100%">
				<thead>
					<tr >
                        <th class="text-center" height="50" width="20">
							Sl
						</th>    
						
						<th class="text-center" height="50" width="150">
							Account Head
						</th>

						<th class="text-center" height="50" width="150">
							Description
						</th>											
						
						<th class="text-center" width="80">
							Debit
						</th>

						<th class="text-center" width="80">
							Credit
						</th>
						
						
					</tr>
				</thead>
				<tbody>
					<tr id='addr0'>

						<td height='40' style="text-align:center">
						 1				
						</td>

						
					<td height='40' style="text-align:center">
						

                        <select class="js-example-basic-single-0" style="width:90%;" name="account[]" id="account_0" required>
							<option value=""></option>
							<?php
								while($acRow = mysqli_fetch_array($getAllAccountQ)){
							?>
								<option value="<?php echo $acRow['dataID']; ?>"><?php echo $acRow['account_number'].'-'.$acRow['account_name']; ?></option>
							<?php } ?>
						</select>

						</td>
					    
						<td height='40' style="text-align:center">
						

                        <textarea name="description[]" class="form-control" style="border-radius: 0px;padding: 0px;margin: 0px;border: 0px;"></textarea>

						</td>


											
						
						<td style="text-align:center">
						<input type="text" name='debit[]' id="debit_0" class="changesNo" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" placeholder=''  style="width:90%;" value="0" required />
						</td>

						<td style="text-align:center">
						<input type="text" name='credit[]' id="credit_0" class="changesNo" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" placeholder=''  style="width:90%;" value="0" required />
						</td>
						
						
					</tr>

					<tr id='addr1'>

						<td height='40' style="text-align:center">
						 2				
						</td>

						
					<td height='40' style="text-align:center">
						

                        <select class="js-example-basic-single-1" style="width:90%;" name="account[]" id="account_1" required>
							<option value=""></option>
							<?php
								while($acRow = mysqli_fetch_array($getAllAccountQ1)){
							?>
								<option value="<?php echo $acRow['dataID']; ?>"><?php echo $acRow['account_number'].'-'.$acRow['account_name']; ?></option>
							<?php } ?>
						</select>

						</td>
					    
						<td height='40' style="text-align:center">
						

                        <textarea name="description[]" class="form-control" style="border-radius: 0px;padding: 0px;margin: 0px;border: 0px;"></textarea>

						</td>


											
						
						<td style="text-align:center">
						<input type="text" name='debit[]' id="debit_1" class="changesNo" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" placeholder=''  style="width:90%;" value="0" required />
						</td>

						<td style="text-align:center">
						<input type="text" name='credit[]' id="credit_1" class="changesNo" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" placeholder=''  style="width:90%;" value="0" required />
						</td>
						
						
					</tr>

                    <tr id='addr2'></tr>
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


		<script>

		

		$('.js-example-basic-single').select2();

		$('#account_0').select2();

		$('#account_1').select2();








		$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_voucher_data.php', // form action url
			type: 'POST', // form submit method get/post
			dataType: 'html', // request type html/json/xml
			data: new FormData(this), // serialize form data
			contentType: false,
            cache: false,
            processData:false,
			beforeSend: function() {
				
				submit.html('<i class="fa fa-spinner fa-spin"></i> Signing in, please wait'); // change submit button text
				setTimeout(200000000000000000);


			},
			success: function(data) {

				//alert(data);
				

                if(data==1)
				{
				
				    toastr.success('Data Saved Successfully');


				    

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else
				{
				
				    toastr.error('Error!!');
				
				
				}




				//form.trigger('reset'); // reset form
				submit.html('Login Now'); // reset submit button text
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});

		


	      
//adds extra table rows

/*
var i=$('table tr').length;
$("#add_row").on('click',function(){

	i++;
	
	html = '<tr>';

	html += "<td style='text-align:center;'>"+i+"</td>";

	html += "<td style='text-align:center;'><input type='text' name='voucher_no[]' id='voucher_no_"+i+"' placeholder=''  style='width:90%;'  /> </td>";

	html += "<td style='text-align:center;'><input type='date' name='voucher_date[]' id='voucher_date_"+i+"' placeholder=''  style='width:90%;'  /> </td>";

	html += "<td height='40' style='text-align:center'><select class='js-example-basic-single"+i+"' style='width:90%;' name='acGroupID[]' id='acGroupID_"+i+"' onChange='getAccounts("+i+",this.value)' required><option value=''></option><?php while($cRow2=mysqli_fetch_array($getAllAccountGroup2)){?><option value='<?php echo $cRow2['dataID']; ?>'><?php echo htmlentities($cRow2['group_title']); ?></option><?php } ?></select></td>";
	
	html += "<td height='40' style='text-align:center'><select class='js-example-basic-single-"+i+"' style='width:90%;' name='account[]' id='account_"+i+"' required><option value=''></option></select></td>";
	
	html += "<td style='text-align:center;'><textarea name='description[]' id='description_"+i+"' placeholder=''  class='form-control' style='border-radius: 0px;padding: 0px;margin: 0px;border: 0px;'></textarea> </td>";

	html += "<td style='text-align:center;'><input type='text' name='debit[]' id='debit_"+i+"' placeholder=''  style='width:90%;' value='0'  /> </td>";
	
	html += "<td style='text-align:center'><input type='text' name='credit[]' id='credit_"+i+"' value='0' class='changesNo' onkeypress='return IsNumeric(event);' ondrop='return false;' onpaste='return false;' placeholder=''  style='width:90%;' required /></td>";
	
	html += '</tr>';
	$('table').append(html);
	
	$('.js-example-basic-single-'+i).select2();

	$('.js-example-basic-single'+i).select2();

	
});


$("#delete_row").click(function(){
	var i=$('table tr').length;
    	 
		 $("#tbl tr:last").remove();

           


	 });
*/



	 function getAccounts(sl, groupID)
{

	var dataString = 'groupID='+ groupID;

 //alert(dataString);
 
  $.ajax
  ({
   type: "POST",
   url: "get_accounts_by_group.php",
   data: dataString,
   cache: false,
   success: function(html)
   {
      $("#account_"+sl).html(html);
   } 
   });

}
		
		
		
		</script>