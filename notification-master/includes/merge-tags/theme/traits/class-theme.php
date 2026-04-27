<?php
/**
 * Class Theme Trait
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Theme\Traits;

/**
 * Theme trait.
 */
trait Theme {

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			'ntfm_theme_merge_tags',
			array(
				'name'           => array(
					'label'       => __( 'Name', 'notification-master' ),
					'description' => __( 'The name of the theme.', 'notification-master' ),
				),
				'version'        => array(
					'label'       => __( 'Version', 'notification-master' ),
					'description' => __( 'The version of the theme.', 'notification-master' ),
				),
				'author'         => array(
					'label'       => __( 'Author', 'notification-master' ),
					'description' => __( 'The author of the theme.', 'notification-master' ),
				),
				'author_uri'     => array(
					'label'       => __( 'Author URI', 'notification-master' ),
					'description' => __( 'The author URI of the theme.', 'notification-master' ),
				),
				'description'    => array(
					'label'       => __( 'Description', 'notification-master' ),
					'description' => __( 'The description of the theme.', 'notification-master' ),
				),
				'template'       => array(
					'label'       => __( 'Template', 'notification-master' ),
					'description' => __( 'The template of the theme.', 'notification-master' ),
				),
				'stylesheet'     => array(
					'label'       => __( 'Stylesheet', 'notification-master' ),
					'description' => __( 'The stylesheet of the theme.', 'notification-master' ),
				),
				'text_domain'    => array(
					'label'       => __( 'Text Domain', 'notification-master' ),
					'description' => __( 'The text domain of the theme.', 'notification-master' ),
				),
				'domain_path'    => array(
					'label'       => __( 'Domain Path', 'notification-master' ),
					'description' => __( 'The domain path of the theme.', 'notification-master' ),
				),
				'theme_uri'      => array(
					'label'       => __( 'Theme URI', 'notification-master' ),
					'description' => __( 'The theme URI of the theme.', 'notification-master' ),
				),
				'is_child_theme' => array(
					'label'       => __( 'Is Child Theme', 'notification-master' ),
					'description' => __( 'Whether the theme is a child theme.', 'notification-master' ),
				),
				'parent_theme'   => array(
					'label'       => __( 'Parent Theme', 'notification-master' ),
					'description' => __( 'The parent theme of the theme.', 'notification-master' ),
				),
			)
		);
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Merge tag.
	 *
	 * @return mixed
	 */
	public function get_value( $tag ) {
		if ( empty( $this->theme ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'name':
				return $this->theme->get( 'Name' );
			case 'version':
				return $this->theme->get( 'Version' );
			case 'author':
				return $this->theme->get( 'Author' );
			case 'author_uri':
				return $this->theme->get( 'AuthorURI' );
			case 'description':
				return $this->theme->get( 'Description' );
			case 'template':
				return $this->theme->get( 'Template' );
			case 'stylesheet':
				return $this->theme->get_stylesheet();
			case 'text_domain':
				return $this->theme->get( 'TextDomain' );
			case 'domain_path':
				return $this->theme->get( 'DomainPath' );
			case 'theme_uri':
				return $this->theme->get( 'ThemeURI' );
			case 'parent_theme':
				return $this->theme->get( 'Template' );
			case 'is_child_theme':
				return $this->theme->parent();
		}
	}
}
