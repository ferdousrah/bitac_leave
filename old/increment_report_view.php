<?php
include('connection.php');
include('library/number_converter.php');

function dateDiffInDays($date1, $date2) 
  {
      // Calculating the difference in timestamps
      $diff = strtotime($date2) - strtotime($date1);
  
      // 1 day = 24 hours
      // 24 * 60 * 60 = 86400 seconds
      return abs(round($diff / 86400));
  }

$employeeID = $_POST['employeeID'];


$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQW[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$getMyIncrementDataQ = mysqli_query($con, "select * from yearly_salary_increment where employeeID='$employeeID' and status=1");


?>





<p style="text-align:right"><button class="print-link no-print" onclick="jQuery('#ele1').print()">
                Print
                </button></p>


<div id="ele1" style="padding:0px; margin:0; width:100%;">

<p style="text-align:center;padding:0px;">
<span style="font-size: 20px;">বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)</span><br><span style="font-size: 14px;">১১৬(খ), তেজগাঁও শিল্প এলাকা, ঢাকা-১২০৮
</span>
</p>

<p style="text-align:center;"><?php echo $getEmployeeDetailsQW['employee_name']; ?>, <?php echo $getDesignationDetailsQRW['job_title_name']; ?></p>


<table border="1" width="100%">
							<tr>
							 <th>&nbsp;ক্রমিক নং</th>
							 <th>&nbsp;বৎসর</th>
							 <th>&nbsp;মূল বেতন</th>
							 <th>&nbsp;বেতন বৃদ্ধির হার</th>
							 <th>&nbsp;বেতন বৃদ্ধির পর মূল বেতন</th>
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
							</tr>
				

							<?php } ?>


						</table>


</div> <!-- end of print div -->





<script src="./app-assets/vendors/js/core/jquery-3.2.1.min.js" type="text/javascript"></script>
<script src="./jQuery.print.js"></script>


<script type='text/javascript'>
        //<![CDATA[
        jQuery(function($) { 'use strict';
            try {
                var original = document.getElementById('canvasExample');
                original.getContext('2d').fillRect(20, 20, 120, 120);
            } catch (err) {
                console.warn(err)
            }
            $("#ele2").find('.print-link').on('click', function() {
                //Print ele2 with default options
                $.print("#ele2");
            });
            $("#ele4").find('button').on('click', function() {
                //Print ele4 with custom options
                $("#ele4").print({
                    //Use Global styles
                    globalStyles : false,
                    //Add link with attrbute media=print
                    mediaPrint : false,
                    //Custom stylesheet
                    stylesheet : "http://fonts.googleapis.com/css?family=Inconsolata",
                    //Print in a hidden iframe
                    iframe : false,
                    //Don't print this
                    noPrintSelector : ".avoid-this",
                    //Add this at top
                    prepend : "Hello World!!!<br/>",
                    //Add this on bottom
                    append : "<span><br/>Buh Bye!</span>",
                    //Log to console when printing is done via a deffered callback
                    deferred: $.Deferred().done(function() { console.log('Printing done', arguments); })
                });
            });
        });
        //]]>
        </script>