<?php
include('header.php');

$getMyIncrementDataQ = mysqli_query($con, "select * from yearly_salary_increment where employeeID='$getUserInfoQRW[employee_id]' and status=1");

?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>বাৎসরিক বেতন বৃদ্ধির সার্ভিস বুক</h4>
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
                            

						<p style="text-align:right"><button class="print-link no-print" onclick="jQuery('#ele1').print()">
                Print
                </button></p>

				<div id="ele1" style="padding:0px; margin:0; width:100%;">


						<table border="1" width="100%">
							<tr>
							 <th>&nbsp;ক্রমিক নং</th>
							 <th>&nbsp;বৎসর</th>
							 <th>&nbsp;মূল বেতন</th>
							 <th>&nbsp;বেতন বৃদ্ধির হার</th>
							 <th>&nbsp;বেতন বৃদ্ধির পর মূল বেতন</th>
							 <th>&nbsp;অফিস আদেশ</th>
							</tr>

							<?php
							  $sl = 0;
							  while($dataRow = mysqli_fetch_array($getMyIncrementDataQ)){
								$sl++;
							?>

							<tr>
							 <td>&nbsp;<?php echo $obj->engToBn($sl); ?></td>
							 <td>&nbsp;<?php echo $obj->engToBn(number_format($dataRow['incrementYear'],2)); ?></td>
							 <td>&nbsp;<?php echo $obj->engToBn(number_format($dataRow['presentSalary'],2)); ?></td>
							 <td>&nbsp;<?php echo $obj->engToBn(number_format($dataRow['incrementAmount'],2)); ?></td>
							 <td>&nbsp;<?php echo $obj->engToBn(number_format($dataRow['incrementSalary'],2)); ?></td>
							 <td><a target="_blank" href="increment_office_notice.php?employeeID=<?php echo $getUserInfoQRW['employee_id']; ?>&financialYear=<?php echo $dataRow['incrementYear']; ?>">অফিস আদেশ</a></td>
							</tr>
				

							<?php } ?>


						</table>

                    

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




<script type="text/javascript">

//table.row( 100 ).scrollTo();


$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'auto_generate_increment_action.php', // form action url
			type: 'POST', // form submit method get/post
			dataType: 'html', // request type html/json/xml
			data: new FormData(this), // serialize form data
			contentType: false,
            cache: false,
            processData:false,
			beforeSend: function() {
				
				submit.html('<i class="fa fa-spinner fa-spin"></i> Processing, please wait'); // change submit button text
				setTimeout(200000000000000000);


			},
			success: function(data) {

				//alert(data);
				

                if(data==1)
				{
				
				    toastr.success('Data Generated Successfully');


				    

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else
				{
				
				    toastr.error('Error!!');
				
				
				}




				//form.trigger('reset'); // reset form
				submit.html('Generate Again'); // reset submit button text
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});






</script>