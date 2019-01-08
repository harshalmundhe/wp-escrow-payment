<?php


class WPEcrowShortcode extends WPEcrowPayment{
	
	public function __construct(){
		
	}
	
	public function ep_displayshortcodeform($id = "", $show_submit_button = false){
		$ep_settings = $this->ep_getdefaultsettings();
		
		$dname = $damt = $ddesc = $role = $seller_broker_fee = $buyer_broker_fee = $seller_email = "";
		if($id != ""){
			list($dname,$damt,$ddesc,$role,$seller_broker_fee,$buyer_broker_fee,$seller_email) = $this->ep_getshortcodedetails($id);
		}
		$html = "";
		$html .= "<form method='post' action='' id='escrowform'>";
		$html .= "<div id='error_msg' class='error error_msg notice' style='display:none'> 
				<p><strong class='errormsg'></strong></p>
				</div>";
		$html .= "<table class='adminescrowtable'>";
		$html .= "<tr>";
		$html .= "<th>Domain Name</th>";
		$html .= "<td><input type='text' name='dname' value='".$dname."'></td>";
		$html .= "</tr>";
		$html .= "<tr>";
		$html .= "<th>Amount</th>";
		$html .= "<td><input type='text' name='damt' value='".$damt."'></td>";
		$html .= "</tr>";
		$html .= "<tr>";
		$html .= "<th>Description</th>";
		$html .= "<td><textarea name='ddesc'>".$ddesc."</textarea></td>";
		$html .= "</tr>";
		if($ep_settings['role'] == "other"){
			$html .= "<tr>";
			$html .= "<th>My Role</th>";
			
			$html .= "<td><input type='radio' name='role' class='role' value='broker' ".(($role==''|| $role == 'broker')?'checked':'').">Broker<br>
						<input type='radio' name='role' value='seller' class='role' ".(($role=='seller')?'checked':'').">Seller<br>
						</td>";
			$html .= "</tr>";
		}
		if(isset($ep_settings['broker_fee_override']) && $ep_settings['role'] != "seller"){
			$html .= "<tr class='broker_fee_row'>";
			$html .= "<th>Seller Broker Fee(%)</th>";
			$html .= "<td><input type='text' name='seller_broker_fee'  value='".$seller_broker_fee."'>
						</td>";
			$html .= "</tr>";
			$html .= "<tr class='broker_fee_row'>";
			$html .= "<th>Buyer Broker Fee(%)</th>";
			$html .= "<td><input type='text' name='buyer_broker_fee'  value='".$buyer_broker_fee."'>
						</td>";
			$html .= "</tr>";
			$html .= "<tr class='broker_fee_row'>";
			$html .= "<th>Seller Email</th>";
			$html .= "<td><input type='text' name='seller_email'  value='".$seller_email."'>
						</td>";
			$html .= "</tr>";
		}
		
		$html .= "</table>";
		if($show_submit_button){
			$html .= "<input type='submit' class='button button-primary' name='submit' value='Save'>";
		}
		$html .= "</form>";
		return $html;
	}
	
	public function ep_getshortcodedetails( $id ){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		$dname = $damt = $ddesc = $role = $seller_broker_fee = $buyer_broker_fee = $seller_email = "";
		$sql = "SELECT * FROM ".$table_prefix."escrow_transaction_shortcodes_settings WHERE shortcode_id='".$id."'";
		$res = $wpdb->get_results($sql);
		if(empty($res)){
			wp_die("Invalid Id");
		}
		foreach($res as $data){
			$ret_arr[$data->param] = $data->value;
		}
		
		if(isset($ret_arr['dname'])){
			$dname = $ret_arr['dname'];
		}
		if(isset($ret_arr['damt'])){
			$damt = $ret_arr['damt'];
		}
		if(isset($ret_arr['ddesc'])){
			$ddesc = $ret_arr['ddesc'];
		}
		if(isset($ret_arr['role'])){
			$role = $ret_arr['role'];
		}
		if(isset($ret_arr['seller_broker_fee'])){
			$seller_broker_fee = $ret_arr['seller_broker_fee'];
		}
		if(isset($ret_arr['buyer_broker_fee'])){
			$buyer_broker_fee = $ret_arr['buyer_broker_fee'];
		}
		if(isset($ret_arr['seller_email'])){
			$seller_email = $ret_arr['seller_email'];
		}
		return array($dname,$damt,$ddesc,$role,$seller_broker_fee,$buyer_broker_fee,$seller_email);
	}
	
	
	public function ep_validateshortcodeform($post_vars){
		$error = array();
		
		if($post_vars['dname']== ""){
			$error['error'] = 1;
			$error['message'] = "Invalid Domain";
		}
		elseif(!is_numeric($post_vars['damt'])){
			$error['error'] = 1;
			$error['message'] = "Invalid Amount";
		}elseif($post_vars['role'] != "seller"){
			if(!is_numeric($post_vars['seller_broker_fee'])){
				$error['error'] = 1;
				$error['message'] = "Invalid seller broker fee";
			}
			elseif(!is_numeric($post_vars['buyer_broker_fee'])){
				$error['error'] = 1;
				$error['message'] = "Invalid buyer broker fee";
			}
			elseif(!filter_var($post_vars['seller_email'],FILTER_VALIDATE_EMAIL)){
				$error['error'] = 1;
				$error['message'] = "Invalid seller email";
			}
		}
		
		return $error;
	}
	
	public function ep_createshortcode(){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		$wpdb->query("INSERT INTO ".$table_prefix."escrow_transaction_shortcodes(id) VALUES(null)");
		
		$lastid = $wpdb->insert_id;
		
		
		$sql = "INSERT into ".$table_prefix."escrow_transaction_shortcodes_settings(shortcode_id,param,value) VALUES ";
		
		$ins_arr = array();
		foreach($_POST['data'] as $data){
			$ins_arr[] = "('".$lastid."','".sanitize_text_field($data['name'])."','".sanitize_text_field($data['value'])."')";
		}
		$ins_str = implode(",",$ins_arr);
		
		$sql .= $ins_str;
		
		$wpdb->query($sql);
		
		return "[wp_escow_payment id=$lastid]";
	}
	
	
	function ep_processshortcode($shortcode_id){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		
		$html="";
		$ep_settings = $this->ep_getdefaultsettings();
		
		$valid_shortcode = $wpdb->get_var("SELECT count(*) as count FROM ".$table_prefix."escrow_transaction_shortcodes where id='".$shortcode_id."'");
		
		
		if(isset($ep_settings['buttom_image_attachment_id']) && $ep_settings['buttom_image_attachment_id']!=""){
			$img_url = wp_get_attachment_url( $ep_settings["buttom_image_attachment_id"]);
		}else{
			$img_url = plugin_dir_url(__FILE__)."/img/butnowescrow.png";
		}
		
		if($valid_shortcode){
		
			$in_transation = $this->ep_checkdomainstatus($shortcode_id);
			if($in_transation){
				$html .= "<div class='escrowbtndiv'>Transaction already started for this domain.</div>";
			}else{
				$html = "<div class='escrowbtndiv escrowdiv".$shortcode_id."'><a href='#TB_inline?width=300&height=200&inlineId=userform".$shortcode_id."' class='escrowbtn thickbox'><img src='".$img_url."'></a></div>";
				add_thickbox(); 
				$html .='<div id="userform'.$shortcode_id.'" class="userform" style="display:none;">
						<div class="loadingdiv"></div>
						<form mehod="post" id="escrowform" class="escrowform'.$shortcode_id.'" action="">
						<div id="error_msg" class="error error_msg notice" style="display:none"> 
						<p><strong class="errormsg"></strong></p>
						</div>
						<label>Email: <input type="text" name="buyer_email">
						</label>
						<input type="hidden" name="shortcode_id" value="'.$shortcode_id.'">
						<input type="button" class="ep_buynow" value="Buy Now" name="Buynow" onclick="wp_createorder('.$shortcode_id.');">
						</form>
						
						</div>';
			}
		}
		return $html;
	}
	
	public function ep_checkdomainstatus($shortcode_id){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		$sql = "select value from ".$table_prefix."escrow_transaction_shortcodes_settings WHERE param='dname' and shortcode_id='".$shortcode_id."'";
		$dmn_name = $wpdb->get_var($sql);
		
		$sql = "SELECT count(id) as count FROM ".$table_prefix."escrow_products WHERE name='".$dmn_name."'";
		$count = $wpdb->get_var($sql);
		if($count==0){
			return false;
		}else{
			return true;
		}
	}
	
	
	
	public function ep_displayshortcodeslist(){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		$short_code = "[wp_escow_payment id=#id#]";
		
		$sql = "SELECT id,(SELECT value FROM ".$table_prefix."escrow_transaction_shortcodes_settings WHERE shortcode_id=ets.id and param='dname' ) as domain_name FROM ".$table_prefix."escrow_transaction_shortcodes ets";
		
		$shortcodes_obj = $wpdb->get_results($sql);
		
		?>
		<h1>Escrow ShortCodes List</h1>
		<div class="wrap">
        <table class="wp-list-table widefat">
            <thead>
                <tr>
                    <th>Domain Name</th>
                    <th>ShortCode</th>
                </tr>
            </thead>
			<?php
				if(empty($shortcodes_obj)){
					?>
					<tr>
						<td colspan="2">No Shortcodes created.</td>
					</tr>
					<?php
				}else{
					foreach($shortcodes_obj as $shortcode){
						?>
						<tr id="escrow-<?=$shortcode->id?>" class="iedit escrow-<?=$shortcode->id?>">
							<td class="has-row-actions column-primary"><?=$shortcode->domain_name;?>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo admin_url( 'admin.php?page=edit-escrow-shortcodes&id='.$shortcode->id.'&action=edit' ); ?>" aria-label="Edit “Shortcode <?=$shortcode->id?>”">Edit</a> | </span>
								<span class="trash"><a onclick="return window.confirm('Are you sure you want to delete this shortcode?')" href="<?php echo admin_url( 'admin.php?page=edit-escrow-shortcodes&id='.$shortcode->id.'&action=delete' ); ?>" class="submitdelete" aria-label="Remove “Shortcode <?=$shortcode->id?>” to the Trash">Delete</a></span>
							</div>
							
							</td>
							<td><input type="text" value='<?=str_replace("#id#",$shortcode->id,$short_code);?>' readonly></td>
						</tr>
						<?php
					}
				}
			?>
			</table>
		</div>
		<?php
		
	}
	
	public function ep_escrow_edit_shortcodes(){
		$form = $success =  "";
		if($_GET['action'] == "delete"){
			if(!isset($_GET['id']) || trim($_GET['id'])=="" || !is_numeric($_GET['id'])){
				wp_die("Invalid Id");
			}
			$this->ep_deleteshortcode($_GET['id']);
			ob_start();
			wp_redirect(admin_url("admin.php?page=escrow-shortcodes"));
			exit;
		}
		
		if($_GET['action'] == "edit"){
			if(!isset($_GET['id']) || trim($_GET['id'])=="" || !is_numeric($_GET['id'])){
				wp_die("Invalid Id");
			}
			
			if(isset($_POST['submit'])){
				$error = $this->ep_validateshortcodeform($_POST);
				if(empty($error)){
					$this->ep_updateshortcode($_POST);
					$success = "Shortcode updated successfully";
				}
			}
			
			$form = $this->ep_displayshortcodeform($_GET['id'],true);
		}
		?>
		<h1>Edit ShortCode ID:<?=$_GET['id'];?></h1>
		<script>
			jQuery(document).ready(function(){
				jQuery(".toplevel_page_escrow-payment-settings").addClass("current wp-has-current-submenu wp-menu-open");
				
				if(jQuery('.role:checked').val() == "seller"){
					jQuery(".broker_fee_row").addClass("ep_hide");
				}else{
					jQuery(".broker_fee_row").removeClass("ep_hide");
				}
			});
		</script>
		
		<?php if(isset($error['message'])){ ?>
			<div id='error_msg' class='error error_msg notice'> 
			<p><strong class='errormsg'><?=$error['message'];?></strong></p>
			</div>
		<?php } 
		if($success != ""){ ?>
		 <div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"> 
		<p><strong><?=$success;?>.</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
		 <?php
		}
		
		echo $form;
	}
	
	public function ep_updateshortcode($post_vars){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		if($post_vars['role'] == "seller"){
			$post_vars['buyer_broker_fee'] = 0;
			$post_vars['seller_broker_fee'] = 0;
			$post_vars['seller_email'] = "";
		}
		foreach($post_vars as $key=>$value){
			$value = trim($value);
			
			$wpdb->query("UPDATE ".$table_prefix."escrow_transaction_shortcodes_settings SET value = '".sanitize_text_field($value)."' WHERE shortcode_id='".sanitize_text_field($_GET['id'])."' and param = '".$key."'");
			
		}
	}
	
	public function ep_deleteshortcode( $id ){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		
		$wpdb->query("DELETE FROM ".$table_prefix."escrow_transaction_shortcodes_settings WHERE shortcode_id='".$id."'");
		$wpdb->query("DELETE FROM ".$table_prefix."escrow_transaction_shortcodes WHERE id='".$id."'");
	}
}

