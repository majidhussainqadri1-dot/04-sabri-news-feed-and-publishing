<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Checksum {
	public static function canonicalize( $value ) {
		if ( is_array( $value ) ) {
			if ( self::is_assoc( $value ) ) {
				ksort( $value );
			}
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::canonicalize( $item );
			}
		}
		return $value;
	}

	public static function hash( $value ) {
		return hash( 'sha256', wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
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
			$canonical_meta[ $key ] = array_map( 'maybe_unserialize', (array) $values );
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
		$comments = get_comments(
			array(
				'post_id' => $post->ID,
				'status'  => 'approve',
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			)
		);
		$comment_rows = array();
		foreach ( (array) $comments as $comment ) {
			if ( ! is_object( $comment ) ) {
				continue;
			}
			$comment_id = absint( $comment->comment_ID ?? 0 );
			$parent_id  = absint( $comment->comment_parent ?? 0 );
			$comment_rows[] = array(
				'user_id'           => absint( $comment->user_id ?? 0 ),
				'author'            => (string) ( $comment->comment_author ?? '' ),
				'content'           => (string) ( $comment->comment_content ?? '' ),
				'type'              => (string) ( $comment->comment_type ?? '' ),
				'date_gmt'          => (string) ( $comment->comment_date_gmt ?? '' ),
				'legacy_comment_id' => $source ? $comment_id : absint( get_comment_meta( $comment_id, '_sabri_hnf_legacy_comment_id', true ) ),
				'legacy_parent_id'  => $source ? $parent_id : ( $parent_id > 0 ? absint( get_comment_meta( $parent_id, '_sabri_hnf_legacy_comment_id', true ) ) : 0 ),
			);
		}
		usort( $comment_rows, static function ( $a, $b ) { return ( $a['legacy_comment_id'] <=> $b['legacy_comment_id'] ); } );
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
			'comments'       => $comment_rows,
		);
	}

	public static function migration_equivalent( $source_id, $target_id ) {
		$source = self::publication_projection( $source_id, true );
		$target = self::publication_projection( $target_id, false );
		if ( empty( $source ) || empty( $target ) ) {
			return false;
		}
		return hash_equals( self::hash( $source ), self::hash( $target ) );
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
		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}
}
