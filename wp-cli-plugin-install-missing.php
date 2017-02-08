<?php
/**
 * Plugin Name: WP CLI Install Missing
 * Plugin URI:  https://github.com/billerickson/wp-cli-install-missing
 * Description: Using wp cli, install any plugins that are "active" but missing.
 * Version:     0.1.0
 * Author:      Bill Erickson
 * Author URI:  http://www.billerickson.net
 * License: GPL v2.0
 */

if ( ! ( defined('WP_CLI') && WP_CLI ) ) {
	return;
}

function is_flag_enabled( $assoc_args, $flag ) {
	return isset( $assoc_args[$flag] ) && (bool) $assoc_args[$flag] ? true : false;
}

function handle_response( $response, $continue_on_error=true ) {
	if( $response->return_code > 0 ) {
		if( $continue_on_error ) {
			WP_CLI::log( $response->stderr );
		} else {
			WP_CLI::error( str_replace( array('Error: ', 'Warning: '), '', $response->stderr ) );
			WP_CLI::halt( 1 );
		}
	} else {
		if( !empty( $response->stderr ) ) {
			WP_CLI::log( $response->stderr );
		}
		WP_CLI::log( $response->stdout );
	}

	return $response->return_code;
}

function active_plugins_list( $site_url=null ) {
	$assoc_args = array( 'format' => 'json', 'status' => 'active' );
	if($site_url != null) {
		$assoc_args['url'] = $site_url;
	}

	$response = WP_CLI::launch_self( 'plugin list', array(), $assoc_args, false, true );

	if( $response->return_code > 0 ) {
		WP_CLI::error( str_replace( 'Error: ', '', $response->stderr ) );
	}

	return wp_list_pluck( json_decode( $response->stdout ), 'name' );
}

function installed_plugins_list( $blog_id=null ) {
	if( $blog_id == null ) {
		$installed = get_option( 'active_plugins' );
	} else {
		switch_to_blog($blog_id);
		$installed = get_option( 'active_plugins' );
		restore_current_blog();
	}

	return array_map( function($value) { return strstr( $value, '/', true ); }, $installed );
}

function plugin_install( $plugin_name, $continue_on_error ) {
	$response = WP_CLI::launch_self( 'plugin install', array( $plugin_name ), array('activate' => $activate), false, true );

	return handle_response( $response, $continue_on_error );
}

function install_missing_plugins( $dry_run, $continue_on_error=false, $site=null ) {

	if( $site == null ) {
		$active_plugins = active_plugins_list( );
		$installed_plugins = installed_plugins_list( );
		$success_message = 'Installed missing plugins.';
	} else {
		$active_plugins = active_plugins_list( $site->url );
		$installed_plugins = installed_plugins_list( $site->blog_id );
		$success_message = 'Installed missing plugins for ' . $site->url .'.';
	}
	
	$missing_plugins = array_diff( $installed_plugins, $active_plugins );

	// Print the current site when running in network mode
	if( $site != null ) {
		WP_CLI::log( '' );
		WP_CLI::log( 'Site: ' . $site->url );
	}

	// No Missing Plugins
	if( empty( $missing_plugins ) ) {
		WP_CLI::success( 'No missing plugins' );
		return;
	}

	// Display list of Missing Plugins
	WP_CLI::log( 'The following plugins are missing:' );
	foreach( $missing_plugins as $plugin ) {
		WP_CLI::log( '* ' . $plugin );
	}

	// Quit here for dry run
	if ( $dry_run ) {
		return;
	}

	// Install plugins
	WP_CLI::log( 'Installing plugins...' );
	foreach( $missing_plugins as $plugin ) {
		plugin_install( $plugin, $continue_on_error );
	}
	WP_CLI::success( $success_message );
}

/**
 * Installs any plugins that are active but missing
 *
 * ## OPTIONS
 *
 * [--continue-on-error]
 * : Continue execution if a plugin fails to install
 *
 * [--network]
 * : Run the missing plugin install action for each site in the network
 *
 * [--dry-run]
 * : Run the search and show report, but don't install missing plugins
 *
 */
function be_wpcli_install_missing( $args, $assoc_args ) {

	$dry_run = is_flag_enabled( $assoc_args, 'dry-run' );
	$network = is_flag_enabled( $assoc_args, 'network' );
	$continue_on_error = is_flag_enabled( $assoc_args, 'continue-on-error' );

	if( $network && !is_multisite( ) ) {
		WP_CLI::error_multi_line( array(
			"This is not a multisite installation!",
			"Remove the --network flag to run the plugin on a normal installation"
		) );
		WP_CLI::halt( 1 );
	}

	$sites = null;
	if( $network ) {
		$response = WP_CLI::launch_self( 'site list', array(), array( 'format' => 'json', 'status' => 'active' ), false, true );
		$sites = json_decode($response->stdout);
	}

	if($sites == null) {
		install_missing_plugins( $dry_run, $continue_on_error );
	} else {
		foreach ($sites as $site) {
			install_missing_plugins( $dry_run, $continue_on_error, $site );
		}
	}
}
WP_CLI::add_command( 'plugin install-missing', 'be_wpcli_install_missing' );
