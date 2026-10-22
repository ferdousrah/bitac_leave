<?php
$getMainMenuQuery = mysqli_query($con, "select distinct access_permission.module_id,modules.display_order from access_permission inner join modules on access_permission.module_id=modules.dataID where access_permission.user_id='$getUserInfoQRW[dataID]' order by modules.display_order asc");

/// ছুটির সুপারিশ ও অনুমোদন

$totalLeaveModTask = 0;

$loggedUserID = $_SESSION['userID'];

$checkForAppSettingsQ = mysqli_query($con, "select * from leave_approval_signatory where employeeID='$getUserInfoQRW[employee_id]'");
$checkForAppSettingsQNumRows = mysqli_num_rows($checkForAppSettingsQ);



$getSupervisorApplication = mysqli_query($con, "select * from `leave_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSupervisor=1 and isApproved=0 order by leaveApplicationID desc");

$getApplicationFromAdmin = mysqli_query($con,"select * from `leave_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSentbyAdmin=1 and isSupervisor!=1 and isApproved=0 order by leaveApplicationID desc");


$totalInLeaveApprove = 0;

// recommendation

while($empRow=mysqli_fetch_array($getSupervisorApplication))
	{											
																						
		if($empRow['prevSignatory'] == 0){

			$proceed = 1;
											
		}else{	
			

				$prevserial = $empRow['serial'] - 1;


				$checkPrevSognatorySignedQ = mysqli_query($con, "select * from `leave_data_for_approval` where leaveApplicationID='$empRow[leaveApplicationID]' and signatory='$empRow[prevSignatory]' and isApproved=1  and serial='$prevserial'");
				$checkPrevSognatorySignedQNumRows = mysqli_num_rows($checkPrevSognatorySignedQ);

				if($checkPrevSognatorySignedQNumRows > 0){


				$proceed = 1;												
												
				}else{
												
				$proceed = 0;
												
				}
											
		}


		if($proceed == 1){

			// proposed

			$totalInLeaveApprove = $totalInLeaveApprove + 1;

			$totalLeaveModTask++;													

			// inside while

			$proceed = 0;

		} // end of if
} // end of while


// approval

while($empRow=mysqli_fetch_array($getApplicationFromAdmin))
	{											
																						
		if($empRow['prevSignatory'] == 0){

			$proceed = 1;
											
		}else{	
			

				$prevserial = $empRow['serial'] - 1;


				$checkPrevSognatorySignedQ = mysqli_query($con, "select * from `leave_data_for_approval` where leaveApplicationID='$empRow[leaveApplicationID]' and signatory='$empRow[prevSignatory]' and isApproved=1  and serial='$prevserial'");
				$checkPrevSognatorySignedQNumRows = mysqli_num_rows($checkPrevSognatorySignedQ);

				if($checkPrevSognatorySignedQNumRows > 0){


				$proceed = 1;												
												
				}else{
												
				$proceed = 0;
												
				}
											
		}


		if($proceed == 1){

			// proposed

			$totalInLeaveApprove = $totalInLeaveApprove + 1;

			$totalLeaveModTask++;													

			// inside while

			$proceed = 0;

		} // end of if
} // end of while



// কর্মস্থলে যোগদানের সুপারিশ ও অনুমোদন

$getSupervisorApplication2 = mysqli_query($con, "select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSupervisor=1 and isApproved=0 order by leaveApplicationID desc");

$getApplicationFromAdmin2 = mysqli_query($con,"select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSentbyAdmin=1 and isSupervisor!=1 and isApproved=0 order by leaveApplicationID desc");





//
$totalJoiningLeaveApprove = 0;

while($empRow2=mysqli_fetch_array($getSupervisorApplication2))
										{
											

											
	if($empRow2['prevSignatory'] == 0){

		$proceed2 = 1;
											
	}else{
												


		$checkPrevSognatorySignedQ2 = mysqli_query($con, "select * from `leave_joining_data_for_approval` where leaveApplicationID='$empRow2[leaveApplicationID]' and signatory='$empRow2[prevSignatory]' and isApproved=1");
		$checkPrevSognatorySignedQNumRows2 = mysqli_num_rows($checkPrevSognatorySignedQ2);

		if($checkPrevSognatorySignedQNumRows2 > 0){

		$proceed2 = 1;												
												
		}else{
												
		$proceed2 = 0;
												
		}
											
	}


	if($proceed2 == 1){					

	// inside while

	$totalJoiningLeaveApprove = $totalJoiningLeaveApprove + 1;

	$totalLeaveModTask++;

	$proceed2 = 0;

	}


} // end of while



while($empRow2=mysqli_fetch_array($getApplicationFromAdmin2))
										{
											

											
	if($empRow2['prevSignatory'] == 0){

		$proceed2 = 1;
											
	}else{
												


		$checkPrevSognatorySignedQ2 = mysqli_query($con, "select * from `leave_joining_data_for_approval` where leaveApplicationID='$empRow2[leaveApplicationID]' and signatory='$empRow2[prevSignatory]' and isApproved=1");
		$checkPrevSognatorySignedQNumRows2 = mysqli_num_rows($checkPrevSognatorySignedQ2);

		if($checkPrevSognatorySignedQNumRows2 > 0){

		$proceed2 = 1;												
												
		}else{
												
		$proceed2 = 0;
												
		}
											
	}


	if($proceed2 == 1){					

	// inside while

	$totalJoiningLeaveApprove = $totalJoiningLeaveApprove + 1;

	$totalLeaveModTask++;

	$proceed2 = 0;

	}


} // end of while


// ছুটি সম্পাদনা

$checkModuleAccessQ = mysqli_query($con, "SELECT * FROM `access_permission` WHERE `user_id`='$getUserInfoQRW[dataID]' and `submodule_id`=149");
$checkModuleAccessQNumRows = mysqli_num_rows($checkModuleAccessQ);

if($checkModuleAccessQNumRows > 0){

$manageLeaveApp = 0;

$getAllAppApplicationQ = mysqli_query($con, "select * from `leave_data_for_approval` where isSupervisor=1 and isApproved=1 and isSentbyAdmin=0");
$getAllAppApplicationQNumRows = mysqli_num_rows($getAllAppApplicationQ);

$manageLeaveApp = $getAllAppApplicationQNumRows;

$totalLeaveModTask = $totalLeaveModTask + $getAllAppApplicationQNumRows;

}

// যোগদানের আবেদন সম্পাদনা


$checkModuleAccessQ2 = mysqli_query($con, "SELECT * FROM `access_permission` WHERE `user_id`='$getUserInfoQRW[dataID]' and `submodule_id`=150");
$checkModuleAccessQNumRows2 = mysqli_num_rows($checkModuleAccessQ2);

if($checkModuleAccessQNumRows2 > 0){

$manageLeaveJoiningApp = 0;

$getAllAppApplicationQ2 = mysqli_query($con, "select * from `leave_joining_data_for_approval` where isSupervisor=1 and isApproved=1");

while($empRow4=mysqli_fetch_array($getAllAppApplicationQ2))
	{

		$getApplicationTypeQ2 = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$empRow4[leaveApplicationID]'");
		$getApplicationTypeQRW2 = mysqli_fetch_assoc($getApplicationTypeQ2);

		if($empRow4['isSentbyAdmin'] == 0){ 

			if($getApplicationTypeQRW2['joiningType'] != 1){

				$manageLeaveJoiningApp = $manageLeaveJoiningApp + 1;

				$totalLeaveModTask++;

			}

		}

	}


}



// leave edit approval

$getLeaveApplicationsQNumRows = 0;

if($_SESSION['username'] == 'Saifullah' || $_SESSION['username'] == 'saifullah'){


	$getLeaveApplicationsQ = mysqli_query($con,"select * from leave_edit_data_for_approval where signatory='$getUserInfoQRW[employee_id]' and isApproved=0");
	$getLeaveApplicationsQNumRows = mysqli_num_rows($getLeaveApplicationsQ);

}else if($_SESSION['username'] == 'Mohsin' || $_SESSION['username'] == 'mohsin'){


	$getLeaveApplicationsQ = mysqli_query($con,"select * from leave_edit_data_for_approval where prevSignatory='872' and signatory='$getUserInfoQRW[employee_id]' and isApproved=0");
	$getLeaveApplicationsQNumRows = mysqli_num_rows($getLeaveApplicationsQ);

}


$totalLeaveModTask = $totalLeaveModTask + $getLeaveApplicationsQNumRows;


?>




<div class="sidebar-content">
          <div class="nav-container">
            <ul id="main-menu-navigation" data-menu="menu-navigation" class="navigation navigation-main">

			
              
			  <?php

                if(isset($_GET['menuslug']))
				{
				   $menuSlug = $_GET['menuslug'];
				}
				else if(isset($_POST['menuslug']))
				{
				   $menuSlug = $_POST['menuslug'];
				}


				while($menuRow=mysqli_fetch_array($getMainMenuQuery))
				{
					//$getSubModulesQ = mysqli_query($con, "select distinct submodule_id from access_permission where module_id='$menuRow[module_id]' and user_id='$getUserInfoQRW[dataID]' order by display_order asc");
					
					$getSubModulesQ = mysqli_query($con, "select distinct access_permission.submodule_id,submodules.display_order from access_permission inner join submodules on access_permission.submodule_id=submodules.dataID where access_permission.module_id='$menuRow[module_id]' and access_permission.user_id='$getUserInfoQRW[dataID]' order by submodules.display_order asc");
					$getSubModulesQNumRows = mysqli_num_rows($getSubModulesQ);
					
					$getSubMenusQ = mysqli_query($con, "select * from submodules where module_id='$menuRow[module_id]' order by display_order asc");
					$getSubMenusQNumRows = mysqli_num_rows($getSubMenusQ);
					
					// get Menu Details
					$getMenuDetailsQ = mysqli_query($con, "select * from modules where dataID='$menuRow[module_id]' order by display_order asc");
					$getMenuDetailsQRW = mysqli_fetch_assoc($getMenuDetailsQ);

					
					if($getMenuDetailsQRW['page_link']=='#')
					{
					   $mainMenuURL = $getMenuDetailsQRW['page_link'];
					}
					else
					{
					   $mainMenuURL = $baseURL.$getMenuDetailsQRW['page_link'];
					}
			  ?>
			  
			  <li class="<?php if($getSubMenusQNumRows>0){ echo "has-sub";} ?> nav-item <?php if($menuSlug==$getMenuDetailsQRW['slug']){ echo "active";} ?>"><a href="<?php echo $mainMenuURL; ?>?menuslug=<?php echo $getMenuDetailsQRW['slug']; ?>"><i class="<?php echo $getMenuDetailsQRW['icon']; ?>"></i><span data-i18n="" class="menu-title"><?php echo $getMenuDetailsQRW['module_name']; ?></span>
			  
			  <?php if($menuRow['module_id'] == 47 && $totalLeaveModTask > 0){ ?><span class="badge badge-pill badge-danger"><?php echo $totalLeaveModTask; ?></span> <?php } ?> </a>

			  <?php
                  if($getSubModulesQNumRows>0){
				  ?>


					<ul class="menu-content">
					  <?php
						while($sMenuRow=mysqli_fetch_array($getSubModulesQ))
							{
						      $getSubMenuDetailsQ = mysqli_query($con, "select * from submodules where dataID='$sMenuRow[submodule_id]'");
							  $getSubMenuDetailsQRW = mysqli_fetch_assoc($getSubMenuDetailsQ);

							  
							  if($getSubMenuDetailsQRW['page_link']=='#')
								{
							      $subMenuURL = $getSubMenuDetailsQRW['page_link'];
							  }
							  else
								{
							      $subMenuURL = $baseURL.$getSubMenuDetailsQRW['page_link'];
							  }
						?>
					  <li <?php if($menuSlug==$getSubMenuDetailsQRW['slug']){ echo "class='active'";} ?>><a href="<?php echo $subMenuURL; ?>?menuslug=<?php echo $getSubMenuDetailsQRW['slug']; ?>" class="menu-item"><?php echo $getSubMenuDetailsQRW['submodule_name']; ?> 
							
						<?php if($getSubMenuDetailsQRW['slug'] == 'leave-approval' && $totalInLeaveApprove > 0){ ?><span class="badge badge-pill badge-danger"><?php echo $totalInLeaveApprove; ?></span> <?php } ?>
						
						<?php if($getSubMenuDetailsQRW['slug'] == 'leave-joining-approval' && $totalJoiningLeaveApprove > 0){ ?><span class="badge badge-pill badge-danger"><?php echo $totalJoiningLeaveApprove; ?></span> <?php } ?>

						<?php if($getSubMenuDetailsQRW['slug'] == 'allowed-leave-applications' && $manageLeaveApp > 0){ ?><span class="badge badge-pill badge-danger"><?php echo $manageLeaveApp; ?></span> <?php } ?>

						<?php if($getSubMenuDetailsQRW['slug'] == 'manage-approved-leaves' && $manageLeaveJoiningApp > 0){ ?><span class="badge badge-pill badge-danger"><?php echo $manageLeaveJoiningApp; ?></span> <?php } ?>

						<?php if($getSubMenuDetailsQRW['slug'] == 'leave-edit-approval' && $getLeaveApplicationsQNumRows > 0){ ?><span class="badge badge-pill badge-danger"><?php echo $getLeaveApplicationsQNumRows; ?></span> <?php } ?>

							
						
						
						
						</a></li>
					  <?php
							}
							?>


					

					</ul>




					  <?php
							}
					  ?>
                
              </li>

			  <?php
				}
				  ?>


				  <!-- Button visible on mobile and tablet devices -->
				  	
					<hr id="mobile-tab-button">

				  	<li class="nav-item" id="mobile-tab-button"><a href="./my_profile?menuslug=dashboard"><i style="color: #fff;" class="ft-user font-medium-3"></i><span data-i18n="" class="menu-title">My Account</span></a></li>

					 <li class="nav-item" id="mobile-tab-button"><a onClick="logout()" ><i class="ft-power mr-2"></i><span data-i18n="" class="menu-title">Logout</span></a></li>
			  
			  
			  
			  

			  <!--
			  <li class="has-sub nav-item"><a href="#"><i class="fa fa-users"></i><span data-i18n="" class="menu-title">Manage Tax Payers</span></a>
                <ul class="menu-content">
                  <li><a href="new_tax_payer_info" class="menu-item">New Tax Payer</a></li>
                  <li><a href="tax_payer_list" class="menu-item">Tax Payer List</a></li>
				  

                </ul>
              </li>
			  <li class="has-sub nav-item"><a href="#"><i class="fa fa-money"></i><span data-i18n="" class="menu-title">Manage Taxes</span></a>
                <ul class="menu-content">
                  
                  <li><a href="tax_collection" class="menu-item">Tax Collection</a></li>
				  <li><a href="tax_arrear" class="menu-item">Arrear Tax List</a></li>
				  <li><a href="advance_tax_payers" class="menu-item">Advance Tax Payers</a></li>
				  <li><a href="return_not_submitted" class="menu-item">Return Not Submitted</a></li>
				  <li><a href="wealthy_files" class="menu-item">Wealthy Files</a></li>

                </ul>
              </li>
			  <li class="has-sub nav-item"><a href="#"><i class="fa fa-th-list"></i><span data-i18n="" class="menu-title">Notice Management</span></a>
                <ul class="menu-content">
                  

                </ul>
              </li>
			  <li class="nav-item"><a href="dashboard"><i class="ft-home"></i><span data-i18n="" class="menu-title">Penalty</span></a>
                
              </li>
			  <li class="nav-item has-sub"><a href="#"><i class="fa fa-chart"></i><span data-i18n="" class="menu-title">Report</span></a>
                <ul class="menu-content">
                  <li><a href="new_tax_payer_info" class="menu-item">Collection</a></li>
                  <li><a href="tax_collection" class="menu-item">No. of Returns</a></li>
				  

                </ul>
              </li>

			  -->
              
            </ul>
          </div>
        </div>