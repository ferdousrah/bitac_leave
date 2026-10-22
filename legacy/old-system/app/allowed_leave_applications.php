<?php
include('header.php');
include('library/number_converter.php');

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
        <h4>ছুটি সম্পাদনা</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='new_employee_form?menuslug=manage-employee'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> নতুন সংযোজন </button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">


			<div class="row match-height">
				<div class="col-xl-12 col-lg-12">
				  <div class="card">
					<div class="card-header">

					</div>
					<div class="card-body">
					  <div class="card-block">
						<ul style="margin-left: 0px; padding-left: 0px;margin-bottom: 0px;" class="tabs clearfix" data-tabgroup="second-tab-group">					  
						  <li style="margin-bottom: 0px;"><a class="active" href="#tab6">প্রক্রিয়াধীন  ছুটি</a></li>
						  <li style="margin-bottom: 0px;"><a href="#tab7">সম্পাদিত ছুটি</a></li>
						  
						</ul>

						<div class="wrapper2">
						
						<section id="second-tab-group" class="tabgroup">
						  <div id="tab6">
							<!-- tab 6 -->
							<p>

							<table id="employeeTable" class="table table-striped table-bordered scroll-vertical" style="width: 100%;" width="100%">
								<thead>
									<tr>
										<th width="60">ক্রমিক</th>
										<th>আবেদনকারীর নাম ও পদবী</th>
										<th>আইডি</th>
										<th>শাখা</th>
										<th>আবেদনের তারিখ ও সময়</th>
										<th>চাহিত ছুটির ধরণ</th>
										<th>চাহিত ছুটি(দিন)</th>
										<th>প্রস্তাবিত ছুটি(দিন)</th>
										<th>প্রস্তাবিত ছুটির ধরণ</th>
										<th>স্টেটাস</th>
										<th width="140">Action</th>
									</tr>
								</thead>
								<tbody></tbody> <!-- This will be filled by AJAX -->
							</table>


							</p>

						<!-- end of tab 6 -->
					  </div>
					  <div id="tab7">
						<!-- tab 6 -->
						<p>


							<table id="inactiveEmployeeTable" class="table table-striped table-bordered scroll-vertical" style="width: 100%;" width="100%">
								<thead>
									<tr>
										<th width="60">ক্রমিক</th>
										<th>আবেদনকারীর নাম ও পদবী</th>
										<th>আইডি</th>
										<th>শাখা</th>
										<th>আবেদনের তারিখ ও সময়</th>
										<th>চাহিত ছুটির ধরণ</th>
										<th>চাহিত ছুটি(দিন)</th>
										<th>প্রস্তাবিত ছুটি(দিন)</th>
										<th>প্রস্তাবিত ছুটির ধরণ</th>
										<th>স্টেটাস</th>
										<th width="100">Action</th>
									</tr>
								</thead>
								<tbody></tbody> <!-- This will be filled by AJAX -->
							</table>


							<!-- end of 2nd tab -->
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


$(document).ready(function() {
    // Initialize DataTable for active employees
    $('#employeeTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "fetch_forwardbyadmin_pending_data.php", // Server-side script to fetch active employee data
            "type": "POST"
        },
        "columns": [
            { "data": "sl" },
            { "data": "applicant_name" },
            { "data": "employee_id" },
            { "data": "section_name" },
            { "data": "application_date_time" },
            { "data": "requested_leave_type" },
			{ "data": "requested_leave_days" },
			{ "data": "proposed_leave_days" },
			{ "data": "proposed_leave_type" },
			{ "data": "status" },
            { "data": "action" }
        ]
    });

    // Initialize DataTable for inactive employees
    $('#inactiveEmployeeTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "fetch_forwardedbyadmin_data.php", // Server-side script to fetch inactive employee data
            "type": "POST"
        },
        "columns": [
            { "data": "sl" },
            { "data": "applicant_name" },
            { "data": "employee_id" },
            { "data": "section_name" },
            { "data": "application_date_time" },
            { "data": "requested_leave_type" },
			{ "data": "requested_leave_days" },
			{ "data": "proposed_leave_days" },
			{ "data": "proposed_leave_type" },
			{ "data": "status" },
            { "data": "action" }
        ]
    });
});




</script>