<?php
defined( 'ABSPATH' ) || exit;

final class SNP_SEO {
	public function hooks() {
		add_action( 'wp_head', array( $this, 'article_schema' ), 20 );
		add_filter( 'document_title_parts', array( $this, 'title' ) );
	}

	public function article_schema() {
		if ( ! is_singular( SNP_Content::TYPE ) ) {
			return;
		}
		$post_id   = get_queried_object_id();
		$author_id = absint( get_post_field( 'post_author', $post_id ) );
		$data = array(
			'@context' => 'https://schema.org',
			'@type' => 'Article',
			'headline' => get_the_title( $post_id ),
			'description' => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
			'datePublished' => get_the_date( DATE_W3C, $post_id ),
			'dateModified' => get_the_modified_date( DATE_W3C, $post_id ),
			'articleSection' => SNP_Content::topic_name( $post_id ),
			'inLanguage' => 'en-US',
			'mainEntityOfPage' => get_permalink( $post_id ),
			'author' => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $author_id ), 'url' => SNP_Permissions::profile_url( $author_id ) ),
			'publisher' => array( '@type' => 'Organization', 'name' => 'Sabri Social Homeopathy Platform', 'url' => home_url( '/' ) ),
		);
		$image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $image ) {
			$data['image'] = array( $image );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
	}

	public function title( $parts ) {
		if ( is_post_type_archive( SNP_Content::TYPE ) ) {
			$parts['title'] = 'News Publications';
		}
		return $parts;
	}
}

