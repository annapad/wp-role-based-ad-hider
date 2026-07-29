<?php
/**
 * Settings page for the Role-Based Ad Hider plugin.
 *
 * Registers the Settings -> Ad Hider admin page using the WordPress Settings API.
 *
 * @package Role_Based_Ad_Hider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RBAH_Settings
 */
class RBAH_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var RBAH_Settings|null
	 */
	private static $instance = null;

	const GROUP = 'rbah_settings_group';
	const PAGE  = 'rbah_settings';

	/**
	 * Get the singleton instance.
	 *
	 * @return RBAH_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - registers admin hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the plugin settings page under Settings -> Ad Hider.
	 */
	public function register_menu() {
		add_options_page(
			esc_html__( 'Role-Based Ad Hider', 'role-based-ad-hider' ),
			esc_html__( 'Ad Hider', 'role-based-ad-hider' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the settings and their fields using the Settings API.
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			RBAH_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(
					'roles'            => array(),
					'capability'       => '',
					'custom_selectors' => array(),
				),
			)
		);

		add_settings_section(
			'rbah_main_section',
			esc_html__( 'Access Rules', 'role-based-ad-hider' ),
			function () {
				echo '<p>' . esc_html__( 'Choose which users should see the site without ads.', 'role-based-ad-hider' ) . '</p>';
			},
			self::PAGE
		);

		add_settings_field(
			'roles',
			esc_html__( 'Eligible Roles', 'role-based-ad-hider' ),
			array( $this, 'render_roles_field' ),
			self::PAGE,
			'rbah_main_section'
		);

		add_settings_field(
			'capability',
			esc_html__( 'Eligible Capability', 'role-based-ad-hider' ),
			array( $this, 'render_capability_field' ),
			self::PAGE,
			'rbah_main_section'
		);

		add_settings_section(
			'rbah_selectors_section',
			esc_html__( 'Custom CSS Selectors', 'role-based-ad-hider' ),
			function () {
				echo '<p>' . esc_html__( 'Add extra CSS selectors that identify ad containers on your site. One per line.', 'role-based-ad-hider' ) . '</p>';
				echo '<p><em>' . esc_html__( 'Default selectors (always applied): .adsbygoogle, [id^="div-gpt-ad"], [class*="ai-viewport-"], [class*="advads-"], .ad-container, .advertisement', 'role-based-ad-hider' ) . '</em></p>';
			},
			self::PAGE
		);

		add_settings_field(
			'custom_selectors',
			esc_html__( 'Custom Selectors', 'role-based-ad-hider' ),
			array( $this, 'render_custom_selectors_field' ),
			self::PAGE,
			'rbah_selectors_section'
		);
	}

	/**
	 * Render the eligible roles checkbox list.
	 */
	public function render_roles_field() {
		$settings       = get_option( RBAH_OPTION_KEY, array() );
		$selected_roles = isset( $settings['roles'] ) ? (array) $settings['roles'] : array();
		$all_roles      = get_editable_roles();

		echo '<fieldset>';
		foreach ( $all_roles as $slug => $role ) {
			printf(
				'<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="%1$s[roles][]" value="%2$s" %3$s /> %4$s <code>(%2$s)</code></label>',
				esc_attr( RBAH_OPTION_KEY ),
				esc_attr( $slug ),
				checked( in_array( $slug, $selected_roles, true ), true, false ),
				esc_html( translate_user_role( $role['name'] ) )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Users in any of the selected roles will see the site without ads.', 'role-based-ad-hider' ) . '</p>';
	}

	/**
	 * Render the eligible capability text input.
	 */
	public function render_capability_field() {
		$settings   = get_option( RBAH_OPTION_KEY, array() );
		$capability = isset( $settings['capability'] ) ? $settings['capability'] : '';

		printf(
			'<input type="text" name="%1$s[capability]" value="%2$s" class="regular-text" placeholder="e.g. read_private_content" />',
			esc_attr( RBAH_OPTION_KEY ),
			esc_attr( $capability )
		);
		echo '<p class="description">' . esc_html__( 'Optional. Users with this capability will also see the site without ads, regardless of their role.', 'role-based-ad-hider' ) . '</p>';
	}

	/**
	 * Render the custom selectors textarea.
	 */
	public function render_custom_selectors_field() {
		$settings         = get_option( RBAH_OPTION_KEY, array() );
		$custom_selectors = isset( $settings['custom_selectors'] ) ? (array) $settings['custom_selectors'] : array();
		$textarea_value   = implode( "\n", $custom_selectors );

		printf(
			'<textarea name="%1$s[custom_selectors]" rows="6" cols="60" class="large-text code" placeholder="%2$s">%3$s</textarea>',
			esc_attr( RBAH_OPTION_KEY ),
			esc_attr( ".my-custom-ad-slot\n#sidebar-ad\n[data-ad-placeholder]" ),
			esc_textarea( $textarea_value )
		);
		echo '<p class="description">' . esc_html__( 'One CSS selector per line. These are added on top of the default list.', 'role-based-ad-hider' ) . '</p>';
	}

	/**
	 * Sanitize submitted settings before saving.
	 *
	 * @param array $input Raw input from the settings form.
	 * @return array Sanitized settings.
	 */
	public function sanitize( $input ) {
		$output = array(
			'roles'            => array(),
			'capability'       => '',
			'custom_selectors' => array(),
		);

		// Roles: array of known role slugs.
		if ( isset( $input['roles'] ) && is_array( $input['roles'] ) ) {
			$editable_roles = array_keys( get_editable_roles() );
			foreach ( $input['roles'] as $role_slug ) {
				$role_slug = sanitize_key( $role_slug );
				if ( in_array( $role_slug, $editable_roles, true ) ) {
					$output['roles'][] = $role_slug;
				}
			}
		}

		// Capability: a single slug.
		if ( isset( $input['capability'] ) ) {
			$output['capability'] = sanitize_key( $input['capability'] );
		}

		// Custom selectors: textarea, one selector per line.
		if ( isset( $input['custom_selectors'] ) ) {
			$raw = is_array( $input['custom_selectors'] )
				? implode( "\n", $input['custom_selectors'] )
				: (string) $input['custom_selectors'];

			$lines = preg_split( '/\r\n|\r|\n/', $raw );
			foreach ( $lines as $line ) {
				$line = trim( wp_strip_all_tags( $line ) );
				if ( '' !== $line ) {
					$output['custom_selectors'][] = $line;
				}
			}
		}

		return $output;
	}

	/**
	 * Render the settings page markup.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Role-Based Ad Hider', 'role-based-ad-hider' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
