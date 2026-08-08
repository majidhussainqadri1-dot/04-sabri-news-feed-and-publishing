<?php
/**
 * Plugin Name: Sabri News Feed Legacy Foundation Adapter
 * Plugin URI: https://github.com/majidhussainqadri1-dot/04-sabri-news-feed-and-publishing
 * Description: Read-only, auditable, reversible migration adapter from historical File 04 records into canonical File 21, with Future18 migration intelligence, two ten-round hardening audits and safety controls.
 * Version: 2.0.2
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * License: GPL-2.0-or-later
 * Text Domain: sabri-news-feed-legacy-adapter
 */

defined( 'ABSPATH' ) || exit;

define( 'SNFLA_VERSION', '2.0.2' );
// Second-audit hardening adds no custom-table schema; proven v1.3.0 storage remains current.
define( 'SNFLA_SCHEMA_VERSION', '1.3.0' );
define( 'SNFLA_FILE', __FILE__ );
define( 'SNFLA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNFLA_URL', plugin_dir_url( __FILE__ ) );
define( 'SNFLA_TEXT_DOMAIN', 'sabri-news-feed-legacy-adapter' );
define( 'SNFLA_FILE21_MIN_PACKAGE', '1.0.3.2' );
define( 'SNFLA_FILE21_MIN_RUNTIME', '1.0.3' );

$snfla_files = array(
	'class-snfla-schema.php',
	'class-snfla-capabilities.php',
	'class-snfla-database.php',
	'class-snfla-checksum.php',
	'class-snfla-integrity.php',
	'class-snfla-audit.php',
	'class-snfla-mapping.php',
	'class-snfla-inventory.php',
	'class-snfla-file21-adapter.php',
	'class-snfla-interaction-provider.php',
	'class-snfla-migration.php',
	'class-snfla-reconciliation.php',
	'class-snfla-redirects.php',
	'class-snfla-rollback.php',
	'class-snfla-retirement.php',
	'class-snfla-rest.php',
	'class-snfla-admin.php',
	'class-snfla-cli.php',
	'class-snfla-central-plan.php',
	'class-snfla-plan-completion.php',
	'class-snfla-future18.php',
	'class-snfla-post-audit-hardening.php',
	'class-snfla-plugin.php',
);

foreach ( $snfla_files as $snfla_file ) {
	require_once SNFLA_DIR . 'includes/' . $snfla_file;
}

register_activation_hook( SNFLA_FILE, array( 'SNFLA_Database', 'activate' ) );
register_deactivation_hook( SNFLA_FILE, array( 'SNFLA_Database', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		SNFLA_Central_Plan::boot();
		SNFLA_Plan_Completion::boot();
		SNFLA_Future18::boot();
		SNFLA_Post_Audit_Hardening::boot();
		SNFLA_Plugin::instance()->boot();
	},
	30
);