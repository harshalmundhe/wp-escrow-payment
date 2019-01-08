<?php

/*
 * This file is a webhook for
 * Managing Escrow Transaction.
 *
 * This file make an entry into transaction table
 * For action retured from escrow
 *
 */
// Require globals

define( 'SHORTINIT', true );
require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );
global $wpdb;

$table_prefix = $wpdb->prefix;

 



//get response from escrow
$response = file_get_contents('php://input');

$escrow_reponse = json_decode($response);

if(!isset($escrow_reponse->transaction_id) || $escrow_reponse->transaction_id=="" || !is_numeric($escrow_reponse->transaction_id)){
	echo "Invalid Escrow Transaction";
	exit();
}
$event = $escrow_reponse->event;
$transaction_id = $escrow_reponse->transaction_id;

$order_id = $wpdb->get_var("SELECT order_id FROM ".$table_prefix."escrow_orders WHERE escrow_transaction_id ='".$transaction_id."'");
if(!isset($order_id) || $order_id==""){
	echo "Invalid Escrow Transaction";
	exit();
}

$wpdb->query("INSERT INTO ".$table_prefix."escrow_transaction_phases(escrow_transaction_id,order_id,phase) VALUES (
			 '".$transaction_id."',
			 '".$order_id."',
			 '".$event."')
			 ");

$wpdb->query("UPDATE ".$table_prefix."escrow_orders SET status='".$event."' WHERE escrow_transaction_id ='".$transaction_id."'");
