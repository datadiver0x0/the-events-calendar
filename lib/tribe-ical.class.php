<?php
/**
 *	Class that implements the export to iCal functionality
 *  both for list and single events
 */
class TribeiCal {

	/**
	 * Set all the filters and actions necessary for the operation of the iCal generator.
	 * @static
	 */
	public static function init() {
		add_filter( 'tribe_events_list_after_template', 	array( __CLASS__, 'maybe_add_link'	 ), 30, 1 );
		add_filter( 'tribe_events_calendar_after_template', array( __CLASS__, 'maybe_add_link'	 ), 30, 1 );
		add_filter( 'tribe_events_week_after_template', 	array( __CLASS__, 'maybe_add_link'	 ), 30, 1 );
		add_action( 'tribe_tec_template_chooser', 			array( __CLASS__, 'do_ical_template' ) 		  );
	}


	/**
	 * Returns the url for the iCal generator for lists of posts
	 * @static
	 * @return string
	 */
	public static function get_ical_link() {
		$tec = TribeEvents::instance();
		return trailingslashit( $tec->getLink( 'home' ) ) . 'ical';
	}

	/**
	 * Generates the markup for the "iCal Import" link for the views.
	 *
	 * @static
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public static function maybe_add_link( $content ) {

		$show_ical = apply_filters( 'tribe_events_list_show_ical_link', true );

		if ( ! $show_ical )
			return $content;

		$ical    = '<a class="tribe-events-ical tribe-events-button" title="' . __( 'iCal Import', 'tribe-events-calendar' ) . '" href="' . tribe_get_ical_link() . '">' . __( '+ iCal Import', 'tribe-events-calendar' ) . '</a>';
		$content = $ical . $content;

		return $content;

	}

	/**
	 * Executes the iCal generator when the appropiate query_var or $_GET is setup
	 *
	 * @static
	 *
	 * @param $template
	 */
	public static function do_ical_template( $template ) {
		// hijack to iCal template
		if ( get_query_var( 'ical' ) || isset( $_GET['ical'] ) ) {
			global $wp_query;
			if ( is_single() ) {
				self::generate_ical_feed( $wp_query->post, null );
			} else {
				self::generate_ical_feed();
			}
			die();
		}
	}


	/**
	 * Generates the iCal file
	 *
	 * @static
	 *
	 * @param int|null $post If you want the ical file for a single event
	 */
	public static function generate_ical_feed( $post = null ) {

		$tribeEvents      = TribeEvents::instance();
		$postId           = $post ? $post->ID : null;
		$wpTimezoneString = get_option( 'timezone_string' );
		$postType         = TribeEvents::POSTTYPE;
		$events           = '';
		$blogHome         = get_bloginfo( 'url' );
		$blogName         = get_bloginfo( 'name' );
		$includePosts     = ( $postId ) ? '&include=' . $postId : '';

		if ( class_exists( 'TribeEventsFilterView' ) ) {
			TribeEventsFilterView::instance()->createFilters( null, true );
		}

		TribeEventsQuery::init();

		$events =  TribeEventsQuery::getEvents(array(), true);

		if ( $post ) {
			$eventPosts   = array();
			$eventPosts[] = $post;
		} else {
			$eventPosts = get_posts( 'posts_per_page=-1&post_type=' . $postType . $includePosts );
		}

		foreach ( $eventPosts as $eventPost ) {

			$startDate = $eventPost->EventStartDate;
			$endDate   = $eventPost->EventEndDate;

			// convert 2010-04-08 00:00:00 to 20100408T000000 or YYYYMMDDTHHMMSS
			$startDate = str_replace( array( '-', ' ', ':' ), array( '', 'T', '' ), $startDate );
			$endDate   = str_replace( array( '-', ' ', ':' ), array( '', 'T', '' ), $endDate );
			if ( get_post_meta( $eventPost->ID, '_EventAllDay', true ) == 'yes' ) {
				$startDate = substr( $startDate, 0, 8 );
				$endDate   = substr( $endDate, 0, 8 );
				// endDate bumped ahead one day to counter iCal's off-by-one error
				$endDateStamp = strtotime( $endDate );
				$endDate      = date( 'Ymd', $endDateStamp + 86400 );
				$type         = 'DATE';
			} else {
				$type = 'DATE-TIME';
			}
			$description = preg_replace( "/[\n\t\r]/", ' ', strip_tags( $eventPost->post_content ) );

			// add fields to iCal output
			$item   = array();
			$item[] = "DTSTART;VALUE=$type:" . $startDate;
			$item[] = "DTEND;VALUE=$type:" . $endDate;
			$item[] = 'DTSTAMP:' . date( 'Ymd\THis', time() );
			$item[] = 'CREATED:' . str_replace( array( '-', ' ', ':' ), array( '', 'T', '' ), $eventPost->post_date );
			$item[] = 'LAST-MODIFIED:' . str_replace( array( '-', ' ', ':' ), array( '', 'T', '' ), $eventPost->post_modified );
			$item[] = 'UID:' . $eventPost->ID . '-' . strtotime( $startDate ) . '-' . strtotime( $endDate ) . '@' . $blogHome;
			$item[] = 'SUMMARY:' . $eventPost->post_title;
			$item[] = 'DESCRIPTION:' . str_replace( ',', '\,', $description );
			$item[] = 'LOCATION:' . html_entity_decode( $tribeEvents->fullAddressString( $eventPost->ID ), ENT_QUOTES );
			$item[] = 'URL:' . get_permalink( $eventPost->ID );

			$item = apply_filters( 'tribe_ical_feed_item', $item, $eventPost );

			$events .= "BEGIN:VEVENT\n" . implode( "\n", $item ) . "\nEND:VEVENT\n";
		}

		header( 'Content-type: text/calendar' );
		header( 'Content-Disposition: attachment; filename="iCal-TribeEvents.ics"' );
		$content = "BEGIN:VCALENDAR\n";
		$content .= "VERSION:2.0\n";
		$content .= 'PRODID:-//' . $blogName . ' - ECPv' . TribeEvents::VERSION . "//NONSGML v1.0//EN\n";
		$content .= "CALSCALE:GREGORIAN\n";
		$content .= "METHOD:PUBLISH\n";
		$content .= 'X-WR-CALNAME:' . apply_filters( 'tribe_ical_feed_calname', $blogName ) . "\n";
		$content .= 'X-ORIGINAL-URL:' . $blogHome . "\n";
		$content .= 'X-WR-CALDESC:Events for ' . $blogName . "\n";
		if ( $wpTimezoneString ) $content .= 'X-WR-TIMEZONE:' . $wpTimezoneString . "\n";
		$content = apply_filters( 'tribe_ical_properties', $content );
		$content .= $events;
		$content .= 'END:VCALENDAR';
		echo $content;
		exit;
	}
}