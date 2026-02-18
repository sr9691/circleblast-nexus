<?php
/**
 * Portal Router
 *
 * ITER-0006 / UX Refresh: Shortcode-based routing for the member portal.
 * Updated layout: horizontal pill navigation (no sidebar), plum & gold
 * branded header, stacked content area.
 *
 * Usage: Add [cbnexus_portal] shortcode to a WordPress page.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Router {

	/**
	 * Portal sections and their render callbacks.
	 *
	 * @var array<string, array{label: string, icon: string, callback: callable}>
	 */
	private static $sections = [];

	/**
	 * Initialize the portal.
	 */
	public static function init(): void {
		add_shortcode('cbnexus_portal', [__CLASS__, 'render_shortcode']);
		add_action('template_redirect', [__CLASS__, 'access_control']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

		// Register default sections.
		self::$sections = [
			'dashboard' => [
				'label'    => __('Home', 'circleblast-nexus'),
				'icon'     => '🏠',
				'callback' => ['CBNexus_Portal_Dashboard', 'render'],
			],
			'directory' => [
				'label'    => __('Directory', 'circleblast-nexus'),
				'icon'     => '👥',
				'callback' => ['CBNexus_Directory', 'render'],
			],
			'meetings' => [
				'label'    => __('Meetings', 'circleblast-nexus'),
				'icon'     => '🤝',
				'callback' => ['CBNexus_Portal_Meetings', 'render'],
			],
			'circleup' => [
				'label'    => __('CircleUp', 'circleblast-nexus'),
				'icon'     => '📢',
				'callback' => ['CBNexus_Portal_CircleUp', 'render'],
			],
			'events' => [
				'label'    => __('Events', 'circleblast-nexus'),
				'icon'     => '📅',
				'callback' => ['CBNexus_Portal_Events', 'render'],
			],
			'club' => [
				'label'    => __('Club', 'circleblast-nexus'),
				'icon'     => '📊',
				'callback' => ['CBNexus_Portal_Club', 'render'],
			],
			'profile' => [
				'label'    => __('Profile', 'circleblast-nexus'),
				'icon'     => '👤',
				'callback' => ['CBNexus_Portal_Profile', 'render'],
			],
		];
	}

	/**
	 * Enqueue portal CSS on pages that contain the shortcode.
	 */
	public static function enqueue_assets(): void {
		global $post;

		if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'cbnexus_portal')) {
			return;
		}

		wp_enqueue_style(
			'cbnexus-portal',
			CBNEXUS_PLUGIN_URL . 'assets/css/portal.css',
			[],
			CBNEXUS_VERSION
		);

		// WordPress dashicons still used in some sub-components.
		wp_enqueue_style('dashicons');

		// Events calendar styles.
		wp_enqueue_style(
			'cbnexus-portal-events',
			CBNEXUS_PLUGIN_URL . 'assets/css/portal-events.css',
			['cbnexus-portal'],
			CBNEXUS_VERSION
		);

		// Events JS (RSVP).
		wp_enqueue_script(
			'cbnexus-events',
			CBNEXUS_PLUGIN_URL . 'assets/js/events.js',
			[],
			CBNEXUS_VERSION,
			true
		);
		wp_localize_script('cbnexus-events', 'cbnexus_ajax_url', admin_url('admin-ajax.php'));
		wp_add_inline_script('cbnexus-events', 'window.cbnexus_events_nonce = "' . wp_create_nonce('cbnexus_events') . '";', 'before');
	}

	/**
	 * Access control: redirect non-members away from portal pages.
	 */
	public static function access_control(): void {
		global $post;

		if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'cbnexus_portal')) {
			return;
		}

		// Not logged in → redirect to login.
		if (!is_user_logged_in()) {
			wp_safe_redirect(wp_login_url(get_permalink()));
			exit;
		}

		// Logged in but not a CB member → redirect to home with notice.
		if (!CBNexus_Member_Repository::is_member(get_current_user_id())) {
			wp_safe_redirect(home_url('?cbnexus_access=denied'));
			exit;
		}
	}

	/**
	 * Render the portal shortcode.
	 *
	 * @return string HTML output.
	 */
	public static function render_shortcode(): string {
		if (!is_user_logged_in()) {
			return '<p>' . esc_html__('Please log in to access the member portal.', 'circleblast-nexus') . '</p>';
		}

		$user_id = get_current_user_id();
		if (!CBNexus_Member_Repository::is_member($user_id)) {
			return '<p>' . esc_html__('You do not have access to the member portal.', 'circleblast-nexus') . '</p>';
		}

		$current_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'dashboard';
		if (!isset(self::$sections[$current_section])) {
			$current_section = 'dashboard';
		}

		$profile = CBNexus_Member_Repository::get_profile($user_id);

		ob_start();
		?>
		<div class="cbnexus-portal">
			<?php self::render_header($profile); ?>

			<div class="cbnexus-portal-layout">
				<?php self::render_nav($current_section); ?>

				<main class="cbnexus-portal-content">
					<?php self::render_section($current_section, $profile); ?>
				</main>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the portal header — branded with plum & gold dot.
	 */
	private static function render_header(array $profile): void {
		$initials = self::get_initials($profile);
		?>
		<header class="cbnexus-portal-header">
			<div>
				<div class="cbnexus-portal-brand">
					<span class="cbnexus-portal-brand-dot"></span>
					<span class="cbnexus-portal-brand-name"><?php esc_html_e('CircleBlast', 'circleblast-nexus'); ?></span>
				</div>
				<h1 class="cbnexus-portal-subtitle"><?php esc_html_e('Member Portal', 'circleblast-nexus'); ?></h1>
			</div>
			<div style="display:flex;align-items:center;gap:10px;">
				<div class="cbnexus-portal-avatar">
					<span class="cbnexus-portal-avatar-initials"><?php echo esc_html($initials); ?></span>
				</div>
			</div>
		</header>
		<?php
	}

	/**
	 * Render horizontal pill navigation.
	 */
	private static function render_nav(string $current): void {
		$base_url = get_permalink();
		?>
		<nav class="cbnexus-portal-nav">
			<ul>
				<?php foreach (self::$sections as $slug => $section) : ?>
					<li class="<?php echo $slug === $current ? 'cbnexus-nav-active' : ''; ?>">
						<a href="<?php echo esc_url(add_query_arg('section', $slug, $base_url)); ?>">
							<span class="cbnexus-nav-icon"><?php echo esc_html($section['icon']); ?></span>
							<span class="cbnexus-nav-label"><?php echo esc_html($section['label']); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}

	/**
	 * Render the active section content.
	 */
	private static function render_section(string $section, array $profile): void {
		$callback = self::$sections[$section]['callback'] ?? null;

		if (is_callable($callback)) {
			call_user_func($callback, $profile);
		} else {
			self::render_section_placeholder($profile);
		}
	}

	/**
	 * Generic placeholder for sections not yet built.
	 */
	public static function render_section_placeholder(array $profile): void {
		?>
		<div class="cbnexus-card">
			<h2><?php esc_html_e('Coming Soon', 'circleblast-nexus'); ?></h2>
			<p class="cbnexus-text-muted"><?php esc_html_e('This section is under development and will be available in a future update.', 'circleblast-nexus'); ?></p>
		</div>
		<?php
	}

	/**
	 * Get the portal page URL (finds the page containing the shortcode).
	 *
	 * @return string Portal page URL or home URL as fallback.
	 */
	public static function get_portal_url(): string {
		global $wpdb;

		$page_id = $wpdb->get_var(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_content LIKE '%[cbnexus_portal]%'
			 AND post_status = 'publish'
			 AND post_type = 'page'
			 LIMIT 1"
		);

		return $page_id ? get_permalink($page_id) : home_url();
	}

	/**
	 * Get initials from a member profile.
	 */
	private static function get_initials(array $m): string {
		$first = $m['first_name'] ?? '';
		$last  = $m['last_name'] ?? '';
		if ($first && $last) {
			return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
		}
		$display = $m['display_name'] ?? '?';
		return strtoupper(mb_substr($display, 0, 2));
	}
}
