<?php

//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) 
    exit();

//future usage for plugin options default template:
	//$option_name = 'plugin_option_name';
	//delete_option( $option_name );
	// For site options in multisite
	//delete_site_option( $option_name );  

//drop custom db table(s)
global $wpdb;
$tables = array(
	$wpdb->prefix."amdbible_key_eng",
	$wpdb->prefix."amdbible_key_abbr_eng",
	$wpdb->prefix."amdbible_key_genre_eng",
	$wpdb->prefix."amdbible_cross_reference",
	$wpdb->prefix."amdbible_kjv",
	$wpdb->prefix."amdbible_plans"
);
$tables = implode(",",$tables);
$wpdb->query("DROP TABLE IF EXISTS ".$tables);


?>