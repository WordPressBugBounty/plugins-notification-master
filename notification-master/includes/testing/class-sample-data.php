<?php
/**
 * Class Sample_Data
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Testing;

use Notification_Master\Abstracts\Trigger;
use Notification_Master\Abstracts\Post_Trigger;
use Notification_Master\Abstracts\Comment_Trigger;
use Notification_Master\Abstracts\User_Trigger;
use Notification_Master\Abstracts\Media_Trigger;
use Notification_Master\Abstracts\Plugin_Trigger;
use Notification_Master\Abstracts\Theme_Trigger;
use Notification_Master\Abstracts\Taxonomy_Trigger;
use Notification_Master\Abstracts\Privacy_Trigger;

/**
 * Fills a copy of a trigger with real sample objects so merge tags resolve.
 */
class Sample_Data {

	/**
	 * Return a clone of the trigger filled with sample data.
	 *
	 * @since 2.0.0
	 *
	 * @param Trigger $trigger Registered trigger.
	 *
	 * @return Trigger
	 */
	public static function fill( Trigger $trigger ) {
		$sample = clone $trigger;
		$user   = wp_get_current_user();

		if ( $sample instanceof Post_Trigger ) {
			$post = self::latest_post( $sample->post_type_object ? $sample->post_type_object->name : 'post' );
			if ( $post ) {
				$sample->post             = $post;
				$author                   = get_userdata( $post->post_author );
				$sample->post_author      = $author ? $author : $user;
				$sample->post_last_editor = $sample->post_author;
			}
			$sample->current_user = $user;
		} elseif ( $sample instanceof Comment_Trigger ) {
			self::fill_comment( $sample );
		} elseif ( $sample instanceof User_Trigger || $sample instanceof Privacy_Trigger ) {
			$sample->user = $user;
			if ( $sample instanceof User_Trigger ) {
				$sample->user_meta = get_user_meta( $user->ID );
			}
		} elseif ( $sample instanceof Media_Trigger ) {
			$attachment = self::latest_post( 'attachment', 'inherit' );
			if ( $attachment ) {
				$sample->attachment = $attachment;
				$sample->author     = get_userdata( $attachment->post_author );
			}
		} elseif ( $sample instanceof Plugin_Trigger ) {
			$sample->plugin = $sample->get_plugin_data( NOTIFICATION_MASTER_BASENAME );
		} elseif ( $sample instanceof Theme_Trigger ) {
			$sample->theme     = wp_get_theme();
			$sample->old_theme = wp_get_theme();
		} elseif ( $sample instanceof Taxonomy_Trigger ) {
			self::fill_term( $sample );
		}

		/**
		 * Filter the sample trigger used by "Send a test" (Pro fills its own triggers here).
		 *
		 * @since 2.0.0
		 *
		 * @param Trigger $sample  Clone with sample data.
		 * @param Trigger $trigger Original trigger.
		 */
		return apply_filters( 'notification_master_test_sample_trigger', $sample, $trigger );
	}

	/**
	 * Newest post of a type, published first.
	 *
	 * @since 2.0.0
	 *
	 * @param string $post_type Post type.
	 * @param string $status    Preferred status.
	 *
	 * @return \WP_Post|null
	 */
	private static function latest_post( $post_type, $status = 'publish' ) {
		foreach ( array( $status, 'any' ) as $post_status ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => $post_status,
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			if ( ! empty( $posts ) ) {
				return $posts[0];
			}
		}

		return null;
	}

	/**
	 * Fill a comment trigger with the newest comment of its type.
	 *
	 * @since 2.0.0
	 *
	 * @param Comment_Trigger $sample Trigger clone.
	 */
	private static function fill_comment( $sample ) {
		$comments = get_comments(
			array(
				'type'   => $sample->get_group(),
				'number' => 1,
				'status' => 'all',
			)
		);
		if ( empty( $comments ) ) {
			return;
		}

		$comment             = $comments[0];
		$sample->comment     = $comment;
		$sample->post        = get_post( $comment->comment_post_ID );
		$sample->post_author = get_userdata( $comment->user_id );
	}

	/**
	 * Fill a taxonomy trigger with the newest term.
	 *
	 * @since 2.0.0
	 *
	 * @param Taxonomy_Trigger $sample Trigger clone.
	 */
	private static function fill_term( $sample ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $sample->get_group(),
				'hide_empty' => false,
				'number'     => 1,
				'orderby'    => 'term_id',
				'order'      => 'DESC',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$term         = $terms[0];
		$link         = get_term_link( $term );
		$sample->args = array(
			'date'             => current_time( 'mysql' ),
			'term_id'          => $term->term_id,
			'term_name'        => $term->name,
			'term_description' => $term->description,
			'term_slug'        => $term->slug,
			'term_url'         => is_wp_error( $link ) ? '' : $link,
		);
	}
}
