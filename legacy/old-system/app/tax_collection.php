<?php
include('header.php');

$getAllTaxPayerQ = mysqli_query($con,"select * from tax_payer_info");

$getAllFinYearQ = mysqli_query($con,"select * from financial_year");

$getAllFinYearQ2 = mysqli_query($con,"select * from financial_year");

$getArticleQ = mysqli_query($con,"select * from articles");

?>


<link rel='stylesheet' href='app-assets/css/select2.min.css'>



<div class="main-panel">
        <div class="main-content">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>Tax Collection Entry Form</h4>
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
	                    <form class="form form-horizontal" action="tax_collection_action" method="post">
	                    	<div class="form-body">
	                    		

								<h4 class="form-section"><i class="ft-file-text"></i> Tax Collection</h4>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">Tax Payer </label>
		                            <div class="col-md-9">
		                            	<select class="select2 form-control" name="country">
										<?php
                                          while($dataRow=mysqli_fetch_array($getAllTaxPayerQ))
										  {
										?>

											<option value="<?php echo $dataRow['dataID']; ?>"><?php echo $dataRow['etin'].' - '.$dataRow['taxPayerName']; ?></option>

										  <?php
										  }
										  ?>
										</select>
		                            </div>
		                        </div>

		                        <table id="myTable" class=" table order-list">
									
									<tbody>
										<tr>
											<td class="col-sm-2" style="width:150px;">
												<select id="financial_year" name="financial_year[]" class="form-control" style="width:150px;">
													<option value="0" selected="" disabled="">Financial Year</option>
													<?php
													  while($finRow=mysqli_fetch_array($getAllFinYearQ))
													  {
													?>
													<option value="<?php echo $finRow['financial_year']; ?>"><?php echo $finRow['financial_year']; ?></option>
													<?php
													  }
														?>
												</select>
											</td>
											<td class="col-sm-3" style="width:200px;">
												<div class="position-relative has-icon-left">
												<input type="date" id="etin_issue_date" class="form-control" name="etin_issue_date" style="width:200px;">
												<div class="form-control-position">
													<i class="ft-message-square"></i>
												</div>
											</td>
											<td class="col-sm-2" style="width:120px;">
												<input type="text" id="wealth" class="form-control"  name="wealth[]" placeholder='arrear' style="width:120px;">
											</td>
											<td class="col-sm-2" style="width:120px;">
												<input type="text" id="tax_paid" class="form-control"  name="tax_paid[]" placeholder='Tax Paid' style="width:120px;">
											</td>
											<td class="col-sm-2" style="width:200px;">
											<select id="financial_year" name="dhara[]" class="js-example-basic-multiple" multiple="multiple" style="width:200px;">
													<option disabled></option>
													<?php
														while($arRow=mysqli_fetch_array($getArticleQ))
														{
													?>
													<option value="<?php echo $arRow['dataID']; ?>"><?php echo $arRow['articleNo']; ?></option>
													<?php
														}
														?>
													
												</select>
											</td>
											<td class="col-sm-1" style="width:80px;"><a class="deleteRow"></a>

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

<script src='app-assets/js/select2.min.js'></script>



<script>

$('.select2').select2({
});


$('.js-example-basic-multiple').select2();


$(document).ready(function () {
    var counter = 0;

    $("#addrow").on("click", function () {
        var newRow = $("<tr>");
        var cols = "";

        cols += '<td><select id="financial_year" name="financial_year[]" class="form-control"><option value="0" selected="" disabled="">Financial Year</option><option value="2014-15">2014-15</option><option value="2015-16">2015-16</option><option value="2016-17">2016-17</option><option value="2017-18">2017-18</option></select></td>';
        cols += '<td><input type="text" id="income" class="form-control"  name="income[]" placeholder="Income"></td>';
        cols += '<td><input type="text" id="wealth" class="form-control"  name="wealth[]" placeholder="Wealth"></td>';
		cols += '<td><input type="text" id="tax_paid" class="form-control"  name="tax_paid[]" placeholder="Tax Paid"></td>';

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