<?php 
$date = date('Y-m-d');
$currency_name = '';
if(isset($base_currency)){
	$currency_name = $base_currency->name;
}
$array_list_id = [];
if(isset($_COOKIE['service_id_list'])){
	$list_id = $_COOKIE['service_id_list'];
	if($list_id){
		$array_list_id = new_explode(',',$list_id);
	}
}

$user_id = '';
if(is_client_logged_in()) {
	$user_id = get_client_user_id();
}

?>

<div class="row">
	<?php foreach ($product as $item) { ?>

			<div class="col-md-3 grid col-sm-6">
				<div class="grid product-cell">

					<?php 
					$tax_value = 0;


					$discount_percent = 0;
					$prices_discount  = 0;
					?>


					<div class="product-image"> 
						<a href="<?php 	echo site_url('service_management/service_management_client/detail/'.$item['id']); ?>"> 
							<img class="pic-1" src="<?php echo sm_get_image_items($item['id']); ?>">
						</a>               					                  
					</div>
					<div class="product-content">
						<div class="title"><a href="<?php 	echo site_url('service_management/service_management_client/detail/'.$item['id']); ?>"><?php echo new_html_entity_decode($item['description']); ?></a></div> 
						<div class="price_w">
							<span class="price text-danger">
								<?php $price = $item['subscription_price'];
								if($item['service_type'] == 'subscriptions'){
			                        $subtext = app_format_money($price, $currency_name);
			                        if ($item['subscription_count'] == 1) {
			                            $subtext .= ' / ' . _l($item['subscription_period']);
			                        } else {
			                            $subtext .= ' / ' . $item['subscription_count'] . '' . _l($item['subscription_period'] . 's');
			                        }
			                        echo new_html_entity_decode($subtext);
			                    }
		                        ?>
							</span>	
						</div>
					</div>
					<?php if($item['service_type'] == 'normal'){ ?>
						<div class="pb-1 add-cart">
							<input type="hidden" name="has_variation" value="<?php echo new_html_entity_decode(1); ?>">
							<input type="number" name="qty" class="form-control qty hide" value="1" min="1" >
							<button type="button" class="added btn btn-primary <?php if(in_array($item['id'],$array_list_id)){ echo ''; }else{ echo 'hide'; } ?>" data-id="<?php echo new_html_entity_decode($item['id']); ?>"><i class="fa fa-shopping-cart"></i> <?php echo _l('added'); ?></button>	
							<button type="button" class="add_cart btn btn-success <?php if(in_array($item['id'],$array_list_id)){ echo 'hide'; }else{ echo ''; } ?>" data-id="<?php echo new_html_entity_decode($item['id']); ?>"><i class="fa fa-shopping-cart"></i> <?php echo _l('add_to_cart'); ?></button>


						</div>
					<?php }else if($item['service_type'] == 'subscriptions'){ ?>
						<?= form_open(site_url('service_management/service_management_client/subscribe'), ['class' => 'servicesForm']) ?>
						<?= form_hidden('product_id', html_escape($item['id'])) ?>
                        <?= form_hidden('type', 'subscription') ?>
                        <?php  echo  form_hidden('quantity', '1'); ?>
						<div class="pb-1 add-cart">
							 <button type="submit" class="subscribe-btn btn btn-success"><i class="fa fa-repeat"></i> <?= _l('subscribe') ?></button>
						</div>
						<?= form_close() ?>
					<?php } ?>
				</div>
			</div>
	
	<?php } ?>	             
</div>

