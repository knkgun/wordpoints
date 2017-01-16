<?php

/**
 * WordPoints widgets.
 *
 * @package WordPoints\Points
 * @since 1.0.0
 */

/**
 * WordPoints Top Users Widget.
 *
 * Note that the class name is WordPoints_Top_Users_Points_Widget, but the ID base
 * for instances of this widget is WordPoints_Top_Users_Widget. This wasn't intended
 * but that's how it is staying for now.
 *
 * @since 1.0.0
 *
 * @see WordPoints_Points_Widget Parent class.
 */
class WordPoints_Top_Users_Points_Widget extends WordPoints_Points_Widget {

	/**
	 * Initialize the widget.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		parent::__construct(
			'WordPoints_Top_Users_Widget'
			, _x( 'WordPoints Top Users', 'widget name', 'wordpoints' )
			, array(
				'description' => __( 'Showcase the users with the most points.', 'wordpoints' ),
				'wordpoints_hook_slug' => 'top_users',
			)
		);

		$this->defaults = array(
			'title'       => _x( 'Top Users', 'widget title', 'wordpoints' ),
			'points_type' => wordpoints_get_default_points_type(),
			'num_users'   => 3,
		);
	}

	/**
	 * @since 1.9.0
	 */
	protected function verify_settings( $instance ) {

		if ( empty( $instance['num_users'] ) ) {
			$instance['num_users'] = $this->defaults['num_users'];
		}

		return parent::verify_settings( $instance );
	}

	/**
	 * @since 1.9.0
	 */
	protected function widget_body( $instance ) {

		wordpoints_points_show_top_users(
			$instance['num_users']
			, $instance['points_type']
			, 'widget'
		);
	}

	/**
	 * Update widget settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $new_instance The new settings for this instance.
	 * @param array $old_instance The old settings for this instance.
	 *
	 * @return array The updated settings for the widget instance.
	 */
	public function update( $new_instance, $old_instance ) {

		parent::update( $new_instance, $old_instance );

		if ( ! wordpoints_posint( $this->instance['num_users'] ) ) {
			$this->instance['num_users'] = $this->defaults['num_users'];
		}

		return $this->instance;
	}

	/**
	 * @since 1.0.0
	 */
	public function form( $instance ) {

		parent::form( $instance );

		if ( ! wordpoints_posint( $this->instance['num_users'] ) ) {
			$this->instance['num_users'] = $this->defaults['num_users'];
		}

		?>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'num_users' ) ); ?>"><?php esc_html_e( 'Number of top users to show', 'wordpoints' ); ?></label>
			<input type="number" min="1" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'num_users' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'num_users' ) ); ?>" value="<?php echo absint( $this->instance['num_users'] ); ?>" />
		</p>

		<?php

		return true;
	}
}

/**
 * Recent points logs widget.
 *
 * @since 1.0.0
 */
class WordPoints_Points_Logs_Widget extends WordPoints_Points_Widget {

	/**
	 * Initialize the widget.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		parent::__construct(
			'WordPoints_Points_Logs_Widget'
			, _x( 'Points Logs', 'widget name', 'wordpoints' )
			, array(
				'description' => __( 'Display the latest points activity.', 'wordpoints' ),
				'wordpoints_hook_slug' => 'points_logs',
			)
		);

		$this->defaults = array(
			'title'       => _x( 'Points Logs', 'widget title', 'wordpoints' ),
			'number_logs' => 10,
			'points_type' => wordpoints_get_default_points_type(),
		);
	}

	/**
	 * @since 1.9.0
	 */
	protected function verify_settings( $instance ) {

		if ( ! wordpoints_posint( $instance['number_logs'] ) ) {
			$instance['number_logs'] = $this->defaults['number_logs'];
		}

		return parent::verify_settings( $instance );
	}

	/**
	 * @since 1.9.0
	 */
	public function widget_body( $instance ) {

		$query_args = wordpoints_get_points_logs_query_args( $instance['points_type'] );

		$query_args['limit'] = $instance['number_logs'];

		$logs_query = new WordPoints_Points_Logs_Query( $query_args );
		$logs_query->prime_cache();

		wordpoints_show_points_logs( $logs_query, array( 'paginate' => false, 'searchable' => false ) );
	}

	/**
	 * Update widget settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $new_instance The new settings for this instance.
	 * @param array $old_instance The old settings for this instance.
	 *
	 * @return array The updated settings for the widget instance.
	 */
	public function update( $new_instance, $old_instance ) {

		parent::update( $new_instance, $old_instance );

		if ( ! wordpoints_posint( $this->instance['number_logs'] ) ) {
			$this->instance['number_logs'] = $this->defaults['number_logs'];
		}

		return $this->instance;
	}

	/**
	 * @since 1.0.0
	 */
	public function form( $instance ) {

		parent::form( $instance );

		if ( ! wordpoints_posint( $this->instance['number_logs'] ) ) {
			$this->instance['number_logs'] = $this->defaults['number_logs'];
		}

		?>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number_logs' ) ); ?>"><?php esc_html_e( 'Number of log entries to display', 'wordpoints' ); ?></label>
			<input type="number" min="1" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'number_logs' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number_logs' ) ); ?>" value="<?php echo absint( $this->instance['number_logs'] ); ?>" />
		</p>

		<?php

		return true;
	}
}

/**
 * My Points widget.
 *
 * @since 1.0.0
 * @deprecated 2.3.0 Use WordPoints_Points_Widget_User_Points instead.
 */
class WordPoints_My_Points_Widget extends WordPoints_Points_Widget_User_Points {

	/**
	 * @since 1.0.0
	 */
	public function __construct() {

		_deprecated_function(
			__METHOD__
			, '2.3.0'
			, 'WordPoints_Points_Widget_User_Points::__construct'
		);

		parent::__construct();
	}
}

// EOF
