<?php


//Delete the table when plugin is deleted
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;

$sql = "DROP TABLE IF EXISTS `".$wpdb->prefix . "escrow_payment_settings`;";
$wpdb->query($sql);

$sql = "DROP TABLE IF EXISTS `".$wpdb->prefix . "escrow_orders`;";
$wpdb->query($sql);


$sql = "DROP TABLE IF EXISTS `".$wpdb->prefix . "escrow_products`;";
$wpdb->query($sql);

$sql = "DROP TABLE IF EXISTS `".$wpdb->prefix . "escrow_transaction_phases`;";
$wpdb->query($sql);

$sql = "DROP TABLE IF EXISTS `".$wpdb->prefix . "escrow_transaction_shortcodes`;";
$wpdb->query($sql);

$sql = "DROP TABLE IF EXISTS `".$wpdb->prefix . "escrow_transaction_shortcodes_settings`;";
$wpdb->query($sql);

delete_option("ep_version");