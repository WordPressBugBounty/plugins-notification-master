<?php
/**
 * Class Old Theme Merge Tags
 *
 * This class is responsible for loading the Old Theme Merge Tags.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Theme;

use Notification_Master\Abstracts\Merge_Tags_Group;
use Notification_Master\Merge_Tags\Theme\Traits\Theme as Theme_Trait;

/**
 * Old Theme Merge Tags class.
 */
class Old_Theme extends Merge_Tags_Group {

	/**
	 * Theme.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Theme|null
	 */
	protected $theme;

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Old Theme';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'old_theme';

	/**
	 * Set trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger.
	 */
	public function set_trigger( $trigger ) {
		parent::set_trigger( $trigger );
		$this->theme = $trigger->old_theme ?? null;
	}

	use Theme_Trait;
}
