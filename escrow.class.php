<?php
/*
* Escrow Class
* Manage All the tasks that are related to escrow transaction
*
*/


require_once("wp-escrow-payment.php");
require_once("wp-escrow-shortcode.php");
class Escrow{

	/**
		* function LogEscrowTransaction()
		* Log the current step of escrow webhook hit in database
		*
		*/
	public function LogEscrowTransaction($param){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		
			
		$wpdb->insert(
				$table_prefix."escrow_transaction_phases",
				array(
					"escrow_transaction_id"=> $param['transaction_id'],
					"order_id"=> $param['order_id'],
					"phase"=> $param['phase'],
					"phase_date"=> current_time( 'mysql' )
				)
				);
	}

	
	public function CreateEscrowProduct($param){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
				
		
		$wpdb->insert(
					  $table_prefix ."escrow_products",
					  array(
						"name"=>$param['domain_name'],
						"price"=>$param['domain_amt'],
						"seller_email"=>$param['seller_email'],
						"buyer_email"=>$param['buyer_email'],
						"role"=>$param['role'],
						"buyer_broker_fee"=>$param['buyer_broker_fee'],
						"seller_broker_fee"=>$param['seller_broker_fee'],
					  )
					  );
		return $wpdb->insert_id;
	}
	
	public function CreateEscrowOrder($param){
		global $wpdb;
		$table_prefix = $wpdb->prefix;
	
		$wpdb->insert(
			$table_prefix."escrow_orders",
			array(
				"product_id"=>$param['product_id'],
				"escrow_transaction_id"=>$param['transaction_id'],
				"price"=>$param['domain_amt'],
				"description"=>$param['domain_desc'],
				"status"=>$param['phase'],
				"created_date"=> current_time( 'mysql' )
			)
		);
		return $wpdb->insert_id;
	}
	
	/**
		* function CreateEscrowTransaction()
		* Creates a new escrow transaction
		*
		*/

	public function CreateEscrowTransaction($param){
		$WPEcrowPayment = new WPEcrowPayment();
		$escrow_settings = $WPEcrowPayment->ep_getdefaultsettings();
		
		$WPEcrowShortcode = new WPEcrowShortcode();
		list($dname,$damt,$ddesc,$role,$seller_broker_fee,$buyer_broker_fee,$seller_email) = $WPEcrowShortcode->ep_getshortcodedetails($param['shortcode_id']);
		
		$api_url = $escrow_settings['api_url'];
		$apikey = $escrow_settings['api_key'];
		$api_email = $escrow_settings['api_email'];
		
		if($role == "seller"){
			$seller_email = get_option('admin_email');
			$parties = array(
						array(
							'customer' => $param['buyer_email'],
							'role' => 'buyer',
						),
						array(
							'customer' => 'me',
							'role' => 'seller',
						),
				);
			$schedule = array(
				'payer_customer' => $param['buyer_email'],
				'amount' => $damt,
				'beneficiary_customer' => 'me',
			);
			
			
			
			$final_arr = array(
				'currency' => strtolower($escrow_settings['currency']),
				'items' => array(
									array(
										'description' => $ddesc,
										'schedule' => array(
											$schedule,
										),
										'title' =>  $dname,
										'inspection_period' => $this->GetInspectionPeriodInSeconds($escrow_settings['inspection_period']),
										'type' => 'domain_name',
										'quantity' => '1',
									),
						),
				'description' =>  $ddesc,
				'parties' => $parties,
			);
			
			
			
	
		}else{
			
						
			$parties = array(
						array(
							'customer' => 'me',
							'role' => 'broker',
						),
						array(
							'customer' => $param['buyer_email'],
							'role' => 'buyer',
						),
						array(
							'customer' => $seller_email,
							'role' => 'seller',
						),
				);
				
			$schedule = array(
				'payer_customer' => $param['buyer_email'],
				'amount' => $damt,
				'beneficiary_customer' => $seller_email,
			);
			
			
			$final_arr = array(
				'currency' => strtolower($escrow_settings['currency']),
				'items' => array(
									array(
										'description' => $ddesc,
										'schedule' => array(
											$schedule,
										),
										'title' =>  $dname,
										'inspection_period' => $this->GetInspectionPeriodInSeconds($escrow_settings['inspection_period']),
										'type' => 'domain_name',
										'quantity' => '1',
									),
					array(
						'type' => 'broker_fee',
						'schedule' => array(
										array(
												'payer_customer' => $param['buyer_email'],
												'amount' => $this->CalculateBrokerFee($damt,$buyer_broker_fee),
												'beneficiary_customer' => 'me',
										),
						),
					),
                     array(
						'type' => 'broker_fee',
						'schedule' => array(
										array(
												'payer_customer' => $seller_email,
												'amount' => $this->CalculateBrokerFee($damt,$seller_broker_fee),
												'beneficiary_customer' => 'me',
										),
						),
					),
				),
				'description' =>  $ddesc,
				'parties' => $parties,
			);
			
		}

			
		$curl = curl_init();

		curl_setopt_array($curl, array(
		CURLOPT_URL => $api_url.'transaction',
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_USERPWD => $api_email.":".$apikey,
		CURLOPT_HTTPHEADER => array(
						'Content-Type: application/json'
		),
		CURLOPT_POSTFIELDS => json_encode(
			$final_arr
		)
		));

			$output = curl_exec($curl);
			curl_close($curl);

			$output = json_decode($output);
			if(isset($output->errors) || isset($output->error)){
				$error['error'] = 1;
				$error['message'] = "Something went wrong.Please try again later.";
				return $error;
			}
			
			$param['transaction_id'] = $output->id;
			
			$param['domain_name'] = $dname;
			$param['domain_amt'] = $damt;
			$param['domain_desc'] = $ddesc;
			$param['seller_broker_fee'] = $seller_broker_fee;
			$param['buyer_broker_fee'] = $buyer_broker_fee;
			$param['seller_email'] = $seller_email;
			$param['role'] = $role;
			$param['phase'] = "new";
			
			$product_id = $this->CreateEscrowProduct($param);
			$param['product_id'] = $product_id;
			$order_id = $this->CreateEscrowOrder($param);			
			$param['order_id'] = $order_id;
			$this->LogEscrowTransaction($param);
			
			return "success";
	}

	/**
		* function CalculateBrokerFee()
		* Calculates buyer broker fee from amount and % set in db
		*
		*/
	public function CalculateBrokerFee($amount,$percentfee){
		return (($amount*$percentfee)/100);
	}

	/**
		* function GetInspectionPeriodInSeconds()
		* Get the inspection period in seconds for days stored in db
		*
		*/
	public function GetInspectionPeriodInSeconds($inspection_period){
		return (intVal($inspection_period)*60*60*24);
	}




	/**
		* function ApproveEscrowWireTransfer()
		* Approve wire transfer payment
		* to be used only in development evnironment
		*
		*/

	public function ApproveEscrowWireTransfer($param){
		$WPEcrowPayment = new WPEcrowPayment();
		$escrow_settings = $WPEcrowPayment->ep_getdefaultsettings();
		
		$apikey = $escrow_settings['api_key'];
		$api_email = $escrow_settings['api_email'];
		$curl = curl_init();
		curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://integrationhelper.escrow-sandbox.com/v1/transaction/'.$param['transaction_id'].'/payments_in',
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_USERPWD => $api_email.":".$apikey,
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json'
		),
		CURLOPT_POSTFIELDS => json_encode(
			array(
				'method' => 'wire_transfer',
				'amount' => $param['amount'],
			)
		)
		));

		$output = curl_exec($curl);

		curl_close($curl);
		return $output;
	}
	
}
