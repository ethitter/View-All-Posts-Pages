<?php
/**
 * Compatibility shim for PHP 8 tests.
 *
 * Yoast polyfills add return type declaration to `setUp` that isn't supported
 * before PHP 7.1, hence this workaround.
 */

namespace View_All_Posts_Pages\Tests;

if ( version_compare( phpversion(), '8.0.0', '<' ) ) {
	/**
	 * Class TestCase.
	 */
	abstract class TestCase extends WP_UnitTestCase {
		/**
		 * Set up the test.
		 *
		 * @return void
		 */
		protected function setUp() {
			parent::setUp();
			$this->_do_set_up();
		}

		/**
		 * Set up the test.
		 *
		 * @return void
		 */
		abstract function _do_set_up();
	}
} else {
	abstract class TestCase extends WP_UnitTestCase {
		/**
		 * Set up the test.
		 *
		 * @return void
		 */
		protected function setUp(): void {
			parent::setUp();
			$this->_do_set_up();
		}

		/**
		 * Set up the test.
		 *
		 * Not setting a return type as implementing methods cannot always do
		 * so.
		 *
		 * @return void
		 */
		abstract protected function _do_set_up();
	}
}
