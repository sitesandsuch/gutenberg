<?php
/**
 * Functions used in making widgets interopable with block editors.
 *
 * @package gutenberg
 */

/**
 * Emulates the Widgets screen `admin_print_styles` when at the block editor
 * screen.
 */
function gutenberg_block_editor_admin_print_styles() {
	if ( get_current_screen()->is_block_editor() ) {
		/** This action is documented in wp-admin/admin-footer.php */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		do_action( 'admin_print_styles-widgets.php' );
	}
}
add_action( 'admin_print_styles', 'gutenberg_block_editor_admin_print_styles' );

/**
 * Emulates the Widgets screen `admin_print_scripts` when at the block editor
 * screen.
 */
function gutenberg_block_editor_admin_print_scripts() {
	if ( get_current_screen()->is_block_editor() ) {
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		do_action( 'admin_print_scripts-widgets.php' );
	}
}
add_action( 'admin_print_scripts', 'gutenberg_block_editor_admin_print_scripts' );

/**
 * Emulates the Widgets screen `admin_print_footer_scripts` when at the block
 * editor screen.
 */
function gutenberg_block_editor_admin_print_footer_scripts() {
	if ( get_current_screen()->is_block_editor() ) {
		/** This action is documented in wp-admin/admin-footer.php */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		do_action( 'admin_print_footer_scripts-widgets.php' );
	}
}
add_action( 'admin_print_footer_scripts', 'gutenberg_block_editor_admin_print_footer_scripts' );

/**
 * Emulates the Widgets screen `admin_footer` when at the block editor screen.
 */
function gutenberg_block_editor_admin_footer() {
	if ( get_current_screen()->is_block_editor() ) {
		/** This action is documented in wp-admin/admin-footer.php */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		do_action( 'admin_footer-widgets.php' );
	}
}
add_action( 'admin_footer', 'gutenberg_block_editor_admin_footer' );

/**
 * Extends default editor settings with values supporting legacy widgets.
 *
 * @param array $settings Default editor settings.
 *
 * @return array Filtered editor settings.
 */
function gutenberg_legacy_widget_settings( $settings ) {
	/**
	 * TODO: The hardcoded array should be replaced with a mechanism to allow
	 * core and third party blocks to specify they already have equivalent
	 * blocks, and maybe even allow them to have a migration function.
	 */
	$core_widgets = array(
		'WP_Widget_Pages',
		'WP_Widget_Calendar',
		'WP_Widget_Archives',
		'WP_Widget_Media_Audio',
		'WP_Widget_Media_Image',
		'WP_Widget_Media_Gallery',
		'WP_Widget_Media_Video',
		'WP_Widget_Meta',
		'WP_Widget_Search',
		'WP_Widget_Text',
		'WP_Widget_Categories',
		'WP_Widget_Recent_Posts',
		'WP_Widget_Recent_Comments',
		'WP_Widget_RSS',
		'WP_Widget_Tag_Cloud',
		'WP_Nav_Menu_Widget',
		'WP_Widget_Custom_HTML',
	);

	$has_permissions_to_manage_widgets = current_user_can( 'edit_theme_options' );
	$available_legacy_widgets          = array();
	global $wp_widget_factory, $wp_registered_widgets;
	foreach ( $wp_widget_factory->widgets as $class => $widget_obj ) {
		if ( ! in_array( $class, $core_widgets ) ) {
			$available_legacy_widgets[ $class ] = array(
				'name'             => html_entity_decode( $widget_obj->name ),
				// wp_widget_description is not being used because its input parameter is a Widget Id.
				// Widgets id's reference to a specific widget instance.
				// Here we are iterating on all the available widget classes even if no widget instance exists for them.
				'description'      => isset( $widget_obj->widget_options['description'] ) ?
					html_entity_decode( $widget_obj->widget_options['description'] ) :
					null,
				'isCallbackWidget' => false,
			);
		}
	}
	foreach ( $wp_registered_widgets as $widget_id => $widget_obj ) {
		if (
			is_array( $widget_obj['callback'] ) &&
			isset( $widget_obj['callback'][0] ) &&
			( $widget_obj['callback'][0] instanceof WP_Widget )
		) {
			continue;
		}
		$available_legacy_widgets[ $widget_id ] = array(
			'name'             => html_entity_decode( $widget_obj['name'] ),
			'description'      => html_entity_decode( wp_widget_description( $widget_id ) ),
			'isCallbackWidget' => true,
		);
	}

	$settings['hasPermissionsToManageWidgets'] = $has_permissions_to_manage_widgets;
	$settings['availableLegacyWidgets']        = $available_legacy_widgets;

	return $settings;
}
add_filter( 'block_editor_settings', 'gutenberg_legacy_widget_settings' );


function create_wp_area_post_type() {
	register_post_type(
		'wp_area',
		array(
			'labels'                => array(
				'name'                     => _x( 'WordPress Area', 'post type general name' ),
				'singular_name'            => _x( 'WordPress Area', 'post type singular name' ),
				'menu_name'                => _x( 'WordPress Areas', 'admin menu' ),
				'name_admin_bar'           => _x( 'WordPress Area', 'add new on admin bar' ),
				'add_new'                  => _x( 'Add New', 'Block' ),
				'add_new_item'             => __( 'Add New WordPress Area' ),
				'new_item'                 => __( 'New WordPress Area' ),
				'edit_item'                => __( 'Edit WordPress Area' ),
				'view_item'                => __( 'View WordPress Area' ),
				'all_items'                => __( 'All WordPress Areas' ),
				'search_items'             => __( 'Search WordPress Areas' ),
				'not_found'                => __( 'No WordPress area found.' ),
				'not_found_in_trash'       => __( 'No WordPress areas found in Trash.' ),
				'filter_items_list'        => __( 'Filter WordPress areas list' ),
				'items_list_navigation'    => __( 'WordPress areas list navigation' ),
				'items_list'               => __( 'WordPress areas list' ),
				'item_published'           => __( 'WordPress area published.' ),
				'item_published_privately' => __( 'WordPress area published privately.' ),
				'item_reverted_to_draft'   => __( 'WordPress area reverted to draft.' ),
				'item_scheduled'           => __( 'WordPress area scheduled.' ),
				'item_updated'             => __( 'WordPress area updated.' ),
			),
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'show_in_rest'          => true,
			'capabilities'          => array(
				// You need to be able to edit posts, in order to read blocks in their raw form.
				'read'                   => 'edit_posts',
				// You need to be able to publish posts, in order to create blocks.
				'create_posts'           => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
			),
			'map_meta_cap'          => true,
			'supports'              => array(
				'title',
				'editor',
			),
		)
	);
}
add_action( 'init', 'create_wp_area_post_type' );


