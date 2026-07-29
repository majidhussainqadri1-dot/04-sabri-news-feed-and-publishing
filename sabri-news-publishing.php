<?php
/**
 * Plugin Name: Sabri News Feed and Publishing
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Moderated news feed, approved publishing topics, Like/Unlike, Save and reporting for the Sabri Social Homeopathy Platform.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: sabri-news-publishing
 */

defined( 'ABSPATH' ) || exit;

define( 'SNP_VERSION', '0.1.0' );
define( 'SNP_FILE', __FILE__ );
define( 'SNP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNP_URL', plugin_dir_url( __FILE__ ) );

require_once SNP_DIR . 'includes/class-snp-permissions.php';
require_once SNP_DIR . 'includes/class-snp-content.php';
require_once SNP_DIR . 'includes/class-snp-activator.php';
require_once SNP_DIR . 'includes/class-snp-publishing.php';
require_once SNP_DIR . 'includes/class-snp-feed.php';
require_once SNP_DIR . 'includes/class-snp-interactions.php';
require_once SNP_DIR . 'includes/class-snp-comments.php';
require_once SNP_DIR . 'includes/class-snp-admin.php';
require_once SNP_DIR . 'includes/class-snp-seo.php';
require_once SNP_DIR . 'includes/class-snp-privacy.php';
require_once SNP_DIR . 'includes/class-snp-plugin.php';

register_activation_hook( SNP_FILE, array( 'SNP_Activator', 'activate' ) );
register_deactivation_hook( SNP_FILE, array( 'SNP_Activator', 'deactivate' ) );

function snp_start_plugin() {
	( new SNP_Plugin() )->run();
}
add_action( 'plugins_loaded', 'snp_start_plugin' );
