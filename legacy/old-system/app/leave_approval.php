<?php
include('header.php');

?>


<style>


.wrapper2 {
  margin:0px auto;
  width:100%;
  font-family:sans-serif;
  color:#555;
  font-size:14px;
  line-height:24px;
  border-left: 1px solid #ccc;
  border-bottom: 1px solid #ccc;
  border-right: 1px solid #ccc;
  border-top: 2px solid #CC2B52;
  background-color: #fff;
  margin-top: -2px;

}
h1 {
  font-size:20px;
  font-weight:bold;
  text-align:center;
  text-transform:uppercase;
}
h1 + p {
  text-align:center;
  margin:20px 0;
  font-size:16px;
}
.tabs {
  li {
    float:left;
    width:20%;
	list-style-type: none;
  }
  a {
    display:block;
    text-align:center;
    text-decoration:none;
    text-transform:uppercase;
    color:#000;
    padding:20px 0;
    
    background:#f4cad6;
	border-bottom:2px solid #CC2B52;

	border-top-left-radius: 10px;
	border-top-right-radius: 10px;
	    
    &:hover {
	  color: #000;
	  border-bottom:2px solid #CC2B52;
	  border-top-left-radius: 10px;
	  border-top-right-radius: 10px;
    }
	&.active {
      background:#953952;
	  color: #fff;
	  border-bottom:2px solid #CC2B52;
	  border-top-left-radius: 10px;
	  border-top-right-radius: 10px;
    }
  }
}


</style>



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
        <!--<h4>ছুটির সুপারিশ ও অনুমোদন</h4>-->
    </div>
	<div class="col-md-2">
        
    </div>
</div>

        <div class="card-body">
            <div id="invoice-template">
                
                
                
                <!-- Invoice Footer -->
                
					<ul style="margin-left: 0px; padding-left: 0px;margin-bottom: 0px;" class="tabs clearfix" data-tabgroup="second-tab-group">					  
					  <li style="margin-bottom: 0px;"><a class="active" href="#tab6">সুপারিশ</a></li>
					  <li style="margin-bottom: 0px;"><a href="#tab7">অনুমোদন</a></li>
					  
					</ul>

					<div class="wrapper2">
					
					<section id="second-tab-group" class="tabgroup">
					  <div id="tab6">
						<!-- tab 6 -->
						<p>
						<table id="leaveTable" class="table table-striped table-bordered">
							<thead>
								<tr>
									<th>ক্রমিক</th>
									<th>আবেদনকারীর নাম ও পদবী</th>
									<th>শাখা</th>
									<th>চাহিত ছুটির ধরণ</th>
									<th>চাহিত ছুটি(দিন)</th>
									<th>প্রস্তাবিত ছুটি(দিন)</th>
									<th>প্রস্তাবিত ছুটির ধরণ</th>
									<th width="220">Action</th>
								</tr>
							</thead>
						</table>

						</p>

						<!-- end of tab 6 -->
					  </div>
					  <div id="tab7">
						<!-- tab 6 -->
						<p>
						<table id="leaveApproveTable" class="table table-striped table-bordered" style="width: 100%;">
							<thead>
								<tr>
									

									<th>ক্রমিক</th>

									<th>আবেদনকারীর নাম ও পদবী</th>

									<th>শাখা</th>

									<th>চাহিত ছুটির ধরণ</th>

									<th>চাহিত ছুটি(দিন) </th>

									<th>প্রস্তাবিত ছুটি(দিন)</th>
									
									<th>প্রস্তাবিত ছুটির ধরণ</th>
									
									<th width="220">Action</th>

								
								</tr>
							</thead>						
							
						</table>
						</p>

						<!-- end of tab 6 -->
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


$('.tabgroup > div').hide();
$('.tabgroup > div:first-of-type').show();
$('.tabs a').click(function(e){
  e.preventDefault();
    var $this = $(this),
        tabgroup = '#'+$this.parents('.tabs').data('tabgroup'),
        others = $this.closest('li').siblings().children('a'),
        target = $this.attr('href');
    others.removeClass('active');
    $this.addClass('active');
    $(tabgroup).children('div').hide();
    $(target).show();
  
})
    


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
            data :  'dataID='+ dataID+'&tableName=modules', //Pass $id
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



$(document).ready(function() {
        $('#leaveTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "fetch_waitingtosupervise_leave_data.php",
                "type": "POST",
                "dataSrc": function (json) {
                    console.log(json); // Log the returned JSON to the console
                    return json.data;
                }
            },
            "columns": [
                { "data": "serial" },
                { "data": "applicant_name" },
                { "data": "section" },
                { "data": "leave_type" },
                { "data": "requested_leave_days" },
                { "data": "proposed_leave_days" },
                { "data": "proposed_leave_type" },
                { "data": "action" }
            ]
        });

		
		


});


$(document).ready(function() {
        

		
		$('#leaveApproveTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "fetch_waitingtoapprove_leave_data.php",
                "type": "POST",
                "dataSrc": function (json) {
                    console.log(json); // Log the returned JSON to the console
                    return json.data;
                }
            },
            "columns": [
                { "data": "serial" },
                { "data": "applicant_name" },
                { "data": "section" },
                { "data": "leave_type" },
                { "data": "requested_leave_days" },
                { "data": "proposed_leave_days" },
                { "data": "proposed_leave_type" },
                { "data": "action" }
            ]
        });



		

});





</script>