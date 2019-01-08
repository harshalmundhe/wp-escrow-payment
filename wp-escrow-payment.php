<?php
/**
 * Plugin Name: WP Ecrow Payment
 * Plugin URI: http://wordpress.harshal/test/
 * Description: The plugin is for using escrow payment gateway for non woocomerce escrow platform
 * Version: 1.0
 * Author: Harshal
 * Author URI: http://wordpress.harshal/test/
 */

require_once("return/escrow-constants.php");
require_once("wp-escrow-shortcode.php");
require_once("wp-escrow-orders.php");

class WPEcrowPayment{
	private $ep_version;
	/*
	 * Using constructor function to initailize all data
	 *
	 */
	public function __construct(){
		
		$this->ep_version = "1.0";
		
		register_activation_hook( __FILE__, array($this,'ep_generatetables'));
		add_action('admin_menu', array($this, 'ep_adminmenu'));
		add_shortcode( 'wp_escow_payment', array($this,'ep_main') );
		add_action('admin_head', array($this,'ep_scripts') );
		add_filter("mce_external_plugins", array($this,"ep_shortcode_add_button"));
		add_filter("mce_buttons",  array($this,"ep_shortcode_register_button"));
		add_action( 'wp_ajax_ep_shortcode_btn_insert_dialog', array($this,'ep_shortcode_btn_insert_dialog' ));
		add_action( 'admin_enqueue_scripts', array($this,'load_admin_style') );
		add_action('wp_enqueue_scripts', array($this,'load_custom_style'));
		add_action( 'wp_ajax_ep_shortcode_create', array($this,'ep_create_shortcode' ));
		add_action( 'wp_ajax_ep_create_order', array($this,'ep_create_order') );
		add_action( 'wp_ajax_nopriv_ep_create_order', array($this,'ep_create_order') );
		add_action('init', array($this,'ep_output_buffer'));
	}
	public function ep_output_buffer() {
		ob_start();
	} 
	
    public function load_admin_style() {
		wp_register_style( 'admin_css', plugin_dir_url(__FILE__).'/css/admin-style.css');
		wp_enqueue_style( 'admin_css' );
    }
	
	function load_custom_style(){
		wp_register_style( 'user_css', plugin_dir_url(__FILE__).'/css/user-style.css' );
		wp_enqueue_style( 'user_css' );
		wp_enqueue_script( 'user_js', plugin_dir_url(__FILE__).'/js/user-form.js', array( 'jquery' ) );
		wp_localize_script( 'user_js', 'ajaxobject',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
		
	}
	
	/*
	 * Function ep_generatetables()
	 * Add the latest version of the plugin to the db
	 *	And generate Required Tables
	 */
	public function ep_generatetables(){
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		update_option("ep_version",$this->ep_version);
		
		$charset_collate = $wpdb->get_charset_collate();
		$table_prefix = $wpdb->prefix;
		
		//table to store escrow settings
		$sql = "CREATE TABLE ".$table_prefix."escrow_payment_settings (
			param VARCHAR(100) NOT NULL DEFAULT '',
			value text NOT NULL  DEFAULT ''
		) $charset_collate;";
		
		dbDelta($sql);
		
		$ep_settings = $this->ep_getdefaultsettings();
		if(empty($ep_settings)){
			$this->ep_insertdefaultset();
		}
		
		//table to store escrow orders
		$sql = "CREATE TABLE ".$table_prefix."escrow_orders (
			order_id mediumint(9) NOT NULL AUTO_INCREMENT,
			product_id INT NOT NULL,
			escrow_transaction_id INT NOT NULL,
			price FLOAT(10,2) NOT NULL,
			description TEXT DEFAULT '',
			status VARCHAR(255) NOT NULL,
			created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY id (order_id)
		) $charset_collate;";
		dbDelta($sql);
		
		//table to store escrow product
		$sql = "CREATE TABLE ".$table_prefix."escrow_products (
			id mediumint(9) NOT NULL AUTO_INCREMENT,	
			name VARCHAR(255) NOT NULL,
			price FLOAT(10,2) NOT NULL,
			seller_email VARCHAR(255)  NOT NULL,
			buyer_email VARCHAR(255) NOT NULL,
			role VARCHAR(255) NOT NULL,
			buyer_broker_fee VARCHAR(255)  NOT NULL,
			seller_broker_fee VARCHAR(255)  NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";
		dbDelta($sql);
		
		//table to store escrow phase
		$sql = "CREATE TABLE ".$table_prefix."escrow_transaction_phases (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			escrow_transaction_id INT NOT NULL,
			order_id INT NOT NULL,
			phase VARCHAR(255) NOT NULL,
			phase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY id (id)
		) $charset_collate;";
		dbDelta($sql);
		
		//table to store escrow shortcodes
		$sql = "CREATE TABLE ".$table_prefix."escrow_transaction_shortcodes (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			UNIQUE KEY id (id)
		) $charset_collate;";
		dbDelta($sql);
		
		//table to store escrow shortcodes
		$sql = "CREATE TABLE ".$table_prefix."escrow_transaction_shortcodes_settings (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			shortcode_id INT NOT NULL,
			param VARCHAR(255),
			value VARCHAR(255),
			UNIQUE KEY id (id)
		) $charset_collate;";
		dbDelta($sql);
		
		
	}
	
	
	function ep_insertdefaultset(){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		
		$sql = "INSERT INTO ".$table_prefix."escrow_payment_settings(param,value) VALUES
				('enviroment','sandbox'),
				('api_url','".SANDBOX_API_URL."'),
				('api_key',''),
				('api_email',''),
				('role','broker'),
				('seller_broker_fee','".SELLER_BROKER_FEE."'),
				('buyer_broker_fee','".BUYER_BROKER_FEE."'),
				('inspection_period','".INSPECTION_PERIOD."')
				";
		$wpdb->query($sql);
	}
	
	
	/*
	 * Function ep_adminmenu()
	 * Function to develop admin menu for setting page
	 *
	 */
	public function ep_adminmenu(){
		add_menu_page('Escrow Payment', //page title
            'Escrow Payment', //menu title
            'manage_options', //capabilities
            'escrow-payment-settings', //menu slug
            array($this,'ep_defaultsettingsform') //function
        );
		add_submenu_page('escrow-payment-settings', 'Escrow ShortCodes', 'Escrow ShortCodes', 'manage_options', 'escrow-shortcodes', array($this,'ep_escrow_shortcodes') );
		add_submenu_page('escrow-shortcodes', 'Escrow ShortCodes', null, 'manage_options', 'edit-escrow-shortcodes', array($this,'ep_escrow_edit_shortcodes') );
		add_submenu_page('escrow-payment-settings', 'Escrow Orders', 'Escrow Orders', 'manage_options', 'escrow-orders', array($this,'ep_escrow_orders') );
		add_submenu_page('escrow-orders', 'Escrow Order Details', null, 'manage_options', 'escrow-order-details', array($this,'ep_escroworderdetails') );
	}
	
	public function ep_escrow_orders(){
		$WPEscrowOrders = new WPEscrowOrders();
		$WPEscrowOrders->ep_displayorders();
	}
	
	public function ep_escrow_shortcodes(){
		$WPEcrowShortcode = new WPEcrowShortcode();
		$WPEcrowShortcode->ep_displayshortcodeslist();
	}
	
	public function ep_escrow_edit_shortcodes(){
		$WPEcrowShortcode = new WPEcrowShortcode();
		$WPEcrowShortcode->ep_escrow_edit_shortcodes();
	}
	
	function ep_shortcode_add_button($plugin_array)
	{
		//enqueue TinyMCE plugin script with its ID.
		$plugin_array["ep_shortcode_btn"] =  plugin_dir_url(__FILE__) . "js/wp-escrow-payment.js";
		return $plugin_array;
	}
	
	function ep_shortcode_register_button($buttons)
	{
		//register buttons with their id.
		array_push($buttons, "ep_shortcode");
		return $buttons;
	}

	function ep_shortcode_btn_insert_dialog() {
		$WPEcrowShortcode = new WPEcrowShortcode();
		$short_code_form = $WPEcrowShortcode->ep_displayshortcodeform();
		echo $short_code_form;
		exit;
	}
	public function ep_create_shortcode(){
		$WPEcrowShortcode = new WPEcrowShortcode();
		
		if(empty($_POST)){
			$error['error'] = 1;
			$error['message'] = "Invalid Request";
			echo json_encode($error);
			exit;
		}
		
		$post_vars = array();
		foreach($_POST['data'] as $data){
			$post_vars[$data['name']] = $data['value'];
		}
		
		$error = $WPEcrowShortcode->ep_validateshortcodeform($post_vars);
		if(empty($error)){
			$ret['shortcode'] = $WPEcrowShortcode->ep_createshortcode();
			echo json_encode($ret);
		}else{
			echo json_encode($error);
		}
		exit;
	}
	
	
	public function ep_create_order(){
		$WPEscrowOrders = new WPEscrowOrders();
		if(empty($_POST)){
			$error['error'] = 1;
			$error['message'] = "Invalid Request";
			echo json_encode($error);
			exit;
		}
		$post_vars = array();
		foreach($_POST['data'] as $data){
			$post_vars[$data['name']] = $data['value'];
		}
		$error = $WPEscrowOrders->ep_validateorderform($post_vars);
		if(empty($error)){
			$ret =  $WPEscrowOrders->ep_createorder($post_vars); 
			if(!isset($ret['error'])){
				echo json_encode(array($ret));
			}else{
				echo json_encode($ret);
			}
		}else{
			echo json_encode($error);
		}
		exit;
	}
	
	public function ep_escroworderdetails(){
		$WPEscrowOrders = new WPEscrowOrders();
		$WPEscrowOrders->ep_displayorderdetails();
	}
	
	/*
	 * Function ep_defaultsettingsform()
	 * Function to create admin setting form
	 *
	 */
	public function ep_defaultsettingsform(){
		global $wpdb;
		$error = $success = "";
		$table_prefix = $wpdb->prefix;
		if(isset($_POST['ep_updatesettings'])){
			unset($_POST['ep_updatesettings']);
			$_POST = array_map("trim",$_POST);
			if($_POST['enviroment'] == "sandbox"){
				$api_url = SANDBOX_API_URL;
			}else{
				$api_url = LIVE_API_URL;
			}
			if($_POST['api_key'] == ""){
				$error .= "Please enter api key.<br>";
			}
			if($_POST['api_email'] == ""){
				$error .= "Please enter api email.<br>";
			}
			if($_POST['role'] != "seller"){
				if(!is_numeric($_POST['seller_broker_fee']) || $_POST['seller_broker_fee']<0){
					$error .= "Please enter valid seller broker fee.<br>";	
				}
				if(!is_numeric($_POST['buyer_broker_fee']) || $_POST['buyer_broker_fee']<0){
					$error .= "Please enter valid buyer broker fee.<br>";	
				}
			}
			if(!($_POST['inspection_period']>0 && $_POST['inspection_period']<30)){
				$error .= "Inspection Period should be less than 30 days.<br>";
			}
			
			if($error == ""){
				$wpdb->query("DELETE FROM ".$table_prefix."escrow_payment_settings");
				
				$sql = "INSERT INTO ".$table_prefix."escrow_payment_settings(param,value) VALUES";
				$insert_arr = array();
				foreach($_POST as $key=>$val){
					$insert_arr[] = "('".$key."','".$val."')";
				}
				$insert_str = implode(",",$insert_arr);
				$sql = $sql.$insert_str;
				$wpdb->query($sql);
			}
			
		}
		
		$ep_settings = $this->ep_getdefaultsettings();
		
		
		
		?>
		 <h1><?php _e( 'Escrow Payment Settings', 'ep_message' ); ?></h1>
		 <?php
		 if($error != ""){
		 ?>
			<div id="setting-error-settings_updated" class="error settings-error notice is-dismissible"> 
			<p><strong><?php _e($error,'ep_message');?></strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
		 <?php
		 }
		 if($success != ""){
		 ?>
		 <div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"> 
			<p><strong><?php _e($success,'ep_message');?>.</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
		 <?php
		 }
		 ?> 
		 <form method="post" action="">
			<table class='form-table'>
				<tr>
					<th scope="row">Environment</th>
					<td><input type="radio" name="enviroment" class='enviroment' value="sandbox"
					<?php if(isset($ep_settings['enviroment']) && $ep_settings['enviroment']=="sandbox"){
						echo "checked='checked'";
					}?>>Sandbox
					<br>
					<p class="description">Login for api key <a href="https://www.escrow-sandbox.com/integrations/portal/api" target="_blank">here.</a></p>
					<br>
					<input type="radio" name="enviroment" class='enviroment' value="live"
					<?php if(isset($ep_settings['enviroment']) && $ep_settings['enviroment']=="live"){
						echo "checked='checked'";
					}?>>Live
					<br>
					<p class="description">Login for api key <a href="https://www.escrow.com/integrations/portal/api" target="_blank">here.</a></p>
					</td>
				</tr>
				<tr>	
					<th scope="row">API Key</th>
					<td><input type="text" class="regular-text code" name="api_key"
					<?php if(isset($ep_settings['api_key'])){
						echo "value='".$ep_settings['api_key']."'";
					}?>
					></td>
				</tr>
				<tr>
					<th scope="row">API URL</th>
					<td><input type="text" class="regular-text code" name="api_url" id="api_url"
					<?php if(isset($ep_settings['api_url'])){
						echo "value='".$ep_settings['api_url']."'";
					}?>
					></td>
				</tr>
				<tr>
					<th scope="row">API Email</th>
					<td><input type="text" class="regular-text code" name="api_email"
					<?php if(isset($ep_settings['api_email'])){
						echo "value='".$ep_settings['api_email']."'";
					}?>
					></td>
				</tr>
				<tr>
					<th scope="row">My Role</th>
					<td><input type="radio" name="role" class='role' value="broker"
					<?php if(isset($ep_settings['role']) && $ep_settings['role']=="broker"){
						echo "checked='checked'";
					}?>> Broker
					<br>
					<input type="radio" name="role" class='role' value="seller"
					<?php if(isset($ep_settings['role']) && $ep_settings['role']=="seller"){
						echo "checked='checked'";
					}?>> Seller
					<br>
					<input type="radio" name="role" class='role' value="other"
					<?php if(isset($ep_settings['role']) && $ep_settings['role']=="other"){
						echo "checked='checked'";
					}?>> Set In Shortcode
					<br>
					</td>
				</tr>
				<tr class="broker_fee_row <?php if($ep_settings['role']=='seller'){echo 'ep_hide';} ?>">
					<th scope="row">Seller Boker Fees (%)</th>
					<td><input type="text" class="regular-text code" name="seller_broker_fee"
					<?php if(isset($ep_settings['seller_broker_fee']) && !empty($ep_settings)){
						echo "value='".$ep_settings['seller_broker_fee']."'";
					}?>> %
					</td>
				</tr>
				<tr class="broker_fee_row <?php if($ep_settings['role']=='seller'){echo 'ep_hide';} ?>">
					<th scope="row"> Buyer Boker Fees (%)</th>
					<td><input type="text" class="regular-text code" name="buyer_broker_fee"
					<?php if(isset($ep_settings['buyer_broker_fee'])){
						echo "value='".$ep_settings['buyer_broker_fee']."'";
					}?>> %
					</td>
				</tr>
				<tr class="broker_fee_row <?php if($ep_settings['role']=='seller'){echo 'ep_hide';} ?>">
					<th scope="row"> Allow BrokerFee Override</th>
					<td><input  type="checkbox" name="broker_fee_override" <?php if(isset($ep_settings['broker_fee_override'])){
						echo "checked='checked'";
					}?>>
					<br>
					
					</td>
				</tr>
				<tr>
					<th scope="row"> Default Inspection Period</th>
					<td><input maxlength="2"  type="text" class="regular-text code" name="inspection_period"
					<?php if(isset($ep_settings['inspection_period'])){
						echo "value='".$ep_settings['inspection_period']."'";
					}?>> Days
					<br>
					<p class="description">Max 30 Days</p>
					</td>
				</tr>
				<tr>
					<th scope="row"> Currency</th>
					<td><select name="currency">
						<option value="USD">USD</option>
						</select>
					</td>
				</tr>
				
				<tr>
				<th>
				<?php
				wp_enqueue_media(); 
				?>
				Button Image</th>
				<td><input class="upload_image_button" type="button" data-uploadtype="header" class="button" value="<?php _e( 'Upload image' ); ?>" />
				<input type='hidden' name='buttom_image_attachment_id' id='header_image_attachment_id' value='<?php echo isset($ep_settings["buttom_image_attachment_id"])?$ep_settings["buttom_image_attachment_id"]:""; ?>'>
				<div class='image-preview-wrapper header-image-preview-wrapper'>
				<span class="ep_close_btn" data-preview="header_image_attachment_id">Delete</span>
					<img id='header-image-preview' src='<?php echo isset($ep_settings["buttom_image_attachment_id"])?wp_get_attachment_url( $ep_settings["buttom_image_attachment_id"] ):""; ?>'>
				</div>
				</td>
				</tr>
				
				<tr>
					<th scope="row"><input name="ep_updatesettings" id="ep_updatesettings" class="button button-primary" value="Save" type="submit"></th>
					<td></td>
				</tr>
			</table>
			<p>Use url <kbd><?=plugins_url("return/escrow-transaction-state.php",__FILE__); ?></kbd> As your webhook url.
			<br>
			<span class='ep_webhook_dev'><a href="https://www.escrow-sandbox.com/integrations/portal/webhooks" target="_blank">Click here</a> to register new webhook on Escrow.com for Sandbox.</span>
			<span class='ep_webhook_live' style="display: none;"><a href="https://www.escrow.com/integrations/portal/webhooks" target="_blank">Click here</a> to register new webhook on Escrow.com for Live.</span>
			</p>
			
		 </form>
		 
		<?php
	}
	/*
	 * Function ep_getdefaultsettings()
	 * Function get the setting saved in db
	 *
	 */
	
	public function ep_getdefaultsettings(){
		global $wpdb;
		$site_set = array();
		$table_prefix = $wpdb->prefix;
		$res = $wpdb->get_results("SELECT * FROM ".$table_prefix."escrow_payment_settings");
		$ret_arr = array();
		
		foreach($res as $key=>$val){
			$ret_arr[$val->param] = $val->value;
		}
		
		return $ret_arr;
	}
	
	/*
	 * Function ep_main()
	 * Function main function to execute the shortcode and display the form
	 *
	 */

	public function ep_main( $atts ){
		
		$html = "";
		if(!isset($atts['id'])){
			$html = "Invalid Code";
			return $html;
		}
		$WPEcrowShortcode = new WPEcrowShortcode();
		$html .= $WPEcrowShortcode->ep_processshortcode($atts['id']);
		
		return $html;
	}
	
	
	/*
	 * Function ep_scripts()
	 * Function to show hide settings
	 *
	 */
	public function ep_scripts(){
		$my_saved_attachment_post_id = get_option( 'media_selector_attachment_id', 0 );
		?>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				jQuery(".enviroment").on("click",function(){
					if(jQuery(this).val() == "live"){
						jQuery("#api_url").val("<?=LIVE_API_URL;?>");
						jQuery(".ep_webhook_dev").hide();
						jQuery(".ep_webhook_live").show();
					}else{
						jQuery("#api_url").val("<?=SANDBOX_API_URL;?>");
						jQuery(".ep_webhook_dev").hide();
						jQuery(".ep_webhook_live").show();
					}
				});
				
				jQuery(document).on("click",".role",function(){
					if(jQuery(this).val() == "seller"){
						jQuery(".broker_fee_row").addClass("ep_hide");
					}else{
						jQuery(".broker_fee_row").removeClass("ep_hide");
					}
				});
				
				jQuery(".image-preview-wrapper").hide();
				if($("#header_image_attachment_id").val()>0 && $("#header_image_attachment_id").val() !== ""){
					jQuery(".header-image-preview-wrapper").show();
				}
				if($("#bg_image_attachment_id").val()>0 && $("#bg_image_attachment_id").val() !== ""){
					jQuery(".bg-image-preview-wrapper").show();
				}
				
				
				var file_frame;
				var utype;
				var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id
				var set_to_post_id = <?php echo $my_saved_attachment_post_id; ?>; // Set this
				jQuery('.upload_image_button').on('click', function( event ){
					utype = jQuery(this).attr("data-uploadtype");
					event.preventDefault();
					// If the media frame already exists, reopen it.
					if ( file_frame ) {
					// Set the post ID to what we want
					file_frame.uploader.uploader.param( 'post_id', set_to_post_id );
					// Open frame
					file_frame.open();
					return;
					} else {
					// Set the wp.media post id so the uploader grabs the ID we want when initialised
					wp.media.model.settings.post.id = set_to_post_id;
					}
					// Create the media frame.
					file_frame = wp.media.frames.file_frame = wp.media({
					title: 'Select a image to upload',
					button: {
					text: 'Use this image',
					},
					multiple: false	// Set to true to allow multiple files to be selected
					});
					// When an image is selected, run a callback.
					file_frame.on( 'select', function() {
							// We set multiple to false so only get one image from the uploader
							attachment = file_frame.state().get('selection').first().toJSON();
							// Do something with attachment.id and/or attachment.url here
							$( '#'+utype+'-image-preview' ).attr( 'src', attachment.url );
							$( '#'+utype+'_image_attachment_id' ).val( attachment.id );
							jQuery("."+utype+"-image-preview-wrapper").show();
							// Restore the main post ID
							wp.media.model.settings.post.id = wp_media_post_id;
							
							});
					// Finally, open the modal
					file_frame.open();
					});
					// Restore the main ID when the add media button is pressed
					jQuery( 'a.add_media' ).on( 'click', function() {
							wp.media.model.settings.post.id = wp_media_post_id;
							});
					jQuery( '.ep_close_btn' ).on( 'click', function() {
								var ele_id = jQuery(this).attr("data-preview");
								jQuery("#"+ele_id).val("");
								jQuery(this).parent().hide();
							});
							
			});
		</script>
		<style>
			.ep_hide{
				display: none;
			}
		</style>
		<?php
	}
}

new WPEcrowPayment();	

	

?>