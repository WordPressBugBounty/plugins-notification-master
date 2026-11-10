<?php
/**
 * Class Period
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Stats;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A window of the last N site-local days plus the window before it.
 */
class Period {

	/**
	 * Allowed window lengths.
	 *
	 * @since 2.0.0
	 *
	 * @var int[]
	 */
	const ALLOWED_DAYS = array( 7, 30, 90 );

	/**
	 * Number of days.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	public $days;

	/**
	 * Window start (Y-m-d H:i:s, site-local).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $from;

	/**
	 * Window end (Y-m-d H:i:s, site-local).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $to;

	/**
	 * Previous window start.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $previous_from;

	/**
	 * Previous window end.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $previous_to;

	/**
	 * Every day in the window, oldest first (Y-m-d).
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	public $dates = array();

	/**
	 * Build the window ending today (site time zone).
	 *
	 * @since 2.0.0
	 *
	 * @param int                    $days Number of days, today included.
	 * @param DateTimeImmutable|null $now  Override "now" (tests).
	 */
	public function __construct( $days = 7, $now = null ) {
		$this->days = max( 1, (int) $days );

		$now   = $now instanceof DateTimeImmutable ? $now : new DateTimeImmutable( 'now', self::zone() );
		$today = $now->setTime( 0, 0, 0 );
		$start = $today->modify( '-' . ( $this->days - 1 ) . ' days' );

		$this->from          = $start->format( 'Y-m-d H:i:s' );
		$this->to            = $today->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
		$this->previous_from = $start->modify( '-' . $this->days . ' days' )->format( 'Y-m-d H:i:s' );
		$this->previous_to   = $start->modify( '-1 second' )->format( 'Y-m-d H:i:s' );

		for ( $i = 0; $i < $this->days; $i++ ) {
			$this->dates[] = $start->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
		}
	}

	/**
	 * Whether a day count is allowed.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $days Value to check.
	 *
	 * @return bool
	 */
	public static function is_allowed( $days ) {
		return is_numeric( $days ) && in_array( (int) $days, self::ALLOWED_DAYS, true );
	}

	/**
	 * Site time zone label, e.g. "+02:00" or "Africa/Cairo".
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function timezone() {
		return self::zone()->getName();
	}

	/**
	 * Site time zone object (works below WordPress 5.3).
	 *
	 * @since 2.0.0
	 *
	 * @return DateTimeZone
	 */
	public static function zone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}

		$name = get_option( 'timezone_string' );
		if ( $name ) {
			return new DateTimeZone( $name );
		}

		$offset  = (float) get_option( 'gmt_offset' );
		$hours   = (int) $offset;
		$minutes = abs( ( $offset - $hours ) * 60 );

		return new DateTimeZone( sprintf( '%s%02d:%02d', $offset < 0 ? '-' : '+', abs( $hours ), $minutes ) );
	}
}
