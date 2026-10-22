


</div>
    <!-- ////////////////////////////////////////////////////////////////////////////-->

    
    
    <!-- BEGIN VENDOR JS-->
    <script src="<?php echo $baseURL; ?>app-assets/vendors/js/core/jquery-3.2.1.min.js" type="text/javascript"></script>
    <script src="<?php echo $baseURL; ?>app-assets/vendors/js/core/popper.min.js" type="text/javascript"></script>
    <script src="<?php echo $baseURL; ?>app-assets/vendors/js/core/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo $baseURL; ?>app-assets/vendors/js/perfect-scrollbar.jquery.min.js" type="text/javascript"></script>
    <script src="<?php echo $baseURL; ?>app-assets/vendors/js/prism.min.js" type="text/javascript"></script>
    <script src="<?php echo $baseURL; ?>app-assets/vendors/js/jquery.matchHeight-min.js" type="text/javascript"></script>
    <script src="<?php echo $baseURL; ?>app-assets/vendors/js/screenfull.min.js" type="text/javascript"></script>
    <script src="<?php echo $baseURL; ?>app-assets/vendors/js/pace/pace.min.js" type="text/javascript"></script>
	<script src="<?php echo $baseURL; ?>app-assets/vendors/js/toastr.min.js" type="text/javascript"></script>
	<script src="<?php echo $baseURL; ?>app-assets/vendors/js/sweetalert2.min.js" type="text/javascript"></script>
	<script src="<?php echo $baseURL; ?>app-assets/vendors/js/datatable/datatables.min.js" type="text/javascript"></script>
    <!-- BEGIN VENDOR JS-->
    <!-- BEGIN PAGE VENDOR JS-->
	<script src="<?php echo $baseURL; ?>app-assets/vendors/js/toastr.min.js" type="text/javascript"></script>
    <!-- END PAGE VENDOR JS-->
    <!-- BEGIN APEX JS-->
    <script src="<?php echo $baseURL; ?>app-assets/js/app-sidebar.js" type="text/javascript"></script>
    <script src="<?php echo $baseURL; ?>app-assets/js/notification-sidebar.js" type="text/javascript"></script>
    <script src="<?php echo $baseURL; ?>app-assets/js/customizer.js" type="text/javascript"></script>
	<!-- BEGIN PAGE LEVEL JS-->
    <script src="<?php echo $baseURL; ?>app-assets/js/toastr.min.js" type="text/javascript"></script>
	<script src="<?php echo $baseURL; ?>app-assets/js/sweet-alerts.js" type="text/javascript"></script>

	<!--<script src="<?php echo $baseURL; ?>app-assets/js/data-tables/datatable-basic.js" type="text/javascript"></script>-->

    <!-- END PAGE LEVEL JS-->
    <!-- END APEX JS-->
    <!-- BEGIN PAGE LEVEL JS-->
	<script src="<?php echo $baseURL; ?>app-assets/js/toastr.min.js" type="text/javascript"></script>
	<script src="<?php echo $baseURL; ?>app-assets/js/tooltip.js" type="text/javascript"></script>
	<script src="<?php echo $baseURL; ?>app-assets/js/sweet-alerts.js" type="text/javascript"></script>
    <!-- END PAGE LEVEL JS-->
	<script src="//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

	<link rel="stylesheet" href="//code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">
	<script src="//code.jquery.com/ui/1.13.0/jquery-ui.js"></script>

  </body>
</html>



<script>


function logout()
{
	
	
	swal({
  title: "Are you sure?",
  text: "Once logout, you will not be able to access this page without login!",
  icon: "warning",
  buttons: true,
  dangerMode: true,
})
.then((willDelete) => {
  if (willDelete) {   

	  
	$.ajax({
            type : 'post',
            url : 'http://'+(window.location.host) + '/logout.php', //Here you will fetch records 
            data :  'username='+ ''+'&password='+'', //Pass $id
            success : function(data){
				
				window.location= 'http://'+(window.location.host) + '/index';
            
            }
        });	  
	  
    
  } else {
    swal("Your login state is safe!");
  }
});
	
	
}




// Function to load notifications after the page has loaded
document.addEventListener("DOMContentLoaded", function() {
    fetchNotifications(); // Fetch the notifications after the page load
	
});

function fetchNotifications() {
    //console.log('Fetching Notifications...');
    fetch('fetch_notifications.php')
        .then(response => response.json())
        .then(data => {
            //console.log('Data received:', data); // Log the response data
            if (data.error) {
                //console.error(data.error);
                return;
            }

            // Update the notification badge count
            // Select the badge element inside the notification
			const notificationBadge = document.querySelector('.dropdown .notification.badge-pill');
			
			if (notificationBadge) {
				// Update the badge's text content
				const totalNotifications = data.length; // Assuming each notification is unread
				notificationBadge.textContent = totalNotifications; // Replace with the dynamic value you want
			}

            // Update the notifications dropdown
            const notificationList = document.querySelector('.noti-list');
            notificationList.innerHTML = ''; // Clear previous notifications

            // Log to check if notifications are being rendered
            data.forEach(notification => {
                //console.log('Rendering notification:', notification); // Log each notification
                const notificationElement = document.createElement('a');
                notificationElement.href = notification.link; // Use dynamic link
                notificationElement.classList.add('dropdown-item', 'noti-container', 'py-3', 'border-bottom', 'border-bottom-blue-grey', 'border-bottom-lighten-4');
                notificationElement.innerHTML = `
                    <i class="ft-bell info float-left d-block font-large-1 mt-1 mr-2"></i>
                    <span class="noti-wrapper">
                        <span class="noti-title line-height-1 d-block text-bold-400 info">${notification.dateTime}</span>
                        <span class="noti-text">${notification.message}</span>
                    </span>
                `;
                notificationList.appendChild(notificationElement);
            });
        })
        .catch(error => console.error('Error fetching notifications:', error));
}


$(document).ready(function() {
    // Use AJAX to load the sidebar_menu.php file into the sidebar
    $('.sidebar-content').load('sidebar_menu_backup.php', function(response, status, xhr) {
      if (status == "error") {
        // Handle error if the AJAX request fails
        console.log("Error loading sidebar menu: " + xhr.status + " " + xhr.statusText);
      }else{
		loadContent();
      }
    });
  });



function loadContent() {
   // Make AJAX request to the server-side PHP script
   $.ajax({
            url: 'load_taskno.php',  // Replace with your PHP file's URL
            type: 'GET',  // Or 'POST' depending on your needs
            dataType: 'json',  // Expecting JSON response
            success: function(response) {
                // Once the data is returned, display it in the HTML elements
                //$('#totalApplicationsToSupervise').text(response.totalApplicationsToSupervise);
				const totalinleaveapproval = (response.totalApplicationsToSupervise + response.totalApplicationsToApprove);
				if(totalinleaveapproval > 0){
					$('#leave-approval').text(totalinleaveapproval);
					$('#leave-approval').css('display', 'inline');
				}
				if(response.totalJoiningLeaveApprove > 0){
					$('#leave-joining-approval').text(response.totalJoiningLeaveApprove);
					$('#leave-joining-approval').css('display', 'inline');
				}
				if(response.totalAllowedLeaveApp > 0){
					$('#allowed-leave-applications').text(response.totalAllowedLeaveApp);
					$('#allowed-leave-applications').css('display', 'inline');
				}
				if(response.totalApprovedLeaveApp > 0){
					$('#manage-approved-leaves').text(response.totalApprovedLeaveApp);
					$('#manage-approved-leaves').css('display', 'inline');
				}
				if(response.totalLeaveDeductHis > 0){
					$('#previous-leave-regular-info-approve').text(response.totalLeaveDeductHis);
					$('#previous-leave-regular-info-approve').css('display', 'inline');
				}
				if(response.totalTask > 0){
					$('#totalTask').text(response.totalTask);
					$('#totalTask').css('display', 'inline');
				}
            },
            error: function(xhr, status, error) {
                // Handle any errors (optional)
                console.log('Error:', error);
            }
        });
}

</script>


<?php
global $start; // Declare $start as a global variable

$end = microtime(true);
$time = number_format(($end - $start), 2);

?>

<div class="pull-right icons-group" style="text-align: center;">

<?php //echo 'This page loaded in ', $time, ' seconds'; ?>

</div>
