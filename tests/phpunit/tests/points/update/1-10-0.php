<?php

/**
 * A test case for the points component update to 1.10.0.
 *
 * @package WordPoints\Tests
 * @since 1.10.0
 */

/**
 * Test that the points component updates to 1.10.0 properly.
 *
 * @since 1.10.0
 *
 * @group points
 * @group update
 */
class WordPoints_Points_1_10_0_Update_Test extends WordPoints_Points_UnitTestCase {

	/**
	 * @since 1.10.0
	 */
	protected $previous_version = '1.9.0';

	/**
	 * Test that unused 'post_title' log meta deleted on update.
	 *
	 * @since 1.10.0
	 *
	 * @covers WordPoints_Points_Un_Installer::update_single_to_1_10_0
	 * @covers WordPoints_Points_Un_Installer::_1_10_0_delete_post_title_points_log_meta
	 */
	public function test_post_title_points_log_meta_deleted() {

		if ( is_multisite() ) {
			$this->markTestSkipped( 'Multisite must be disabled.' );
		}

		$this->factory->wordpoints_points_log->create(
			array(
				'log_type' => 'post_delete',
				'log_meta' => array( 'post_title' => 'Test Post' ),
			)
		);

		$query = new WordPoints_Points_Logs_Query(
			array(
				'log_type'   => 'post_delete',
				'meta_query' => array(
					array( 'key' => 'post_title', 'compare' => 'EXISTS' ),
				),
			)
		);

		$this->assertCount( 1, $query->get() );

		$this->update_component();

		$this->assertCount( 0, $query->get() );
	}

	/**
	 * Test that unused 'post_title' log meta deleted on update.
	 *
	 * @since 1.10.0
	 *
	 * @covers WordPoints_Points_Un_Installer::update_network_to_1_10_0
	 * @covers WordPoints_Points_Un_Installer::_1_10_0_delete_post_title_points_log_meta
	 */
	public function test_post_title_points_log_meta_deleted_network() {

		if ( ! is_wordpoints_network_active() ) {
			$this->markTestSkipped( 'WordPoints must be network-active.' );
		}

		$this->factory->wordpoints_points_log->create(
			array(
				'log_type' => 'post_delete',
				'log_meta' => array( 'post_title' => 'Test Post' ),
			)
		);

		$blog_id = $this->factory->blog->create();

		switch_to_blog( $blog_id );
		$this->factory->wordpoints_points_log->create(
			array(
				'log_type' => 'post_delete',
				'log_meta' => array( 'post_title' => 'Test Post' ),
			)
		);
		restore_current_blog();

		$query = new WordPoints_Points_Logs_Query(
			array(
				'log_type'   => 'post_delete',
				'blog_id'    => false,
				'meta_query' => array(
					array( 'key' => 'post_title', 'compare' => 'EXISTS' ),
				),
			)
		);

		$this->assertCount( 2, $query->get() );

		$this->update_component();

		$this->assertCount( 0, $query->get() );
	}
}

// EOF
