<?php
/**
 * Handles the update of the Events custom tables information.
 *
 * @since   TBD
 *
 * @package TEC\Events_Pro\Updates
 */

namespace TEC\Events\Custom_Tables\V1\Updates;

use WP_Post;
use WP_REST_Request;

/**
 * Class Controller
 *
 * @since   TBD
 *
 * @package TEC\Events\Custom_Tables\V1\Updates
 */
class Controller {

	/**
	 * A reference to the current Meta Watcher service implementation.
	 *
	 * @since TBD
	 *
	 * @var Meta_Watcher
	 */
	private $meta_watcher;
	/**
	 * A reference to the current Request Factory implementation.
	 *
	 * @since TBD
	 *
	 * @var Requests
	 */
	private $request_factory;
	/**
	 * @var Models
	 */
	private $models;

	/**
	 * Controller constructor.
	 *
	 * @since TBD
	 *
	 * @param Meta_Watcher $meta_watcher A reference to the current Meta Watcher service implementation.
	 */
	public function __construct( Meta_Watcher $meta_watcher, Requests $request_factory, Models $models ) {
		$this->meta_watcher = $meta_watcher;
		$this->request_factory = $request_factory;
		$this->models = $models;
	}

	/**
	 * Updates the custom tables' information for each Event post whose important
	 * meta was updated during the request.
	 *
	 * @since TBD
	 */
	public function commit_updates() {
		if ( empty( $this->meta_watcher->get_marked_ids() ) ) {
			return;
		}

		$request = $this->request_factory->from_http_request();

		foreach ( $this->meta_watcher->get_marked_ids() as $booked_id ) {
			$this->commit_post_updates( $booked_id, $request );
		}
	}

	/**
	 * Updates the custom tables' information for an Event post whose important
	 * meta was updated.
	 *
	 * After a first update, the post ID is removed from the marked-for-update stack
	 * and will not be automatically updated again during the request.
	 *
	 * @since TBD
	 *
	 * @param WP_REST_Request|null $request A reference to the object modeling the current request,
	 *                                      or `null` to build a request from the current HTTP data.
	 *                                      Mind the WP_REST_Request class can be used to
	 *                                      model a non-REST API request too|
	 *
	 * @param int                  $post_id The post ID, not guaranteed to be an Event post ID if this
	 *                                      method is not called from this class!
	 *
	 * @return bool Whether the post updates were correctly applied or not.
	 */
	public function commit_post_updates( $post_id, WP_REST_Request $request = null ) {
		if ( null === $request ) {
			$request = $this->request_factory->from_http_request();
		}

		if ( ! $this->meta_watcher->is_tracked( $post_id ) ) {
			// The post relevant meta was not changed, do nothing.
			return false;
		}

		/**
		 * Fires before the default The Events Calendar logic to update an Event custom tables
		 * information is applied.
		 * Returning a non `null` value from this filter will prevent the default logic from running.
		 *
		 * @since TBD
		 *
		 * @param mixed|null      $updated      Whether the post custom tables information was updated by any
		 *                                      filtering function or not. If a non `null` value is returned
		 *                                      from this filter, then the default logic will not be applied.
		 * @param int             $post_id      The post ID of the Event whose custom tables information should be
		 *                                      updated.
		 * @param WP_REST_Request $request      A reference to the object modeling the current request,
		 *                                      if any. Mind the WP_REST_Request class can be used to
		 *                                      model a non-REST API request too!
		 *
		 * @return bool Whether the custom tables' updates were correctly applied or not.
		 */
		$updated = apply_filters( 'tec_events_custom_tables_v1_commit_post_updates', null, $post_id, $request );

		if ( null === $updated ) {
			$updated = $this->update_custom_tables( $post_id );
		}

		if ( $updated ) {
			// Remove the post ID from the list of post IDs still to update.
			$this->meta_watcher->remove( $post_id );
		}

		return true;
	}

	/**
	 * Updates the custom tables' information for an Event post whose important meta
	 * was updated in the context of a REST request.
	 *
	 * After a first update, the post ID is removed from the marked-for-update stack
	 * and will not be automatically updated again during the request.
	 *
	 * @since TBD
	 *
	 * @param WP_Post         $post    A reference to the post object representing the Event
	 *                                 post.
	 * @param WP_REST_Request $request A reference to the REST API request object that is,
	 *                                 currently, being processed.
	 *
	 * @return bool Whether the custom tables' updates were correctly applied or not.
	 */
	public function commit_post_rest_update( WP_Post $post, WP_REST_Request $request ) {
		if ( ! $this->meta_watcher->is_tracked( $post->ID ) ) {
			return false;
		}

		return $this->commit_post_updates( $post->ID, $request );
	}

	/**
	 * Deletes an Event custom tables information.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The deleted Event post ID.
	 *
	 * @return int|false Either the number of affected rows, or `false` on failure.
	 */
	public function delete_custom_tables_data( $post_id, WP_Post $post ) {
		$affected = $this->models->delete($post_id);

		/**
		 * Fires after the Event custom tables data has been removed from the database.
		 *
		 * By the time this action fires, the Event post has not yet been removed from
		 * the posts tables.
		 *
		 * @since TBD
		 *
		 * @param int     $affected The number of affected rows, across all custom tables.
		 *                          Keep in mind db-level deletions will not be counted in
		 *                          this value!
		 * @param int     $post_id  The Event post ID.
		 * @param WP_Post $post     A reference to the deleted Event post.
		 *
		 */
		return apply_filters( 'tec_events_custom_tables_v1_delete_post', $affected, $post_id, $post );
	}


	/**
	 * Updates the custom tables with the data for an Event post.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The Even post ID.
	 *
	 * @return bool Whether the update was successful or not.
	 */
	private function update_custom_tables( $post_id ) {
		return	$this->models->update($post_id);

		// @todo do_action here
	}
}