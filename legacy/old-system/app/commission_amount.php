<?php
include('header.php');

$todayTaskDate = ShowBangladeshDate();

if(isset($_POST['filter']))
{
						
						                      

						
						$dateF = date_create($_POST['dateFrom']);

						$dateFrom = date_format($dateF,"Y-m-d");


						$dateT = date_create($_POST['dateTo']);

						$dateTo = date_format($dateT,"Y-m-d");


						


						


						$getDataQuery = mysqli_query($con,"select * from courses_by_students where date between '$dateFrom' and '$dateTo'");

						//echo "select * from item_arrival_history where 1=1 $itemSQL $dateSQL order by `arrivalDate` asc";
						//echo $_POST['dateTo'];


						


}
else
{
	$dateFrom = date('Y').'-'.date('m').'-01';

	$dateTo = date("Y-m-t", strtotime($dateFrom));

$getDataQuery = mysqli_query($con,"select * from courses_by_students where date between '$dateFrom' and '$dateTo'");


//$dateF = date_create(date('Y-m-d'));

//$dateFrom = date_format($dateF,"Y-m-d");

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
        <h4>Comisssion Report</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='add_new_instructor_form?menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
								<!--  onkeyup="search(this.value)" -->
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" name="form" id="form" method="POST" class="form-horizontal row-border" enctype="multipart/form-data">


				<div class="form-group row">
					<label class="col-sm-1 control-label">Date From&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-2">
						<input type="date" class="form-control" id="datepicker2" autocomplete="off" name="dateFrom" placeholder="date from" value="<?php echo $dateFrom; ?>">
					</div>
					<label class="col-sm-1 control-label">Date To&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-2">
						<input type="date" class="form-control" id="datepicker2" autocomplete="off" name="dateTo" placeholder="date to" value="<?php echo $dateTo; ?>">
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
Comission Report<br>


<label style="font-size:14px;color:#000;line-height: 16px;">Date: <?php $fdate=date_create($dateFrom); echo date_format($fdate,"d/m/Y"); ?> - <?php $tdate=date_create($dateTo); echo date_format($tdate,"d/m/Y"); ?></label>

</p>
							                               

                                <table width="100%" align="center" border="1" style="border: 1px solid #4d4f55; border-collapse:collapse;font-size:14px;">
                                    <thead>
                                    <tr>
                                        <th width="4%">&nbsp;&nbsp;SL</th>

										<th width="20%">&nbsp;&nbsp;Date</th>

										<th width="20%">&nbsp;&nbsp;Student Name</th>

										<th width="20%">&nbsp;&nbsp;Mobile No.</th>

										<th width="20%">&nbsp;&nbsp;On Account Of</th>

										<th align="center" width="20%">&nbsp;&nbsp;Comission Amount(BDT)</th>

										
                                    </tr>
                                    </thead>


                                    <tbody>

                                    <?php
										$sl = 0;
										$totalCollection = 0;
										while($orgRow=mysqli_fetch_array($getDataQuery))
										{
											$sl = $sl + 1;

											$getStudentDetailsQ = mysqli_query($con, "select * from students where dataID='$orgRow[studentID]'");
											$getStudentDetailsQRW = mysqli_fetch_assoc($getStudentDetailsQ);

											$getCourseInfoQ = mysqli_query($con, "select * from courses where dataID='$orgRow[courseID]'");
											$getCourseInfoQRW = mysqli_fetch_assoc($getCourseInfoQ);

											$rdate=date_create($orgRow['date']); 
											
											$receiptDate = date_format($rdate,"d/m/Y")
											
																																	
										?>
                                        <tr id="tr_<?php echo $sl; ?>">

				                            <td>&nbsp;&nbsp;<?php echo $sl; ?></td>		
											
									
											<td>&nbsp;&nbsp;<?php echo $receiptDate; ?></td>

											<td>&nbsp;&nbsp;<?php echo $getStudentDetailsQRW['full_name']; ?></td>

											<td>&nbsp;&nbsp;<?php echo $getStudentDetailsQRW['students_cell']; ?></td>
											
											<td>&nbsp;&nbsp;<?php echo $getCourseInfoQRW['courseTitle']; ?></td>

											<td align="right">&nbsp;&nbsp;<?php echo number_format($orgRow['comission'], 2); $totalCollection = $totalCollection + $orgRow['comission']; ?>&nbsp;</td>

									

                                           
				                        </tr>
				                        
                                        <?php
										}
										?>

										<tr><th colspan="5">&nbsp;&nbsp;TOTAL</th><th style="text-align: right;" align="right">&nbsp;&nbsp;<?php echo number_format($totalCollection, 2); ?>&nbsp;</th></tr>
                                    
                                    </tbody>
                                </table>

                           


				 </div>
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
		
		function removeData(sl,dataID)
{
	

	swal({
  title: "Are you sure to delete this data?",
  text: "",
  icon: "warning",
  buttons: true,
  dangerMode: true,
})
.then((willDelete) => {
  if (willDelete) {
	  
	$.ajax({
            type : 'post',
            url : 'delete_data.php', //Here you will fetch records 
            data :  'dataID='+ dataID +'&tableName=voucher_list', //Pass $id
            success : function(data){
            $("#tr_"+sl).hide(1000);
            }
        });	  
	  
    swal("Deleted!", {
      icon: "success",
    });
	//window.location= 'index.html';
  } else {
    swal("Your data is safe!");
  }
});
	
	

}
		
		</script>