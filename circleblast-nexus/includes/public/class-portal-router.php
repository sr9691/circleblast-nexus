<?php
/**
 * Portal Router
 *
 * ITER-0006: Shortcode-based routing for the member portal.
 * Handles access control, portal navigation, and redirects
 * non-members away from portal pages.
 *
 * Usage: Add [cbnexus_portal] shortcode to a WordPress page.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Router {

	/**
	 * Portal sections and their render callbacks.
	 *
	 * @var array<string, array{label: string, callback: callable}>
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
				'label'    => __('Dashboard', 'circleblast-nexus'),
				'icon'     => 'dashicons-dashboard',
				'callback' => ['CBNexus_Portal_Dashboard', 'render'],
			],
			'directory' => [
				'label'    => __('Directory', 'circleblast-nexus'),
				'icon'     => 'dashicons-groups',
				'callback' => ['CBNexus_Directory', 'render'],
			],
			'meetings' => [
				'label'    => __('Meetings', 'circleblast-nexus'),
				'icon'     => 'dashicons-calendar-alt',
				'callback' => ['CBNexus_Portal_Meetings', 'render'],
			],
			'circleup' => [
				'label'    => __('CircleUp', 'circleblast-nexus'),
				'icon'     => 'dashicons-megaphone',
				'callback' => ['CBNexus_Portal_CircleUp', 'render'],
			],
			'profile' => [
				'label'    => __('My Profile', 'circleblast-nexus'),
				'icon'     => 'dashicons-admin-users',
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

		// WordPress dashicons for nav icons.
		wp_enqueue_style('dashicons');
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
	 * Render the portal header with user greeting.
	 */
	private static function render_header(array $profile): void {
		$name = $profile['first_name'] ?: $profile['display_name'];
		?>
		<header class="cbnexus-portal-header">
			<div class="cbnexus-portal-header-left">
				<h1 class="cbnexus-portal-title">CircleBlast</h1>
				<span class="cbnexus-portal-greeting">
					<?php printf(esc_html__('Welcome, %s', 'circleblast-nexus'), esc_html($name)); ?>
				</span>
			</div>
			<div class="cbnexus-portal-header-right">
				<a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="cbnexus-btn cbnexus-btn-outline">
					<?php esc_html_e('Log Out', 'circleblast-nexus'); ?>
				</a>
			</div>
		</header>
		<?php
	}

	/**
	 * Render the portal navigation.
	 */
	private static function render_nav(string $current): void {
		$base_url = get_permalink();
		?>
		<nav class="cbnexus-portal-nav">
			<ul>
				<?php foreach (self::$sections as $slug => $section) : ?>
					<li class="<?php echo $slug === $current ? 'cbnexus-nav-active' : ''; ?>">
						<a href="<?php echo esc_url(add_query_arg('section', $slug, $base_url)); ?>">
							<span class="dashicons <?php echo esc_attr($section['icon']); ?>"></span>
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
	 * Dashboard placeholder (replaced with live data in ITER-0015).
	 */
	public static function render_dashboard_placeholder(array $profile): void {
		$name = $profile['first_name'] ?: $profile['display_name'];
		?>
		<div class="cbnexus-card">
			<h2><?php printf(esc_html__('Welcome back, %s!', 'circleblast-nexus'), esc_html($name)); ?></h2>
			<p><?php esc_html_e('Your personalized dashboard is coming soon. In the meantime, explore the portal using the navigation on the left.', 'circleblast-nexus'); ?></p>

			<div class="cbnexus-quick-stats">
				<div class="cbnexus-stat-card">
					<span class="cbnexus-stat-value">—</span>
					<span class="cbnexus-stat-label"><?php esc_html_e('1:1 Meetings', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card">
					<span class="cbnexus-stat-value">—</span>
					<span class="cbnexus-stat-label"><?php esc_html_e('Members Met', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card">
					<span class="cbnexus-stat-value">—</span>
					<span class="cbnexus-stat-label"><?php esc_html_e('CircleUp Attended', 'circleblast-nexus'); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Generic placeholder for sections not yet built.
	 */
	public static function render_section_placeholder(array $profile): void {
		?>
		<div class="cbnexus-card">
			<h2><?php esc_html_e('Coming Soon', 'circleblast-nexus'); ?></h2>
			<p><?php esc_html_e('This section is under development and will be available in a future update.', 'circleblast-nexus'); ?></p>
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
}
