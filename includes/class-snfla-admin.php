<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Admin {
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
	}

	public static function menu() {
		$cap = apply_filters( 'snfla_admin_menu_capability', 'sabri_feed_run_migrations' );
		add_management_page( 'File 04 Legacy Adapter', 'File 04 Legacy Adapter', $cap, 'snfla-legacy-adapter', array( __CLASS__, 'page' ) );
	}

	public static function assets( $hook ) {
		if ( 'tools_page_snfla-legacy-adapter' === $hook ) {
			wp_enqueue_style( 'snfla-admin', SNFLA_URL . 'assets/css/admin.css', array( 'dashicons' ), SNFLA_VERSION );
		}
	}

	public static function dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) { return; }
		$handover = get_option( SNFLA_Database::HANDOVER_OPTION, array() );
		if ( get_transient( 'snfla_activation_notice' ) && SNFLA_Integrity::evidence_valid( $handover ) && ( ! empty( $handover['deactivated_plugins'] ) || ! empty( $handover['legacy_pages'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>File 04 legacy handover completed.</strong> The obsolete publishing runtime was disabled and its owned public pages were quarantined without deleting source records.</p></div>';
			delete_transient( 'snfla_activation_notice' );
		}
		if ( ! SNFLA_Capabilities::file21_ready() ) {
			echo '<div class="notice notice-error"><p><strong>File 04 Legacy Adapter is fail-closed.</strong> Canonical File 21 package ' . esc_html( SNFLA_FILE21_MIN_PACKAGE ) . ' or later and File 00 fresh identity assurance are required.</p></div>';
		}
	}

	public static function page() {
		$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
		if ( is_wp_error( $actor ) ) {
			$error_data = $actor->get_error_data();
			$response   = is_array( $error_data ) && isset( $error_data['status'] ) ? absint( $error_data['status'] ) : 403;
			wp_die( esc_html( $actor->get_error_message() ), '', array( 'response' => $response ) );
		}
		global $wpdb;
		$t = SNFLA_Database::tables();
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$tabs = array( 'overview' => 'Overview', 'inventory' => 'Inventory', 'dry-run' => 'Dry Run', 'mapping' => 'Mapping', 'conflicts' => 'Conflicts', 'redirects' => 'Redirects', 'reconciliation' => 'Reconciliation', 'rollback' => 'Rollback', 'retirement' => 'Retirement' );
		$status = SNFLA_Schema::public_status();
		?>
		<div class="wrap snfla-admin">
			<h1><span class="dashicons dashicons-migrate"></span> File 04 — Legacy Foundation Adapter</h1>
			<p class="snfla-lead">Read-only, auditable and reversible migration into canonical File 21. This module does not own publishing, feed ranking, comments, reactions, saves, reports, navigation or public composition.</p>
			<div class="snfla-state"><strong>Lifecycle:</strong> <?php echo esc_html( $status['state'] ); ?> <span>v<?php echo absint( $status['version'] ); ?></span> · <strong>Open conflicts:</strong> <?php echo absint( SNFLA_Mapping::open_conflict_count() ); ?> · <strong>File 21:</strong> <?php echo SNFLA_Capabilities::file21_ready() ? 'Ready' : 'Blocked'; ?></div>
			<nav class="nav-tab-wrapper" aria-label="File 04 migration sections">
			<?php foreach ( $tabs as $key => $label ) : ?><a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'snfla-legacy-adapter', 'tab' => $key ), admin_url( 'tools.php' ) ) ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( self::icon( $key ) ); ?>"></span><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
			</nav>
			<section class="snfla-panel">
			<?php
			switch ( $tab ) {
				case 'inventory': self::json_block( SNFLA_Inventory::locked() ); break;
				case 'dry-run': self::json_block( get_option( SNFLA_Schema::DRY_RUN_OPTION, array() ) ); break;
				case 'mapping': self::table_block( $wpdb->get_results( "SELECT legacy_id,target_id,target_type,status,source_checksum,target_checksum,run_uuid,last_error_code,updated_at FROM {$t['map']} ORDER BY legacy_id ASC LIMIT 500", ARRAY_A ) ); break;
				case 'conflicts': self::table_block( $wpdb->get_results( "SELECT id,legacy_id,conflict_code,severity,status,run_uuid,created_at,resolved_at FROM {$t['conflicts']} ORDER BY status ASC,severity DESC,id DESC LIMIT 500", ARRAY_A ) ); break;
				case 'redirects': self::json_block( array( 'state' => $status['state'], 'fallback_window' => get_option( SNFLA_Schema::FALLBACK_OPTION, array() ), 'policy' => 'Same-origin 302 redirects only during cutover/fallback; permanent redirects or gone responses must be handed to a verified canonical route owner before retirement.' ) ); break;
				case 'reconciliation': self::json_block( SNFLA_Reconciliation::report() ); break;
				case 'rollback': self::json_block( SNFLA_Rollback::proof() ); break;
				case 'retirement': self::json_block( array( 'required_confirmation' => SNFLA_Retirement::CONFIRMATION, 'evidence' => get_option( SNFLA_Schema::RETIREMENT_OPTION, array() ), 'source_deletion' => 'Never automatic' ) ); break;
				default: self::json_block( array( 'status' => $status, 'file21' => SNFLA_File21_Adapter::status(), 'backup_proof_valid' => SNFLA_Migration::backup_proof_valid(), 'reconciliation' => SNFLA_Reconciliation::report(), 'rest_namespace' => SNFLA_REST::NAMESPACE, 'legacy_page_quarantine' => SNFLA_Database::public_page_quarantine_status(), 'runbook' => 'MIGRATION-RUNBOOK.md and ROLLBACK-RUNBOOK.md' ) );
			}
			?>
			</section>
			<p><strong>Mutations are intentionally executed only through the authenticated REST or WP-CLI contracts</strong>, with nonce/CSRF protection, File 00 step-up assurance, canonical capabilities, idempotency, locking and audit evidence.</p>
		</div>
		<?php
	}

	private static function json_block( $data ) {
		echo '<pre class="snfla-json">' . esc_html( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
	}

	private static function table_block( $rows ) {
		$rows = is_array( $rows ) ? $rows : array();
		if ( empty( $rows ) ) { echo '<p>No records.</p>'; return; }
		echo '<div class="snfla-table-wrap"><table class="widefat striped"><thead><tr>';
		foreach ( array_keys( $rows[0] ) as $key ) { echo '<th>' . esc_html( $key ) . '</th>'; }
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) { echo '<tr>'; foreach ( $row as $value ) { echo '<td><code>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</code></td>'; } echo '</tr>'; }
		echo '</tbody></table></div>';
	}

	private static function icon( $tab ) {
		$icons = array( 'overview' => 'dashboard', 'inventory' => 'database', 'dry-run' => 'visibility', 'mapping' => 'randomize', 'conflicts' => 'warning', 'redirects' => 'external', 'reconciliation' => 'yes-alt', 'rollback' => 'undo', 'retirement' => 'archive' );
		return $icons[ $tab ] ?? 'admin-tools';
	}
}
