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
					  <li style="margin-bottom: 0px;"><a class="active" href="#tab6">প্রধান কার্যালয়</a></li>
					  <li style="margin-bottom: 0px;"><a href="#tab7">বিটাক, ঢাকা</a></li>
					  
					</ul>

					<div class="wrapper2">
					
					<section id="second-tab-group" class="tabgroup">
					  <div id="tab6">
						<!-- tab 6 -->
						<p>
						<table id="leaveTable" class="table table-striped table-bordered" style="width: 100%;">
							<thead>
								<tr>
									<th>ক্রমিক</th>
									<th>পদবী</th>
									<th>বর্তমানে কর্মরত</th>
									<th>অনুমতির ক্রমিক নং</th>
									<th>বাধ্যতামূলক</th>									
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
									<th>পদবী</th>
									<th>বর্তমানে কর্মরত</th>
									<th>অনুমতির ক্রমিক নং</th>
									<th>বাধ্যতামূলক</th>									
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
    


function removeData(sl, dataID) {
    swal({
        title: "Are you sure you want to delete this data?",
        icon: "warning",
        buttons: true,
        dangerMode: true,
    }).then((willDelete) => {
        if (willDelete) {
            $.ajax({
                type: 'POST',
                url: 'delete_signatory.php', 
                data: { dataID: dataID, tableName: 'leave_approval_signatory' },
                success: function(response) {
                    // Show success message after AJAX completion
                    swal("Deleted!", {
                        icon: "success",
                    });

                    // Reload DataTables
                    if ($.fn.DataTable.isDataTable('#leaveTable')) {
                        $('#leaveTable').DataTable().ajax.reload();
                    }
                    if ($.fn.DataTable.isDataTable('#leaveApproveTable')) {
                        $('#leaveApproveTable').DataTable().ajax.reload();
                    }
                },
                error: function() {
                    swal("Error!", "Something went wrong.", "error");
                }
            });
        } else {
            swal("Your data is safe!");
        }
    });
}



$(document).ready(function() {
    
	// Initialize DataTable
    let leaveTable = $('#leaveTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "fetch_bitac_center_approval_setting_data.php",
            "type": "POST"
        },
        "columns": [
            { "data": "serial" },
            { "data": "designation" },
            { "data": "signatory" },
            { "data": "approvalSL" },
			{ "data": "isMandatory" },
            { "data": "action" }
        ],
		"dom": '<"top"f>rt<"bottom"lp><"clear">', // Only include the search field ('f') without buttons
        "initComplete": function() {
            const addButton = `
                <button id="add-signatory-btn" class="btn btn-primary ml-2" onclick="location.href='new_signatory_form?id=4&menuslug=leave-settings'">
                    <i class="fa fa-plus" aria-hidden="true"></i> Add New
                </button>`;
            $('#leaveTable_filter').append(addButton); // Append the button to the search container
        },
        "drawCallback": function () {
            // Add data-id attribute to rows dynamically after table draw
            $('#leaveTable tbody tr').each(function () {
                const actionCell = $(this).find('td:last'); // Assuming the last column contains the actions
                const editLink = actionCell.find('a').attr('href'); // Get the href attribute of the edit link
                const dataIDMatch = editLink.match(/dataID=(\d+)/); // Extract dataID from URL
                
                if (dataIDMatch && dataIDMatch[1]) {
                    $(this).attr('data-id', dataIDMatch[1]); // Add data-id attribute
                }
            });
        }
    });

    // Enable drag-and-drop sorting
    $('#leaveTable tbody').sortable({
        helper: "clone",
        axis: "y",
        update: function (event, ui) {
            let order = [];
            $('#leaveTable tbody tr').each(function (index) {
                const dataId = $(this).data("id"); // Get the dynamically added data-id
                const position = index + 2;
                order.push({ id: dataId, approvalSL: position });
            });

            console.log(order); // Verify the data being sent

            // Send the updated order to the server
            $.ajax({
                url: "update_leave_approval_order.php",
                method: "POST",
                data: { order: order },
                success: function (response) {
                    console.log(response); // Log the server response
					leaveTable.ajax.reload(); // Reload the table
                },
                error: function (xhr, status, error) {
                    console.error("Error updating order:", error);
                }
            });
        }
    }).disableSelection();

	// 2nd table

	// Initialize DataTable
    let leaveApproveTable = $('#leaveApproveTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "fetch_bitac_dhaka_approval_setting_data.php",
            "type": "POST"
        },
        "columns": [
            { "data": "serial" },
            { "data": "designation" },
            { "data": "signatory" },
            { "data": "approvalSL" },
			{ "data": "isMandatory" },
            { "data": "action" }
        ],		
		"dom": '<"top"f>rt<"bottom"lp><"clear">', // Only include the search field ('f') without buttons
        "initComplete": function() {
            const addButton = `
                <button id="add-signatory-btn" class="btn btn-primary ml-2" onclick="location.href='new_signatory_form?id=5&menuslug=leave-settings'">
                    <i class="fa fa-plus" aria-hidden="true"></i> Add New
                </button>`;
            $('#leaveApproveTable_filter').append(addButton); // Append the button to the search container
        },
        "drawCallback": function () {
            // Add data-id attribute to rows dynamically after table draw
            $('#leaveApproveTable tbody tr').each(function () {
                const actionCell = $(this).find('td:last'); // Assuming the last column contains the actions
                const editLink = actionCell.find('a').attr('href'); // Get the href attribute of the edit link
                const dataIDMatch = editLink.match(/dataID=(\d+)/); // Extract dataID from URL
                
                if (dataIDMatch && dataIDMatch[1]) {
                    $(this).attr('data-id', dataIDMatch[1]); // Add data-id attribute
                }
            });
        }
    });

    // Enable drag-and-drop sorting
    $('#leaveApproveTable tbody').sortable({
        helper: "clone",
        axis: "y",
        update: function (event, ui) {
            let order = [];
            $('#leaveApproveTable tbody tr').each(function (index) {
                const dataId = $(this).data("id"); // Get the dynamically added data-id
                const position = index + 2;
                order.push({ id: dataId, approvalSL: position });
            });

            console.log(order); // Verify the data being sent

            // Send the updated order to the server
            $.ajax({
                url: "update_leave_approval_order.php",
                method: "POST",
                data: { order: order },
                success: function (response) {
                    console.log(response); // Log the server response
					leaveApproveTable.ajax.reload(); // Reload the table
                },
                error: function (xhr, status, error) {
                    console.error("Error updating order:", error);
                }
            });
        }
    }).disableSelection();

/*
    $('#leaveApproveTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "fetch_bitac_dhaka_approval_setting_data.php",
            "type": "POST",
            "dataSrc": function (json) {
                console.log(json); // Log the returned JSON to the console
                return json.data;
            }
        },
        "columns": [
            { "data": "serial" },
            { "data": "designation" },
            { "data": "signatory" },
            { "data": "approvalSL" },
            { "data": "action" }
        ],
        "dom": '<"top"f>rt<"bottom"lp><"clear">', // Only include the search field ('f') without buttons
        "initComplete": function() {
            const addButton = `
                <button id="add-signatory-btn" class="btn btn-primary ml-2" onclick="location.href='new_signatory_form?id=4&menuslug=leave-settings'">
                    <i class="fa fa-plus" aria-hidden="true"></i> Add New
                </button>`;
            $('#leaveApproveTable_filter').append(addButton); // Append the button to the search container
        }
    });

*/

});





</script>