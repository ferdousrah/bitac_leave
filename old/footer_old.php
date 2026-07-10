


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
    <!-- Minimal sidebar script - handles ONLY collapse and mobile toggle -->
    <script src="<?php echo $baseURL; ?>app-assets/js/app-sidebar-minimal.js" type="text/javascript" data-turbo-eval="false"></script>

    <!-- NEW Custom Sidebar Menu JavaScript - handles menu expand/collapse -->
    <script src="<?php echo $baseURL; ?>app-assets/js/custom-sidebar-menu.js" type="text/javascript" data-turbo-eval="false"></script>
    <script src="<?php echo $baseURL; ?>app-assets/js/notification-sidebar.js" type="text/javascript" data-turbo-eval="false"></script>
    <script src="<?php echo $baseURL; ?>app-assets/js/customizer.js" type="text/javascript" data-turbo-eval="false"></script>
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
            url : '<?php echo $baseURL; ?>logout.php', //Here you will fetch records
            data :  'username='+ ''+'&password='+'', //Pass $id
            success : function(data){

				window.location= '<?php echo $baseURL; ?>index.php';

            }
        });


  } else {
    swal("Your login state is safe!");
  }
});


}




// Function to load notifications after the page has loaded
// Use turbo:load for Turbo compatibility
document.addEventListener("turbo:load", function() {
    fetchNotifications(); // Fetch the notifications after the page load

});

// Also keep DOMContentLoaded for non-Turbo scenarios
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


// ========================================
// Custom Menu System - All logic is in custom-sidebar-menu.js
// ========================================
// No additional JavaScript needed here - the new menu handles everything automatically!

// ========================================
// New Custom Menu System - Load Sidebar
// ========================================

// Function to force dark blue gradient on sidebar background
function applySidebarGradient() {
    console.log('Applying dark blue gradient to sidebar...');
    $('.sidebar-background').css({
        'background': 'linear-gradient(180deg, #1a237e 0%, #0d47a1 50%, #01579b 100%)',
        'background-image': 'linear-gradient(180deg, #1a237e 0%, #0d47a1 50%, #01579b 100%)'
    });
    console.log('Dark blue gradient applied!');
}

$(document).ready(function() {
    console.log('Document ready, checking sidebar...');
    console.log('Sidebar content length:', $('.sidebar-content').length);

    if ($('.sidebar-content').length === 0) {
        console.error('ERROR: .sidebar-content element not found!');
        return;
    }

    // Check if the actual menu exists (not just scrollbar HTML)
    if ($('.sidebar-content .custom-sidebar-menu').length === 0) {
        console.log('Menu not found, loading sidebar_menu_new.php...');
        // Load the NEW custom menu
        $('.sidebar-content').load('sidebar_menu_new.php', function(response, status, xhr) {
            console.log('Sidebar load status:', status);
            if (status === "success") {
                console.log('Sidebar loaded successfully!');
                console.log('Response length:', response.length);
                // Initialize the custom menu AFTER sidebar is loaded
                if (window.CustomSidebarMenu) {
                    window.CustomSidebarMenu.init();
                } else {
                    console.error('ERROR: CustomSidebarMenu not found!');
                }
                loadContent(); // Load badge counts

                // Force dark blue gradient on sidebar background
                applySidebarGradient();
            } else if (status === "error") {
                console.error('ERROR loading sidebar:', xhr.status, xhr.statusText);
                console.error('Response:', response);
            }
        });
    } else {
        // Already loaded from cache
        console.log('Menu found in cache, initializing...');
        if (window.CustomSidebarMenu) {
            window.CustomSidebarMenu.init();
        }
        loadContent();

        // Force dark blue gradient on sidebar background
        applySidebarGradient();
    }
});

// Note: turbo:load event listener moved below to avoid duplication

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

// ========================================
// Turbo Configuration for Smooth Navigation
// ========================================

// Global flag to prevent duplicate event listener registration
if (typeof window._turboEventsInitialized === 'undefined') {
    window._turboEventsInitialized = false;
}

// Only register Turbo event listeners ONCE
if (!window._turboEventsInitialized) {
    console.log('Registering Turbo event listeners...');

    // Debug: Log Turbo navigation events
    document.addEventListener('turbo:before-visit', function(event) {
        console.log('Turbo: Before visit to', event.detail.url);
    });

    // Turbo render event - fired when page is rendered (from cache or fresh)
    document.addEventListener('turbo:render', function() {
        console.log('Turbo: Page rendered (preview from cache)');

        // Restore sidebar menu state
        const openMenusJson = sessionStorage.getItem('openSidebarMenus');
        if (openMenusJson) {
            try {
                const openMenus = JSON.parse(openMenusJson);
                openMenus.forEach(function(href) {
                    const menuItem = $('.app-sidebar a[href="' + href + '"]').parent();
                    if (menuItem.hasClass('has-sub')) {
                        menuItem.addClass('open');
                    }
                });
            } catch (e) {
                console.log('Error restoring menu state:', e);
            }
        }
    });

    // Reinitialize DataTables and other plugins after Turbo navigation
    document.addEventListener('turbo:load', function() {
        console.log('Turbo: Page fully loaded');

        // Update badge counts
        if ($('.sidebar-content').html().trim() !== '') {
            loadContent();
        }

        // Reinitialize menu highlighting after Turbo navigation
        if (window.CustomSidebarMenu && $('.custom-sidebar-menu').length > 0) {
            console.log('Turbo: Reinitializing menu highlighting');
            window.CustomSidebarMenu.reinit();
        }

        // Force dark blue gradient on sidebar background after navigation
        applySidebarGradient();

    // Destroy existing DataTables instances before reinitializing
    if ($.fn.DataTable) {
        $('.table').each(function() {
            if ($.fn.DataTable.isDataTable(this)) {
                $(this).DataTable().destroy();
            }
        });
    }

    // Reinitialize Select2 if exists (but only in main content, not sidebar)
    if ($.fn.select2) {
        $('.main-content .select2, .main-panel .select2').select2();
    }

    // Reinitialize tooltips (but only in main content, not sidebar)
    if ($.fn.tooltip) {
        $('.main-content [data-toggle="tooltip"], .main-panel [data-toggle="tooltip"]').tooltip();
    }

        // Scroll to top on page change
        window.scrollTo(0, 0);
    });

    // Before cache - clean up to prevent memory leaks
    document.addEventListener('turbo:before-cache', function() {
        // Destroy DataTables before caching
        if ($.fn.DataTable) {
            $('.table').each(function() {
                if ($.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable().destroy();
                }
            });
        }

        // Remove Select2 (but only in main content)
        if ($.fn.select2) {
            try {
                $('.main-content .select2, .main-panel .select2').select2('destroy');
            } catch(e) {
                console.log('Select2 destroy error:', e);
            }
        }

        // Remove tooltips (but only in main content)
        // Check if using Bootstrap tooltip (has 'dispose') or jQuery UI tooltip (has 'destroy')
        try {
            if (typeof $('.main-content [data-toggle="tooltip"]').tooltip === 'function') {
                $('.main-content [data-toggle="tooltip"], .main-panel [data-toggle="tooltip"]').each(function() {
                    try {
                        // Try jQuery UI destroy first
                        if ($(this).tooltip('instance')) {
                            $(this).tooltip('destroy');
                        }
                    } catch(e) {
                        // Try Bootstrap dispose
                        try {
                            $(this).tooltip('dispose');
                        } catch(e2) {
                            // Ignore if neither works
                        }
                    }
                });
            }
        } catch(e) {
            console.log('Tooltip cleanup error:', e);
        }

        // Preserve sidebar menu state
        // Store which menus are open before caching
        const openMenus = [];
        $('.app-sidebar .has-sub.open').each(function() {
            openMenus.push($(this).find('a').first().attr('href'));
        });
        if (openMenus.length > 0) {
            sessionStorage.setItem('openSidebarMenus', JSON.stringify(openMenus));
        }
    });

    // Show loading bar during navigation
    document.addEventListener('turbo:before-fetch-request', function() {
        // You can add a loading spinner here if desired
        // e.g., $('.loading-overlay').show();
    });

    // Handle form submissions with Turbo
    document.addEventListener('turbo:submit-end', function(event) {
        // Re-enable submit buttons after form submission
        const form = event.detail.formSubmission.formElement;
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.removeAttribute('disabled');
        }
    });

    // Error handling for Turbo failures
    document.addEventListener('turbo:fetch-request-error', function(event) {
        console.error('Turbo fetch error:', event);
        // If Turbo fails, fall back to regular navigation
        event.preventDefault();
        window.location.href = event.detail.fetchOptions.url;
    });

    // Mark as initialized
    window._turboEventsInitialized = true;
    console.log('Turbo event listeners registered successfully');
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
