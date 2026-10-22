<?php
include('header.php');

$getAllFinYearQ = mysqli_query($con,"select * from financial_year");

$getAllFinYearQ2 = mysqli_query($con,"select * from financial_year");

?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>New Tax Payer Entry</h4>
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                <!-- Invoice Footer -->
                <div id="invoice-footer">
                    <div class="row">
                        <div class="col-md-12 col-sm-12">
                            




                        <div class="px-3">
	                    <form class="form form-horizontal" action="insert_tax_payer_info" method="post">
	                    	<div class="form-body">
	                    		<h4 class="form-section"><i class="ft-user"></i> Personal Info</h4>
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">Tax Payer's Name: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="projectinput1" class="form-control"  name="name">
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">E-TIN: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="etin" class="form-control"  name="etin">
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">TIN Issue Date: </label>
		                            <div class="col-md-9">
		                            	<div class="position-relative has-icon-left">
										<input type="date" id="etin_issue_date" class="form-control" name="etin_issue_date">
										<div class="form-control-position">
											<i class="ft-message-square"></i>
										</div>
									</div>
		                            </div>
		                        </div>
		                        <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput2">Address: </label>
									<div class="col-md-9">
	                            		<input type="text" id="projectinput2" class="form-control"  name="address">
	                            	</div>
		                        </div>

		                        <div class="form-group row">
		                            <label class="col-md-3 label-control" for="projectinput3">E-mail: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="projectinput3" class="form-control"  name="email">
		                            </div>
		                        </div>

		                        <div class="form-group row">
		                            <label class="col-md-3 label-control" for="projectinput4">Phone: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="projectinput4" class="form-control"  name="phone">
		                            </div>
		                        </div>

								<h4 class="form-section"><i class="ft-file-text"></i> Tax Demand</h4>

		                        <table id="myTable" class=" table order-list">
									
									<tbody>
										<tr>
											<td class="col-sm-2">
												<select id="financial_year" name="financial_year[]" class="form-control" style="width:200px;">
												   <optgroup>
													<option value="0" selected="" disabled="">Financial Year</option>
													<?php
													  while($finRow=mysqli_fetch_array($getAllFinYearQ))
													  {
													?>
													<option value="<?php echo $finRow['financial_year']; ?>"><?php echo $finRow['financial_year']; ?></option>
													<?php
													  }
														?>
													</optgroup>
												</select>
											</td>
											<td class="col-sm-3">
												<input type="text" id="income" class="form-control"  name="income[]" placeholder='Income' style="width:200px;">
											</td>
											<td class="col-sm-3">
												<input type="text" id="wealth" class="form-control"  name="wealth[]" placeholder='Wealth' style="width:200px;">
											</td>
											<td class="col-sm-3">
												<input type="text" id="tax_paid" class="form-control"  name="tax_paid[]" placeholder='Tax Demand' style="width:200px;">
											</td>
											<td class="col-sm-1"><a class="deleteRow"></a>

											</td>
										</tr>
									</tbody>
									<tfoot>
										<tr>
											<td colspan="5" style="text-align: left;">
												<input type="button" class="btn btn-lg btn-block " id="addrow" value="Add Row" />
											</td>
										</tr>
										<tr>
										</tr>
									</tfoot>
								</table>

	                        <div class="form-actions">
	                            <button type="button" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Cancel
	                            </button>
	                            <button type="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> Save
	                            </button>
	                        </div>
	                    </form>
	                </div>





                        </div>
                        
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




<script>


$(document).ready(function () {
    var counter = 0;

    $("#addrow").on("click", function () {
        var newRow = $("<tr>");
        var cols = "";

        cols += "<td><select id='financial_year' name='financial_year[]' class='form-control' style='width:200px;'><option value='0' selected='' disabled=''>Financial Year</option><?php while($dataRow=mysqli_fetch_array($getAllFinYearQ2)){?> <option><?php echo $dataRow['financial_year']; ?></option> <?php } ?></select></td>";
        cols += '<td><input type="text" id="income" class="form-control"  name="income[]" placeholder="Income" style="width:200px;"></td>';
        cols += '<td><input type="text" id="wealth" class="form-control"  name="wealth[]" placeholder="Wealth" style="width:200px;"></td>';
		cols += '<td><input type="text" id="tax_paid" class="form-control"  name="tax_paid[]" placeholder="Tax Demand" style="width:200px;"></td>';

        cols += '<td><input type="button" class="ibtnDel btn btn-md btn-danger "  value="Delete"></td>';
        newRow.append(cols);
        $("table.order-list").append(newRow);
        counter++;
    });



    $("table.order-list").on("click", ".ibtnDel", function (event) {
        $(this).closest("tr").remove();       
        counter -= 1
    });


});



function calculateRow(row) {
    var price = +row.find('input[name^="price"]').val();

}

function calculateGrandTotal() {
    var grandTotal = 0;
    $("table.order-list").find('input[name^="price"]').each(function () {
        grandTotal += +$(this).val();
    });
    $("#grandtotal").text(grandTotal.toFixed(2));
}


</script>