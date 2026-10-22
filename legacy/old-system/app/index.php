<?php
//phpinfo();
include('connection.php');


// ip check
function getClientIP() {
    if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
        // Check if IP is from shared internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Check if IP is passed from a proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
        // Check if IP is from remote address
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        // If no valid IP found
        $ip = 'Unknown IP';
    }
    
    // In case of multiple forwarded IPs (e.g., "123.45.67.89, 98.76.54.32")
    // Use the first IP (client's original IP)
    if (strpos($ip, ',') !== false) {
        $ip = explode(',', $ip)[0];
    }
    
    return trim($ip);
}

// Get the client's IP address
$clientIP = getClientIP();

/*

//echo $clientIP;

// Static IP to compare with
$staticIP = '103.103.99.107';  // Example static IP

// Compare client IP with the static IP
if ($clientIP === $staticIP) {
    //echo "IP matched! The client IP is: " . $clientIP;
} else {
    echo "<script>window.location='maintenance-page.html'</script>";
}



//


*/

?>

<!DOCTYPE html>
<html lang="en" class="loading">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="description" content="">
    <meta name="keywords" content="">
    <meta name="author" content="Technocrats">
    <title>Login</title>
    <link rel="apple-touch-icon" sizes="60x60" href="app-assets/img/ico/apple-icon-60.png">
    <link rel="apple-touch-icon" sizes="76x76" href="app-assets/img/ico/apple-icon-76.png">
    <link rel="apple-touch-icon" sizes="120x120" href="app-assets/img/ico/apple-icon-120.png">
    <link rel="apple-touch-icon" sizes="152x152" href="app-assets/img/ico/apple-icon-152.png">
    <link rel="shortcut icon" type="image/x-icon" href="app-assets/img/ico/favicon.ico">
    <link rel="shortcut icon" type="image/png" href="app-assets/img/ico/favicon-32.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link href="//fonts.googleapis.com/css?family=Rubik:300,400,500,700,900|Montserrat:300,400,500,600,700,800,900" rel="stylesheet">
    <!-- BEGIN VENDOR CSS-->
    <!-- font icons-->
    <link rel="stylesheet" type="text/css" href="app-assets/fonts/feather/style.min.css">
    <link rel="stylesheet" type="text/css" href="app-assets/fonts/simple-line-icons/style.css">
    <link rel="stylesheet" type="text/css" href="app-assets/fonts/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="app-assets/vendors/css/perfect-scrollbar.min.css">
    <link rel="stylesheet" type="text/css" href="app-assets/vendors/css/prism.min.css">
    <link rel="stylesheet" type="text/css" href="app-assets/vendors/css/toastr.css">
    <!-- END VENDOR CSS-->
    <!-- BEGIN APEX CSS-->
    <link rel="stylesheet" type="text/css" href="app-assets/css/app.css">
    <!-- END APEX CSS-->
    <!-- BEGIN Page Level CSS-->
    <!-- END Page Level CSS-->
    <!-- BEGIN Custom CSS-->
    <!-- END Custom CSS-->
  </head>
  <body data-col="1-column" class="1-column blank-page blank-page">
    <!-- ////////////////////////////////////////////////////////////////////////////-->
    <div class="wrapper">
      <div class="main-panel">
        <div class="main-content">
          <div class="content-wrapper" style="background: rgb(173,173,175);background: linear-gradient(90deg, rgba(173,173,175,1) 0%, rgba(239,239,255,1) 35%, rgba(153,177,182,1) 100%);background-size: cover;box-shadow: inset 0 0 0 2000px rgba(0, 0, 0, 0.7);">
            <!--Login Page Starts-->
            <section id="login">
              <div class="container-fluid">
                <div class="row full-height-vh">
                  <div class="col-sm-12" style="text-align: center; color: #fff; margin-top: 10%; font-size: 32px;">
                    বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)
                    <br><br>
                    <span style="font-size: 22px;">Leave Management</span>
                  </div>

                  <div class="col-12 d-flex align-items-center justify-content-center" style="margin-top: -20%;">
                    <div class="card gradient-indigo-blue text-center width-400">
                      <div class="card-body">
                        <div class="card-block">
                          <h2 class="white">Login</h2>
                          <form class="form-login" name="form" id="form" action="login_action.php" method="POST">
                            <div class="form-group">
                              <div class="col-md-12">
                                <input type="text" class="form-control" name="username" id="username" placeholder="Username" >
                              </div>
                            </div>

                            <div class="form-group">
                              <div class="col-md-12">
                                <input type="password" class="form-control" name="password" id="password" placeholder="Password" >
                              </div>
                            </div>

                            <div class="form-group">
                              <div class="row">
                                <div class="col-md-12">
                                  <div class="custom-control custom-checkbox mb-2 mr-sm-2 mb-sm-0 ml-3">
                                    <input type="checkbox" class="custom-control-input" id="rememberme" name="rememberme">
                                    <label class="custom-control-label rememberme float-left" for="rememberme">Remember Me</label>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <div class="form-group">
                              <div class="col-md-12">
                                <button type="submit" name="submit" id="submit" class="btn btn-pink btn-block btn-raised">Submit</button>
                                <button type="reset" class="btn btn-secondary btn-block btn-raised">Reset</button>
                              </div>
                            </div>
                          </form>
                        </div>
                      </div>
                      <div class="card-footer">
                        <!-- <div class="float-left"><a (click)="onForgotPassword()" class="white">Recover Password</a></div> -->
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>
            <!--Login Page Ends-->
          </div>
        </div>
      </div>
    </div>
    <!-- ////////////////////////////////////////////////////////////////////////////-->

    <!-- BEGIN VENDOR JS-->
    <script src="app-assets/vendors/js/core/jquery-3.2.1.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/core/popper.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/core/bootstrap.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/perfect-scrollbar.jquery.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/prism.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/jquery.matchHeight-min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/screenfull.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/pace/pace.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/toastr.min.js" type="text/javascript"></script>
    <!-- BEGIN PAGE VENDOR JS-->
    <!-- END PAGE VENDOR JS-->
    <!-- BEGIN APEX JS-->
    <script src="app-assets/js/app-sidebar.js" type="text/javascript"></script>
    <script src="app-assets/js/notification-sidebar.js" type="text/javascript"></script>
    <script src="app-assets/js/customizer.js" type="text/javascript"></script>
    <!-- END APEX JS-->
    <!-- BEGIN PAGE LEVEL JS-->
    <script src="app-assets/js/toastr.min.js" type="text/javascript"></script>
	<script src="app-assets/js/login.js" type="text/javascript"></script>
    <!-- END PAGE LEVEL JS-->
  </body>
</html>













