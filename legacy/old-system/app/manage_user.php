<?php
include('header.php');

?>


<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-10">
        <h4>User List</h4>
    </div>
	<div class="col-md-2">
        <button onClick="window.location='new_user_form?menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button>
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                

                                

 <table id="userListTable" class="table table-striped table-bordered scroll-vertical">
    <thead>
        <tr>
            <th>SL</th>
            <th>Full Name</th>
            <th>Designation</th>
            <th>Email</th>
            <th>Username</th>
            <th>Action</th>
        </tr>
    </thead>
    
</table>


                            
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
        $('#userListTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "fetch_user_data.php",
                "type": "POST",
                "dataSrc": function (json) {
                    //console.log(json); // Log the returned JSON to the console
                    return json.data;
                }
            },
            "columns": [
                { "data": "sl" },
				{ "data": "full_name" },
				{ "data": "designation" },
				{ "data": "email" },
				{ "data": "user_id" },
				{ "data": "action", "orderable": false }
            ]
        });

		
		


});




		
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
            data :  'dataID='+ dataID+'&tableName=user_list', //Pass $id
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