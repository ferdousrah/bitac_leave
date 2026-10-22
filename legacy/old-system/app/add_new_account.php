<?php
include('header.php');

$getAllAccountType = mysqli_query($con, "select * from `account_types`");

$getAllAccountGroup = mysqli_query($con, "select * from `accounts_group`");


//$newProductID = "2000"+($getLastProductIDQRW['dataID'] + 1);

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
        <h4>Create New Account</h4>
    </div>
	<div class="col-md-2">
         
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div class="card-block">
                
                
                
               


					

                 <form name="form" id="form" enctype="multipart/form-data">

				 <input type="hidden" name="menuslug" value="<?php echo $menuSlug; ?>" />


				 <div class="form-group row">
					<label class="col-sm-3 control-label">Enter Account Name&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="text" class="form-control" name="account_name" value="<?php //echo $newProductID; ?>" required>
					</div>
				</div>


				<div class="form-group row">
					<label class="col-sm-3 control-label">Account Number/Code&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="text" class="form-control" name="account_number" value="<?php //echo $newProductID; ?>" required>
					</div>
				</div>

				<div class="form-group row">
					<label class="col-sm-3 control-label">Type&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<select class="form-control" name="account_type">
							<option value=''></option>
							<?php while($typeRow = mysqli_fetch_array($getAllAccountType)) { ?>
							<option value="<?php echo $typeRow['dataID']; ?>"><?php echo $typeRow['account_type']; ?></option>
							<?php } ?>
						</select>
					</div>
				</div>


				<div class="form-group row">
					<label class="col-sm-3 control-label">Accounts Group&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<select class="form-control" name="account_group">
							<option value=''></option>
							<?php while($gRow = mysqli_fetch_array($getAllAccountGroup)) { ?>
							<option value="<?php echo $gRow['dataID']; ?>"><?php echo $gRow['group_title']; ?></option>
							<?php } ?>
						</select>
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

//table.row( 100 ).scrollTo();


$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'add_account_action.php', // form action url
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


$("#form")[0].reset()

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