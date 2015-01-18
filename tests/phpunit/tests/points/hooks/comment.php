<?php

/**
 * A test case for the comment points hook.
 *
 * @package WordPoints\Tests
 * @since 1.3.0
 */

/**
 * Test that the comment points hook functions as expected.
 *
 * Since 1.0.0 it was a part of WordPoints_Included_Points_Hooks_Test.
 *
 * @since 1.3.0
 *
 * @group points
 * @group points_hooks
 */
class WordPoints_Comment_Points_Hook_Test extends WordPoints_Points_UnitTestCase {

	/**
	 * @since 1.9.0
	 */
	public function tearDown() {

		$hook = WordPoints_Points_Hooks::get_handler_by_id_base(
			'wordpoints_comment_points_hook'
		);

		$hook->set_option( 'disable_auto_reverse_label', null );

		parent::tearDown();
	}

	/**
	 * Test that points are awarded as expected.
	 *
	 * Since 1.3.0 it was called test_points_awarded_removed().
	 *
	 * @since 1.4.0
	 */
	public function test_points_awarded() {

		wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 10 )
		);

		$user_id = $this->factory->user->create();

		wordpoints_set_points( $user_id, 100, 'points', 'test' );
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );

		$post_id = $this->factory->post->create(
			array( 'post_author' => $user_id )
		);

		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $post_id,
			)
		);

		$this->assertEquals( 110, wordpoints_get_points( $user_id, 'points' ) );

		// Points should not be awarded twice in a row.
		do_action( 'transition_comment_status', 'approve', 'hold', get_comment( $comment_id ) );

		$this->assertEquals( 110, wordpoints_get_points( $user_id, 'points' ) );

		// Test that points are awarded on transition from hold.
		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $post_id,
				'comment_status'  => 'hold',
			)
		);

		wp_set_comment_status( $comment_id, 'approve' );
		$this->assertEquals( 120, wordpoints_get_points( $user_id, 'points' ) );

		// Test that points are awarded on transition from spam.
		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $post_id,
				'comment_status'  => 'spam',
			)
		);

		wp_set_comment_status( $comment_id, 'approve' );
		$this->assertEquals( 130, wordpoints_get_points( $user_id, 'points' ) );

		// Test that points are awarded on transition from trash.
		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $post_id,
				'comment_status'  => 'trash',
			)
		);

		wp_set_comment_status( $comment_id, 'approve' );
		$this->assertEquals( 140, wordpoints_get_points( $user_id, 'points' ) );

	} // public function test_points_awarded()

	/**
	 * Test automatic reversal of the hook when the comment's status is toggled.
	 *
	 * @since 1.9.0
	 */
	public function test_points_auto_reversal() {

		$hook = wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 10 )
		);
		$this->assertInstanceOf( 'WordPoints_Comment_Points_Hook', $hook );

		$user_id = $this->factory->user->create();

		wordpoints_set_points( $user_id, 100, 'points', 'test' );
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );

		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $this->factory->post->create(
					array( 'post_author' => $user_id )
				),
			)
		);

		$this->assertEquals( 110, wordpoints_get_points( $user_id, 'points' ) );

		// Test that status transitions award/remove points correctly.
		wp_set_comment_status( $comment_id, 'hold' );
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );

		wp_set_comment_status( $comment_id, 'approve' );
		$this->assertEquals( 110, wordpoints_get_points( $user_id, 'points' ) );

		wp_set_comment_status( $comment_id, 'spam' );
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );

		wp_set_comment_status( $comment_id, 'approve' );
		$this->assertEquals( 110, wordpoints_get_points( $user_id, 'points' ) );

		wp_set_comment_status( $comment_id, 'trash' );
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );

		wp_set_comment_status( $comment_id, 'approve' );
		$this->assertEquals( 110, wordpoints_get_points( $user_id, 'points' ) );

	} // public function test_points_auto_reversal()

	/**
	 * Test that the auto-reversal setting of the instance for the post's type has priority.
	 *
	 * @since 1.9.0
	 */
	public function test_auto_reversal_post_type_priority_off() {

		// Create a hook for comments on pages.
		$page_hook = wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 10, 'post_type' => 'page' )
		);
		$this->assertInstanceOf( 'WordPoints_Comment_Points_Hook', $page_hook );

		// Create another for the 'post' post type only.
		$post_hook = wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 15, 'post_type' => 'post', 'auto_reverse' => 0 )
		);
		$this->assertInstanceOf( 'WordPoints_Comment_Points_Hook', $post_hook );

		$post_hook->set_option( 'disable_auto_reverse_label', true );

		$user_id = $this->factory->user->create();

		wordpoints_set_points( $user_id, 100, 'points', 'test' );
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );

		// Create a comment.
		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $this->factory->post->create(
					array( 'post_author' => $user_id, 'post_type' => 'post' )
				),
			)
		);

		// Only the post hook will award the user.
		$this->assertEquals( 115, wordpoints_get_points( $user_id, 'points' ) );

		wp_set_comment_status( $comment_id, 'spam' );

		// No reversals will take place.
		$this->assertEquals( 115, wordpoints_get_points( $user_id, 'points' ) );
	}

	/**
	 * Test that auto-reversal can only be disabled on legacy installs.
	 *
	 * @since 1.9.0
	 */
	public function test_auto_reversal_cannot_be_disabled() {

		// Create a hook for comments on posts.
		$post_hook = wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 15, 'post_type' => 'post', 'auto_reverse' => 0 )
		);
		$this->assertInstanceOf( 'WordPoints_Comment_Points_Hook', $post_hook );

		$user_id = $this->factory->user->create();

		wordpoints_set_points( $user_id, 100, 'points', 'test' );
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );

		// Create a comment.
		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $this->factory->post->create(
					array( 'post_author' => $user_id, 'post_type' => 'post' )
				),
			)
		);

		$this->assertEquals( 115, wordpoints_get_points( $user_id, 'points' ) );

		wp_set_comment_status( $comment_id, 'spam' );

		// Reversal will still take place, even though it is turned off.
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );
	}

	/**
	 * Test that the auto-reversal setting of the instance for the post's type has priority.
	 *
	 * @since 1.9.0
	 */
	public function test_auto_reversal_post_type_priority_on() {

		// Create a hook for comments on pages.
		$page_hook = wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 10, 'post_type' => 'page', 'auto_reverse' => 0 )
		);
		$this->assertInstanceOf( 'WordPoints_Comment_Points_Hook', $page_hook );

		// Create another for the 'post' post type only.
		$post_hook = wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 15, 'post_type' => 'post' )
		);
		$this->assertInstanceOf( 'WordPoints_Comment_Points_Hook', $post_hook );

		$post_hook->set_option( 'disable_auto_reverse_label', true );

		$user_id = $this->factory->user->create();

		wordpoints_set_points( $user_id, 100, 'points', 'test' );
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );

		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $this->factory->post->create(
					array( 'post_author' => $user_id, 'post_type' => 'post' )
				),
			)
		);

		// Only the post hook will award the user.
		$this->assertEquals( 115, wordpoints_get_points( $user_id, 'points' ) );

		wp_set_comment_status( $comment_id, 'spam' );

		// Reversal of the post hook will take place.
		$this->assertEquals( 100, wordpoints_get_points( $user_id, 'points' ) );
	}

	/**
	 * Test that points are only awarded for the specified post type.
	 *
	 * @since 1.5.0
	 */
	public function test_points_only_awarded_for_specified_post_type() {

		wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 20, 'post_type' => 'post' )
		);

		$user_id = $this->factory->user->create();

		// Create a comment on a post.
		$this->factory->comment->create(
			array(
				'user_id' => $user_id,
				'comment_post_ID' => $this->factory->post->create(
					array( 'post_type' => 'post' )
				),
			)
		);

		// Test that points were awarded for the comment.
		$this->assertEquals( 20, wordpoints_get_points( $user_id, 'points' ) );

		// Now create a comment on a page.
		$this->factory->comment->create(
			array(
				'user_id' => $user_id,
				'comment_post_ID' => $this->factory->post->create(
					array( 'post_type' => 'page' )
				),
			)
		);

		// Test that no points were awarded for the comment.
		$this->assertEquals( 20, wordpoints_get_points( $user_id, 'points' ) );

	} // public function test_points_only_awarded_for_specified_post_type()

	/**
	 * Test that the logs are cleaned properly when a comment is deleted.
	 *
	 * @since 1.4.0
	 */
	public function test_logs_cleaned_on_comment_deletion() {

		wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 10 )
		);

		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create( array( 'post_author' => $user_id ) );
		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $post_id,
			)
		);

		wp_delete_comment( $comment_id, true );

		$query = new WordPoints_Points_Logs_Query(
			array(
				'log_type'   => 'comment_approve',
				'meta_key'   => 'comment_id',
				'meta_value' => $comment_id,
			)
		);

		$this->assertEquals( 0, $query->count() );

		$query = new WordPoints_Points_Logs_Query(
			array(
				'log_type'   => 'comment_approve',
				'meta_key'   => 'post_id',
				'meta_value' => $post_id,
			)
		);

		$this->assertEquals( 1, $query->count() );

		$log = $query->get( 'row' );

		$link = '<a href="' . get_permalink( $post_id ) . '">'
			. get_the_title( $post_id )
			. '</a>';

		$this->assertEquals(
			sprintf(
				_x( 'Comment on %s.', 'points log description', 'wordpoints' )
				, $link
			)
			, $log->text
		);
	}

	/**
	 * Test that logs are cleaned properly when a post is deleted.
	 *
	 * @since 1.4.0
	 */
	public function test_logs_cleaned_on_post_deletion() {

		wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 10 )
		);

		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create( array( 'post_author' => $user_id ) );
		$comment_id = $this->factory->comment->create(
			array(
				'user_id'         => $user_id,
				'comment_post_ID' => $post_id,
			)
		);

		wp_delete_comment( $comment_id, true );
		wp_delete_post( $post_id, true );

		$query = new WordPoints_Points_Logs_Query(
			array(
				'log_type'   => 'comment_approve',
				'meta_key'   => 'post_id',
				'meta_value' => $post_id,
			)
		);

		$this->assertEquals( 0, $query->count() );

		$query = new WordPoints_Points_Logs_Query(
			array( 'log_type' => 'comment_approve' )
		);
		$log = $query->get( 'row' );

		$this->assertEquals(
			_x( 'Comment', 'points log description', 'wordpoints' )
			, $log->text
		);
	}

	/**
	 * Test that logs are hidden for users who don't have the required capabilities.
	 *
	 * @since 1.3.0
	 */
	public function test_logs_hidden_for_insufficient_caps() {

		wordpointstests_add_points_hook(
			'wordpoints_comment_points_hook'
			, array( 'points' => 10 )
		);

		$post = $this->factory->post->create_and_get(
			array( 'post_author' => $this->factory->user->create() )
		);

		$this->factory->comment->create(
			array(
				'user_id'         => $this->factory->user->create(),
				'comment_post_ID' => $post->ID,
			)
		);

		// Make the post private.
		$post->post_status = 'private';
		wp_update_post( $post );

		wp_set_current_user( $this->factory->user->create() );

		// The log shouldn't be displayed.
		$document = new DOMDocument;
		$document->preserveWhiteSpace = false;
		$document->loadHTML(
			WordPoints_Shortcodes::do_shortcode(
				array( 'points_type' => 'points' )
				, null
				, 'wordpoints_points_logs'
			)
		);
		$xpath = new DOMXPath( $document );
		$this->assertEquals( 1, $xpath->query( '//tbody[. = ""]' )->length );
	}
}

// EOF
