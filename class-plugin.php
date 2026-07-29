<?php
/**
 * Main plugin class - handles ad hiding for eligible users.
 *
 * Adds a body class and injects CSS/JS to hide ads on the front-end
 * when the current user matches the configured roles or capabilities.
 *
 * @package Role_Based_Ad_Hider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RBAH_Plugin
 */
class RBAH_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var RBAH_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Default CSS selectors matching common ad systems.
	 *
	 * @var array
	 */
	private $default_selectors = array(
		'.adsbygoogle',              // Google AdSense.
		'[id^="div-gpt-ad"]',        // Google Ad Manager (GPT).
		'[class*="ai-viewport-"]',   // Ad Inserter plugin.
		'[class*="advads-"]',        // Advanced Ads plugin.
		'.ad-container',             // Generic ad wrapper.
		'.advertisement',            // Generic ad label.
	);

	/**
	 * Get the singleton instance.
	 *
	 * @return RBAH_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - registers hooks.
	 */
	private function __construct() {
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_action( 'wp_head', array( $this, 'render_hide_css' ), 100 );
		add_action( 'wp_footer', array( $this, 'render_mutation_observer' ) );
	}

	/**
	 * Determine whether the current user should have ads hidden.
	 *
	 * A user is eligible if they are logged in AND either:
	 *  - they have one of the configured roles, or
	 *  - they have the configured capability.
	 *
	 * The final decision can be overridden by the 'ad_hider_should_hide' filter.
	 *
	 * @return bool
	 */
	public function should_hide_ads() {
		$should_hide = false;

		if ( is_user_logged_in() ) {
			$user     = wp_get_current_user();
			$settings = $this->get_settings();

			// Match by role.
			$eligible_roles = $settings['roles'];
			if ( ! empty( $eligible_roles ) ) {
				$user_roles = (array) $user->roles;
				if ( array_intersect( $user_roles, $eligible_roles ) ) {
					$should_hide = true;
				}
			}

			// Match by capability.
			$capability = $settings['capability'];
			if ( ! $should_hide && ! empty( $capability ) ) {
				if ( user_can( $user, $capability ) ) {
					$should_hide = true;
				}
			}
		}

		/**
		 * Filter whether to hide ads for the current user.
		 *
		 * Return true to force ad-free experience, false to skip.
		 *
		 * @param bool $should_hide Default eligibility decision.
		 */
		return (bool) apply_filters( 'ad_hider_should_hide', $should_hide );
	}

	/**
	 * Add a CSS class to <body> for eligible users.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		if ( $this->should_hide_ads() ) {
			$classes[] = 'has-ad-free-access';
		}
		return $classes;
	}

	/**
	 * Get the full list of CSS selectors (defaults merged with custom entries).
	 *
	 * Filterable via 'ad_hider_selectors'.
	 *
	 * @return array
	 */
	public function get_selectors() {
		$settings         = $this->get_settings();
		$custom_selectors = $settings['custom_selectors'];
		$all_selectors    = array_merge( $this->default_selectors, $custom_selectors );

		/**
		 * Filter the CSS selectors used to hide ads.
		 *
		 * @param array $all_selectors Combined default and custom selectors.
		 */
		return (array) apply_filters( 'ad_hider_selectors', $all_selectors );
	}

	/**
	 * Render inline CSS in the <head> that hides matched elements for eligible users.
	 */
	public function render_hide_css() {
		if ( ! $this->should_hide_ads() ) {
			return;
		}

		$selectors = $this->get_selectors();
		if ( empty( $selectors ) ) {
			return;
		}

		// Prefix each selector with body.has-ad-free-access so the rules
		// only apply when the body class is present.
		$prefixed = array_map(
			function ( $selector ) {
				return 'body.has-ad-free-access ' . $selector;
			},
			$selectors
		);

		$css = implode( ",\n", $prefixed ) . " {\n\tdisplay: none !important;\n\tvisibility: hidden !important;\n}\n";

		echo "<style id=\"rbah-ad-hider\">\n" . esc_html( $css ) . "</style>\n";
	}

	/**
	 * Render a MutationObserver in the footer to catch ads inserted after page load.
	 *
	 * Some ad networks inject their containers asynchronously, so a plain CSS rule
	 * is not always enough. This script hides matched nodes as they appear.
	 */
	public function render_mutation_observer() {
		if ( ! $this->should_hide_ads() ) {
			return;
		}

		$selectors = $this->get_selectors();
		if ( empty( $selectors ) ) {
			return;
		}

		$selectors_json = wp_json_encode( $selectors );
		?>
		<script id="rbah-ad-observer">
			(function () {
				var selectors = <?php echo $selectors_json; ?>;
				var selectorString = selectors.join(',');

				function hideMatching(root) {
					try {
						var nodes = (root || document).querySelectorAll(selectorString);
						for (var i = 0; i < nodes.length; i++) {
							nodes[i].style.setProperty('display', 'none', 'important');
						}
					} catch (e) { /* Ignore invalid selectors. */ }
				}

				// Initial pass in case CSS missed anything.
				hideMatching(document);

				// Watch for dynamically inserted ad containers.
				if (typeof MutationObserver !== 'undefined') {
					var observer = new MutationObserver(function (mutations) {
						for (var i = 0; i < mutations.length; i++) {
							var added = mutations[i].addedNodes;
							for (var j = 0; j < added.length; j++) {
								if (added[j].nodeType === 1) {
									hideMatching(added[j]);
								}
							}
						}
					});
					observer.observe(document.body, { childList: true, subtree: true });
				}
			})();
		</script>
		<?php
	}

	/**
	 * Retrieve plugin settings with sensible defaults.
	 *
	 * @return array
	 */
	private function get_settings() {
		$defaults = array(
			'roles'            => array(),
			'capability'       => '',
			'custom_selectors' => array(),
		);

		$saved  = get_option( RBAH_OPTION_KEY, array() );
		$merged = wp_parse_args( $saved, $defaults );

		// Normalise types so callers can trust the shape.
		$merged['roles']            = array_filter( array_map( 'trim', (array) $merged['roles'] ) );
		$merged['capability']       = sanitize_key( $merged['capability'] );
		$merged['custom_selectors'] = array_filter( array_map( 'trim', (array) $merged['custom_selectors'] ) );

		return $merged;
	}
}
