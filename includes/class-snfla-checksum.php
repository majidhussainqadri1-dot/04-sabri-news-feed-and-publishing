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
		$bounded_meta = array();
		foreach ( is_array( $meta ) ? $meta : array() as $key => $values ) {
			if ( 0 === strpos( $key, '_edit_' ) || in_array( $key, array( '_wp_old_slug', '_thumbnail_id' ), true ) ) {
				continue;
			}
			$bounded_meta[ $key ] = array_map( 'maybe_unserialize', array_slice( (array) $values, 0, 50 ) );
		}
		$terms = wp_get_object_terms( $post->ID, 'snp_topic', array( 'fields' => 'slugs' ) );
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
				'meta'           => $bounded_meta,
				'terms'          => is_wp_error( $terms ) ? array() : array_values( $terms ),
				'comment_count'  => (int) get_comments_number( $post->ID ),
			)
		);
	}

	private static function is_assoc( array $value ) {
		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}
}
