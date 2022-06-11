<?php
/**
 * Class PostFilters
 *
 * @package View_All_Posts_Pages
 */

/**
 * Content-filter test case.
 */
class PostFilters extends WP_UnitTestCase {
	/**
	 * Text for each page of multipage post.
	 *
	 * @var array
	 */
	protected static $pages_content = array(
		1 => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec at neque sit amet massa pulvinar ullamcorper. Sed cursus, quam a tristique volutpat, diam justo cursus nunc, eu elementum sem orci ut ante. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Cras aliquet, diam sit amet tincidunt pulvinar, tortor neque accumsan dui, efficitur placerat justo nisl et justo. Pellentesque convallis dui nulla, vel finibus dui cursus quis. Sed semper nunc et euismod tristique. Aliquam tincidunt eget massa ac congue. Ut ipsum eros, dignissim ut eleifend eu, consectetur a eros. Proin in mattis dui.',
		2 => 'Sed sed sapien et lectus aliquam tempor. Duis consequat sapien scelerisque metus pulvinar aliquam. Pellentesque vestibulum id justo vel egestas. Nullam a metus sed risus blandit egestas. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed scelerisque ipsum ante, quis iaculis nibh suscipit eget. Nulla facilisi. Nulla at lacus at mauris sodales varius et nec massa. Etiam in nisi commodo, semper velit vitae, condimentum nisl. Ut quis mauris non ipsum feugiat vehicula pulvinar vitae dui. Nulla facilisi.',
		3 => 'Donec condimentum ipsum felis. Vivamus rhoncus mauris ac commodo hendrerit. Quisque ultrices nibh laoreet purus volutpat, ut congue purus suscipit. Sed eget lacus nec eros scelerisque volutpat. Fusce tristique quam eu risus porta, id vulputate dui maximus. Phasellus suscipit faucibus leo, imperdiet facilisis nisi. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed sit amet velit eu felis rhoncus placerat vel rutrum ante. Donec luctus urna quis nulla porta vestibulum. Vivamus ac lacinia odio.',
	);

	/**
	 * Page break trigger.
	 *
	 * @var string
	 */
	protected static $page_break = "<!--nextpage-->";

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Prepare data for tests.
	 */
	protected function setUp() {
		parent::setUp();

		static::$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Pagination Test',
				'post_status'  => 'publish',
				'post_date'    => '2019-01-01 00:01:01',
				'post_content' => implode( static::$page_break, static::$pages_content ),
			)
		);
	}

	/**
	 * Test retrieving page 1 content.
	 */
	public function test_view_page_1() {
		query_posts(
			array(
				'p' => static::$post_id,
			)
		);

		$this->assertTrue( have_posts() );

		while ( have_posts() ) {
			the_post();

			$this->assertEquals( static::$pages_content[1], get_the_content() );
		}
	}

	/**
	 * Test retrieving page 2 content.
	 */
	public function test_view_page_2() {
		query_posts(
			array(
				'p'    => static::$post_id,
				'page' => 2,
			)
		);

		$this->assertTrue( have_posts() );

		while ( have_posts() ) {
			the_post();

			$this->assertEquals( static::$pages_content[2], get_the_content() );
		}
	}

	/**
	 * Test retrieving page 3 content.
	 */
	public function test_view_page_3() {
		query_posts(
			array(
				'p'    => static::$post_id,
				'page' => 3,
			)
		);

		$this->assertTrue( have_posts() );

		while ( have_posts() ) {
			the_post();

			$this->assertEquals( static::$pages_content[3], get_the_content() );
		}
	}

	/**
	 * Test retrieving "view all" contents.
	 */
	public function test_view_all() {
		query_posts(
			array(
				'p'        => static::$post_id,
				'view-all' => true,
			)
		);

		$this->assertTrue( have_posts() );

		while ( have_posts() ) {
			the_post();

			$content = get_the_content();

			foreach ( static::$pages_content as $page => $text ) {
				$this->assertContains( $text, $content, "Failed to assert that content contained page {$page}." );
			}
		}
	}
}
