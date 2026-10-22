<?php
include('header.php');

$getTotalTaxPayerQ = mysqli_query($con,"select count(dataID) as totalTaxPayer from tax_payer_info");
$getTotalTaxPayerQRW = mysqli_fetch_assoc($getTotalTaxPayerQ);
?>






<div class="main-panel">
        <div class="main-content">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-10">
        <h4>Dashboard</h4>
    </div>
	<div class="col-md-2">
        <select id="financial_year" name="financial_year[]" class="form-control">
													<option value="0" selected="" disabled="">Financial Year</option>
													<option value="2014-15">2014-15</option>
													<option value="2015-16">2015-16</option>
													<option value="2016-17">2016-17</option>
													<option value="2017-18">2017-18</option>
												</select>
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">


			<h4>Welcome! <?php echo '<span class="bg-success" style="color:#fff">&nbsp;'.$getUserInfoQRW['full_name'].'&nbsp;</span>'; ?></h4>
                
                
                
                <!-- Invoice Footer -->
                <div id="invoice-footer">
                    <div class="row">


					
                        

                    <div class="col-xl-3 col-lg-6 col-md-6 col-12">
		<div class="card gradient-blackberry">
			<div class="card-body">
				<div class="card-block pt-2 pb-0">
					<div class="media">
						<div class="media-body white text-left">
							<h3 class="font-large-1 mb-0"><?php echo $getTotalTaxPayerQRW['totalTaxPayer']; ?></h3>
							<span>Total Tax Payer</span>
						</div>
						<div class="media-right white text-right">
							<i class="icon-user font-large-1"></i>
						</div>
					</div>
				</div>
				<div id="Widget-line-chart" class="height-75 WidgetlineChart WidgetlineChartshadow mb-2">					
				</div>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-lg-6 col-md-6 col-12">
		<div class="card gradient-ibiza-sunset">
			<div class="card-body">
				<div class="card-block pt-2 pb-0">
					<div class="media">
						<div class="media-body white text-left">
							<h3 class="font-large-1 mb-0">1567</h3>
							<span>Total Collection</span>
						</div>
						<div class="media-right white text-right">
							<i class="icon-wallet font-large-1"></i>
						</div>
					</div>
				</div>
				<div id="Widget-line-chart1" class="height-75 WidgetlineChart WidgetlineChartshadow mb-2">					
				</div>

			</div>
		</div>
	</div>
	
	<div class="col-xl-3 col-lg-6 col-md-6 col-12">
		<div class="card gradient-green-tea">
			<div class="card-body">
				<div class="card-block pt-2 pb-0">
					<div class="media">
						<div class="media-body white text-left">
							<h3 class="font-large-1 mb-0">4566</h3>
							<span>No. of Returns</span>
						</div>
						<div class="media-right white text-right">
							<i class="icon-size-actual font-large-1"></i>
						</div>
					</div>
				</div>
				<div id="Widget-line-chart2" class="height-75 WidgetlineChart WidgetlineChartshadow mb-2">				
				</div>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-lg-6 col-md-6 col-12">
		<div class="card gradient-pomegranate">
			<div class="card-body">
				<div class="card-block pt-2 pb-0">
					<div class="media">
						<div class="media-body white text-left">
							<h3 class="font-large-1 mb-0">8695</h3>
							<span>Penalty</span>
						</div>
						<div class="media-right white text-right">
							<i class="icon-wallet font-large-1"></i>
						</div>
					</div>
				</div>
				<div id="Widget-line-chart3" class="height-75 WidgetlineChart WidgetlineChartshadow mb-2">					
				</div>
			</div>
		</div>
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