<?php
include('header.php');

$moneyReceipt = $_GET['receiptno'];

$getMoneyReceiptQ = mysqli_query($con, "select * from `money_receipt` where `receipt_no`='$moneyReceipt'");
$getMoneyReceiptQRW = mysqli_fetch_assoc($getMoneyReceiptQ);

$getStudentComesFromInfoQ = mysqli_query($con, "select * from `student_collection_from` where `studentID`='$getMoneyReceiptQRW[studentID]'");
$getStudentComesFromInfoQNumRows = mysqli_num_rows($getStudentComesFromInfoQ);

$str = "";
$sl = 1;
while($dataRow = mysqli_fetch_array($getStudentComesFromInfoQ)) {

 if($getStudentComesFromInfoQNumRows == $sl) {

	$str = $str.$dataRow['from'];
	
	if($dataRow['other_text'] != '') {
	$str = $str." : ".$dataRow['other_text'];
	}

 }
 else {

	 $str = $str.$dataRow['from'].", ";
 
 }

$sl = $sl + 1;
}



// Create a function for converting the amount in words
function AmountInWords(float $amount)
{
   $amount_after_decimal = round($amount - ($num = floor($amount)), 2) * 100;
   // Check if there is any number after decimal
   $amt_hundred = null;
   $count_length = strlen($num);
   $x = 0;
   $string = array();
   $change_words = array(0 => '', 1 => 'One', 2 => 'Two',
     3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
     7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
     10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
     13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
     16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
     19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
     40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
     70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety');
    $here_digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
    while( $x < $count_length ) {
      $get_divider = ($x == 2) ? 10 : 100;
      $amount = floor($num % $get_divider);
      $num = floor($num / $get_divider);
      $x += $get_divider == 10 ? 1 : 2;
      if ($amount) {
       $add_plural = (($counter = count($string)) && $amount > 9) ? 's' : null;
       $amt_hundred = ($counter == 1 && $string[0]) ? ' and ' : null;
       $string [] = ($amount < 21) ? $change_words[$amount].' '. $here_digits[$counter]. $add_plural.' 
       '.$amt_hundred:$change_words[floor($amount / 10) * 10].' '.$change_words[$amount % 10]. ' 
       '.$here_digits[$counter].$add_plural.' '.$amt_hundred;
        }
   else $string[] = null;
   }
   $implode_to_Rupees = implode('', array_reverse($string));
   $get_paise = ($amount_after_decimal > 0) ? "And " . ($change_words[$amount_after_decimal / 10] . " 
   " . $change_words[$amount_after_decimal % 10]) . ' Paisa' : '';
   return ($implode_to_Rupees ? $implode_to_Rupees . 'Taka ' : '') . $get_paise;
}





?>


<style>

.hidden {
  display:none;
}

.maindiv {
  border-style: dashed;
  border-width: 1px;
  border-color: #ccc;
  
}


</style>

<link href="https://fonts.googleapis.com/css2?family=Architects+Daughter&display=swap" rel="stylesheet">

<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>Money Receipt</h4>
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
				<p style="text-align: right;"><i id="doPrint" style="font-size: 32px;" class="fa fa-print"></i></p>
                
                <!-- Invoice Footer -->
                <div id="invoice-footer">
                    <div class="row">
                        <div class="col-md-12 col-sm-12">
                            
							<div id="printDiv">

							<div class="maindiv">

								<table width="100%" border="0">
									<tr>
									<td align="center" valign="top">
									<div style="margin-left: 5px;">
									<h4 style="color: #000;margin-bottom:0px;">Princeton Preparation</h4>
									<p style="font-size: 13px;">House# 02, Sultana Tower, Mirpur Road<br> Kalabagan Dhaka-1205, Bangladesh<br>Hotline: 01979003333, Web: www.princetonprepbd.com, Email: info@princetonprepbd.com</p>
									<p style="font-weight: bold;">Money Receipt</p>
									</div>
									</td>
									<td align="right" valign="top">&nbsp;<img src="http://117.103.87.26/princeton_backoffice/uploads/princeton-logo.png" />
									<td>
									</tr>

									<tr>
									<td><div style="margin-left: 5px;font-size: 14px;">M No#&nbsp;<span style="font-size: 16px;font-weight: bold;"><?php echo $_GET['receiptno']; ?></span></div></td>
									<td align="right">Date:<span style="border-style: dotted;border-width: 1px;border-left: 0px;border-right: 0px;border-top: 0px;">&nbsp;&nbsp;<?php echo $getMoneyReceiptQRW['date']; ?>&nbsp;&nbsp;</span>&nbsp;</td>
									</tr>

									<tr>
									<td colspan="2">
									<div style="margin-left: 5px;">
									<b>Received</b> with Thanks from <span style="border-style: dotted;border-width: 1px;border-left: 0px;border-right: 0px;border-top: 0px;font-family: 'Architects Daughter', cursive;font-size: 20px;width: 100%;">&nbsp;<?php echo $getMoneyReceiptQRW['received_from']; ?>&nbsp;</span>
									</div>
									</td>
									</tr>

									<tr>
									<td>
									<div style="border-style: dotted;border-width: 1px;border-left: 0px;border-right: 0px;border-top: 0px;font-family: 'Architects Daughter', cursive;font-size: 20px;width: 100%;margin-left: 5px;">&nbsp;</div>
									</td>
	
									<td align="right">
									TK.<span style="border-style: solid;border-width: 1px;border-left: 1px;border-right: 1px;border-top: 1px;font-family: 'Architects Daughter', cursive;font-size: 20px;">&nbsp;<?php echo $getMoneyReceiptQRW['received_amount']; ?>/=&nbsp;</span>
									</td>

									</tr>

									<tr>
									<td colspan="2">
									<div style="margin-left: 5px;">
									<b>(Taka in word</b> <span style="border-style: dotted;border-width: 1px;border-left: 0px;border-right: 0px;border-top: 0px;font-family: 'Architects Daughter', cursive;font-size: 20px;">&nbsp;<?php echo AmountInWords($getMoneyReceiptQRW['received_amount']); ?>Only&nbsp;</span><b>)</b>
									</div>
									</td>
									</tr>

									<tr height="50">
									<td colspan="2">
									<div style="margin-left: 5px;">
									In Cash /Cheque/D.D/bKash No.<span style="border-style: dotted;border-width: 1px;border-left: 0px;border-right: 0px;border-top: 0px;font-family: 'Architects Daughter', cursive;font-size: 20px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
									</div>
									</td>
									</tr>


									<tr>
									<td colspan="2">
									<div style="margin-left: 5px;">
									On Account of Course Name <span style="border-style: dotted;border-width: 1px;border-left: 0px;border-right: 0px;border-top: 0px;font-family: 'Architects Daughter', cursive;font-size: 20px;">&nbsp;<?php echo $getMoneyReceiptQRW['on_account_of']; ?>&nbsp;</span>
									</div>
									</td>
									</tr>


									<tr>
									<td colspan="2">
									<div style="margin-left: 5px;">
									Source <span style="border-style: dotted;border-width: 1px;border-left: 0px;border-right: 0px;border-top: 0px;font-family: 'Architects Daughter', cursive;font-size: 20px;">&nbsp;<?php echo $str; ?>&nbsp;</span>
									</div>
									</td>
									</tr>


									

								</table>


								<p>&nbsp;</p>


								<table width="100%">

									<tr height="4">
									<td><div style="margin-left: 5px;"><hr style="background-color: #000;padding: 0; margin: 0;width: 150px;"></div></td>
									<td><div style="margin-left: 5px;"><hr style="background-color: #000;padding: 0; margin: 0;width: 150px;"></div></td>
									<td><div style="margin-left: 5px;"><hr style="background-color: #000;padding: 0; margin: 0;width: 150px;"></div></td>
									</tr>

									<tr>
									<td><div style="margin-left: 5px;font-size: 14px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Accounts Officer</div></td>
									<td><div style="margin-left: 5px;font-size: 14px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Admin-Director</div></td>
									<td><div style="margin-left: 5px;font-size: 14px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Managing Director</div></td>
									</tr>
								
								</table>


								<p>&nbsp;</p>
							
							</div>

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


$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_instructor_data.php', // form action url
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
				

                if(data==0)
				{
				
				    		
					toastr.error('Error!!');

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else
				{
				
				    toastr.success('Data Saved Successfully');

					window.location='manage_instructors?menuslug=manage-instructors';
				
				
				}




				//form.trigger('reset'); // reset form
				submit.html('Submit'); // reset submit button text
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});




document.getElementById("doPrint").addEventListener("click", function() {
     var printContents = document.getElementById('printDiv').innerHTML;
     var originalContents = document.body.innerHTML;
     document.body.innerHTML = printContents;
     window.print();
     document.body.innerHTML = originalContents;
});




</script>