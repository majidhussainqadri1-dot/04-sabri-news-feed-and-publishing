<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Checksum {
	public static function canonicalize( $value ) {
		if ( is_array( $value ) ) {
			if ( self::is_assoc( $value ) ) {
				ksort( $value, SORT_STRING );
			}
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::canonicalize( $item );
			}
			return $value;
		}
		if ( is_object( $value ) ) {
			return array( '__class' => get_class( $value ), '__properties' => self::canonicalize( get_object_vars( $value ) ) );
		}
		if ( is_resource( $value ) ) {
			return array( '__resource_type' => get_resource_type( $value ) );
		}
		if ( is_float( $value ) && ( is_nan( $value ) || is_infinite( $value ) ) ) {
			return (string) $value;
		}
		return $value;
	}

	public static function encode( $value ) {
		$encoded = wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $encoded ) ? $encoded : '';
	}

	public static function hash( $value ) {
		return hash( 'sha256', self::encode( $value ) );
	}

	public static function post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		$meta = get_post_meta( $post->ID );
		$canonical_meta = array();
		foreach ( is_array( $meta ) ? $meta : array() as $key => $values ) {
			if ( 0 === strpos( $key, '_edit_' ) || '_wp_old_slug' === $key ) {
				continue;
			}
			// Hash exact stored strings. Never unserialize untrusted legacy metadata,
			// because object payloads can execute magic methods during inventory.
			$canonical_meta[ (string) $key ] = array_values( array_map( 'strval', (array) $values ) );
		}
		ksort( $canonical_meta );
		$terms = wp_get_object_terms( $post->ID, 'snp_topic', array( 'fields' => 'slugs' ) );
		$terms = is_wp_error( $terms ) ? array() : array_values( array_map( 'sanitize_title', (array) $terms ) );
		sort( $terms, SORT_STRING );
		$thumbnail_id = absint( get_post_meta( $post->ID, '_thumbnail_id', true ) );

		return self::hash(
			array(
				'id'             => (int) $post->ID,
				'type'           => (string) $post->post_type,
				'status'         => (string) $post->post_status,
				'author'         => (int) $post->post_author,
				'date_gmt'       => (string) $post->post_date_gmt,
				'modified_gmt'   => (string) $post->post_modified_gmt,
				'title'          => (string) $post->post_title,
				'excerpt'        => (string) $post->post_excerpt,
				'content'        => (string) $post->post_content,
				'slug'           => (string) $post->post_name,
				'comment_status' => (string) $post->comment_status,
				'meta'           => $canonical_meta,
				'terms'          => $terms,
				'thumbnail'      => self::attachment_evidence( $thumbnail_id ),
				'comment_count'  => (int) get_comments_number( $post->ID ),
			)
		);
	}

	public static function publication_projection( $post_id, $source = true ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$taxonomy = $source ? 'snp_topic' : ( 'sabri_news' === $post->post_type ? 'sabri_news_topic' : 'post_tag' );
		$terms = taxonomy_exists( $taxonomy ) ? wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) ) : array();
		$terms = is_wp_error( $terms ) ? array() : array_values( array_map( 'sanitize_text_field', (array) $terms ) );
		sort( $terms, SORT_NATURAL | SORT_FLAG_CASE );
		$comment_projection = self::comment_projection_digest( $post->ID, (bool) $source );
		if ( is_wp_error( $comment_projection ) ) { return array(); }
		return array(
			'author'         => (int) $post->post_author,
			'date_gmt'       => (string) $post->post_date_gmt,
			'title'          => (string) $post->post_title,
			'excerpt'        => (string) $post->post_excerpt,
			'content'        => (string) $post->post_content,
			'slug'           => (string) $post->post_name,
			'comment_status' => (string) $post->comment_status,
			'thumbnail_id'   => absint( get_post_meta( $post->ID, '_thumbnail_id', true ) ),
			'terms'          => $terms,
			'comments'       => $comment_projection,
		);
	}

	public static function migration_projection_checksum( $post_id, $source = false ) {
		$projection = self::publication_projection( $post_id, (bool) $source );
		if ( empty( $projection ) ) {
			return '';
		}
		$projection['runtime_status'] = sanitize_key( (string) get_post_status( absint( $post_id ) ) );
		return self::hash( $projection );
	}

	public static function migration_equivalent( $source_id, $target_id ) {
		$source = self::publication_projection( $source_id, true );
		$target = self::publication_projection( $target_id, false );
		if ( empty( $source ) || empty( $target ) ) {
			return false;
		}
		$source_post = get_post( absint( $source_id ) );
		$target_post = get_post( absint( $target_id ) );
		if ( ! $source_post instanceof WP_Post || ! $target_post instanceof WP_Post ) {
			return false;
		}
		$source_status = sanitize_key( (string) $source_post->post_status );
		$expected_status = 'sabri_news' === (string) $target_post->post_type
			? 'draft'
			: ( in_array( $source_status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ? $source_status : 'draft' );
		return $expected_status === sanitize_key( (string) $target_post->post_status )
			&& hash_equals( self::hash( $source ), self::hash( $target ) );
	}

	/** Stream approved comments into a deterministic bounded-memory digest. */
	private static function comment_projection_digest( $post_id, $source ) {
		global $wpdb;
		$post_id    = absint( $post_id );
		$cursor     = 0;
		$count      = 0;
		$batch_size = 500;
		$hash       = hash_init( 'sha256' );
		do {
			$wpdb->last_error = '';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT comment_ID,comment_parent,user_id,comment_approved,comment_date_gmt,comment_author,comment_author_email,comment_author_url,comment_content,comment_type FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_approved='1' AND comment_ID>%d ORDER BY comment_ID ASC LIMIT %d",
					$post_id,
					$cursor,
					$batch_size
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'snfla_comment_projection_query_failed' );
			}
			foreach ( $rows as $comment ) {
				$comment_id = absint( $comment['comment_ID'] ?? 0 );
				$parent_id  = absint( $comment['comment_parent'] ?? 0 );
				$cursor     = max( $cursor, $comment_id );
				$row = array(
					'user_id'           => absint( $comment['user_id'] ?? 0 ),
					'author'            => (string) ( $comment['comment_author'] ?? '' ),
					'author_email'      => (string) ( $comment['comment_author_email'] ?? '' ),
					'author_url'        => (string) ( $comment['comment_author_url'] ?? '' ),
					'approved'          => (string) ( $comment['comment_approved'] ?? '' ),
					'content'           => (string) ( $comment['comment_content'] ?? '' ),
					'type'              => (string) ( $comment['comment_type'] ?? '' ),
					'date_gmt'          => (string) ( $comment['comment_date_gmt'] ?? '' ),
					'legacy_comment_id' => $source ? $comment_id : absint( get_comment_meta( $comment_id, '_sabri_hnf_legacy_comment_id', true ) ),
					'legacy_parent_id'  => $source ? $parent_id : ( $parent_id > 0 ? absint( get_comment_meta( $parent_id, '_sabri_hnf_legacy_comment_id', true ) ) : 0 ),
				);
				hash_update( $hash, self::encode( $row ) . "\n" );
				$count++;
			}
		} while ( count( $rows ) === $batch_size );
		return array( 'count' => $count, 'digest' => hash_final( $hash ) );
	}

	private static function attachment_evidence( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return array( 'id' => 0 );
		}
		$path = function_exists( 'get_attached_file' ) ? (string) get_attached_file( $attachment_id, true ) : '';
		return array(
			'id'            => $attachment_id,
			'mime'          => (string) get_post_mime_type( $attachment_id ),
			'metadata'      => get_post_meta( $attachment_id, '_wp_attachment_metadata', true ),
			'file_meta'     => (string) get_post_meta( $attachment_id, '_wp_attached_file', true ),
			'file_sha256'   => $path && is_readable( $path ) && is_file( $path ) ? hash_file( 'sha256', $path ) : '',
		);
	}

	private static function is_assoc( array $value ) {
		return ! empty( $value ) && array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}
}
