<?php

require_once("escrow.class.php");

class WPEscrowOrders extends WPEcrowPayment{
	
	public function ep_displayorders(){
		
		$order_obj = $this->ep_getorderdetails();
		$ep_settings = $this->ep_getdefaultsettings();
		
		?>
		<h1>Escrow Orders List</h1>
		<div class="wrap">
		<?php	
		if(isset($_GET['securepayment'])){
			?>
			<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"> 
			<p><strong>Payment has been secured by escrow.</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
			<?php
		}
		?>
        <table class="wp-list-table widefat">
            <thead>
                <tr>
                    <th>Order Id</th>
                    <th>Domain Name</th>
					<th>Created</th>
					<th>Status</th>
					<th>Details</th>
                </tr>
            </thead>
			<?php
				if(empty($order_obj)){
					?>
					<tr>
						<td colspan="2">No Orders created.</td>
					</tr>
					<?php
				}else{
					foreach($order_obj as $orders){
						?>
						<tr id="escroworder-<?=$orders->id?>" class="iedit escroworder-<?=$orders->order_id?>">
							<td class="has-row-actions column-primary"><?=$orders->order_id;?>
							<div class="row-actions">
								<span class="trash"><a onclick="return window.confirm('Are you sure you want to delete this Order?')" href="<?php echo admin_url( 'admin.php?page=escrow-order-details&id='.$orders->order_id.'&action=delete' ); ?>" class="submitdelete" aria-label="Move “Shrotcode <?=$orders->order_id?>” to the Trash">Delete</a></span>
							</div>
							</td>
							<td><?=$orders->name;?></td>
							<td><?=$orders->created_date;?></td>
							<td><?=$orders->status;?>
							<?php if($orders->status == "agree" && $ep_settings['enviroment'] == "sandbox"){ ?>
							<a href="<?php echo admin_url( 'admin.php?page=escrow-order-details&id='.$orders->order_id.'&action=securepayment' ); ?>" class="fright button button-default" target="_blank">Mark Payment Secured</a>
							<?php } ?>
							</td>
							<td><a href="<?php echo admin_url( 'admin.php?page=escrow-order-details&id='.$orders->order_id.'&action=view' ); ?>">View</a></td>
						</tr>
						<?php
					}
				}
			?>
			</table>
		</div>
		<?php
	}
	
	
	public function ep_getorderdetails($id = ""){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		
		
		$sql = "SELECT * from ".$table_prefix."escrow_orders eo join  ".$table_prefix."escrow_products ep on(eo.product_id=ep.id)";
		if($id!=""){
			$sql .= " and order_id ='".sanitize_text_field($id)."'"	;
		}else{
			$sql .="order by eo.created_date DESC";
		}
		$order_obj = $wpdb->get_results($sql);
		return $order_obj;
	}
	
	
	public function ep_validateorderform($post_vars){
		$error = array();
		if(!filter_var($post_vars['buyer_email'],FILTER_VALIDATE_EMAIL)){
			$error['error'] = 1;
			$error['message'] = "Invalid email address";
		}
		return $error;
	}
	
	public function ep_createorder($post_vars){
		$Escrow = new Escrow();
		$param = $post_vars;
		$ret = $Escrow->CreateEscrowTransaction($param);
		return $ret;
	}
	
	public function ep_displayorderdetails(){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		if(!isset($_GET['id'])){
			wp_die("Invalid Order id");
		}
		
		
		if(isset($_GET['action']) && $_GET['action'] == "delete"){
			$product_id = $wpdb->get_var("select product_id from ".$table_prefix."escrow_orders WHERE order_id='".$_GET['id']."'");
			if($product_id == ""){
				wp_die("Invalid Order id");
			}
			$wpdb->query("DELETE FROM ".$table_prefix."escrow_orders WHERE order_id='".$_GET['id']."'");
			$wpdb->query("DELETE FROM ".$table_prefix."escrow_products WHERE id='".$product_id."'");
			$wpdb->query("DELETE FROM ".$table_prefix."escrow_transaction_phases WHERE order_id='".$_GET['id']."'");
			ob_start();
			wp_redirect(admin_url( 'admin.php?page=escrow-orders'));
			exit;
		}
		if(isset($_GET['action']) && $_GET['action'] == "securepayment"){
			$res = $wpdb->get_row("select escrow_transaction_id,price from ".$table_prefix."escrow_orders WHERE order_id='".$_GET['id']."'");
			if(empty($res)){
				wp_die("Invalid Order id");
			}
			$Escrow = new Escrow();
			$param['transaction_id'] = $res->escrow_transaction_id;
			$param['amount'] = $res->price;
			$output = $Escrow->ApproveEscrowWireTransfer($param);
			$output = json_decode($output);
			if($output->error){
				echo $output->error;
			}else{
				wp_redirect(admin_url( 'admin.php?page=escrow-orders&securepayment=true'));
			}
			exit;
			
		}
		
		$order_obj = $this->ep_getorderdetails($_GET['id']);
		if(!isset($order_obj[0])){
			wp_die("Invalid Order");
		}
		$order_obj = $order_obj[0];
		
		$order_id = $order_obj->order_id;
		
		$transaction_phases = $wpdb->get_results("select * from ".$table_prefix."escrow_transaction_phases where order_id='".$order_id."'");
		
		
		
		?>
		<h1>Order Details Order ID <?=$_GET['id'];?></h1>
		<table class="detailstable">
			<tr>
				<td valign="top">
					<h2>Order Details</h2>
					<table>
						<tr>
							<th>Order Id</th>
							<td><?=$order_obj->order_id;?></td>
						</tr>
						<tr>
							<th>Escrow Transaction Id</th>
							<td><?=$order_obj->escrow_transaction_id;?></td>
						</tr>
						<tr>
							<th>My Role</th>
							<td><?=$order_obj->role;?></td>
						</tr>
						<tr>
							<th>Order Status</th>
							<td class="uppercase"><?=$order_obj->status;?></td>
						</tr>
						<tr>
							<th>Order Created Date</th>
							<td><?=$order_obj->created_date;?></td>
						</tr>
					</table>
				</td>
				<td valign="top">
					<h2>Product Details</h2>
					<table>
						<tr>
							<th>Product Id</th>
							<td><?=$order_obj->id;?></td>
						</tr>
						<tr>
							<th>Product Name</th>
							<td><?=$order_obj->name;?></td>
						</tr>
						<tr>
							<th>Seller Email</th>
							<td><?=$order_obj->seller_email;?></td>
						</tr>
						<tr>
							<th>Buyer Email</th>
							<td><?=$order_obj->buyer_email;?></td>
						</tr>
						<?php if($order_obj->role == "broker"){ ?>
						<tr>
							<th>Seller Broker Fee</th>
							<td><?=$order_obj->seller_broker_fee;?></td>
						</tr>
						<tr>
							<th>Buyer Broker Fee</th>
							<td><?=$order_obj->buyer_broker_fee;?></td>
						</tr>
						<?php } ?>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<h2>Escrow Transaction Details</h2>
					<table class="phasetable">
						<tr>
							<th>Phase</th>
							<th>Date</th>
						</tr>
						<?php foreach($transaction_phases as $id=>$phase){ ?>
						<tr>
							<td class="uppercase"><?=$phase->phase;?></td>
							<td><?=$phase->phase_date;?></td>
						</tr>
						<?php } ?>
					</table>
				</td>
			</tr>
		</table>
		<?php
	}
	
}

?>