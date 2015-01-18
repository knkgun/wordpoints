<?php

/**
 * Class for un/installing the points component.
 *
 * @package WordPoints
 * @since 1.8.0
 */

/**
 * Un/installs the points component.
 *
 * @since 1.8.0
 */
class WordPoints_Points_Un_Installer extends WordPoints_Un_Installer_Base {

	//
	// Protected Vars.
	//

	/**
	 * @since 1.8.0
	 */
	protected $option_prefix = 'wordpoints_points_';

	/**
	 * @since 1.8.0
	 */
	protected $updates = array(
		'1.2.0' => array( 'single' => true, /*     -     */ 'network' => true ),
		'1.4.0' => array( 'single' => true, 'site' => true, 'network' => true ),
		'1.5.0' => array( /*      -      */ 'site' => true  /*      -      */ ),
		'1.5.1' => array( 'single' => true, /*     -     */ 'network' => true ),
		'1.8.0' => array( /*      -      */ 'site' => true  /*      -      */ ),
		'1.9.0' => array( 'single' => true, 'site' => true, 'network' => true ),
	);

	/**
	 * The points types the user has created.
	 *
	 * Used during uninstall to keep from having to retreive them when looping over
	 * sites on multisite.
	 *
	 * @since 1.8.0
	 *
	 * @type array $points_types
	 */
	protected $points_types;

	/**
	 * The component's capabilities.
	 *
	 * Used to hold the list of capabilities during install and uninstall, so that
	 * they don't have to be retrieved all over again for each site (if multisite).
	 *
	 * @since 1.8.0
	 *
	 * @type array $custom_caps
	 */
	protected $custom_caps;

	/**
	 * The component's capabilities (keys only).
	 *
	 * Used to hold the list of capabilities during install and uninstall, so that
	 * they don't have to be retrieved all over again for each site (if multisite).
	 *
	 * @since 1.8.0
	 *
	 * @type array $custom_caps_keys
	 */
	protected $custom_caps_keys;

	/**
	 * The network mode of the points hooks before the updates began.
	 *
	 * Only set if updating from pre-1.4.0.
	 *
	 * @since 1.8.0
	 *
	 * @type bool $points_hooks_network_mode
	 */
	protected $points_hooks_network_mode;

	/**
	 * @since 1.8.0
	 */
	public function before_install() {

		$this->custom_caps = wordpoints_points_get_custom_caps();
		$this->custom_caps_keys = array_keys( $this->custom_caps );
	}

	/**
	 * @since 1.8.0
	 */
	protected function before_uninstall() {

		$this->points_types = wordpoints_get_points_types();
		$this->custom_caps_keys = array_keys( wordpoints_points_get_custom_caps() );
	}

	/**
	 * @since 1.8.0
	 */
	protected function before_update() {

		if ( 1 === version_compare( '1.4.0', $this->updating_from ) ) {
			add_filter( 'wordpoints_points_hook_update_callback', array( $this, '_1_4_0_clean_hook_settings' ), 10, 4 );
		}

		if ( 1 === version_compare( '1.5.0', $this->updating_from ) ) {

			if ( ! $this->network_wide ) {
				unset( $this->updates['1_5_0'] );
			} else {
				$this->custom_caps = wordpoints_points_get_custom_caps();
			}
		}

		if ( $this->network_wide ) {
			unset( $this->updates['1_8_0'] );
		}

		if ( 1 === version_compare( '1.9.0', $this->updating_from ) ) {

			// If we're updating to 1.4.0, we initialize the hooks early, because
			// we use them during the update.
			remove_action( 'wordpoints_modules_loaded', array( 'WordPoints_Points_Hooks', 'initialize_hooks' ) );

			WordPoints_Points_Hooks::register(
				'WordPoints_Comment_Removed_Points_Hook'
			);

			WordPoints_Points_Hooks::initialize_hooks();

			// Default to network mode off during the tests, but save the current
			// mode so we can restore it afterward.
			$this->points_hooks_network_mode = WordPoints_Points_Hooks::get_network_mode();
			WordPoints_Points_Hooks::set_network_mode( false );
		}
	}

	/**
	 * @since 1.8.0
	 */
	protected function after_update() {

		if ( isset( $this->points_hooks_network_mode ) ) {

			WordPoints_Points_Hooks::set_network_mode( $this->points_hooks_network_mode );

			remove_filter( 'wordpoints_points_hook_update_callback', array( $this, '_1_4_0_clean_hook_settings' ), 10 );
		}
	}

	/**
	 * @since 1.8.0
	 */
	protected function install_network() {

		$this->install_points_main();
	}

	/**
	 * @since 1.8.0
	 */
	protected function install_site() {

		/*
		 * Regenerate the custom caps every time on multisite, because they depend on
		 * network activation status.
		 */
		wordpoints_remove_custom_caps( $this->custom_caps_keys );
		wordpoints_add_custom_caps( $this->custom_caps );
	}

	/**
	 * @since 1.8.0
	 */
	protected function install_single() {

		wordpoints_add_custom_caps( $this->custom_caps );
		add_option( 'wordpoints_default_points_type', '' );

		$this->install_points_main();
	}

	/**
	 * Install the main portion of the points component.
	 *
	 * @since 1.8.0
	 */
	protected function install_points_main() {

		dbDelta( wordpoints_points_get_db_schema() );

		$this->set_component_version( 'points', WORDPOINTS_VERSION );
	}

	/**
	 * @since 1.8.0
	 */
	protected function load_dependencies() {

		require_once WORDPOINTS_DIR . '/components/points/includes/functions.php';
		require_once WORDPOINTS_DIR . '/components/points/includes/constants.php';
	}

	/**
	 * @since 1.8.0
	 */
	protected function uninstall_network() {

		$this->uninstall_points_main();

		delete_site_option( 'wordpoints_points_types' );
		delete_site_option( 'wordpoints_default_points_type' );
		delete_site_option( 'wordpoints_points_types_hooks' );
	}

	/**
	 * @since 1.8.0
	 */
	protected function uninstall_site() {

		global $wpdb;

		foreach ( $this->points_types as $slug => $settings ) {

			delete_metadata( 'comment', 0, "wordpoints_last_status-{$slug}", '', true );

			$prefix = $wpdb->get_blog_prefix();
			delete_metadata( 'user', 0, $prefix . "wordpoints_points-{$slug}", '', true );
			delete_metadata( 'user', 0, $prefix . 'wordpoints_points_period_start', '', true );
		}

		$this->uninstall_points_single();
	}

	/**
	 * @since 1.8.0
	 */
	protected function uninstall_single() {

		$this->uninstall_points_main();
		$this->uninstall_points_single();
	}

	/**
	 * Uninstall the main portion of the points component.
	 *
	 * @since 1.8.0
	 */
	protected function uninstall_points_main() {

		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->wordpoints_points_logs . '`' );
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->wordpoints_points_log_meta . '`' );

		foreach ( $this->points_types as $slug => $settings ) {

			delete_metadata( 'user', 0, "wordpoints_points-{$slug}", '', true );
		}

		delete_metadata( 'user', 0, 'wordpoints_points_period_start', '', true );
	}

	/**
	 * Uninstall the points component from a single site/site on a network.
	 *
	 * @since 1.8.0
	 */
	protected function uninstall_points_single() {

		delete_option( 'wordpoints_points_types' );
		delete_option( 'wordpoints_default_points_type' );
		delete_option( 'wordpoints_points_types_hooks' );

		delete_option( 'wordpoints_hook-wordpoints_registration_points_hook' );
		delete_option( 'wordpoints_hook-wordpoints_post_points_hook' );
		delete_option( 'wordpoints_hook-wordpoints_comment_points_hook' );
		delete_option( 'wordpoints_hook-wordpoints_periodic_points_hook' );

		delete_option( 'widget_wordpoints_points_logs_widget' );
		delete_option( 'widget_wordpoints_top_users_widget' );
		delete_option( 'widget_wordpoints_points_widget' );

		wordpoints_remove_custom_caps( $this->custom_caps_keys );
	}

	/**
	 * Update the plugin to 1.2.0.
	 *
	 * @since 1.8.0
	 */
	protected function update_network_to_1_2_0() {

		$this->_1_2_0_remove_points_logs_for_deleted_users();
		$this->_1_2_0_regenerate_points_logs_for_deleted_posts();
		$this->_1_2_0_regenerate_points_logs_for_deleted_comments();
	}

	/**
	 * Update the plugin to 1.2.0.
	 *
	 * @since 1.8.0
	 */
	protected function update_single_to_1_2_0() {
		$this->update_network_to_1_2_0();
	}

	/**
	 * Remove the points logs of users who have been deleted.
	 *
	 * @since 1.8.0
	 */
	protected function _1_2_0_remove_points_logs_for_deleted_users() {

		global $wpdb;

		$log_ids = $wpdb->get_col(
			"
				SELECT wppl.id
				FROM {$wpdb->wordpoints_points_logs} AS wppl
				LEFT JOIN {$wpdb->users} as u
					ON wppl.user_id = u.ID
				WHERE u.ID IS NULL
			"
		);

		if ( $log_ids && is_array( $log_ids ) ) {

			$wpdb->query(
				"
					DELETE
					FROM {$wpdb->wordpoints_points_logs}
					WHERE `id` IN (" . implode( ',', array_map( 'absint', $log_ids ) ) . ')
				'
			);

			foreach ( $log_ids as $log_id ) {
				wordpoints_points_log_delete_all_metadata( $log_id );
			}
		}
	}

	/**
	 * Regenerate the points logs for deleted posts.
	 *
	 * @since 1.8.0
	 */
	protected function _1_2_0_regenerate_points_logs_for_deleted_posts() {

		global $wpdb;

		$post_ids = $wpdb->get_col(
			"
				SELECT wpplm.meta_value
				FROM {$wpdb->wordpoints_points_log_meta} AS wpplm
				LEFT JOIN {$wpdb->posts} AS p
					ON p.ID = wpplm.meta_value
				WHERE p.ID IS NULL
					AND wpplm.meta_key = 'post_id'
			"
		);

		$hook = WordPoints_Points_Hooks::get_handler_by_id_base( 'wordpoints_post_points_hook' );

		if ( $post_ids && is_array( $post_ids ) && $hook ) {
			foreach ( $post_ids AS $post_id ) {
				$hook->clean_logs_on_post_deletion( $post_id );
			}
		}
	}

	/**
	 * Regenerate the points logs for deleted comments.
	 *
	 * @since 1.8.0
	 */
	protected function _1_2_0_regenerate_points_logs_for_deleted_comments() {

		global $wpdb;

		$comment_ids = $wpdb->get_col(
			"
				SELECT wpplm.meta_value
				FROM {$wpdb->wordpoints_points_log_meta} AS wpplm
				LEFT JOIN {$wpdb->comments} AS c
					ON c.comment_ID = wpplm.meta_value
				WHERE c.comment_ID IS NULL
					AND wpplm.meta_key = 'comment_id'
			"
		);

		$hook = WordPoints_Points_Hooks::get_handler_by_id_base( 'wordpoints_comment_points_hook' );

		if ( $comment_ids && is_array( $comment_ids ) && $hook ) {
			foreach ( $comment_ids AS $comment_id ) {
				$hook->clean_logs_on_comment_deletion( $comment_id );
			}
		}
	}

	/**
	 * Update a network to 1.4.0.
	 *
	 * @since 1.8.0
	 */
	protected function update_network_to_1_4_0() {

		if ( $this->network_wide ) {

			// Split the network-wide points hooks.
			$network_mode = WordPoints_Points_Hooks::get_network_mode();
			WordPoints_Points_Hooks::set_network_mode( true );
			$this->_1_4_0_split_post_hooks();
			$this->_1_4_0_split_comment_hooks();
			WordPoints_Points_Hooks::set_network_mode( $network_mode );
		}
	}

	/**
	 * Update a site on the network to 1.4.0.
	 *
	 * @since 1.8.0
	 */
	protected function update_site_to_1_4_0() {

		$this->_1_4_0_split_post_hooks();
		$this->_1_4_0_split_comment_hooks();
		$this->_1_4_0_clean_points_logs();
	}

	/**
	 * Update a single site to 1.4.0.
	 *
	 * @since 1.8.0
	 */
	protected function update_single_to_1_4_0() {

		$this->update_site_to_1_4_0();
	}

	/**
	 * Split the post delete points hooks from the post points hooks.
	 *
	 * @since 1.8.0
	 */
	protected function _1_4_0_split_post_hooks() {

		$this->_1_4_0_split_points_hooks(
			'wordpoints_post_points_hook'
			, 'wordpoints_post_delete_points_hook'
			, 'publish'
			, 'trash'
		);
	}

	/**
	 * Split the commend removed points hooks from the comment points hooks.
	 *
	 * @since 1.8.0
	 */
	protected function _1_4_0_split_comment_hooks() {

		$this->_1_4_0_split_points_hooks(
			'wordpoints_comment_points_hook'
			, 'wordpoints_comment_removed_points_hook'
			, 'approve'
			, 'disapprove'
		);
	}

	/**
	 * Split a set of points hooks.
	 *
	 * @since 1.8.0
	 *
	 * @param string $hook      The slug of the hook type to split.
	 * @param string $new_hook  The slug of the new hook that this one is being split into.
	 * @param string $key       The settings key for the hook that holds the points.
	 * @param string $split_key The settings key for points that is being split.
	 */
	protected function _1_4_0_split_points_hooks( $hook, $new_hook, $key, $split_key ) {

		if ( WordPoints_Points_Hooks::get_network_mode() ) {
			$hook_type = 'network';
			$network_ = 'network_';
		} else {
			$hook_type = 'standard';
			$network_ = '';
		}

		$new_hook = WordPoints_Points_Hooks::get_handler_by_id_base( $new_hook );
		$hook = WordPoints_Points_Hooks::get_handler_by_id_base( $hook );

		$points_types_hooks = WordPoints_Points_Hooks::get_points_types_hooks();
		$instances = $hook->get_instances( $hook_type );

		// Loop through all of the post hook instances.
		foreach ( $instances as $number => $settings ) {

			// Don't split the hook if it is just a placeholder, or it's already split.
			if ( 0 === (int) $number || ! isset( $settings[ $key ], $settings[ $split_key ] ) ) {
				continue;
			}

			if ( ! isset( $settings['post_type'] ) ) {
				$settings['post_type'] = 'ALL';
			}

			// If the trash points are set, create a post delete points hook instead.
			if ( isset( $settings[ $split_key ] ) && wordpoints_posint( $settings[ $split_key ] ) ) {

				$new_hook->update_callback(
					array(
						'points'    => $settings[ $split_key ],
						'post_type' => $settings['post_type'],
					)
					, $new_hook->next_hook_id_number()
				);

				// Make sure the correct points type is retrieved for network hooks.
				$points_type = $hook->points_type( $network_ . $number );

				// Add this instance to the points-types-hooks list.
				$points_types_hooks[ $points_type ][] = $new_hook->get_id( $number );
			}

			// If the publish points are set, update the settings of the hook.
			if ( isset( $settings[ $key ] ) && wordpoints_posint( $settings[ $key ] ) ) {

				$settings['points'] = $settings[ $key ];

				$hook->update_callback( $settings, $number );

			} else {

				// If not, delete this instance.
				$hook->delete_callback( $hook->get_id( $number ) );
			}
		}

		WordPoints_Points_Hooks::save_points_types_hooks( $points_types_hooks );
	}

	/**
	 * Clean the settings for the post and comment points hooks.
	 *
	 * Removes old and no longer used settings from the comment and post points hooks.
	 *
	 * @since 1.8.0
	 *
	 * @filter wordpoints_points_hook_update_callback Added during the update to 1.4.0.
	 *
	 * @param array                  $instance     The settings for the instance.
	 * @param array                  $new_instance The new settings for the instance.
	 * @param array                  $old_instance The old settings for the instance.
	 * @param WordPoints_Points_Hook $hook         The hook object.
	 *
	 * @return array The filtered instance settings.
	 */
	public function _1_4_0_clean_hook_settings( $instance, $new_instance, $old_instance, $hook ) {

		if ( $hook instanceof WordPoints_Post_Points_Hook ) {
			unset( $instance['trash'], $instance['publish'] );
		} elseif ( $hook instanceof WordPoints_Comment_Points_Hook ) {
			unset( $instance['approve'], $instance['disapprove'] );
		}

		return $instance;
	}

	/**
	 * Clean the comment_approve points logs for posts that have been deleted.
	 *
	 * @since 1.8.0
	 */
	protected function _1_4_0_clean_points_logs() {

		global $wpdb;

		$post_ids = $wpdb->get_col(
			"
				SELECT wpplm.meta_value
				FROM {$wpdb->wordpoints_points_log_meta} AS wpplm
				LEFT JOIN {$wpdb->posts} AS p
					ON p.ID = wpplm.meta_value
				LEFT JOIN {$wpdb->wordpoints_points_logs} As wppl
					ON wppl.id = wpplm.log_id
				WHERE p.ID IS NULL
					AND wpplm.meta_key = 'post_id'
					AND wppl.log_type = 'comment_approve'
			"
		);

		$hook = WordPoints_Points_Hooks::get_handler_by_id_base( 'wordpoints_comment_points_hook' );

		if ( $post_ids && is_array( $post_ids ) && $hook ) {
			foreach ( $post_ids AS $post_id ) {
				$hook->clean_logs_on_post_deletion( $post_id );
			}
		}
	}

	/**
	 * Update a site on the network to 1.5.0.
	 *
	 * Prior to 1.5.0, capabilities weren't automatically added to new sites when
	 * WordPoints was in network mode.
	 *
	 * @since 1.8.0
	 */
	protected function update_site_to_1_5_0() {

		wordpoints_add_custom_caps( $this->custom_caps );
	}

	/**
	 * Update the network to 1.5.1.
	 *
	 * @since 1.8.0
	 */
	protected function update_network_to_1_5_1() {

		global $wpdb;

		if ( empty( $wpdb->charset ) ) {
			return;
		}

		$charset_collate = " CHARACTER SET {$wpdb->charset}";

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		$wpdb->query( "ALTER TABLE {$wpdb->wordpoints_points_logs} CONVERT TO {$charset_collate}" );
		$wpdb->query( "ALTER TABLE {$wpdb->wordpoints_points_log_meta} CONVERT TO {$charset_collate}" );
	}

	/**
	 * Update a single site to 1.5.1.
	 *
	 * @since 1.8.0
	 */
	protected function update_single_to_1_5_1() {
		$this->update_network_to_1_5_1();
	}

	/**
	 * Update a site to 1.8.0.
	 *
	 * @since 1.8.0
	 */
	protected function update_site_to_1_8_0() {
		$this->add_installed_site_id();
	}

	/**
	 * Update a network to 1.9.0.
	 *
	 * @since 1.9.0
	 */
	protected function update_network_to_1_9_0() {

		if ( $this->network_wide ) {

			// Combine the network-wide points hooks.
			$network_mode = WordPoints_Points_Hooks::get_network_mode();
			WordPoints_Points_Hooks::set_network_mode( true );
			$this->_1_9_0_combine_comment_hooks();
			WordPoints_Points_Hooks::set_network_mode( $network_mode );
		}
	}

	/**
	 * Update a site to 1.9.0.
	 *
	 * @since 1.9.0
	 */
	protected function update_site_to_1_9_0() {
		$this->_1_9_0_combine_comment_hooks();
	}

	/**
	 * Update a single site to 1.9.0.
	 *
	 * @since 1.9.0
	 */
	protected function update_single_to_1_9_0() {
		$this->_1_9_0_combine_comment_hooks();
	}

	/**
	 * Combine any Comment/Comment Removed hook instance pairs.
	 *
	 * @since 1.9.0
	 */
	protected function _1_9_0_combine_comment_hooks() {

		$comment_hook = WordPoints_Points_Hooks::get_handler_by_id_base(
			'wordpoints_comment_points_hook'
		);
		$comment_removed_hook = WordPoints_Points_Hooks::get_handler_by_id_base(
			'wordpoints_comment_removed_points_hook'
		);

		if ( WordPoints_Points_Hooks::get_network_mode() ) {
			$hook_type = 'network';
			$network_ = 'network_';
		} else {
			$hook_type = 'standard';
			$network_ = '';
		}

		$comment_instances = $comment_hook->get_instances( $hook_type );
		$comment_removed_instances = $comment_removed_hook->get_instances( $hook_type );

		$defaults = array( 'points' => 10, 'post_type' => 'ALL' );

		// Get the Comment hooks into an array that is indexed by post type and the
		// number of points. This allows us to easily check for any counterparts when
		// we loop through the Comment Removed hooks below. It is even safe if a user
		// is doing something crazy like multiple hooks for the same post type.
		$comment_instances_indexed = array();

		foreach ( $comment_instances as $number => $instance ) {

			$instance = array_merge( $defaults, $instance );

			$comment_instances_indexed
				[ $comment_hook->points_type( $network_ . $number ) ]
				[ $instance['post_type'] ]
				[ $instance['points'] ]
				[] = $number;
		}

		foreach ( $comment_removed_instances as $number => $instance ) {

			$instance = array_merge( $defaults, $instance );

			$points_type = $comment_removed_hook->points_type( $network_ . $number );

			// We use empty() instead of isset() because array_pop() below may leave
			// us with an empty array as the value.
			if ( empty( $comment_instances_indexed[ $points_type ][ $instance['post_type'] ][ $instance['points'] ] ) ) {
				continue;
			}

			$comment_instance_number = array_pop(
				$comment_instances_indexed[ $points_type ][ $instance['post_type'] ][ $instance['points'] ]
			);

			// We need to unset this instance from the list of Comment instances. It
			// is expected for it to be automatically reversed, and that is the
			// default setting. If we don't unset it here it will get auto-reversal
			// turned off below, which isn't what we want.
			unset( $comment_instances[ $comment_instance_number ] );

			// Now we can just delete this Comment Removed instance.
			$comment_removed_hook->delete_callback(
				$comment_removed_hook->get_id( $number )
			);
		}

		// Any Comment hooks left in the array are not paired with a Comment Removed
		// hook, and aren't expected to auto-reverse, so we need to turn their auto-
		// reversal setting off.
		if ( ! empty( $comment_instances ) ) {

			foreach ( $comment_instances as $number => $instance ) {
				$instance['auto_reverse'] = 0;
				$comment_hook->update_callback( $instance, $number );
			}

			// We add a flag to the database so we'll know to enable legacy features.
			update_site_option(
				'wordpoints_comment_hook_legacy'
				, true
			);
		}

		// Now we check if there are any unpaired Comment Removed hooks. If there are
		// we'll set this flag in the database that will keep some legacy features
		// enabled.
		if ( $comment_removed_hook->get_instances( $hook_type ) ) {
			update_site_option(
				'wordpoints_comment_removed_hook_legacy'
				, true
			);
		}
	}
}

return 'WordPoints_Points_Un_Installer';

// EOF
