<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Inventory {
	private static $capture_errors = array();
	const LEGACY_POST_TYPE = 'snp_publication';
	const LEGACY_TAXONOMY  = 'snp_topic';
	const STREAM_BATCH      = 500;

	public static function capture() {
		global $wpdb;
		self::$capture_errors = array();
		$post_counts = array();
		foreach ( array( 'publish', 'pending', 'draft', 'private', 'future', 'trash' ) as $status ) {
			$post_counts[ $status ] = absint( self::query_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status=%s", self::LEGACY_POST_TYPE, $status ), 'post_count_' . $status, 0 ) );
		}
		$table_counts = array();
		$table_digests = array();
		foreach ( self::legacy_table_suffixes() as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			$exists = self::query_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ), 'table_probe_' . $suffix, '' );
			$table_counts[ $suffix ] = $exists === $table ? absint( self::query_var( "SELECT COUNT(*) FROM `{$table}`", 'table_count_' . $suffix, 0 ) ) : 0;
			$table_digests[ $suffix ] = $exists === $table ? self::table_digest( $table ) : hash( 'sha256', '' );
		}

		$post_hash = hash_init( 'sha256' );
		$legacy_record_count = 0;
		$post_stream = self::each_legacy_id(
			array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			static function ( $legacy_id ) use ( $post_hash, &$legacy_record_count ) {
				$checksum = SNFLA_Checksum::post( $legacy_id );
				if ( '' === $checksum ) {
					self::capture_error( 'legacy_post_checksum_failed' );
					return true;
				}
				$legacy_record_count++;
				hash_update( $post_hash, $legacy_id . ':' . $checksum . "\n" );
			}
		);
		if ( is_wp_error( $post_stream ) ) {
			self::capture_error( $post_stream->get_error_code() );
		}

		$routes = array(
			'snp_page_map'       => SNFLA_Checksum::canonicalize( (array) get_option( 'snp_page_map', array() ) ),
			'legacy_shortcodes'  => array( 'sabri_news_home', 'sabri_news_feed', 'sabri_publish_form', 'sabri_publication_feed', 'sabri_my_publication_reports' ),
			'legacy_page_digest' => self::legacy_page_digest(),
		);
		$inventory = array(
			'schema'                    => 2,
			'schema_fingerprint'        => self::schema_fingerprint(),
			'captured_at_utc'           => gmdate( 'Y-m-d H:i:s' ),
			'post_counts'               => $post_counts,
			'legacy_record_count'       => $legacy_record_count,
			'comment_count'             => self::comment_count(),
			'term_count'                => self::term_count(),
			'attachment_count'          => self::attachment_count(),
			'table_counts'              => $table_counts,
			'source_tree_checksum'      => hash_final( $post_hash ),
			'comment_tree_checksum'     => self::comment_tree_digest(),
			'comment_meta_checksum'     => self::comment_meta_digest(),
			'term_tree_checksum'        => self::term_tree_digest(),
			'term_meta_checksum'        => self::term_meta_digest(),
			'attachment_tree_checksum'  => self::attachment_tree_digest(),
			'table_digests'             => $table_digests,
			'max_modified_gmt'          => (string) self::query_var( $wpdb->prepare( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type=%s", self::LEGACY_POST_TYPE ), 'max_modified_gmt', '' ),
			'routes'                    => $routes,
		);
		if ( ! empty( self::$capture_errors ) ) {
			return new WP_Error( 'snfla_inventory_query_failed', 'One or more legacy inventory queries failed; no inventory signature was accepted.', array( 'error_codes' => array_values( array_unique( array_map( 'sanitize_key', self::$capture_errors ) ) ) ) );
		}
		$inventory['data_signature']   = self::data_signature( $inventory );
		$inventory['source_signature'] = self::signature( $inventory );
		return $inventory;
	}

	public static function each_legacy_id( array $statuses, callable $callback, $batch_size = self::STREAM_BATCH ) {
		global $wpdb;
		$statuses = array_values( array_unique( array_filter( array_map( 'sanitize_key', $statuses ) ) ) );
		if ( empty( $statuses ) ) {
			return 0;
		}
		$batch_size = min( 2000, max( 50, absint( $batch_size ) ) );
		$cursor = 0;
		$total = 0;
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		do {
			$args = array_merge( array( self::LEGACY_POST_TYPE ), $statuses, array( $cursor, $batch_size ) );
			$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status IN ({$placeholders}) AND ID>%d ORDER BY ID ASC LIMIT %d";
			$wpdb->last_error = '';
			$ids = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
			if ( ! is_array( $ids ) || ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'snfla_legacy_stream_failed', 'Legacy publication IDs could not be streamed.' );
			}
			foreach ( $ids as $id ) {
				$id = absint( $id );
				if ( $id <= 0 ) {
					continue;
				}
				$cursor = $id;
				$total++;
				$result = $callback( $id );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( false === $result ) {
					return $total;
				}
			}
		} while ( count( $ids ) === $batch_size );
		return $total;
	}

	public static function count_active() {
		global $wpdb;
		$wpdb->last_error = '';
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status IN ('publish','draft','pending','private','future')", self::LEGACY_POST_TYPE ) );
		return ! empty( $wpdb->last_error ) ? -1 : (int) $value;
	}

	private static function legacy_table_suffixes() {
		return array( 'snp_reactions', 'snp_saves', 'snp_views', 'snp_reports', 'snp_audit_log', 'snp_media_staging', 'snp_rate_limits' );
	}

	private static function query_var( $sql, $error_code, $default = null ) {
		global $wpdb;
		$wpdb->last_error = '';
		$value = $wpdb->get_var( $sql );
		if ( ! empty( $wpdb->last_error ) ) {
			self::capture_error( $error_code );
			return $default;
		}
		return $value;
	}

	private static function capture_error( $error_code ) {
		self::$capture_errors[] = 'snfla_inventory_query_failed';
		self::$capture_errors[] = sanitize_key( (string) $error_code );
	}

	/**
	 * Hash structural database evidence separately from mutable source rows.
	 * SHOW CREATE TABLE is avoided because engine-generated AUTO_INCREMENT values
	 * are data-dependent; only normalized columns and structural index fields are used.
	 */
	private static function schema_fingerprint() {
		global $wpdb;
		$tables = array_filter(
			array_merge(
				array(
					$wpdb->posts ?? '',
					$wpdb->postmeta ?? '',
					$wpdb->comments ?? '',
					$wpdb->commentmeta ?? '',
					$wpdb->terms ?? '',
					$wpdb->term_taxonomy ?? '',
					$wpdb->term_relationships ?? '',
					$wpdb->termmeta ?? '',
				),
				array_map( static function ( $suffix ) use ( $wpdb ) { return $wpdb->prefix . $suffix; }, self::legacy_table_suffixes() )
			)
		);
		$evidence = array();
		foreach ( array_values( array_unique( $tables ) ) as $table ) {
			$probe = self::query_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ), 'schema_table_probe', '' );
			if ( $probe !== $table ) {
				$evidence[ $table ] = array( 'exists' => false, 'columns' => array(), 'indexes' => array() );
				continue;
			}
			$wpdb->last_error = '';
			$columns = $wpdb->get_results( "SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A );
			if ( ! is_array( $columns ) || ! empty( $wpdb->last_error ) ) {
				self::capture_error( 'schema_columns_failed' );
				$columns = array();
			}
			$wpdb->last_error = '';
			$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
			if ( ! is_array( $indexes ) || ! empty( $wpdb->last_error ) ) {
				self::capture_error( 'schema_indexes_failed' );
				$indexes = array();
			}
			$index_fields = array( 'Non_unique', 'Key_name', 'Seq_in_index', 'Column_name', 'Collation', 'Sub_part', 'Packed', 'Null', 'Index_type', 'Comment', 'Index_comment', 'Visible', 'Expression' );
			$normalized_indexes = array();
			foreach ( $indexes as $index ) {
				$row = array();
				foreach ( $index_fields as $field ) {
					if ( array_key_exists( $field, $index ) ) { $row[ $field ] = $index[ $field ]; }
				}
				$normalized_indexes[] = $row;
			}
			$evidence[ $table ] = array(
				'exists'  => true,
				'columns' => SNFLA_Checksum::canonicalize( $columns ),
				'indexes' => SNFLA_Checksum::canonicalize( $normalized_indexes ),
			);
		}
		return SNFLA_Checksum::hash( $evidence );
	}

	private static function term_count() {
		if ( ! taxonomy_exists( self::LEGACY_TAXONOMY ) ) {
			return 0;
		}
		$count = wp_count_terms( array( 'taxonomy' => self::LEGACY_TAXONOMY, 'hide_empty' => false ) );
		if ( is_wp_error( $count ) ) {
			self::capture_error( 'term_count_failed' );
			return 0;
		}
		return absint( $count );
	}

	private static function comment_count() {
		global $wpdb;
		return absint( self::query_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID=c.comment_post_ID WHERE p.post_type=%s", self::LEGACY_POST_TYPE ), 'comment_count', 0 ) );
	}

	private static function attachment_count() {
		global $wpdb;
		return absint( self::query_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} a INNER JOIN {$wpdb->posts} p ON p.ID=a.post_parent WHERE a.post_type='attachment' AND p.post_type=%s", self::LEGACY_POST_TYPE ), 'attachment_count', 0 ) );
	}

	private static function comment_tree_digest() {
		global $wpdb;
		return self::query_digest( $wpdb->prepare( "SELECT c.comment_ID,c.comment_post_ID,c.comment_parent,c.user_id,c.comment_approved,c.comment_date_gmt,c.comment_author,c.comment_author_email,c.comment_author_url,c.comment_content,c.comment_type FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID=c.comment_post_ID WHERE p.post_type=%s", self::LEGACY_POST_TYPE ), 'comment_ID' );
	}

	private static function comment_meta_digest() {
		global $wpdb;
		return self::query_digest( $wpdb->prepare( "SELECT cm.meta_id,cm.comment_id,cm.meta_key,cm.meta_value FROM {$wpdb->commentmeta} cm INNER JOIN {$wpdb->comments} c ON c.comment_ID=cm.comment_id INNER JOIN {$wpdb->posts} p ON p.ID=c.comment_post_ID WHERE p.post_type=%s", self::LEGACY_POST_TYPE ), 'meta_id' );
	}

	private static function term_tree_digest() {
		global $wpdb;
		$hash = hash_init( 'sha256' );
		$object_cursor = 0;
		$taxonomy_cursor = 0;
		do {
			$wpdb->last_error = '';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tr.object_id,tr.term_taxonomy_id,tr.term_order,tt.taxonomy,t.slug,t.name FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id=tt.term_id INNER JOIN {$wpdb->posts} p ON p.ID=tr.object_id WHERE p.post_type=%s AND tt.taxonomy=%s AND (tr.object_id>%d OR (tr.object_id=%d AND tr.term_taxonomy_id>%d)) ORDER BY tr.object_id ASC,tr.term_taxonomy_id ASC LIMIT %d",
					self::LEGACY_POST_TYPE,
					self::LEGACY_TAXONOMY,
					$object_cursor,
					$object_cursor,
					$taxonomy_cursor,
					self::STREAM_BATCH
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
				self::capture_error( 'term_tree_digest_failed' );
				break;
			}
			foreach ( $rows as $row ) {
				$object_cursor   = absint( $row['object_id'] ?? 0 );
				$taxonomy_cursor = absint( $row['term_taxonomy_id'] ?? 0 );
				hash_update( $hash, SNFLA_Checksum::encode( $row ) . "\n" );
			}
		} while ( count( $rows ) === self::STREAM_BATCH );
		return hash_final( $hash );
	}

	private static function term_meta_digest() {
		global $wpdb;
		if ( ! isset( $wpdb->termmeta ) ) {
			return hash( 'sha256', '' );
		}
		return self::query_digest( $wpdb->prepare( "SELECT tm.meta_id,tm.term_id,tm.meta_key,tm.meta_value FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=tm.term_id WHERE tt.taxonomy=%s", self::LEGACY_TAXONOMY ), 'meta_id' );
	}

	private static function attachment_tree_digest() {
		global $wpdb;
		return self::query_digest( $wpdb->prepare( "SELECT a.ID,a.post_parent,a.post_mime_type,a.guid,a.post_status,a.post_date_gmt FROM {$wpdb->posts} a INNER JOIN {$wpdb->posts} p ON p.ID=a.post_parent WHERE a.post_type='attachment' AND p.post_type=%s", self::LEGACY_POST_TYPE ), 'ID' );
	}

	private static function legacy_page_digest() {
		global $wpdb;
		$hash = hash_init( 'sha256' );
		$patterns = array( '[sabri_news_home', '[sabri_news_feed', '[sabri_publish_form', '[sabri_publication_feed', '[sabri_my_publication_reports' );
		$cursor = 0;
		do {
			$where = array();
			$args = array( 'page', $cursor );
			foreach ( $patterns as $pattern ) {
				$where[] = 'post_content LIKE %s';
				$args[] = '%' . $wpdb->esc_like( $pattern ) . '%';
			}
			$args[] = self::STREAM_BATCH;
			$sql = "SELECT ID,post_status,post_name,post_content FROM {$wpdb->posts} WHERE post_type=%s AND ID>%d AND (" . implode( ' OR ', $where ) . ') ORDER BY ID ASC LIMIT %d';
			$wpdb->last_error = '';
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
			if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
				self::capture_error( 'legacy_page_digest_failed' );
				$rows = array();
			}
			foreach ( $rows as $row ) {
				$cursor = max( $cursor, absint( $row['ID'] ?? 0 ) );
				hash_update( $hash, wp_json_encode( SNFLA_Checksum::canonicalize( $row ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
			}
		} while ( count( $rows ) === self::STREAM_BATCH );
		return hash_final( $hash );
	}

	private static function table_digest( $table ) {
		global $wpdb;
		$wpdb->last_error = '';
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
		if ( ! is_array( $columns ) || ! in_array( 'id', $columns, true ) || ! empty( $wpdb->last_error ) ) {
			self::capture_error( 'table_schema_digest_' . substr( hash( 'sha256', $table ), 0, 12 ) );
			return hash( 'sha256', '' );
		}
		return self::query_digest( "SELECT * FROM `{$table}`", 'id' );
	}

	private static function query_digest( $sql, $cursor_column ) {
		global $wpdb;
		$cursor_column = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $cursor_column );
		if ( '' === $cursor_column ) {
			self::capture_error( 'query_digest_cursor_missing' );
			return hash( 'sha256', '' );
		}
		$hash = hash_init( 'sha256' );
		$cursor = 0;
		do {
			$wpdb->last_error = '';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM ({$sql}) snfla_digest WHERE snfla_digest.`{$cursor_column}`>%d ORDER BY snfla_digest.`{$cursor_column}` ASC LIMIT %d",
					$cursor,
					self::STREAM_BATCH
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
				self::capture_error( 'query_digest_' . substr( hash( 'sha256', $sql ), 0, 12 ) );
				break;
			}
			foreach ( $rows as $row ) {
				$row_cursor = absint( $row[ $cursor_column ] ?? 0 );
				if ( $row_cursor <= $cursor ) {
					self::capture_error( 'query_digest_non_monotonic_' . substr( hash( 'sha256', $sql ), 0, 12 ) );
					break 2;
				}
				$cursor = $row_cursor;
				hash_update( $hash, SNFLA_Checksum::encode( $row ) . "\n" );
			}
		} while ( count( $rows ) === self::STREAM_BATCH );
		return hash_final( $hash );
	}

	public static function signature( array $inventory ) {
		unset( $inventory['captured_at_utc'], $inventory['source_signature'] );
		return SNFLA_Checksum::hash( $inventory );
	}

	/**
	 * Signature of immutable legacy data only.
	 *
	 * Activation intentionally changes legacy page visibility while preserving
	 * publication, comment, taxonomy, attachment and interaction source data.
	 * Handover rollback therefore binds to this data-only signature and validates
	 * page state separately from the signed handover snapshot.
	 */
	public static function data_signature( array $inventory ) {
		unset( $inventory['captured_at_utc'], $inventory['source_signature'], $inventory['data_signature'], $inventory['schema_fingerprint'], $inventory['routes'] );
		return SNFLA_Checksum::hash( $inventory );
	}

	public static function lock( $actor_id, $expected_state, $expected_version ) {
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {
			return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) );
		}
		try {
			$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_RUN );
			if ( is_wp_error( $authorized_actor ) ) { return $authorized_actor; }
			$check = SNFLA_Schema::assert_current( $expected_state, $expected_version );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$previous = get_option( SNFLA_Schema::INVENTORY_OPTION, array() );
			$inventory = self::capture();
			if ( is_wp_error( $inventory ) ) { return $inventory; }
			$verification_inventory = self::capture();
			if ( is_wp_error( $verification_inventory ) ) { return $verification_inventory; }
			if ( ! hash_equals( (string) $inventory['source_signature'], (string) $verification_inventory['source_signature'] ) ) {
				return new WP_Error( 'snfla_inventory_unstable', 'The legacy source changed during inventory capture. Stop legacy writes and retry.', array( 'status' => 409 ) );
			}
			if ( $previous !== $inventory && ! update_option( SNFLA_Schema::INVENTORY_OPTION, $inventory, false ) ) {
				return new WP_Error( 'snfla_inventory_persist_failed', 'The inventory lock could not be persisted.', array( 'status' => 500 ) );
			}
			$transition = SNFLA_Schema::transition( 'inventory_locked', $expected_state, $expected_version, $actor_id, array( 'source_signature' => $inventory['source_signature'] ) );
			if ( is_wp_error( $transition ) ) {
				$restored = update_option( SNFLA_Schema::INVENTORY_OPTION, $previous, false ) || get_option( SNFLA_Schema::INVENTORY_OPTION, array() ) === $previous;
				return $restored ? $transition : new WP_Error( 'snfla_inventory_compensation_failed', 'Lifecycle transition failed and the previous inventory evidence could not be restored exactly.', array( 'status' => 500, 'cause' => $transition->get_error_code() ) );
			}
			return array( 'inventory' => $inventory, 'lifecycle' => $transition );
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	public static function locked() {
		$locked = get_option( SNFLA_Schema::INVENTORY_OPTION, array() );
		return is_array( $locked ) ? $locked : array();
	}

	public static function unchanged() {
		$locked = self::locked();
		if ( empty( $locked['source_signature'] ) ) {
			return false;
		}
		$current = self::capture();
		if ( is_wp_error( $current ) ) { return false; }
		return hash_equals( (string) $locked['source_signature'], (string) $current['source_signature'] );
	}
}
