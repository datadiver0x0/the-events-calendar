<?php
/**
 * Controller for Events Calendar Pro Elementor integrations.
 *
 * @since   TBD
 *
 * @package TEC\Events\Integrations\Plugins\Elementor
 */

namespace TEC\Events\Integrations\Plugins\Elementor;

use WP_Post;
use TEC\Common\Integrations\Traits\Plugin_Integration;
use TEC\Events\Integrations\Integration_Abstract;
use TEC\Events\Integrations\Plugins\Elementor\Template\Controller as Template_Controller;
use TEC\Events\Custom_Tables\V1\Models\Occurrence;

use Tribe__Template as Template;
use Tribe__Events__Main as TEC;

/**
 * Class Controller
 *
 * @since   TBD
 *
 * @package TEC\Events\Integrations\Plugins\Elementor
 */
class Controller extends Integration_Abstract {
	use Plugin_Integration;

	/**
	 * The template instance.
	 *
	 * @since TBD
	 *
	 * @var Tribe_Template
	 */
	protected $template;

	/**
	 * {@inheritDoc}
	 *
	 * @since TBD
	 */
	public static function get_slug(): string {
		return 'elementor';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since TBD
	 *
	 * @return bool Whether integrations should load.
	 */
	public function load_conditionals(): bool {
		return defined( 'ELEMENTOR_PATH' ) && ! empty( ELEMENTOR_PATH );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since TBD
	 */
	public function load(): void {
		$this->container->register_on_action( 'elementor/init', Template_Controller::class );
		$this->container->register_on_action( 'elementor/widgets/register', Widgets_Manager::class );
		$this->container->register_on_action( 'elementor/loaded', Assets_Manager::class );

		$this->register_actions();
		$this->register_filters();

		// Make sure we instantiate the assets manager.
		tribe( Assets_Manager::class );

		// Make sure we instantiate the templates controller.
		tribe( Template_Controller::class );

		$this->register_assets();
	}

	/**
	 * Register actions.
	 *
	 * @since TBD
	 */
	public function register_actions(): void {
		// add_action( 'elementor/document/after_save', [ $this, 'action_elementor_document_after_save' ], 10, 2 );
		add_action( 'edit_form_after_title', [ $this, 'modify_switch_mode_button' ], 15, 1 );
		add_action( 'elementor/elements/categories_registered', [ $this, 'action_register_elementor_category' ] );
		add_action( 'elementor/controls/controls_registered', [ $this, 'action_register_elementor_controls' ] );
	}

	/**
	 * Registers widget categories for Elementor.
	 *
	 * @since 5.4.0
	 *
	 * @param Elements_Manager $elements_manager Elementor Manager instance.
	 */
	public function action_register_elementor_category( $elements_manager ) {
		$elements_manager->add_category(
			'the-events-calendar',
			[
				'title' => __( 'The Events Calendar', 'the-events-calendar' ),
				'icon'  => 'eicon-calendar',
			]
		);
	}

	/**
	 * Register filters.
	 *
	 * @since TBD
	 */
	public function register_filters(): void {
		add_filter( 'elementor/query/query_args', [ $this, 'suppress_query_filters' ], 10, 1 );
	}

	/**
	 * Register the assets for the Elementor integration.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function register_assets(): void {
		$plugin = tribe( 'tec.main' );
		tribe_asset(
			$plugin,
			'tec-events-elementor-admin',
			'integrations/plugins/elementor/admin.css',
			[],
			'admin_enqueue_scripts',
			[
				'conditionals' => [ $this, 'should_load_admin_styles' ],
			]
		);
	}

	/**
	 * Registers controls for Elementor.
	 *
	 * @since TBD
	 */
	public function action_register_elementor_controls() {
		return $this->container->make( Controls_Manager::class )->register();
	}

	/**
	 * Checks if Elementor Pro is active.
	 * For registering controllers, etc, use register_on_action(  'elementor_pro/init' )
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	public function is_elementor_pro_active(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}


	/**
	 * Checks if the admin styles should be loaded.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	public function should_load_admin_styles(): bool {
		return \Tribe__Admin__Helpers::instance()->is_post_type_screen( TEC::POSTTYPE );
	}

	/**
	 * Test function to re-save the metadata as the base post in a series.
	 *
	 * This is a temporary solution to fix the issue with the Elementor data not being saved on the real post.
	 * It's NOT WORKING CORRECTLY as of yet, and the issue is still being investigated.
	 *
	 * @since TBD
	 *
	 * @param \Elementor\Core\DocumentTypes\Post $document    The document.
	 * @param array                              $editor_data The editor data.
	 */
	public function action_elementor_document_after_save( $document, $editor_data ): void {
		if ( empty( $document ) ) {
			return;
		}

		$occurrence_id = $document->get_main_id();
		$event         = tribe_get_event( $occurrence_id );

		// This is an occurrence the real post ID is hold as a reference on the occurrence table.
		if ( empty( $event->_tec_occurrence->post_id ) || ! $event->_tec_occurrence instanceof Occurrence ) {
			return;
		}

		$saved_meta = get_post_meta( $occurrence_id, '_elementor_data', true );

		$real_id = $event->_tec_occurrence->post_id;

		// Don't use `update_post_meta` that can't handle `revision` post type.
		$is_meta_updated = update_metadata( 'post', $real_id, '_elementor_data', $saved_meta );
	}

	/**
	 * Modify the switch mode button to show a warning when the event is not properly saved yet.
	 *
	 * @since TBD
	 *
	 * @param WP_Post|int|string $post The post object.
	 *
	 * @return void
	 */
	public function modify_switch_mode_button( $post ): void {
		// Since this is a hook, we need to check if the post is an object.
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! tribe_is_event( $post ) ) {
			return;
		}

		$start_date = get_post_meta( $post->ID, '_EventStartDate', true );
		$end_date   = get_post_meta( $post->ID, '_EventEndDate', true );

		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			return;
		}

		$this->get_template()->template( 'switch-warning', [ 'post' => $post ] );
	}

	/**
	 * Modifies the Elementor posts widget query arguments to set 'tribe_suppress_query_filters' to true for the Event post type.
	 *
	 * @param array $query_args The Elementor posts widget query arguments.
	 *
	 * @return array The modified Elementor posts widget query arguments.
	 */
	public function suppress_query_filters( $query_args ): array {
		/**
		 * Checks if the 'tribe_events' post type is present in the query arguments.
		 * If not, it returns the query arguments unmodified.
		 */
		if ( ! in_array( \Tribe__Events__Main::POSTTYPE, (array) $query_args['post_type'], true ) ) {
			return $query_args;
		}

		// Set the 'tribe_suppress_query_filters' to true.
		$query_args['tribe_suppress_query_filters'] = true;

		return $query_args;
	}

	/**
	 * Gets the template instance used to setup the rendering html.
	 *
	 * @since TBD
	 *
	 * @return Template
	 */
	public function get_template() {
		if ( empty( $this->template ) ) {
			$this->template = new Template();
			$this->template->set_template_origin( tribe( 'tec.main' ) );
			$this->template->set_template_folder( 'src/admin-views/integrations/plugins/elementor' );
			$this->template->set_template_context_extract( true );
		}

		return $this->template;
	}
}