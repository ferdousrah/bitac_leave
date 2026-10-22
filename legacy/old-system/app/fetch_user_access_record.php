<?php
include("header.php");

$getModulesQ = mysqli_query($con,"select * from modules order by display_order asc");

$rowid = $_GET['rowid'];

?>

    
<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>User Access</h4>
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
			
			
			
   
<!-- Accordion -->
<div class="row">
	<div class="col-md-12">
		<div class="panel-group panel-default" id="accordionA">
			
			<?php
								while($moduleRow=mysqli_fetch_array($getModulesQ))
								{
									
									$getAllSubModule = mysqli_query($con,"select * from submodules where module_id='$moduleRow[dataID]'");
									$getAllSubModuleNumRows = mysqli_num_rows($getAllSubModule);
								?>
			
			<div class="panel panel-default">
				<a data-toggle="collapse" data-parent="#accordionA" href="#collapse<?php echo $moduleRow['dataID']; ?>"><div class="panel-heading"><h2><?php echo $moduleRow['module_name']; ?></h2></div></a>
				<div id="collapse<?php echo $moduleRow['dataID']; ?>" class="collapse">
					<div class="panel-body">

					<form class="form-horizontal" role="form" method="post">
                    <input type="hidden" name="uid" id="uid" class="uid" value="<?php echo $rowid; ?>"  />

                    
					 

					 <?php
						if($getAllSubModuleNumRows>0)
						{
						while($subModuleRow=mysqli_fetch_array($getAllSubModule))
						{
							
							// check for view
							$checkViewQ = mysqli_query($con,"select * from access_permission where user_id='$rowid' and module_id='$moduleRow[dataID]' and submodule_id='$subModuleRow[dataID]' and view=1");
							$checkViewQNumRows = mysqli_num_rows($checkViewQ);
							
							// check for add
							$checkAddQ = mysqli_query($con,"select * from access_permission where user_id='$rowid' and module_id='$moduleRow[dataID]' and submodule_id='$subModuleRow[dataID]' and add_p=1");
							$checkAddQNumRows = mysqli_num_rows($checkAddQ);
							
							// check for update
							$checkUpdateQ = mysqli_query($con,"select * from access_permission where user_id='$rowid' and module_id='$moduleRow[dataID]' and submodule_id='$subModuleRow[dataID]' and update_p=1");
							$checkUpdateQNumRows = mysqli_num_rows($checkUpdateQ);
							
							// check for delete
							$checkDeleteQ = mysqli_query($con,"select * from access_permission where user_id='$rowid' and module_id='$moduleRow[dataID]' and submodule_id='$subModuleRow[dataID]' and delete_p=1");
							$checkDeleteQNumRows = mysqli_num_rows($checkDeleteQ);
							
						?>
                        
                        <div class="form-group">
							<label class="col-sm-4 control-label"><?php echo $subModuleRow['submodule_name']; ?></label>
							<div class="col-sm-8">
								<label class="radio-inline radio-info">
									<input type="checkbox" <?php if($checkViewQNumRows>0){ ?> checked="checked" <?php } ?> name="m_<?php echo $moduleRow['dataID']; ?>_subm_<?php echo $subModuleRow['dataID']; ?>_view"  value="1" class="styled m_<?php echo $moduleRow['dataID']; ?>_subm_<?php echo $subModuleRow['dataID']; ?>_view" onChange="editView(<?php echo $rowid; ?>,<?php echo $moduleRow['dataID']; ?>,<?php echo $subModuleRow['dataID']; ?>)">
									View
								</label>
                                <label class="radio-inline radio-info">
									<input type="checkbox" <?php if($checkAddQNumRows>0){ ?> checked="checked" <?php } ?> name="m_<?php echo $moduleRow['dataID']; ?>_subm_<?php echo $subModuleRow['dataID']; ?>_add"  value="1" class="styled m_<?php echo $moduleRow['dataID']; ?>_subm_<?php echo $subModuleRow['dataID']; ?>_add" onChange="editAdd(<?php echo $rowid; ?>,<?php echo $moduleRow['dataID']; ?>,<?php echo $subModuleRow['dataID']; ?>)">
									Add
								</label>
                                <label class="radio-inline radio-info">
									<input type="checkbox" <?php if($checkUpdateQNumRows>0){ ?> checked="checked" <?php } ?> name="m_<?php echo $moduleRow['dataID']; ?>_subm_<?php echo $subModuleRow['dataID']; ?>_update" value="1" class="styled m_<?php echo $moduleRow['dataID']; ?>_subm_<?php echo $subModuleRow['dataID']; ?>_update" onChange="editUpdate(<?php echo $rowid; ?>,<?php echo $moduleRow['dataID']; ?>,<?php echo $subModuleRow['dataID']; ?>)">
									Update
								</label>
                                <label class="radio-inline radio-info">
									<input type="checkbox" <?php if($checkDeleteQNumRows>0){ ?> checked="checked" <?php } ?> name="m_<?php echo $moduleRow['dataID']; ?>_subm_<?php echo $subModuleRow['dataID']; ?>_delete" value="1" class="styled m_<?php echo $moduleRow['dataID']; ?>_subm_<?php echo $subModuleRow['dataID']; ?>_delete" onChange="editDelete(<?php echo $rowid; ?>,<?php echo $moduleRow['dataID']; ?>,<?php echo $subModuleRow['dataID']; ?>)">
									Delete
								</label>
							</div>
						</div>
                        
                       
                        <?php
						}
						}
						else
						{
							
							// check for view
							$checkViewQ = mysqli_query($con,"select * from access_permission where user_id='$rowid' and module_id='$moduleRow[dataID]' and view=1");
							$checkViewQNumRows = mysqli_num_rows($checkViewQ);
							
							// check for add
							$checkAddQ = mysqli_query($con,"select * from access_permission where user_id='$rowid' and module_id='$moduleRow[dataID]' and add_p=1");
							$checkAddQNumRows = mysqli_num_rows($checkAddQ);
							
							// check for update
							$checkUpdateQ = mysqli_query($con,"select * from access_permission where user_id='$rowid' and module_id='$moduleRow[dataID]' and update_p=1");
							$checkUpdateQNumRows = mysqli_num_rows($checkUpdateQ);
							
							// check for delete
							$checkDeleteQ = mysqli_query($con,"select * from access_permission where user_id='$rowid' and module_id='$moduleRow[dataID]' and delete_p=1");
							$checkDeleteQNumRows = mysqli_num_rows($checkDeleteQ);
							
							?>
							
							<div class="form-group">
							<label class="col-sm-4 control-label">&nbsp;</label>
							<div class="col-sm-8">
								<label class="radio-inline radio-info">
									<input type="checkbox" <?php if($checkViewQNumRows>0){ ?> checked="checked" <?php } ?> name="m_<?php echo $moduleRow['dataID']; ?>_subm_0_view"  value="1" class="styled m_<?php echo $moduleRow['dataID']; ?>_subm_0_view" onChange="editView(<?php echo $rowid; ?>,<?php echo $moduleRow['dataID']; ?>,0)">
									View
								</label>
                                <label class="radio-inline radio-info">
									<input type="checkbox" <?php if($checkAddQNumRows>0){ ?> checked="checked" <?php } ?> name="m_<?php echo $moduleRow['dataID']; ?>_subm_0_add"  value="1" class="styled m_<?php echo $moduleRow['dataID']; ?>_subm_0_add" onChange="editAdd(<?php echo $rowid; ?>,<?php echo $moduleRow['dataID']; ?>,0)">
									Add
								</label>
                                <label class="radio-inline radio-info">
									<input type="checkbox" <?php if($checkUpdateQNumRows>0){ ?> checked="checked" <?php } ?> name="m_<?php echo $moduleRow['dataID']; ?>_subm_0_update" value="1" class="styled m_<?php echo $moduleRow['dataID']; ?>_subm_0_update" onChange="editUpdate(<?php echo $rowid; ?>,<?php echo $moduleRow['dataID']; ?>,0)">
									Update
								</label>
                                <label class="radio-inline radio-info">
									<input type="checkbox" <?php if($checkDeleteQNumRows>0){ ?> checked="checked" <?php } ?> name="m_<?php echo $moduleRow['dataID']; ?>_subm_0_delete" value="1" class="styled m_<?php echo $moduleRow['dataID']; ?>_subm_0_delete" onChange="editDelete(<?php echo $rowid; ?>,<?php echo $moduleRow['dataID']; ?>,0)">
									Delete
								</label>
							</div>
						</div>
                            
                            
						<?php }
						?>





					
					</form>
					</div>
				</div>
			</div>
           
           <?php
								}
		   ?>

			
		</div>
	</div>
</div>


<!-- /Accordion -->



</div>
        </div>
    </div>
</section>
<!--Invoice template ends-->
          </div>
        </div>

        
		
</div>




<?php

include("footer.php");

?>




<script type="text/javascript">


function editView(uid,mid,smid)
{
	    var v = "m_"+mid+'_subm_'+smid+'_view';
	   
	   if($('.'+v).is(":checked")){
                var permission = 1;
            }
            else if($('.'+v).is(":not(:checked)")){
               var permission = 0;
            }
			
			dataString = 'user_id='+uid+'&mid='+mid+'&smid='+smid+'&permission='+permission+'&option='+1;
			
	                 
            $.ajax({
            type : 'post',
            url : 'insertAccessPermission.php', //Here you will fetch records 
            data :  dataString, //Pass $id
            success : function(data){
            
			if(data==1)
			{
				
			}
			
			
            }
        });
		

}


function editAdd(uid,mid,smid)
{
	    var v = "m_"+mid+'_subm_'+smid+'_add';
	   
	   if($('.'+v).is(":checked")){
                var permission = 1;
            }
            else if($('.'+v).is(":not(:checked)")){
               var permission = 0;
            }
			
			dataString = 'user_id='+uid+'&mid='+mid+'&smid='+smid+'&permission='+permission+'&option='+2;
			
	                 
            $.ajax({
            type : 'post',
            url : 'insertAccessPermission.php', //Here you will fetch records 
            data :  dataString, //Pass $id
            success : function(data){
            
			if(data==1)
			{
				
			}
			
			
            }
        });
		

}


function editUpdate(uid,mid,smid)
{
	    var v = "m_"+mid+'_subm_'+smid+'_update';
	   
	   if($('.'+v).is(":checked")){
                var permission = 1;
            }
            else if($('.'+v).is(":not(:checked)")){
               var permission = 0;
            }
			
			dataString = 'user_id='+uid+'&mid='+mid+'&smid='+smid+'&permission='+permission+'&option='+3;
			
	                 
            $.ajax({
            type : 'post',
            url : 'insertAccessPermission.php', //Here you will fetch records 
            data :  dataString, //Pass $id
            success : function(data){
            
			if(data==1)
			{
				
			}
			
			
            }
        });
		

}



function editDelete(uid,mid,smid)
{
	    var v = "m_"+mid+'_subm_'+smid+'_delete';
	   
	   if($('.'+v).is(":checked")){
                var permission = 1;
            }
            else if($('.'+v).is(":not(:checked)")){
               var permission = 0;
            }
			
			dataString = 'user_id='+uid+'&mid='+mid+'&smid='+smid+'&permission='+permission+'&option='+4;
			
	                 
            $.ajax({
            type : 'post',
            url : 'insertAccessPermission.php', //Here you will fetch records 
            data :  dataString, //Pass $id
            success : function(data){
            
			if(data==1)
			{
				
			}
			
			
            }
        });
		

}


</script>


