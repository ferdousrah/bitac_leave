<?php
include('header.php');

$getTaxPaidDataQ = mysqli_query($con,"select tax_collection_data.dataID, tax_collection_data.financial_year, tax_collection_data.income, tax_collection_data.wealth, tax_collection_data.tax_paid, tax_payer_info.taxPayerName, tax_payer_info.etin from tax_collection_data inner join tax_payer_info on tax_collection_data.dataID=tax_payer_info.dataID");

?>


<link rel="stylesheet" type="text/css" href="app-assets/vendors/css/tables/datatable/datatables.min.css">



<div class="main-panel">
        <div class="main-content">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>Arrear Tax List</h4>
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                <!-- Invoice Footer -->
                <div id="invoice-footer">
                    <div class="row">
                        <div class="col-md-12 col-sm-12">
                            


							<table class="table table-striped table-bordered file-export dataTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>E-TIN</th>
                                    <th>Financial Year</th>
                                    <th>Arrear</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
							
                            </tbody>
                            
                        </table>



                        </div>
                        
                    </div>
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


<script src="app-assets/vendors/js/datatable/datatables.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/datatable/dataTables.buttons.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/datatable/buttons.flash.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/datatable/jszip.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/datatable/pdfmake.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/datatable/vfs_fonts.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/datatable/buttons.html5.min.js" type="text/javascript"></script>
    <script src="app-assets/vendors/js/datatable/buttons.print.min.js" type="text/javascript"></script>

	<script src="app-assets/js/data-tables/datatable-advanced.js" type="text/javascript"></script>