<?php
/**
 * Class Merge Tags
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

/**
 * Merge Tags Group class.
 */
abstract class Merge_Tags_Group {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $merge_tags = array();

	/**
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var Trigger
	 */
	public $trigger;

	/**
	 * Is advanced.
	 *
	 * @since 1.0.0
	 */
	protected $is_advanced = false;

	/**
	 * Depends on plugin.
	 *
	 * @since 1.5.0
	 *
	 * @var string
	 */
	protected $depends_on_plugin = '';

	/**
	 * Plugin dependency is active.
	 *
	 * @since 1.5.0
	 *
	 * @var bool
	 */
	protected $plugin_dependency_active = true;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->set_merge_tags();
	}

	/**
	 * Get merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_merge_tags() {
		return $this->merge_tags;
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	abstract protected function set_merge_tags();

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Set trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger.
	 *
	 * @return void
	 */
	public function set_trigger( $trigger ) {
		$this->trigger = $trigger;
	}

	/**
	 * Process merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $content Content.
	 *
	 * @return mixed
	 */
	public function process( $content ) {
		if ( is_array( $content ) ) {
			$content = $this->process_merge_tags_array( $content );
		} else {
			$content = $this->process_merge_tags( $content );
		}

		return $content;
	}

	/**
	 * Process merge tags in array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $array Array.
	 *
	 * @return array
	 */
	public function process_merge_tags_array( $array ) {
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$array[ $key ] = $this->process_merge_tags_array( $value );
			} else {
				$array[ $key ] = $this->process_merge_tags( $value );
			}
		}

		return $array;
	}

	/**
	 * Process merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Content.
	 *
	 * @return string
	 */
	public function process_merge_tags( $content ) {
		// Match all group merge tags.
		preg_match_all( '/{{(' . $this->get_slug() . ')\.([^}]*)}}/', $content, $matches );

		// If no matches found, return content.
		if ( empty( $matches[0] ) ) {
			return $content;
		}

		// Loop through each match.
		foreach ( $matches[0] as $index => $full_tag ) {
			$tag = $matches[1][ $index ];
			$key = $matches[2][ $index ];

			$merge_tag = $this->get_merge_tag( $key );
			if ( ! $merge_tag ) {
				continue;
			}

			if ( isset( $merge_tag['trigger'] ) && $merge_tag['trigger'] !== $this->trigger->get_slug() ) {
				$value = '';
			} else {
				// Refresh data before getting value
				$this->refresh_data_before_processing();

				if ( isset( $merge_tag['callback'] ) ) {
					$value = call_user_func( $merge_tag['callback'], $this );
				} else {
					$value = $this->get_value( $key );
				}
			}

			$value = is_null( $value ) ? '' : $value;
			// Replace merge tag with value.
			$content = str_replace( $full_tag, $value, $content );
		}

		return $content;
	}

	/**
	 * Refresh data before processing merge tags
	 * This allows child classes to implement refreshing of their data
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function refresh_data_before_processing() {
		// Default implementation does nothing
		// Child classes can override to refresh their specific data
	}

	/**
	 * Get merge tag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Key.
	 *
	 * @return array|null
	 */
	protected function get_merge_tag( $key ) {
		if ( ! isset( $this->merge_tags[ $key ] ) ) {
			return null;
		}

		return $this->merge_tags[ $key ];
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param array $merge_tag Merge tag.
	 *
	 * @return string
	 */
	protected function get_value( $merge_tag ) {
		return '';
	}

	/**
	 * Is advanced.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_advanced() {
		return $this->is_advanced;
	}

	/**
	 * Get plugin dependency.
	 *
	 * @since 1.5.0
	 *
	 * @return string
	 */
	public function get_plugin_dependency() {
		return $this->depends_on_plugin;
	}

	/**
	 * Is plugin dependency active.
	 *
	 * @since 1.5.0
	 *
	 * @return bool
	 */
	public function is_plugin_dependency_active() {
		return $this->plugin_dependency_active;
	}
}
