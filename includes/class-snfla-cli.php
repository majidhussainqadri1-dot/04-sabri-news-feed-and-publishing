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
		$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
		if ( is_wp_error( $actor ) ) { WP_CLI::error( $actor->get_error_code() . ': ' . $actor->get_error_message() ); }
		$request = new WP_REST_Request( 'GET', '/' . SNFLA_REST::NAMESPACE . '/status' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = SNFLA_REST::status( $request );
		$encoded = wp_json_encode( $response->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) { WP_CLI::error( 'snfla_output_encoding_failed: Status evidence could not be encoded safely.' ); }
		WP_CLI::line( $encoded );
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
		$this->output( SNFLA_Migration::record_backup_proof( $actor, array( 'reference' => $assoc['reference'] ?? '', 'checksum' => $assoc['checksum'] ?? '', 'created_at_utc' => $assoc['created-at'] ?? '', 'restore_reference' => $assoc['restore-reference'] ?? '', 'restore_checksum' => $assoc['restore-checksum'] ?? '', 'restored_at_utc' => $assoc['restored-at'] ?? '', 'restored_source_signature' => $assoc['restored-source-signature'] ?? '', 'restored_post_count' => $assoc['restored-post-count'] ?? -1, 'restored_comment_count' => $assoc['restored-comment-count'] ?? -1, 'restored_table_counts' => isset( $assoc['restored-table-counts-json'] ) && is_array( json_decode( (string) $assoc['restored-table-counts-json'], true ) ) ? json_decode( (string) $assoc['restored-table-counts-json'], true ) : array() ) ) );
	}

	/** Migrate explicit comma-separated legacy IDs. */
	public function migrate( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_RUN );
		$ids = $this->parse_ids( $assoc['ids'] ?? '' );
		$with_interactions = ! isset( $assoc['with-interactions'] ) || ! in_array( strtolower( (string) $assoc['with-interactions'] ), array( '0', 'false', 'no' ), true );
		$result = SNFLA_Migration::migrate( $actor, $ids, $assoc['idempotency-key'] ?? '', $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version(), $with_interactions );
		$this->output( $result );
	}

	/** Approve explicit source-only quarantine dispositions for conflicted dry-run IDs. */
	public function quarantine( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_REVIEW );
		$ids = $this->parse_ids( $assoc['ids'] ?? '' );
		$this->output(
			SNFLA_Migration::quarantine_disposition(
				$actor,
				$ids,
				$assoc['reason-code'] ?? '',
				$assoc['decision-reference'] ?? '',
				$assoc['expected-state'] ?? SNFLA_Schema::state(),
				$assoc['expected-version'] ?? SNFLA_Schema::version()
			)
		);
	}

	/** Resume bounded interaction migration for an existing File 21 target. */
	public function resume_interactions( $args, $assoc ) {
		unset( $args );
		$actor = $this->actor( SNFLA_Capabilities::CAP_RUN );
		$this->output( SNFLA_Interaction_Provider::resume( $actor, $assoc['legacy-id'] ?? 0, $assoc['max-records'] ?? SNFLA_Interaction_Provider::DEFAULT_RECORD_BUDGET ) );
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
		$ids = $this->parse_ids( $assoc['ids'] ?? '' );
		$this->output( SNFLA_Rollback::execute( $actor, $ids, $assoc['idempotency-key'] ?? '', $assoc['expected-state'] ?? SNFLA_Schema::state(), $assoc['expected-version'] ?? SNFLA_Schema::version(), ! empty( $assoc['restore-handover'] ), $assoc['handover-confirmation'] ?? '' ) );
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

	private function parse_ids( $raw ) {
		$parts = explode( ',', (string) $raw );
		$ids = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $part ) ) { WP_CLI::error( 'snfla_invalid_ids: IDs must be positive decimal integers.' ); }
			$id = (int) $part;
			if ( isset( $ids[ $id ] ) ) { WP_CLI::error( 'snfla_duplicate_ids: Duplicate legacy IDs are not accepted.' ); }
			$ids[ $id ] = $id;
		}
		if ( empty( $ids ) || count( $ids ) > SNFLA_Migration::MAX_BATCH ) { WP_CLI::error( 'snfla_invalid_ids: A non-empty bounded ID list is required.' ); }
		return array_values( $ids );
	}

	private function output( $result ) {
		if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_code() . ': ' . $result->get_error_message() ); }
		$encoded = wp_json_encode( $result, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) { WP_CLI::error( 'snfla_output_encoding_failed: Result evidence could not be encoded safely.' ); }
		WP_CLI::success( $encoded );
	}
}
