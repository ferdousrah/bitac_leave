<?php
session_start();

// Record start time for performance profiling
$start = microtime(true);

// Include connection file
include('connection.php');

// Check if the user is logged in, otherwise redirect to the login page
if (!isset($_SESSION['username'])) {
    echo "<script>alert('You are not logged in! Please log in first.')</script>";
    echo "<script>window.location='index.php'</script>";
    exit;
}




function ShowBangladeshDate()
{

$hour = gmdate("H");

$minute = gmdate("i");

$seconds = gmdate("s");

$day = gmdate("d");

$month = gmdate("m");

$year = gmdate("Y");

// This is the offset from the server time to Bangladesh time.

$hour = $hour + 6;

//return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));

return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));

}


function ShowBangladeshDate2()
{

$hour = gmdate("H");

$minute = gmdate("i");

$seconds = gmdate("s");

$day = gmdate("d");

$month = gmdate("m");

$year = gmdate("Y");

// This is the offset from the server time to Bangladesh time.

$hour = $hour + 6;

//return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));

return date("m/d/Y", mktime ($hour,$minute,$seconds,$month,$day,$year));

}


function dateDiffInDays($date1, $date2) 
  {
      // Calculating the difference in timestamps
      $diff = strtotime($date2) - strtotime($date1);
  
      // 1 day = 24 hours
      // 24 * 60 * 60 = 86400 seconds
      return abs(round($diff / 86400));
  }


// Get user information securely using prepared statements to prevent SQL injection
$stmt = $con->prepare("SELECT * FROM user_list WHERE user_id = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$getUserInfoQRW = $stmt->get_result()->fetch_assoc();
$stmt->close();


// Check for unread and unimportant notifications



// Check for unread and important notifications
$stmt = $con->prepare("SELECT * FROM notification WHERE userID = ? AND isRead = 0 AND isImportant = 1 ORDER BY notificationID DESC LIMIT 10");
$stmt->bind_param("s", $_SESSION['userID']);
$stmt->execute();
$getMyMostImpNotificationsQ = $stmt->get_result();
$stmt->close();

// Get employee details (organization information)
$stmt = $con->prepare("SELECT employee_list.organization_id, organization.organization_name FROM employee_list INNER JOIN organization ON employee_list.organization_id = organization.id WHERE employee_list.id = ?");
$stmt->bind_param("s", $getUserInfoQRW['employee_id']);
$stmt->execute();
$getEmployeeDetailsQRW = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Optional: Debugging, check for query errors (remove in production)
/*
if ($con->error) {
    echo "Database error: " . $con->error;
    exit;
}*/

?>

<!DOCTYPE html>
<html lang="en" class="loading">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="description" content="Bitac Leave Management Software by Technocrats">
    <meta name="keywords" content="leave, increment">
    <meta name="author" content="Technocrats">
    <title><?php echo $getSettingsDetailsQRW['software_title']; ?></title>
    <link rel="apple-touch-icon" sizes="60x60" href="<?php echo $baseURL; ?>app-assets/img/ico/apple-icon-60.png">
    <link rel="apple-touch-icon" sizes="76x76" href="<?php echo $baseURL; ?>app-assets/img/ico/apple-icon-76.png">
    <link rel="apple-touch-icon" sizes="120x120" href="<?php echo $baseURL; ?>app-assets/img/ico/apple-icon-120.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo $baseURL; ?>app-assets/img/ico/apple-icon-152.png">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $baseURL; ?>uploads/bitac-logo-inner.png">
    <link rel="shortcut icon" type="image/png" href="<?php echo $baseURL; ?>uploads/bitac-logo-inner.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link href="//fonts.googleapis.com/css?family=Rubik:300,400,500,700,900|Montserrat:300,400,500,600,700,800,900" rel="stylesheet">
    <!-- BEGIN VENDOR CSS-->
    <!-- font icons-->
    <link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/fonts/feather/style.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/fonts/simple-line-icons/style.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/fonts/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/vendors/css/perfect-scrollbar.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/vendors/css/prism.min.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/vendors/css/toastr.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/vendors/css/sweetalert2.min.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/vendors/css/tables/datatable/datatables.min.css">
    <!-- END VENDOR CSS-->
	<link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/vendors/css/toastr.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/vendors/css/sweetalert2.min.css">
    <!-- BEGIN APEX CSS-->
    <link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/css/app.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/css/custom-sidebar-menu.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $baseURL; ?>app-assets/css/sidebar-gradient-override.css">

	<link href="//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

	<script src="//kit.fontawesome.com/4d317fe9d1.js" crossorigin="anonymous"></script>

	<link href="//fonts.maateen.me/siyam-rupali/font.css" rel="stylesheet">


    <!-- END APEX CSS-->
    <!-- BEGIN Page Level CSS-->
    <!-- END Page Level CSS-->
    <!-- BEGIN Custom CSS-->
	<style>
	table th
	{
	  font-size: <?php echo $getSettingsDetailsQRW['table_heading_font_size']; ?>px;
	  text-transform: <?php echo $getSettingsDetailsQRW['form_heading_text_transform']; ?>;
	}
	table td
	{
	  font-size: <?php echo $getSettingsDetailsQRW['table_data_font_size']; ?>px;
	}
	.label-control
	{
	  font-size: <?php echo $getSettingsDetailsQRW['form_label_font_size']; ?>px;
	  text-transform: <?php echo $getSettingsDetailsQRW['form_label_text_transform']; ?>;
	}
	input[type="text"]
	{
		font-size:<?php echo $getSettingsDetailsQRW['form_input_font_size']; ?>px;
	}
	optgroup { font-size:<?php echo $getSettingsDetailsQRW['form_input_font_size']; ?>px; }
	</style>

	<style>
	.form-group-bg {
	background-color: #feefef;
	}

	body {
		font-family: 'SiyamRupali', Arial, sans-serif !important;
	}


	.table-container {
		  height: 300px; /* Set the desired height */
		  overflow: auto; /* Enable scrolling */
		}


		.table-container table thead {
		  position: sticky;
		  top: 0;
		  background-color: #f8f9fa; /* Customize as needed */
		}


		/* Primary Label */
.badge-primary {
  background-color: #007bff;
  padding: 5px 10px;
  color: #fff;
}

/* Secondary Label */
.badge-secondary {
  background-color: #6c757d;
  padding: 5px 10px;
  color: #fff;
}

/* Success Label */
.badge-success {
  background-color: #28a745;
  padding: 5px 10px;
  color: #fff;
}

/* Danger Label */
.badge-danger {
  background-color: #dc3545;
  padding: 5px 10px;
  color: #fff;
}

/* Warning Label */
.badge-warning {
  background-color: #ffc107;
  padding: 5px 10px;
  color: #fff;
}

/* Info Label */
.badge-info {
  background-color: #17a2b8;
  padding: 5px 10px;
  color: #fff;
}

/* Light Label */
.badge-light {
  background-color: #f8f9fa;
  padding: 5px 10px;
  color: #000;
}

/* Dark Label */
.badge-dark {
  background-color: #343a40;



	</style>

	<style>
        /* CSS for the button */
        #mobile-tab-button {
            display: none; /* Initially hide the button */
        }

        /* Media query to show the button on mobile and tablet devices */
        @media (max-width: 1023px) {
            #mobile-tab-button {
                display: block; /* Show the button on screens up to 1023px width */
            }
        }
    </style>

	<!-- Turbo Progress Bar Styling -->
	<style>
	.turbo-progress-bar {
	  position: fixed;
	  display: block;
	  top: 0;
	  left: 0;
	  height: 3px;
	  background: #0076ff;
	  z-index: 9999;
	  transition: width 300ms ease-out, opacity 150ms 150ms ease-in;
	  transform: translate3d(0, 0, 0);
	}

	/* Fix sidebar white space issue */
	.app-sidebar {
	  display: flex;
	  flex-direction: column;
	  height: 100vh;
	  overflow: hidden;
	}

	.sidebar-content {
	  flex: 1;
	  overflow-y: auto;
	  overflow-x: hidden;
	  min-height: 0;
	}

	/* Ensure nav-container fills the space */
	.sidebar-content .nav-container {
	  min-height: 100%;
	}

	/* Remove white background from sidebar-background overlay - all color variations */
	.app-sidebar .sidebar-background:after,
	.app-sidebar[data-background-color] .sidebar-background:after,
	.app-sidebar[data-background-color='white'] .sidebar-background:after,
	.app-sidebar[data-background-color='king-yna'] .sidebar-background:after,
	.app-sidebar[data-background-color='ibiza-sunset'] .sidebar-background:after,
	.app-sidebar[data-background-color='flickr'] .sidebar-background:after,
	.app-sidebar[data-background-color='purple-bliss'] .sidebar-background:after,
	.app-sidebar[data-background-color='man-of-steel'] .sidebar-background:after,
	.app-sidebar[data-background-color='purple-love'] .sidebar-background:after {
	  display: none !important;
	  opacity: 0 !important;
	  background: transparent !important;
	}

	/* Sidebar background - Dark blue gradient instead of image */
	.app-sidebar .sidebar-background,
	.app-sidebar[data-background-color] .sidebar-background,
	.app-sidebar[data-background-color='white'] .sidebar-background,
	.app-sidebar[data-background-color='king-yna'] .sidebar-background,
	.app-sidebar[data-background-color='ibiza-sunset'] .sidebar-background,
	.app-sidebar[data-background-color='flickr'] .sidebar-background,
	.app-sidebar[data-background-color='purple-bliss'] .sidebar-background,
	.app-sidebar[data-background-color='man-of-steel'] .sidebar-background,
	.app-sidebar[data-background-color='purple-love'] .sidebar-background {
	  background: linear-gradient(180deg, #1a237e 0%, #0d47a1 50%, #01579b 100%) !important;
	  background-image: none !important;
	}

	/* Remove white background from navigation menu items */
	.app-sidebar .nav-container,
	.app-sidebar .navigation,
	.app-sidebar .navigation-main,
	.app-sidebar .navigation li,
	.app-sidebar .navigation li > a,
	.app-sidebar .navigation li.open > a,
	.app-sidebar .navigation li ul,
	.app-sidebar .navigation li ul li,
	.app-sidebar .navigation li ul li a,
	.app-sidebar .navigation .menu-content,
	.app-sidebar .navigation .menu-content li,
	.app-sidebar .navigation .menu-content li > a,
	.sidebar-content,
	.sidebar-content * {
	  background-color: transparent !important;
	  background: transparent !important;
	}

	/* ========================================
	   SIDEBAR VISIBILITY FIX
	   ======================================== */

	/* Ensure sidebar is visible on desktop */
	@media (min-width: 992px) {
		.app-sidebar {
			display: flex !important;
			visibility: visible !important;
			opacity: 1 !important;
		}

		.nav-toggle {
			display: block !important;
		}
	}

	/* Mobile sidebar behavior */
	@media (max-width: 991px) {
		/* Ensure sidebar can slide off-screen properly */
		.app-sidebar {
			position: fixed !important;
			z-index: 1050 !important;
		}

		/* Make sure hide-sidebar class works on mobile */
		.app-sidebar.hide-sidebar {
			-webkit-transform: translate3d(-260px, 0, 0) !important;
			transform: translate3d(-260px, 0, 0) !important;
		}
	}
	</style>
    <!-- END Custom CSS-->

    <!-- Turbo for smooth page navigation without refresh -->
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@7.3.0/dist/turbo.es2017-umd.js"></script>

    <!-- Configure Turbo to disable preview caching to prevent "double load" appearance -->
    <meta name="turbo-cache-control" content="no-preview">
  </head>
  <body data-col="2-columns" class=" 2-columns ">
    <!-- ////////////////////////////////////////////////////////////////////////////-->
    <div class="wrapper">


      <!-- main menu-->
      <!--.main-menu(class="#{menuColor} #{menuOpenType}", class=(menuShadow == true ? 'menu-shadow' : ''))-->
      <div id="sidebar-permanent" data-turbo-permanent data-active-color="white" data-background-color="<?php echo $getSettingsDetailsQRW['sidebar_color']; ?>" data-image="<?php echo $baseURL; ?>uploads/<?php echo $getSettingsDetailsQRW['sidebar_bg_img']; ?>" class="app-sidebar">
        <!-- main menu header-->
        <!-- Sidebar Header starts-->
        <div class="sidebar-header" style="padding-bottom: 20px;background-color: #fff">
          <div class="logo clearfix" style="background-color: #fff;margin-top: -10px;"><a href="dashboard" class="logo-text float-left">
              <div class="logo-img" style="margin-left: 60px;"><img height="60" src="<?php echo $baseURL; ?>uploads/<?php echo $getSettingsDetailsQRW['header_logo']; ?>"/></div><span style="font-size:14px;" class="text align-middle"></span></a><a id="sidebarToggle" href="javascript:;" class="nav-toggle d-none d-sm-none d-md-none d-lg-block"><i data-toggle="expanded" class="ft-toggle-right toggle-icon"></i></a><a id="sidebarClose" href="javascript:;" class="nav-close d-block d-md-block d-lg-none d-xl-none"><i class="ft-x"></i></a></div>
        </div>
        <!-- Sidebar Header Ends-->
        <!-- / main menu header-->
        <!-- main menu content-->
        <div class="sidebar-content">
		</div>
        <!-- main menu content-->
        <div class="sidebar-background"></div>
        <!-- main menu footer-->
        <!-- include includes/menu-footer-->
        <!-- main menu footer-->
      </div>
      <!-- / main menu-->


      <!-- Navbar (Header) Starts-->
      <nav id="navbar-permanent" data-turbo-permanent class="navbar navbar-expand-lg navbar-light bg-faded"  style="background-color: #fff; margin-bottom: 20px;">
        <div class="container-fluid">
          <div class="navbar-header">
            <button type="button" data-toggle="collapse" class="navbar-toggle d-lg-none float-left"><span class="sr-only">Toggle navigation</span><span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span></button>
            <form role="search" class="navbar-form navbar-right mt-1">
              <div class="position-relative has-icon-right">
                <!--<input type="text" placeholder="Search" class="form-control round"/>
                <div class="form-control-position"><i class="ft-search"></i></div>-->
				<span style="font-size: 18px;">স্বাগতম! <?php echo '<span class="bg-success" style="color:#fff">&nbsp;'.$getUserInfoQRW['full_name'].', '.$getEmployeeDetailsQRW['organization_name'].'&nbsp;</span>'; ?></span>

              </div>
            </form>
          </div>
          <div class="navbar-container">
            <div id="navbarSupportedContent" class="collapse navbar-collapse">
              <ul class="navbar-nav">
                <!--<li class="nav-item mr-2"><a id="navbar-fullscreen" href="javascript:;" class="nav-link apptogglefullscreen"><i class="ft-maximize font-medium-3 blue-grey darken-4"></i>
                    <p class="d-none">fullscreen</p></a></li>-->
                
                <li class="dropdown nav-item"><a id="dropdownBasic2" href="#" data-toggle="dropdown" class="nav-link position-relative dropdown-toggle"><i class="ft-bell font-medium-3 blue-grey darken-4"></i><span class="notification badge badge-pill badge-danger">0</span>
                    <p class="d-none">Notifications</p></a>
                  <div class="notification-dropdown dropdown-menu dropdown-menu-right">
                    <div class="noti-list">
					
					
					
					</div><a class="noti-footer primary text-center d-block border-top border-top-blue-grey border-top-lighten-4 text-bold-400 py-1">Read All Notifications</a>
                  </div>
                </li>
                <li class="dropdown nav-item"><a id="dropdownBasic3" href="#" data-toggle="dropdown" class="nav-link position-relative dropdown-toggle"><i class="ft-user font-medium-3 blue-grey darken-4"></i>
                    <p class="d-none">User Settings</p></a>
                  <div ngbdropdownmenu="" aria-labelledby="dropdownBasic3" class="dropdown-menu dropdown-menu-right"><a href="./my_profile?menuslug=dashboard" class="dropdown-item py-1"><i class="ft-edit mr-2"></i><span>My Account</span></a>
                    <div class="dropdown-divider"></div><a onClick="logout()" class="dropdown-item" data-turbo="false"><i class="ft-power mr-2"></i><span>Logout</span></a>
                  </div>
                </li>
                
              </ul>
            </div>
          </div>
        </div>
      </nav>
      <!-- Navbar (Header) Ends-->


