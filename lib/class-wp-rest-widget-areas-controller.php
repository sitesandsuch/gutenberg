<?php

class WP_REST_Widget_Areas extends WP_REST_Controller {
	public function __construct() {
		$this->namespace = '__experimental';
		$this->rest_base = 'widget-areas';
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>.+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'The sidebar’s ID.', 'gutenberg' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_user_cannot_edit',
				__( 'Sorry, you are not allowed to edit sidebars.', 'gutenberg' )
			);
		}

		return true;
	}

	public function get_items( $request ) {
		global $wp_registered_sidebars;

		$data = array();

		foreach ( array_keys( $wp_registered_sidebars ) as $sidebar_id ) {
			$data[ $sidebar_id ] = $this->get_sidebar_data( $sidebar_id );
		}

		return rest_ensure_response( $data );
	}

	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_user_cannot_edit',
				__( 'Sorry, you are not allowed to edit sidebars.', 'gutenberg' )
			);
		}

		return true;
	}

	public function get_item( $request ) {
		return rest_ensure_response( $this->get_sidebar_data( $request['id'] ) );
	}

	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_user_cannot_edit',
				__( 'Sorry, you are not allowed to edit sidebars.', 'gutenberg' )
			);
		}

		return true;
	}

	public function update_item( $request ) {
		$status = $this->update_sidebar_data( $request['id'], $request );
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		return rest_ensure_response( $this->get_sidebar_data( $request['id'] ) );
	}

	// TODO: Add schema

	protected function get_sidebar_data( $sidebar_id ) {
		global $wp_registered_sidebars;

		if ( ! isset( $wp_registered_sidebars[ $sidebar_id ] ) ) {
			return new WP_Error(
				'rest_sidebar_invalid_id',
				__( 'Invalid sidebar ID.', 'gutenberg' ),
				array( 'status' => 404 )
			);
		}

		$sidebar = $wp_registered_sidebars[ $sidebar_id ];
		$content = '';
		$blocks  = array();

		$sidebars_items = wp_get_sidebars_widgets();
		if ( is_numeric( $sidebars_items[ $sidebar_id ] ) ) {
			$post    = get_post( $sidebars_items[ $sidebar_id ] );
			$content = apply_filters( 'the_content', $post->post_content );
		} elseif ( ! empty( $sidebars_items[ $sidebar_id ] ) ) {
			foreach ( $sidebars_items[ $sidebar_id ] as $item ) {
				if ( is_array( $item ) && isset( $item['blockName'] ) ) {
					$blocks[] = $item;
				} else {
					$blocks[] = array(
						'blockName' => 'core/legacy-widget',
						'attrs'     => array(
							'identifier' => $item,
							'instance'   => $this->get_sidebars_widget_instance( $sidebar, $item ),
						),
						'innerHTML' => '',
					);
				}
			}
			$content = serialize_blocks( $blocks );
		}

		return array_merge(
			$sidebar,
			array( 'content' => $content )
		);
	}

	protected function update_sidebar_data( $sidebar_id, $request ) {
		global $wp_registered_sidebars;

		if ( ! isset( $wp_registered_sidebars[ $sidebar_id ] ) ) {
			return new WP_Error(
				'rest_sidebar_invalid_id',
				__( 'Invalid sidebar ID.', 'gutenberg' ),
				array( 'status' => 404 )
			);
		}
		if ( isset( $request['content'] ) && is_string( $request['content'] ) ) {
			$sidebars = wp_get_sidebars_widgets();
			$sidebar = $sidebars_items[ $sidebar_id ];
			$items = array();
			$post_id = wp_insert_post(
				array(
					'ID'           => is_numeric( $sidebar ) ? $sidebar : 0,
					'post_content' => $request['content'],
				)
			);
			if( ! is_numeric( $sidebar ) ) {
				wp_set_sidebars_widgets(
					array_merge(
						$sidebars,
						array(
							$sidebar_id           => $post_id,
							'wp_inactive_widgets' => array_merge(
								$sidebars['wp_inactive_widgets'],
								$sidebar
							),
						)
					)
				);
			}
		}

		return true;
	}

	private function get_sidebars_widget_instance( $sidebar, $id ) {
		list( $object, $number, $name ) = $this->get_widget_info( $id );
		if ( ! $object ) {
			return array();
		}

		$object->_set( $number );

		$instances = $object->get_settings();
		$instance  = $instances[ $number ];

		$args = array_merge(
			$sidebar,
			array(
				'widget_id'   => $id,
				'widget_name' => $name,
			)
		);

		/**
		 * Filters the settings for a particular widget instance.
		 *
		 * Returning false will effectively short-circuit display of the widget.
		 *
		 * @since 2.8.0
		 *
		 * @param array     $instance The current widget instance's settings.
		 * @param WP_Widget $this     The current widget instance.
		 * @param array     $args     An array of default widget arguments.
		 */
		$instance = apply_filters( 'widget_display_callback', $instance, $object, $args );

		if ( false === $instance ) {
			return array();
		}

		return $instance;
	}

	private function get_widget_info( $id ) {
		global $wp_registered_widgets;

		if (
			! isset( $wp_registered_widgets[ $id ]['callback'][0] ) ||
			! isset( $wp_registered_widgets[ $id ]['params'][0]['number'] ) ||
			! isset( $wp_registered_widgets[ $id ]['name'] ) ||
			! ( $wp_registered_widgets[ $id ]['callback'][0] instanceof WP_Widget )
		) {
			return array( null, null, null );
		}

		$object = $wp_registered_widgets[ $id ]['callback'][0];
		$number = $wp_registered_widgets[ $id ]['params'][0]['number'];
		$name   = $wp_registered_widgets[ $id ]['name'];
		return array( $object, $number, $name );
	}
}
