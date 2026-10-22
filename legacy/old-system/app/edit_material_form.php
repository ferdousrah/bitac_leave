<?php
include('header.php');

$dataID = $_GET['dataID'];

$getAllDepotList = mysqli_query($con,"select * from `packsize_list`");

$getAllPCatQ = mysqli_query($con,"select * from item_category");

$getDataInfoQ = mysqli_query($con,"select * from row_materials where dataID='$dataID'");
$getDataInfoQRW = mysqli_fetch_assoc($getDataInfoQ);

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
        <h4>Edit Item</h4>
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

				 <input type="hidden" name="dataID" value="<?php echo $dataID; ?>" />


                <div class="form-group row">
					<label class="col-sm-5 control-label">Select Category&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-6">
						<select class="form-control groupID" name="groupID" id="groupID" required>
						<option value=""></option>
						<?php
						while($dataRow=mysqli_fetch_array($getAllPCatQ))
						{
												?>

						<option <?php if($getDataInfoQRW['groupID']==$dataRow['dataID']){ echo "selected='selected'";} ?> value="<?php echo $dataRow['dataID']; ?>">&nbsp;&nbsp;<?php echo $dataRow['category_name']; ?></option>

												<?php
						}
						?>
						</select>
					</div>
					<div class="col-sm-1">

					<button type="button" class="btn btn-primary waves-effect waves-light" data-toggle="modal" data-target=".bs-example-modal-center"><i class="fa fa-plus"></i>&nbsp;Add</button>

					</div>
				</div>
                
                <div class="form-group row">
					<label class="col-sm-5 control-label">Item Name&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-6">
						<input type="text" class="form-control" name="material_name" value="<?php echo $getDataInfoQRW['material_name']; ?>" required>
					</div>
				</div>
                
                <div class="form-group row">
					<label class="col-sm-5 control-label">Code&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-6">
						<input type="text" class="form-control" name="itemCode" value="<?php echo $getDataInfoQRW['itemCode']; ?>" readonly required>
					</div>
				</div>
                
                
                
                                
                <div class="form-group row">
					<label class="col-sm-5 control-label">Unit of Measure&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-6">
						<select class="form-control uom" name="uom" id="uom" required>
						<option value=""></option>
						<?php
                          while($dataRow=mysqli_fetch_array($getAllDepotList))
						  {
						?>

							<option <?php if($getDataInfoQRW['uom']==$dataRow['sizeID']){ echo "selected='selected'";} ?> value="<?php echo $dataRow['sizeID']; ?>"><?php echo $dataRow['packsize']; ?></option>

							<?php
						  }
								?>
						</select>
					</div>
					<div class="col-sm-1">

					<button type="button" class="btn btn-primary waves-effect waves-light" data-toggle="modal" data-target=".size-form"><i class="fa fa-plus"></i>&nbsp;Add</button>

					</div>
				</div>



				<div class="form-group row">
					<label class="col-sm-5 control-label">Opening Stock&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-6">
						<input type="text" class="form-control" name="opening_stock" value="0" required>
					</div>
				</div>


				<div class="form-group row">
					<label class="col-sm-5 control-label">Opening Date&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-6">
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










<div class="modal fade bs-example-modal-center" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title mt-0">New Group</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
															<div class="col-xs-12">
																
																
																	
																	<div class="form-group row" style="padding-left: 10px;">
																		<label class="col-sm-4 control-label">Enter Group Name&nbsp;<span style="color:#F00">*</span></label>
																		<div class="col-sm-8">
																			<input type="text" class="form-control" name="category_name" value="" id="category_name" required>
																		</div>
																	</div>
																	
																	<div class="form-group row">
																	   <label class="col-sm-4 control-label">&nbsp;</label>
																	   <div class="col-sm-8">
																		
																		<button onClick="insertNewCategory()" class="btn-raised btn-success btn pull-right">Save Now</button>
																		</div>
																	</div>
																
															</div>
															
														</div>
                                                    </div>
                                                </div><!-- /.modal-content -->
                                            </div><!-- /.modal-dialog -->
                                        </div><!-- /.modal -->








										<div class="modal fade size-form" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title mt-0">New UOM</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
															<div class="col-xs-12">
																
																
																	
																	<div class="form-group row" style="padding-left: 10px;">
																		<label class="col-sm-4 control-label">Enter UOM&nbsp;<span style="color:#F00">*</span></label>
																		<div class="col-sm-8">
																			<input type="text" class="form-control" name="uom_title" value="" id="uom_title" required>
																		</div>
																	</div>
																	
																	<div class="form-group row">
																	   <label class="col-sm-4 control-label">&nbsp;</label>
																	   <div class="col-sm-8">
																		
																		<button onClick="insertNewUOM()" class="btn-raised btn-success btn pull-right">Save Now</button>
																		</div>
																	</div>
																
															</div>
															
														</div>
                                                    </div>
                                                </div><!-- /.modal-content -->
                                            </div><!-- /.modal-dialog -->
                                        </div><!-- /.modal -->









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
			url: 'edit_item_action.php', // form action url
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

//window.location='central_row_material?menuslug=manage-rowmaterials';

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



function insertNewCategory()
{

var category_name =$('#category_name').val();

var dataString = 'category_name='+ category_name;
  

  $.ajax
  ({
   type: "POST",
   url: "insert_new_item_category.php",
   data: dataString,
   cache: false,
   success: function(html)
   {
      $(".groupID").html(html);

	  $('.bs-example-modal-center').modal('toggle');
   } 
   });





}


function insertNewUOM()
{

var uom_title =$('#uom_title').val();

var dataString = 'packsize='+ uom_title;
  

  $.ajax
  ({
   type: "POST",
   url: "add_packsize_action.php",
   data: dataString,
   cache: false,
   success: function(html)
   {
      $(".uom").html(html);

	  $('.size-form').modal('toggle');
   } 
   });

}


</script>