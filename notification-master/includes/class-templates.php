<?php
/**
 * Class Templates
 *
 * Builds a library of ready-made "starter" notifications that can be imported
 * with a single click. The library is generated dynamically from the triggers
 * that are actually registered on the site, so trigger slugs are always valid
 * and disabled trigger families are automatically excluded.
 *
 * Every generated template ships with five connections:
 *  - Email   (enabled)  delivered to the Administrator role.
 *  - WebPush (enabled)  delivered to every subscriber.
 *  - Discord, Webhook, Instagram (disabled "shells") pre-filled with sensible
 *    content/merge tags so the user only needs to add their endpoint or
 *    credentials and flip the switch.
 *
 * @package notification-master
 *
 * @since 1.7.1
 */

namespace Notification_Master;

use Notification_Master\Triggers\Loader as Triggers_Loader;
use Notification_Master\Abstracts\Post_Trigger;
use Notification_Master\Abstracts\User_Trigger;
use Notification_Master\Abstracts\Comment_Trigger;
use Notification_Master\Abstracts\Taxonomy_Trigger;
use Notification_Master\Abstracts\Theme_Trigger;
use Notification_Master\Abstracts\Plugin_Trigger;
use Notification_Master\Abstracts\Media_Trigger;
use Notification_Master\Abstracts\Privacy_Trigger;

defined( 'ABSPATH' ) || exit;

/**
 * Templates class.
 */
class Templates {

	/**
	 * Build the full starter-template library.
	 *
	 * @since 1.7.1
	 *
	 * @return array[] List of template definitions.
	 */
	public static function get_templates() {
		$templates = array();

		$triggers = Triggers_Loader::get_instance()->get_triggers();

		foreach ( $triggers as $slug => $trigger ) {
			$family = self::get_family( $trigger );

			// Skip families we have no copy for (keeps output predictable).
			if ( null === $family ) {
				continue;
			}

			$content = self::build_content( $trigger, $family );

			$templates[] = array(
				'id'          => 'tpl_' . $slug,
				'title'       => $content['title'],
				'trigger'     => $slug,
				'group'       => $trigger->get_group(),
				'group_label' => $content['group_label'],
				'family'      => $family,
				'name'        => $trigger->get_name(),
				'description' => $trigger->get_description(),
				'status'      => 'draft',
				'channels'    => array( 'email', 'webpush', 'discord', 'webhook', 'instagram' ),
				'connections' => self::build_connections( $content ),
			);
		}

		/**
		 * Allow extensions (or the Pro plugin) to add or filter starter templates.
		 *
		 * @since 1.7.1
		 *
		 * @param array[] $templates Template definitions.
		 */
		return apply_filters( 'notification_master_starter_templates', $templates );
	}

	/**
	 * Detect the family of a trigger via its abstract base class.
	 *
	 * @since 1.7.1
	 *
	 * @param object $trigger Trigger instance.
	 *
	 * @return string|null Family key, or null when unsupported.
	 */
	private static function get_family( $trigger ) {
		if ( $trigger instanceof Post_Trigger ) {
			return 'post';
		}
		if ( $trigger instanceof User_Trigger ) {
			return 'user';
		}
		if ( $trigger instanceof Comment_Trigger ) {
			return 'comment';
		}
		if ( $trigger instanceof Taxonomy_Trigger ) {
			return 'taxonomy';
		}
		if ( $trigger instanceof Theme_Trigger ) {
			return 'theme';
		}
		if ( $trigger instanceof Plugin_Trigger ) {
			return 'plugin';
		}
		if ( $trigger instanceof Media_Trigger ) {
			return 'media';
		}
		if ( $trigger instanceof Privacy_Trigger ) {
			return 'privacy';
		}

		return null;
	}

	/**
	 * Build the copy (titles, messages, links, images) for a trigger.
	 *
	 * Only merge tags that are guaranteed to exist for the trigger's merge-tag
	 * groups are used, so previews resolve cleanly.
	 *
	 * @since 1.7.1
	 *
	 * @param object $trigger Trigger instance.
	 * @param string $family  Family key.
	 *
	 * @return array
	 */
	private static function build_content( $trigger, $family ) {
		$name  = $trigger->get_name();
		$group = $trigger->get_group();
		$brand = '{{general.blogname}}';

		// Defaults shared by every family.
		$content = array(
			'title'       => $name,
			'group_label' => $name,
			'wp_title'    => $name,
			'wp_message'  => $brand,
			'subject'     => sprintf( '[%s] %s', $brand, $name ),
			'html'        => '',
			'url'         => '{{general.home_url}}',
			'image'       => '',
		);

		switch ( $family ) {
			case 'post':
				$label                  = $trigger->post_type_object->labels->singular_name;
				$content['group_label'] = $label;
				$content['wp_title']    = $name;
				$content['wp_message']  = "{{{$group}.title}}";
				$content['subject']     = sprintf( '[%s] %s: {{%s.title}}', $brand, $name, $group );
				$content['url']         = "{{{$group}.permalink}}";
				$content['image']       = "{{{$group}.featured_image_url}}";
				$content['html']        = self::email_html(
					"{{{$group}.title}}",
					$content['url'],
					array(
						/* translators: %1$s: trigger name, %2$s: site name. */
						sprintf( __( '%1$s on %2$s.', 'notification-master' ), $name, $brand ),
						"{{{$group}.excerpt}}",
					),
					array(
						__( 'Author', 'notification-master' )  => "{{{$group}_author.display_name}}",
						__( 'Date', 'notification-master' )    => "{{{$group}.published_date}}",
						__( 'Status', 'notification-master' )  => "{{{$group}.status}}",
					),
					__( 'View', 'notification-master' )
				);
				break;

			case 'user':
				$content['group_label'] = __( 'User', 'notification-master' );
				$content['wp_message']  = '{{user.display_name}} ({{user.email}})';
				$content['subject']     = sprintf( '[%s] %s: {{user.display_name}}', $brand, $name );
				$content['url']         = '{{general.admin_url}}';
				$content['html']        = self::email_html(
					$name,
					$content['url'],
					array(
						/* translators: %s: site name. */
						sprintf( __( 'A user account event was recorded on %s.', 'notification-master' ), $brand ),
					),
					array(
						__( 'User', 'notification-master' )  => '{{user.display_name}}',
						__( 'Email', 'notification-master' ) => '{{user.email}}',
						__( 'Username', 'notification-master' ) => '{{user.username}}',
						__( 'Role', 'notification-master' )  => '{{user.role}}',
					),
					__( 'Manage users', 'notification-master' )
				);
				break;

			case 'comment':
				$label                  = $trigger->comment_type_name ? $trigger->comment_type_name : __( 'Comment', 'notification-master' );
				$content['group_label'] = $label;
				$content['wp_message']  = "{{{$group}_author.name}}: {{post.title}}";
				$content['subject']     = sprintf( '[%s] %s: {{post.title}}', $brand, $name );
				$content['url']         = '{{post.permalink}}';
				$content['html']        = self::email_html(
					$name,
					$content['url'],
					array(
						/* translators: %s: post title merge tag. */
						sprintf( __( 'New comment on "%s":', 'notification-master' ), '{{post.title}}' ),
						"&ldquo;{{{$group}.content}}&rdquo;",
					),
					array(
						__( 'Author', 'notification-master' ) => "{{{$group}_author.name}}",
						__( 'Email', 'notification-master' )  => "{{{$group}_author.email}}",
						__( 'Status', 'notification-master' ) => "{{{$group}.status}}",
					),
					__( 'View comment', 'notification-master' )
				);
				break;

			case 'taxonomy':
				$label                  = $trigger->taxonomy_object->labels->singular_name;
				$content['group_label'] = $label;
				$content['wp_message']  = "{{{$group}.term_name}}";
				$content['subject']     = sprintf( '[%s] %s: {{%s.term_name}}', $brand, $name, $group );
				$content['url']         = "{{{$group}.term_url}}";
				$content['html']        = self::email_html(
					"{{{$group}.term_name}}",
					$content['url'],
					array(
						/* translators: %1$s: trigger name, %2$s: site name. */
						sprintf( __( '%1$s on %2$s.', 'notification-master' ), $name, $brand ),
						"{{{$group}.term_description}}",
					),
					array(
						__( 'Term', 'notification-master' ) => "{{{$group}.term_name}}",
						__( 'Slug', 'notification-master' ) => "{{{$group}.term_slug}}",
					),
					__( 'View', 'notification-master' )
				);
				break;

			case 'plugin':
				$content['group_label'] = __( 'Plugin', 'notification-master' );
				$content['wp_message']  = '{{plugin.name}} ({{plugin.version}})';
				$content['subject']     = sprintf( '[%s] %s: {{plugin.name}}', $brand, $name );
				$content['url']         = '{{general.admin_url}}';
				$content['html']        = self::email_html(
					$name,
					$content['url'],
					array(
						/* translators: %s: site name. */
						sprintf( __( 'A plugin change was detected on %s.', 'notification-master' ), $brand ),
					),
					array(
						__( 'Plugin', 'notification-master' )  => '{{plugin.name}}',
						__( 'Version', 'notification-master' ) => '{{plugin.version}}',
						__( 'Author', 'notification-master' )  => '{{plugin.author}}',
					),
					__( 'Open dashboard', 'notification-master' )
				);
				break;

			case 'media':
				$content['group_label'] = __( 'Media', 'notification-master' );
				$content['wp_message']  = '{{attachment.name}}';
				$content['subject']     = sprintf( '[%s] %s: {{attachment.name}}', $brand, $name );
				$content['url']         = '{{attachment.url}}';
				$content['image']       = '{{attachment.url}}';
				$content['html']        = self::email_html(
					'{{attachment.name}}',
					$content['url'],
					array(
						/* translators: %1$s: trigger name, %2$s: site name. */
						sprintf( __( '%1$s on %2$s.', 'notification-master' ), $name, $brand ),
					),
					array(
						__( 'File', 'notification-master' ) => '{{attachment.name}}',
						__( 'Type', 'notification-master' ) => '{{attachment.type}}',
						__( 'Size', 'notification-master' ) => '{{attachment.size}}',
					),
					__( 'View file', 'notification-master' )
				);
				break;

			case 'privacy':
				$content['group_label'] = __( 'Privacy', 'notification-master' );
				$content['wp_message']  = '{{user.display_name}} ({{user.email}})';
				$content['subject']     = sprintf( '[%s] %s', $brand, $name );
				$content['url']         = '{{general.admin_url}}';
				$content['html']        = self::email_html(
					$name,
					$content['url'],
					array(
						/* translators: %s: site name. */
						sprintf( __( 'A privacy/data request was recorded on %s.', 'notification-master' ), $brand ),
					),
					array(
						__( 'User', 'notification-master' )  => '{{user.display_name}}',
						__( 'Email', 'notification-master' ) => '{{user.email}}',
					),
					__( 'Review requests', 'notification-master' )
				);
				break;

			case 'theme':
			default:
				$content['group_label'] = __( 'Theme', 'notification-master' );
				$content['wp_message']  = $brand;
				$content['subject']     = sprintf( '[%s] %s', $brand, $name );
				$content['url']         = '{{general.admin_url}}';
				$content['html']        = self::email_html(
					$name,
					$content['url'],
					array(
						/* translators: %s: site name. */
						sprintf( __( 'A theme change was detected on %s.', 'notification-master' ), $brand ),
					),
					array(),
					__( 'Open dashboard', 'notification-master' )
				);
				break;
		}

		return $content;
	}

	/**
	 * Compose a simple, inline-styled HTML email body. The Email integration
	 * wraps this in the plugin's outer email template.
	 *
	 * @since 1.7.1
	 *
	 * @param string   $heading Heading text (may contain merge tags).
	 * @param string   $link    URL the heading/CTA points to.
	 * @param string[] $lines   Paragraph lines.
	 * @param array    $meta    Label => value pairs rendered as a small list.
	 * @param string   $cta     Call-to-action button label.
	 *
	 * @return string
	 */
	private static function email_html( $heading, $link, $lines, $meta, $cta ) {
		$html  = '<h2 style="margin:0 0 12px;font-size:20px;">';
		$html .= '<a href="' . $link . '" style="color:#1f6feb;text-decoration:none;">' . $heading . '</a>';
		$html .= '</h2>';

		foreach ( $lines as $line ) {
			$html .= '<p style="margin:0 0 12px;line-height:1.6;color:#333;">' . $line . '</p>';
		}

		if ( ! empty( $meta ) ) {
			$html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:14px;color:#555;">';
			foreach ( $meta as $label => $value ) {
				$html .= '<tr>';
				$html .= '<td style="padding:2px 12px 2px 0;font-weight:600;">' . esc_html( $label ) . '</td>';
				$html .= '<td style="padding:2px 0;">' . $value . '</td>';
				$html .= '</tr>';
			}
			$html .= '</table>';
		}

		$html .= '<p style="margin:8px 0 0;">';
		$html .= '<a href="' . $link . '" style="display:inline-block;background:#1f6feb;color:#fff;';
		$html .= 'padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html( $cta ) . '</a>';
		$html .= '</p>';

		return $html;
	}

	/**
	 * Build the five connections for a template from its copy.
	 *
	 * @since 1.7.1
	 *
	 * @param array $content Copy built by build_content().
	 *
	 * @return array Connections keyed by a stable id.
	 */
	private static function build_connections( $content ) {
		return array(
			'email_default'   => array(
				'enabled'           => true,
				'name'              => __( 'Email to administrators', 'notification-master' ),
				'integration'      => 'email',
				'settings'         => array(
					'emails'          => array(
						array(
							'type'  => 'role',
							'value' => array(
								'value' => 'administrator',
								'label' => __( 'Administrator', 'notification-master' ),
							),
						),
					),
					'excluded_emails' => array(),
					'subject'         => $content['subject'],
					'message'         => $content['html'],
				),
				'enable_conditions' => false,
				'conditions'        => array(),
				'flag'              => false,
			),
			'webpush_default' => array(
				'enabled'           => true,
				'name'              => __( 'Web push to all subscribers', 'notification-master' ),
				'integration'      => 'webpush',
				'settings'         => array(
					'send_to'        => 'all',
					'specific_users' => array(),
					'title'          => $content['wp_title'],
					'message'        => $content['wp_message'],
					'icon'           => '',
					'image'          => $content['image'],
					'url'            => $content['url'],
					'urgency'        => 'normal',
				),
				'enable_conditions' => false,
				'conditions'        => array(),
				'flag'              => false,
			),
			'discord_shell'   => array(
				'enabled'           => false,
				'name'              => __( 'Discord (add your webhook URL)', 'notification-master' ),
				'integration'      => 'discord',
				'settings'         => array(
					'url'     => '',
					'message' => array(
						'title'       => $content['wp_title'],
						'title_link'  => $content['url'],
						'description' => $content['wp_message'],
						'content'     => '',
						'author'      => array(
							'name'     => '{{general.blogname}}',
							'url'      => '{{general.home_url}}',
							'icon_url' => '',
						),
						'fields'      => array(),
					),
				),
				'enable_conditions' => false,
				'conditions'        => array(),
				'flag'              => false,
			),
			'webhook_shell'   => array(
				'enabled'           => false,
				'name'              => __( 'Webhook (add your endpoint URL)', 'notification-master' ),
				'integration'      => 'webhook',
				'settings'         => array(
					'url'               => '',
					'method'            => 'POST',
					'headers'           => array(),
					'body_format'       => 'json',
					'body'              => array(
						array(
							'key'   => 'title',
							'value' => $content['wp_title'],
						),
						array(
							'key'   => 'message',
							'value' => $content['wp_message'],
						),
						array(
							'key'   => 'url',
							'value' => $content['url'],
						),
					),
					'show_empty_fields' => false,
				),
				'enable_conditions' => false,
				'conditions'        => array(),
				'flag'              => false,
			),
			'instagram_shell' => array(
				'enabled'           => false,
				'name'              => __( 'Instagram (add your credentials)', 'notification-master' ),
				'integration'      => 'instagram',
				'settings'         => array(
					'app_id'       => '',
					'app_secret'   => '',
					'access_token' => '',
					'user_id'      => '',
					'text'         => $content['wp_message'],
					'alt_text'     => '',
					'image_url'    => $content['image'],
				),
				'enable_conditions' => false,
				'conditions'        => array(),
				'flag'              => false,
			),
		);
	}
}
