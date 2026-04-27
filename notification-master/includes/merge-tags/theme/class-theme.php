<?php
/**
 * Theme Merge Tags Group
 *
 * This class is responsible for loading the Theme Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Theme;

use Notification_Master\Abstracts\Merge_Tags_Group;
use Notification_Master\Merge_Tags\Theme\Traits\Theme as Theme_Trait;

/**
 * Theme Merge Tags Group class.
 */
class Theme extends Merge_Tags_Group {

	/**
	 * Theme.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Theme|null
	 */
	public $theme;

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Theme';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'theme';

	/**
	 * Set trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger.
	 */
	public function set_trigger( $trigger ) {
		parent::set_trigger( $trigger );
		$this->theme = $trigger->theme ?? null;
	}

	use Theme_Trait;
}
