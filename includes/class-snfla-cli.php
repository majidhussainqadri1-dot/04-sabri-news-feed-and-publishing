<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_CLI {
	public static function register() {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'snfla', __CLASS__ );
		}
	}

	/** Show status. */
	public function status() {
		$request = new WP_REST_Request( 'GET', '/' . SNFLA_REST::NAMESPACE . '/status' );
		$response = SNFLA_REST::status( $request );
		WP_CLI::line( wp_json_encode( $response->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/** Lock exact inventory. */
	public function inventory( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_RUN );
		$result = SNFLA_Inventory::lock( $actor, $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version() );
		$this->output( $result );
	}

	/** Execute bounded dry-run. */
	public function dry_run( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_RUN );
		$result = SNFLA_Migration::dry_run( $actor, $assoc['limit'] ?? 100, $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version() );
		$this->output( $result );
	}


	/** Record a recent backup/restore proof bound to the locked source. */
	public function backup_proof( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_RUN );
		$this->output( SNFLA_Migration::record_backup_proof( $actor, $assoc['reference'] ?? '', $assoc['checksum'] ?? '', $assoc['created-at'] ?? '' ) );
	}

	/** Migrate explicit comma-separated legacy IDs. */
	public function migrate( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_RUN );
		$ids = array_filter( array_map( 'absint', explode( ',', (string) ( $assoc['ids'] ?? '' ) ) ) );
		$result = SNFLA_Migration::migrate( $actor, $ids, $assoc['idempotency-key'] ?? '', $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version(), true );
		$this->output( $result );
	}

	/** Reconcile all mappings. */
	public function reconcile( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_REVIEW );
		$this->output( SNFLA_Reconciliation::run( $actor, $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version() ) );
	}


	/** Approve canonical redirect cutover after fresh green reconciliation. */
	public function cutover( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_REVIEW );
		$this->output( SNFLA_Reconciliation::approve_cutover( $actor, $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version() ) );
	}

	/** Open a bounded signed read-only tombstone fallback window. */
	public function fallback( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_REVIEW );
		$this->output( SNFLA_Redirects::open_fallback( $actor, $assoc['hours'] ?? 24, $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version() ) );
	}

	/** Resolve one conflict only after its underlying defect is gone. */
	public function resolve_conflict( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_REVIEW );
		$this->output( SNFLA_Reconciliation::resolve_conflict( $actor, $assoc['id'] ?? 0, $assoc['resolution-code'] ?? '' ) );
	}

	/** Roll back explicit comma-separated legacy IDs non-destructively. */
	public function rollback( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_RUN );
		$ids = array_filter( array_map( 'absint', explode( ',', (string) ( $assoc['ids'] ?? '' ) ) ) );
		$this->output( SNFLA_Rollback::execute( $actor, $ids, $assoc['idempotency-key'] ?? '', $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version() ) );
	}

	/** Retire after all gates pass. */
	public function retire( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_RETIRE );
		$this->output( SNFLA_Retirement::retire( $actor, $assoc['confirmation'] ?? '', $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version() ) );
	}

	private function actor( $capability ) {
		if ( ! SNFLA_Retirement::mutations_allowed() ) { WP_CLI::error( 'snfla_retired: The adapter is retired and mutation commands are disabled.' ); }
		$actor = SNFLA_Capabilities::current_actor( $capability );
		if ( is_wp_error( $actor ) ) { WP_CLI::error( $actor->get_error_code() . ': ' . $actor->get_error_message() ); }
		return $actor;
	}

	private function output( $result ) {
		if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_code() . ': ' . $result->get_error_message() ); }
		WP_CLI::success( wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) );
	}
}
