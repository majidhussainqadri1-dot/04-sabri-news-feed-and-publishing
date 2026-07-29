<?php
/**
 * Plugin Name: Sabri News Feed and Publishing
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Governed publication composition, editorial review, interactions, reports, and approved feed data for the Sabri Social Homeopathy Platform.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: sabri-news-publishing
 */

defined( 'ABSPATH' ) || exit;

define( 'SNP_VERSION', '0.2.0' );
define( 'SNP_SCHEMA_VERSION', '2' );
define( 'SNP_FILE', __FILE__ );
define( 'SNP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNP_URL', plugin_dir_url( __FILE__ ) );

$files = array(
	'class-snp-membership-adapter.php',
	'class-snp-profile-adapter.php',
	'class-snp-notification-adapter.php',
	'class-snp-rate-limiter.php',
	'class-snp-audit.php',
	'class-snp-media.php',
	'class-snp-permissions.php',
	'class-snp-content.php',
	'class-snp-publication-state.php',
	'class-snp-activator.php',
	'class-snp-publishing.php',
	'class-snp-feed.php',
	'class-snp-interactions.php',
	'class-snp-comments.php',
	'class-snp-admin.php',
	'class-snp-seo.php',
	'class-snp-privacy.php',
	'class-snp-plugin.php',
);
foreach ( $files as $file ) {
	require_once SNP_DIR . 'includes/' . $file;
}

register_activation_hook( SNP_FILE, array( 'SNP_Activator', 'activate' ) );
register_deactivation_hook( SNP_FILE, array( 'SNP_Activator', 'deactivate' ) );

function snp_query_approved_publications( $args = array() ) {
	return SNP_Feed::query( is_array( $args ) ? $args : array() );
}

function snp_render_publication_feed( $args = array() ) {
	return SNP_Feed::render( is_array( $args ) ? $args : array() );
}

function snp_start_plugin() {
	( new SNP_Plugin() )->run();
}
add_action( 'plugins_loaded', 'snp_start_plugin' );
