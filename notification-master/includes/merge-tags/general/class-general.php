<?php
/**
 * Class General Merge Tags
 *
 * This class is responsible for loading the General Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\General;

use Notification_Master\Abstracts\Merge_Tags_Group;

/**
 * General Merge Tags Group class.
 */
class General extends Merge_Tags_Group {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'General';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'general';

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			'ntfm_general_merge_tags',
			array(
				'blogname'        => array(
					'label'       => __( 'Blog Name', 'notification-master' ),
					'description' => __( 'The name of the blog.', 'notification-master' ),
				),
				'blogdescription' => array(
					'label'       => __( 'Blog Description', 'notification-master' ),
					'description' => __( 'The description of the blog.', 'notification-master' ),
				),
				'blogurl'         => array(
					'label'       => __( 'Blog URL', 'notification-master' ),
					'description' => __( 'The URL of the blog.', 'notification-master' ),
				),
				'admin_email'     => array(
					'label'       => __( 'Admin Email', 'notification-master' ),
					'description' => __( 'The email address of the admin.', 'notification-master' ),
				),
				'admin_url'       => array(
					'label'       => __( 'Admin URL', 'notification-master' ),
					'description' => __( 'The URL of the admin.', 'notification-master' ),
				),
				'home_url'        => array(
					'label'       => __( 'Home URL', 'notification-master' ),
					'description' => __( 'The URL of the home page.', 'notification-master' ),
				),
				'site_url'        => array(
					'label'       => __( 'Site URL', 'notification-master' ),
					'description' => __( 'The URL of the site.', 'notification-master' ),
				),
				'site_icon'       => array(
					'label'       => __( 'Site Icon', 'notification-master' ),
					'description' => __( 'The site icon.', 'notification-master' ),
				),
				'wp_version'      => array(
					'label'       => __( 'WordPress Version', 'notification-master' ),
					'description' => __( 'The version of WordPress.', 'notification-master' ),
				),
				'wp_language'     => array(
					'label'       => __( 'WordPress Language', 'notification-master' ),
					'description' => __( 'The language of WordPress.', 'notification-master' ),
				),
				'current_time'    => array(
					'label'       => __( 'Current Time', 'notification-master' ),
					'description' => __( 'The current time.', 'notification-master' ),
				),
				'current_date'    => array(
					'label'       => __( 'Current Date', 'notification-master' ),
					'description' => __( 'The current date.', 'notification-master' ),
				),
			)
		);
	}


	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Tag.
	 *
	 * @return string
	 */
	public function get_value( $tag ) {
		switch ( $tag ) {
			case 'blogname':
				return get_bloginfo( 'name' );
			case 'blogdescription':
				return get_bloginfo( 'description' );
			case 'blogurl':
				return get_bloginfo( 'url' );
			case 'admin_email':
				return get_option( 'admin_email' );
			case 'admin_url':
				return get_admin_url();
			case 'home_url':
				return home_url();
			case 'site_url':
				return site_url();
			case 'wp_version':
				return get_bloginfo( 'version' );
			case 'wp_language':
				return get_bloginfo( 'language' );
			case 'site_icon':
				return get_site_icon_url();
			case 'current_time':
				$time_format = get_option( 'time_format' );
				return date_i18n( $time_format );
			case 'current_date':
				$date_format = get_option( 'date_format' );
				return date_i18n( $date_format );
			default:
				return '';
		}
	}
}
