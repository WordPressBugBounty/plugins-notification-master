<?php
/**
 * Class Post Merge Tags
 *
 * This class is responsible for adding merge tags for post triggers.
 *
 * @package notification-master
 */

namespace Notification_Master\Merge_Tags\Post_Type;

use Notification_Master\Abstracts\Post_Merge_Tags_Group;
use Notification_Master\Utils;

/**
 * Post Merge Tags class.
 */
class Post extends Post_Merge_Tags_Group {

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->post_type_object->labels->singular_name;
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->post_type;
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			"ntfm_post_merge_tags_{$this->post_type}",
			array(
				'id'                 => array(
					'name'        => __( 'ID', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The ID of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'title'              => array(
					'name'        => __( 'Title', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The title of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'content'            => array(
					'name'        => __( 'Content', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The content of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'excerpt'            => array(
					'name'        => __( 'Excerpt', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The excerpt of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'featured_image_id'  => array(
					'name'        => __( 'Featured Image ID', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The featured image ID of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'featured_image_url' => array(
					'name'        => __( 'Featured Image URL', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The featured image URL of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'thumbnail_url'      => array(
					'name'        => __( 'Thumbnail URL', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The thumbnail URL of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'format'             => array(
					'name'        => __( 'Format', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The format of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'permalink'          => array(
					'name'        => __( 'Permalink', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The permalink of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'slug'               => array(
					'name'        => __( 'Slug', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The slug of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'status'             => array(
					'name'        => __( 'Status', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The status of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'published_date'     => array(
					'name'        => __( 'Published Date', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The published date of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'modified_date'      => array(
					'name'        => __( 'Modified Date', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The modified date of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
			)
		);

		// Check if post type supports categories.
		if ( $this->check_taxonomy_support( 'category' ) ) {
			$this->merge_tags['categories'] = array(
				'name'        => __( 'Categories', 'notification-master' ),
				/* translators: %s: Post type singular name */
				'description' => sprintf( __( 'The categories of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
			);
		}

		// Check if post type supports tags.
		if ( $this->check_taxonomy_support( 'post_tag' ) ) {
			$this->merge_tags['tags'] = array(
				'name'        => __( 'Tags', 'notification-master' ),
				/* translators: %s: Post type singular name */
				'description' => sprintf( __( 'The tags of the %s.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
			);
		}
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Merge tag.
	 *
	 * @return string
	 */
	public function get_value( $tag ) {
		if ( ! $this->post ) {
			return '';
		}

		switch ( $tag ) {
			case 'id':
				return $this->get_id();
			case 'title':
				return $this->get_title();
			case 'content':
				return $this->get_content();
			case 'excerpt':
				return $this->get_excerpt();
			case 'featured_image_id':
				return $this->get_featured_image_id();
			case 'featured_image_url':
				return $this->get_featured_image_url();
			case 'thumbnail_url':
				return $this->get_thumbnail_url();
			case 'format':
				return $this->get_format();
			case 'permalink':
				return $this->get_permalink();
			case 'slug':
				return $this->get_post_slug();
			case 'status':
				return $this->get_status();
			case 'categories':
				return $this->get_categories();
			case 'tags':
				return $this->get_tags();
			case 'published_date':
				return $this->get_published_date();
			case 'modified_date':
				return $this->get_modified_date();
		}
	}

	/**
	 * Get ID.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->post->ID;
	}

	/**
	 * Get title.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->post->post_title;
	}

	/**
	 * Get content.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_content() {
		return $this->post->post_content;
	}

	/**
	 * Get excerpt.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_excerpt() {
		return $this->post->post_excerpt;
	}

	/**
	 * Get featured image ID.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_featured_image_id() {
		return get_post_thumbnail_id( $this->post->ID );
	}

	/**
	 * Get featured image URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_featured_image_url() {
		return get_the_post_thumbnail_url( $this->post->ID, 'full' );
	}

	/**
	 * Get thumbnail URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_thumbnail_url() {
		return get_the_post_thumbnail_url( $this->post->ID, 'thumbnail' );
	}

	/**
	 * Get format.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_format() {
		return get_post_format( $this->post->ID );
	}

	/**
	 * Get permalink.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_permalink() {
		return get_permalink( $this->post->ID );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_post_slug() {
		return $this->post->post_name;
	}

	/**
	 * Get status.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->post->post_status;
	}

	/**
	 * Get categories.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_categories() {
		$categories = get_the_category( $this->post->ID );
		if ( ! $categories ) {
			return '';
		}

		$cat_names = wp_list_pluck( $categories, 'name' );

		return implode( ', ', $cat_names );
	}

	/**
	 * Get tags.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_tags() {
		$tags = get_the_tags( $this->post->ID );
		if ( ! $tags ) {
			return '';
		}

		$tag_names = wp_list_pluck( $tags, 'name' );

		return implode( ', ', $tag_names );
	}

	/**
	 * Get published date.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_published_date() {
		return get_the_date( '', $this->post->ID );
	}

	/**
	 * Get modified date.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_modified_date() {
		return get_the_modified_date( '', $this->post->ID );
	}

	/**
	 * Check if post type supports taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return bool
	 */
	public function check_taxonomy_support( $taxonomy ) {
		// Get the taxonomies supported by the post type
		$taxonomies = get_object_taxonomies( $this->post_type );

		// Check if the desired taxonomy is in the array
		return in_array( $taxonomy, $taxonomies, true );
	}
}
