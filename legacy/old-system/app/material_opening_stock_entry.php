<?php
include('header.php');

$getAllDepotList = mysqli_query($con,"select * from `packsize_list`");

$getAllPCatQ = mysqli_query($con,"select * from item_category");



$getAllWareHouseQ = mysqli_query($con, "select * from store_list");




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
        <h4>Add Item/Material Opening Stock</h4>
    </div>
	<div class="col-md-2">
         
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                <!-- Invoice Footer -->
                <div id="invoice-footer">
                    <div class="row">


					

                 <form name="form" id="form" enctype="multipart/form-data">

				 <input type="hidden" name="menuslug" value="<?php echo $menuSlug; ?>" />

				 

				 <div class="form-group row">
					<label class="col-sm-5 control-label">Select Warehouse&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-7">
						<select class="form-control" name="storeID" id="storeID" required>
						<option value=""></option>
						<?php
							while($wRow=mysqli_fetch_array($getAllWareHouseQ))
							{
													?>

							<option value="<?php echo $wRow['dataID']; ?>">&nbsp;&nbsp;<?php echo $wRow['store_name']; ?></option>

													<?php
							}
						?>
						</select>
					</div>
					
				</div>


                 <div class="form-group row">
					<label class="col-sm-5 control-label">Select Category&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-7">
						<select class="form-control groupID" name="groupID" id="groupID" required>
						<option value=""></option>
						<?php
while($dataRow=mysqli_fetch_array($getAllPCatQ))
{
						?>

<option value="<?php echo $dataRow['dataID']; ?>">&nbsp;&nbsp;<?php echo $dataRow['category_name']; ?></option>

						<?php
}
						?>
						</select>
					</div>
					
				</div>
                
                <div class="form-group row">
					<label class="col-sm-5 control-label">Select Item&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-7">

					   <select class="form-control itemID" name="itemID" id="js-example-basic-single" required>
						<option value=""></option>
					   </select>
						
					</div>
				</div>
                
                


				<div class="form-group row">
					<label class="col-sm-5 control-label">Opening Stock&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-7">
						<input type="text" class="form-control" name="opening_stock" value="0" required>
					</div>
				</div>


				<div class="form-group row">
					<label class="col-sm-5 control-label">Opening Date&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-7">
						<input type="date" class="form-control" name="opening_date" value="0" required>
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
			url: 'add_material_opening_action.php', // form action url
			type: 'POST', // form submit method get/post
			data: new FormData( this ),
      processData: false,
      contentType: false,
			beforeSend: function() {
				
				
				swal({
  title: '',
  text: 'Please wait...',
  onOpen: function () {
    swal.showLoading()
  }
}).then(
  function () {},
  // handling the promise rejection
  function (dismiss) {
    
  }
)
				
				
				
			},
			success: function(data) {
				
				
				if(data==1)
{
swal(
  'Data Successfully Saved...',
  '',
  'success'
)

//$("#my-form").reset();

//window.location='central_row_material?submslug=manage-rowmaterials&slug=central-depo';

}
else
{
	swal(
  'Oops...',
  'Something went wrong!',
  'error'
)

}
				
				
				
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});



$(".groupID").change(function()
 {
  var groupID=$(this).val();
  var dataString = 'groupID='+ groupID;
  
 
  $.ajax
  ({
   type: "POST",
   url: "get_item_by_category.php",
   data: dataString,
   cache: false,
   success: function(html)
   {
      $(".itemID").html(html);
	  $('#js-example-basic-single').select2();
   } 
   });
  });


</script>