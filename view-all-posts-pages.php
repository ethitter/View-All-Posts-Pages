<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * View All Posts Pages
 *
 * Plugin Name:     View All Posts Pages
 * Plugin URI:      http://www.oomphinc.com/plugins-modules/view-all-posts-pages/
 * Description:     Provides a "view all" (single page) option for posts, pages, and custom post types paged using WordPress' <a href="http://codex.wordpress.org/Write_Post_SubPanel#Quicktags" target="_blank"><code>&lt;!--nextpage--&gt;</code> Quicktag</a> (multipage posts).
 * Author:          Erick Hitter & Oomph, Inc.
 * Author URI:      http://www.oomphinc.com/
 * Text Domain:     view-all-posts-pages
 * Domain Path:     /languages
 * Version:         0.9.4
 *
 * @package         View_All_Posts_Pages
 *
 * phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Class view_all_posts_pages
 */
class view_all_posts_pages { // phpcs:ignore PEAR.NamingConventions.ValidClassName, Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
	/**
	 * Singleton
	 *
	 * @var self
	 */
	private static $__instance = null;

	/**
	 * Class variables
	 *
	 * @var string
	 */
	private $query_var = 'view-all';

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	private $ns = 'view_all_posts_pages';

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $settings_key = 'vapp';

	/**
	 * Default settings
	 *
	 * @var array|null
	 */
	private $settings_defaults = null;

	/**
	 * Option indicating admin notice was dismissed.
	 *
	 * @var string
	 */
	private $notice_key = 'vapp_admin_notice_dismissed';

	/**
	 * Silence is golden
	 */
	private function __construct() {}

	/**
	 * Implement singleton
	 *
	 * @uses self::setup
	 * @return self
	 */
	public static function get_instance() {
		if ( ! is_a( self::$__instance, __CLASS__ ) ) {
			self::$__instance = new self();

			self::$__instance->setup();
		}

		return self::$__instance;
	}

	/**
	 * Register actions and filters.
	 *
	 * @uses register_deactivation_hook
	 * @uses add_action
	 */
	private function setup() {
		register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

		add_action( 'init', array( $this, 'action_init' ), 20 );
		add_action( 'redirect_canonical', array( $this, 'filter_redirect_canonical' ) );

		add_action( 'the_post', array( $this, 'action_the_post' ), 5 );
	}

	/**
	 * Clean up after plugin deactivation.
	 *
	 * @uses flush_rewrite_rules
	 * @uses delete_option
	 * @action register_deactivation_hook
	 */
	public function deactivation_hook() {
		flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules, WordPressVIPMinimum.VIP.RestrictedFunctions.rewrite_rules_flush_rewrite_rules

		delete_option( $this->settings_key );
		delete_option( $this->notice_key );
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'view-all-posts-pages',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	/**
	 * Register plugin option and disable rewrite rule flush warning.
	 *
	 * @uses register_setting
	 * @uses apply_filters
	 * @uses update_option
	 * @action admin_init
	 */
	public function action_admin_init() {
		register_setting( $this->settings_key, $this->settings_key, array( $this, 'admin_options_validate' ) );

		if (
			isset( $_GET[ $this->notice_key ], $_GET[ $this->notice_key . '_nonce' ] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ $this->notice_key . '_nonce' ] ) ), $this->notice_key ) &&
			apply_filters( 'vapp_display_rewrite_rules_notice', true )
		) {
			update_option( $this->notice_key, 1 );
		}
	}

	/**
	 * Determine if full post view is being requested.
	 *
	 * @global $wp_query
	 * @uses is_404
	 * @return bool
	 */
	public function is_view_all() {
		global $wp_query;
		return is_array( $wp_query->query ) && array_key_exists( $this->query_var, $wp_query->query ) && ! is_404();
	}

	/**
	 * Add rewrite endpoint, which sets query var and rewrite rules.
	 *
	 * @global $wp_rewrite
	 * @uses __
	 * @uses this::get_options
	 * @uses add_filter
	 * @uses apply_filters
	 * @uses get_option
	 * @uses add_action
	 * @uses add_rewrite_endpoint
	 * @action init
	 */
	public function action_init() {
		// Populate default settings, with translation support.
		$this->settings_defaults = array(
			'wlp'             => true,
			'wlp_text'        => __( 'View All', 'view-all-posts-pages' ),
			'wlp_class'       => 'vapp',
			'wlp_post_types'  => array(
				'post',
			),
			'link'            => false,
			'link_position'   => 'below',
			'link_text'       => __( 'View All', 'view-all-posts-pages' ),
			'link_class'      => 'vapp',
			'link_post_types' => array(
				'post',
			),
			'link_priority'   => 10,
		);

		// Register additional plugin actions if settings call for them.
		$options = $this->get_options();

		if ( array_key_exists( 'wlp', $options ) && true === $options['wlp'] ) {
			add_filter( 'wp_link_pages_args', array( $this, 'filter_wp_link_pages_args_early' ), 0 );
		}

		if ( $options['link'] ) {
			add_filter( 'the_content', array( $this, 'filter_the_content_auto' ), $options['link_priority'] );
		}

		if ( apply_filters( 'vapp_display_rewrite_rules_notice', true ) && ! get_option( $this->notice_key ) ) {
			add_action( 'admin_notices', array( $this, 'action_admin_notices_activation' ) );
		}

		// Register rewrite endpoint, which handles most of our rewrite needs.
		add_rewrite_endpoint( $this->query_var, EP_ALL );

		// Extra rules needed if verbose page rules are requested.
		global $wp_rewrite;
		if ( $wp_rewrite->use_verbose_page_rules ) {
			// Build regex.
			$regex  = substr( str_replace( $wp_rewrite->rewritecode, $wp_rewrite->rewritereplace, $wp_rewrite->permalink_structure ), 1 );
			$regex  = trailingslashit( $regex );
			$regex .= $this->query_var . '/?$';

			// Build corresponding query string.
			$query = substr( str_replace( $wp_rewrite->rewritecode, $wp_rewrite->queryreplace, $wp_rewrite->permalink_structure ), 1 );
			$query = explode( '/', $query );
			$query = array_filter( $query );

			$i = 1;
			foreach ( $query as $key => $qv ) {
				$query[ $key ] .= '$matches[' . $i . ']';
				$i++;
			}

			$query[] = $this->query_var . '=1';

			$query = implode( '&', $query );

			// Add rule.
			add_rewrite_rule( $regex, $wp_rewrite->index . '?' . $query, 'top' );
		}
	}

	/**
	 * Prevent canonical redirect if full-post page is requested.
	 *
	 * @param string $url Canonical URL.
	 * @uses this::is_view_all
	 * @filter redirect_canonical
	 * @return string|false
	 */
	public function filter_redirect_canonical( $url ) {
		if ( $this->is_view_all() ) {
			$url = false;
		}

		return $url;
	}

	/**
	 * Modify post variables to display entire post on one page.
	 *
	 * @global $pages, $more
	 * @param WP_Post $post Post object.
	 * @uses this::is_view_all
	 * @action the_post
	 */
	public function action_the_post( $post ) {
		if ( $this->is_view_all() ) {
			global $pages, $more, $multipage;

			$post->post_content = str_replace( "\n<!--nextpage-->\n", "\n\n", $post->post_content );
			$post->post_content = str_replace( "\n<!--nextpage-->", "\n", $post->post_content );
			$post->post_content = str_replace( "<!--nextpage-->\n", "\n", $post->post_content );
			$post->post_content = str_replace( '<!--nextpage-->', ' ', $post->post_content );

			// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
			$pages = array( $post->post_content );

			$more      = 1;
			$multipage = 0;
			// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Add wp_link_pages arguments filter if automatic inclusion is chosen for a given post type.
	 * Automatic inclusion can be disabled by passing false through the vapp_display_link filter.
	 *
	 * @global $post
	 * @param array $args wp_link_pages arguments.
	 * @uses this::get_options
	 * @uses apply_filters
	 * @uses add_filter
	 * @filter wp_link_pages
	 * @return array
	 */
	public function filter_wp_link_pages_args_early( $args ) {
		global $post;

		$options = $this->get_options();

		if ( in_array( $post->post_type, $options['wlp_post_types'], true ) && apply_filters( 'vapp_display_link', true, (int) $post->ID, $options, $post ) ) {
			add_filter( 'wp_link_pages_args', array( $this, 'filter_wp_link_pages_args' ), 999 );
		}

		return $args;
	}

	/**
	 * Filter wp_link_pages arguments to append "View all" link to output.
	 *
	 * @global $more
	 * @param array $args wp_link_pages arguments.
	 * @uses this::get_options
	 * @uses this::is_view_all
	 * @uses esc_attr
	 * @uses esc_url
	 * @return array
	 */
	public function filter_wp_link_pages_args( $args ) {
		$options = $this->get_options();

		if ( is_array( $options ) ) {
			extract( $options, EXTR_OVERWRITE );

			// Set global $more to false so that wp_link_pages outputs links for all pages when viewing full post page.
			if ( $this->is_view_all() ) {
				// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
				$GLOBALS['more'] = false;
				// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
			}

			// Process link text, respecting pagelink parameter.
			$link_text = str_replace( '%', $wlp_text, $args['pagelink'] );

			// View all.
			$link = ' ' . $args['link_before'];

			if ( $this->is_view_all() ) {
				$link .= '<span class="' . esc_attr( $wlp_class ) . '">' . $link_text . '</span><!-- .' . esc_attr( $wlp_class ) . ' -->';
			} else {
				$link .= '<a class="' . esc_attr( $wlp_class ) . '" href="' . esc_url( $this->url() ) . '">' . $link_text . '</a><!-- .' . esc_attr( $wlp_class ) . ' -->';
			}

			$link .= $args['link_after'] . ' ';

			$args['after'] = $link . $args['after'];
		}

		return $args;
	}

	/**
	 * Filter the content if automatic link inclusion is selected.
	 *
	 * @global $post
	 * @param string $content Post content.
	 * @uses this::get_options
	 * @uses this::is_view_all
	 * @uses esc_attr
	 * @uses esc_url
	 * @uses this::url
	 * @filter the_content
	 * @return string
	 */
	public function filter_the_content_auto( $content ) {
		$options = $this->get_options();

		global $post;

		if ( ! $this->is_view_all() && is_array( $options ) && array_key_exists( 'link', $options ) && true === $options['link'] && in_array( $post->post_type, $options['link_post_types'] ) ) {
			extract( $options, EXTR_OVERWRITE );

			$link = '<p class="vapp_wrapper"><a class="' . esc_attr( $link_class ) . '" href="' . esc_url( $this->url() ) . '">' . esc_html( $link_text ) . '</a></p><!-- .vapp_wrapper -->';

			if ( 'above' === $link_position ) {
				$content = $link . $content;
			} elseif ( 'below' === $link_position ) {
				$content = $content . $link;
			} elseif ( 'both' === $link_position ) {
				$content = $link . $content . $link;
			}
		}

		return $content;
	}

	/**
	 * Generate URL
	 *
	 * @global $post
	 * @global $wp_rewrite
	 * @param int|false $post_id Post ID.
	 * @uses is_singular
	 * @uses in_the_loop
	 * @uses get_permalink
	 * @uses is_home
	 * @uses is_front_page
	 * @uses home_url
	 * @uses is_category
	 * @uses get_category_link
	 * @uses get_query_var
	 * @uses is_tag
	 * @uses get_tag_link
	 * @uses is_tax
	 * @uses get_queried_object
	 * @uses get_term_link
	 * @uses path_join
	 * @uses trailingslashit
	 * @uses add_query_arg
	 * @return string or bool
	 */
	public function url( $post_id = false ) {
		$link = false;

		// Get link base specific to page type being viewed.
		if ( is_singular() || in_the_loop() ) {
			$post_id = intval( $post_id );

			if ( ! $post_id ) {
				global $post;
				$post_id = $post->ID;
			}

			if ( ! $post_id ) {
				return false;
			}

			$link = get_permalink( $post_id );
		} elseif ( is_home() || is_front_page() ) {
			$link = home_url( '/' );
		} elseif ( is_category() ) {
			$link = get_category_link( get_query_var( 'cat' ) );
		} elseif ( is_tag() ) {
			$link = get_tag_link( get_query_var( 'tag_id' ) );
		} elseif ( is_tax() ) {
			$queried_object = get_queried_object();

			if ( is_object( $queried_object ) && property_exists( $queried_object, 'taxonomy' ) && property_exists( $queried_object, 'term_id' ) ) {
				$link = get_term_link( (int) $queried_object->term_id, $queried_object->taxonomy );
			}
		}

		// If link base is set, build link.
		if ( false !== $link ) {
			global $wp_rewrite;

			if ( $wp_rewrite->using_permalinks() ) {
				$link = path_join( $link, $this->query_var );

				if ( $wp_rewrite->use_trailing_slashes ) {
					$link = trailingslashit( $link );
				}
			} else {
				$link = add_query_arg( $this->query_var, 1, $link );
			}
		}

		return $link;
	}

	/**
	 * Add menu item for options page
	 *
	 * @uses __
	 * @uses add_options_page
	 * @action admin_menu
	 */
	public function action_admin_menu() {
		/* translators: 1: Plugin name. */
		add_options_page( sprintf( __( '%s Options', 'view-all-posts-pages' ), "View All Post's Pages" ), "View All Post's Pages", 'manage_options', $this->ns, array( $this, 'admin_options' ) );
	}

	/**
	 * Render options page
	 *
	 * @uses settings_fields
	 * @uses this::get_options
	 * @uses this::post_types_array
	 * @uses __
	 * @uses _e
	 * @uses checked
	 * @uses esc_attr
	 * @uses submit_button
	 */
	public function admin_options() {
		?>
		<div class="wrap">
			<h2>View All Post's Pages</h2>

			<form action="options.php" method="post">
				<?php
					settings_fields( $this->settings_key );
					$options = $this->get_options();

					$post_types = $this->post_types_array();
				?>

				<h3>
					<?php
					/* translators: 1: WordPress function name. */
						printf( wp_kses_post( __( '%s Options', 'view-all-posts-pages' ) ), '<em>wp_link_pages</em>' );
					?>
				</h3>

				<p class="description"><?php esc_html_e( 'A "view all" link can be appended to WordPress\' standard page navigation using the options below.', 'view-all-posts-pages' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Automatically append link to post\'s page navigation?', 'view-all-posts-pages' ); ?></th>
						<td>
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[wlp]" id="wlp-true" value="1"<?php checked( $options['wlp'], true, true ); ?> /> <label for="wlp-true"><?php esc_html_e( 'Yes', 'view-all-posts-pages' ); ?></label><br />
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[wlp]" id="wlp-false" value="0"<?php checked( $options['wlp'], false, true ); ?> /> <label for="wlp-false"><?php esc_html_e( 'No', 'view-all-posts-pages' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wlp_text"><?php esc_html_e( 'Link text:', 'view-all-posts-pages' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[wlp_text]" id="wlp_text" value="<?php echo esc_attr( $options['wlp_text'] ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wlp_class"><?php esc_html_e( 'Link\'s CSS class(es):', 'view-all-posts-pages' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[wlp_class]" id="wlp_class" value="<?php echo esc_attr( $options['wlp_class'] ); ?>" class="regular-text" />

							<p class="description"><?php esc_html_e( 'Be aware that Internet Explorer will only interpret the first two CSS classes.', 'view-all-posts-pages' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Display automatically on:', 'view-all-posts-pages' ); ?></th>
						<td>
							<?php foreach ( $post_types as $post_type ) : ?>
								<input type="checkbox" name="<?php echo esc_attr( $this->settings_key ); ?>[wlp_post_types][]" id="wlp-pt-<?php echo esc_attr( $post_type->name ); ?>" value="<?php echo esc_attr( $post_type->name ); ?>"
									<?php
									if ( in_array( $post_type->name, $options['wlp_post_types'], true ) ) {
										echo ' checked="checked"';
									}
									?>
								/>
								<label for="wlp-pt-<?php echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->labels->name ); ?></label><br />
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Standalone Link Options', 'view-all-posts-pages' ); ?></h3>

				<p class="description"><?php esc_html_e( 'In addition to appending the "view all" link to WordPress\' standard navigation, link(s) can be added above and below post content.', 'view-all-posts-pages' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Automatically add links based on settings below?', 'view-all-posts-pages' ); ?></th>
						<td>
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[link]" id="link-true" value="1"<?php checked( $options['link'], true, true ); ?> /> <label for="link-true"><?php esc_html_e( 'Yes', 'view-all-posts-pages' ); ?></label><br />
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[link]" id="link-false" value="0"<?php checked( $options['link'], false, true ); ?> /> <label for="link-false"><?php esc_html_e( 'No', 'view-all-posts-pages' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Automatically place link:', 'view-all-posts-pages' ); ?></th>
						<td>
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[link_position]" id="link_position-above" value="above"<?php checked( $options['link_position'], 'above', true ); ?> /> <label for="link_position-above"><?php esc_html_e( 'Above content', 'view-all-posts-pages' ); ?></label><br />
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[link_position]" id="link_position-below" value="below"<?php checked( $options['link_position'], 'below', true ); ?> /> <label for="link_position-below"><?php esc_html_e( 'Below content', 'view-all-posts-pages' ); ?></label><br />
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[link_position]" id="link_position-both" value="both"<?php checked( $options['link_position'], 'both', true ); ?> /> <label for="link_position-both"><?php esc_html_e( 'Above and below content', 'view-all-posts-pages' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Display automatically on:', 'view-all-posts-pages' ); ?></th>
						<td>
							<?php foreach ( $post_types as $post_type ) : ?>
								<input type="checkbox" name="<?php echo esc_attr( $this->settings_key ); ?>[link_post_types][]" id="link-pt-<?php echo esc_attr( $post_type->name ); ?>" value="<?php echo esc_attr( $post_type->name ); ?>"
									<?php
									if ( in_array( $post_type->name, $options['link_post_types'], true ) ) {
										echo ' checked="checked"';
									}
									?>
								/>
								<label for="link-pt-<?php echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->labels->name ); ?></label><br />
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="link_text"><?php esc_html_e( 'Link text:', 'view-all-posts-pages' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[link_text]" id="link_text" value="<?php echo esc_attr( $options['link_text'] ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="link_class"><?php esc_html_e( 'Link\'s CSS class(es):', 'view-all-posts-pages' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[link_class]" id="link_class" value="<?php echo esc_attr( $options['link_class'] ); ?>" class="regular-text" />

							<p class="description"><?php esc_html_e( 'Be aware that Internet Explorer will only interpret the first two CSS classes.', 'view-all-posts-pages' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="link_priority"><?php esc_html_e( 'Link\'s priority:', 'view-all-posts-pages' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[link_priority]" id="link_priority" class="small-text code" value="<?php echo esc_attr( $options['link_priority'] ); ?>" />

							<p class="description"><?php esc_html_e( 'Priority determines when the link is added to a post\'s content. You can use the above setting to modulate the link\'s placement.', 'view-all-posts-pages' ); ?></p>
							<p class="description"><?php echo wp_kses_post( __( 'The default value is <strong>10</strong>. Lower values mean the link will be added earlier, while higher values will add the link later.', 'view-all-posts-pages' ) ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

		</div><!-- .wrap -->
		<?php
	}

	/**
	 * Validate options
	 *
	 * @param array $options Plugin options.
	 * @uses this::get_options
	 * @uses this::post_types_array
	 * @uses sanitize_text_field
	 * @uses absint
	 * @return array
	 */
	public function admin_options_validate( $options ) {
		$new_options = array();

		if ( is_array( $options ) ) {
			foreach ( $options as $key => $value ) {
				switch ( $key ) {
					case 'wlp':
					case 'link':
						$new_options[ $key ] = (bool) $value;
						break;

					case 'link_position':
						$placements = array(
							'above',
							'below',
							'both',
						);

						$new_options[ $key ] = in_array( $value, $placements, true ) ? $value : 'below';
						break;

					case 'wlp_post_types':
					case 'link_post_types':
						$post_types = $this->post_types_array();

						$new_options[ $key ] = array();

						if ( is_array( $value ) && is_array( $post_types ) ) {
							foreach ( $post_types as $post_type ) {
								if ( in_array( $post_type->name, $value, true ) ) {
									$new_options[ $key ][] = $post_type->name;
								}
							}
						}
						break;

					case 'wlp_text':
					case 'wlp_class':
					case 'link_text':
					case 'link_class':
						$value = sanitize_text_field( $value );

						if ( ( 'wlp_text' === $key || 'link_text' === $key ) && empty( $value ) ) {
							$value = 'View all';
						}

						$new_options[ $key ] = $value;
						break;

					case 'link_priority':
						$value = absint( $value );

						$new_options[ $key ] = $value;
						break;

					default:
						break;
				}
			}
		}

		return $new_options;
	}

	/**
	 * Return plugin options array parsed with default options
	 *
	 * @uses get_option
	 * @uses wp_parse_args
	 * @return array
	 */
	private function get_options() {
		$options = get_option( $this->settings_key, $this->settings_defaults );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( ! array_key_exists( 'wlp_post_types', $options ) ) {
			$options['wlp_post_types'] = array();
		}

		if ( ! array_key_exists( 'link_post_types', $options ) ) {
			$options['link_post_types'] = array();
		}

		return wp_parse_args( $options, $this->settings_defaults );
	}

	/**
	 * Build array of available post types, excluding all built-in ones except 'post' and 'page'.
	 *
	 * @uses get_post_types
	 * @return array
	 */
	private function post_types_array() {
		$post_types = array();
		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			if ( false === $post_type->_builtin || 'post' === $post_type->name || 'page' === $post_type->name ) {
				$post_types[] = $post_type;
			}
		}

		return $post_types;
	}

	/**
	 * Display admin notice regarding rewrite rules flush.
	 *
	 * @uses get_option
	 * @uses apply_filters
	 * @uses _e
	 * @uses __
	 * @uses admin_url
	 * @uses add_query_arg
	 * @action admin_notices
	 */
	public function action_admin_notices_activation() {
		if ( ! get_option( $this->notice_key ) && apply_filters( 'vapp_display_rewrite_rules_notice', true ) ) :
			?>

		<div id="wpf-rewrite-flush-warning" class="error fade">
			<p><strong><?php esc_html_e( 'View All Post\'s Pages', 'view-all-posts-pages' ); ?></strong></p>

			<p>
				<?php
					/* translators: 1: Permalinks settings page URL. */
					printf( wp_kses_post( __( 'You must refresh your site\'s permalinks before <em>View All Post\'s Pages</em> is fully activated. To do so, go to <a href="%s">Permalinks</a> and click the <strong><em>Save Changes</em></strong> button at the bottom of the screen.', 'view-all-posts-pages' ) ), esc_url( admin_url( 'options-permalink.php' ) ) );
				?>
			</p>

			<p>
				<?php
					$query_args = array(
						$this->notice_key            => 1,
						$this->notice_key . '_nonce' => wp_create_nonce( $this->notice_key ),
					);

					/* translators: 1: URL to dismiss admin notice. */
					printf( wp_kses_post( __( 'When finished, click <a href="%s">here</a> to hide this message.', 'view-all-posts-pages' ) ), esc_url( admin_url( add_query_arg( $query_args, 'index.php' ) ) ) );
					?>
			</p>
		</div>

			<?php
		endif;
	}
}

/**
 * Alias global variable used to hold instantiated plugin prior to singleton's introduction in version 0.7.
 */
$GLOBALS['vapp'] = view_all_posts_pages::get_instance();

/**
 * Shortcut to public function for generating full post view URL
 *
 * @param int|false $post_id Post ID.
 * @uses view_all_posts_pages::get_instance
 * @return string or bool
 */
function vapp_get_url( $post_id = false ) {
	return view_all_posts_pages::get_instance()->url( intval( $post_id ) );
}

/**
 * Output link to full post view.
 *
 * @global $post
 * @param string $link_text Link text.
 * @param string $class Link class.
 * @uses vapp_get_url
 * @uses esc_attr
 * @uses esc_url
 * @uses esc_html
 */
function vapp_the_link( $link_text = 'View All', $class = 'vapp' ) {
	global $post;
	$url = vapp_get_url( $post->ID );

	if ( $url ) {
		$link = '<a ' . ( $class ? 'class="' . esc_attr( $class ) . '"' : '' ) . ' href="' . esc_url( $url ) . '">' . esc_html( $link_text ) . '</a>';

		echo wp_kses_post( $link );
	}
}

/**
 * Filter wp_link_pages args.
 * Function is a shortcut to class' filter.
 *
 * @param array $args wp_link_pages args.
 * @uses view_all_posts_pages::get_instance
 * @return array
 */
function vapp_filter_wp_link_pages_args( $args ) {
	return view_all_posts_pages::get_instance()->filter_wp_link_pages_args( $args );
}

if ( ! function_exists( 'is_view_all' ) ) {
	/**
	 * Conditional tag indicating if full post view was requested.
	 *
	 * @uses view_all_posts_pages::get_instance
	 * @return bool
	 */
	function is_view_all() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return view_all_posts_pages::get_instance()->is_view_all();
	}
}
