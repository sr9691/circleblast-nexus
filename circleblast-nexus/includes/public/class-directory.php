<?php
/**
 * Member Directory
 *
 * ITER-0007 / UX Refresh: Searchable, filterable member directory matching
 * demo. Colored initials avatars with rounded-rect shape, "Request 1:1"
 * primary button on cards, pill-styled tags, profile page with two-column
 * layout for expertise/looking-for/can-help.
 */

defined('ABSPATH') || exit;

final class CBNexus_Directory {

	public static function init(): void {
		add_action('wp_ajax_cbnexus_directory_filter', [__CLASS__, 'ajax_filter']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
	}

	public static function enqueue_scripts(): void {
		global $post;
		if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'cbnexus_portal')) {
			return;
		}

		wp_enqueue_script(
			'cbnexus-directory',
			CBNEXUS_PLUGIN_URL . 'assets/js/directory.js',
			[],
			CBNEXUS_VERSION,
			true
		);

		wp_localize_script('cbnexus-directory', 'cbnexusDir', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('cbnexus_directory'),
		]);
	}

	public static function render(array $profile): void {
		if (isset($_GET['member_id']) && absint($_GET['member_id']) > 0) {
			self::render_member_profile(absint($_GET['member_id']), $profile);
			return;
		}

		$industries = CBNexus_Member_Service::get_industries();
		?>
		<div class="cbnexus-directory" id="cbnexus-directory">
			<div class="cbnexus-dir-controls">
				<div class="cbnexus-dir-search">
					<input type="text" id="cbnexus-dir-search" placeholder="<?php esc_attr_e('Search members...', 'circleblast-nexus'); ?>" />
				</div>
				<div class="cbnexus-dir-filters">
					<select id="cbnexus-dir-industry">
						<option value=""><?php esc_html_e('All Industries', 'circleblast-nexus'); ?></option>
						<?php foreach ($industries as $ind) : ?>
							<option value="<?php echo esc_attr($ind); ?>"><?php echo esc_html($ind); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="cbnexus-dir-view-toggle">
						<button type="button" class="cbnexus-view-btn active" data-view="grid" title="<?php esc_attr_e('Grid View', 'circleblast-nexus'); ?>">⊞</button>
						<button type="button" class="cbnexus-view-btn" data-view="list" title="<?php esc_attr_e('List View', 'circleblast-nexus'); ?>">☰</button>
					</div>
				</div>
			</div>

			<div class="cbnexus-dir-meta">
				<span id="cbnexus-dir-count"></span>
			</div>

			<div class="cbnexus-dir-loading" id="cbnexus-dir-loading" style="display:none;">
				<p><?php esc_html_e('Loading members...', 'circleblast-nexus'); ?></p>
			</div>

			<div class="cbnexus-dir-results cbnexus-dir-grid" id="cbnexus-dir-results">
				<?php
				$members = CBNexus_Member_Repository::get_all_members('active');
				echo self::render_cards($members);
				?>
			</div>
			<script>document.getElementById('cbnexus-dir-count').textContent = '<?php echo esc_js(sprintf(_n('%d member', '%d members', count($members), 'circleblast-nexus'), count($members))); ?>';</script>
		</div>
		<?php
	}

	public static function ajax_filter(): void {
		check_ajax_referer('cbnexus_directory', 'nonce');

		if (!is_user_logged_in() || !CBNexus_Member_Repository::is_member(get_current_user_id())) {
			wp_send_json_error('Access denied.', 403);
		}

		$search   = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
		$industry = isset($_POST['industry']) ? sanitize_text_field(wp_unslash($_POST['industry'])) : '';
		$status   = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'active';

		if ($search !== '') {
			$members = CBNexus_Member_Repository::search($search, $status);
		} else {
			$members = CBNexus_Member_Repository::get_all_members($status);
		}

		if ($industry !== '') {
			$members = array_filter($members, function ($m) use ($industry) {
				return ($m['cb_industry'] ?? '') === $industry;
			});
			$members = array_values($members);
		}

		$html  = self::render_cards($members);
		$count = count($members);

		wp_send_json_success([
			'html'  => $html,
			'count' => $count,
			'label' => sprintf(_n('%d member', '%d members', $count, 'circleblast-nexus'), $count),
		]);
	}

	private static function render_cards(array $members): string {
		if (empty($members)) {
			return '<div class="cbnexus-dir-empty"><p>' . esc_html__('No members found matching your criteria.', 'circleblast-nexus') . '</p></div>';
		}

		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$html = '';

		foreach ($members as $m) {
			$profile_url = add_query_arg(['section' => 'directory', 'member_id' => $m['user_id']], $portal_url);
			$initials    = self::get_initials($m);
			$color       = self::get_avatar_color($m['user_id']);
			$photo       = !empty($m['cb_photo_url']) ? $m['cb_photo_url'] : '';
			$expertise   = is_array($m['cb_expertise']) ? $m['cb_expertise'] : [];

			$html .= '<div class="cbnexus-member-card" data-industry="' . esc_attr($m['cb_industry'] ?? '') . '">';

			// Avatar
			$html .= '<div class="cbnexus-member-avatar" style="background:' . esc_attr($color) . '14;">';
			if ($photo) {
				$html .= '<img src="' . esc_url($photo) . '" alt="' . esc_attr($m['display_name']) . '" />';
			} else {
				$html .= '<span class="cbnexus-member-initials" style="color:' . esc_attr($color) . ';">' . esc_html($initials) . '</span>';
			}
			$html .= '</div>';

			// Info (clickable)
			$html .= '<a href="' . esc_url($profile_url) . '" style="text-decoration:none;color:inherit;display:contents;">';
			$html .= '<div class="cbnexus-member-info">';
			$html .= '<h3 class="cbnexus-member-name">' . esc_html($m['display_name']) . '</h3>';
			$html .= '<p class="cbnexus-member-title">' . esc_html($m['cb_title'] ?? '') . '</p>';

			if (!empty($m['cb_industry'])) {
				$html .= '<span class="cbnexus-member-industry">' . esc_html($m['cb_industry']) . '</span>';
			}

			if (!empty($expertise)) {
				$html .= '<div class="cbnexus-member-tags">';
				$show = array_slice($expertise, 0, 3);
				foreach ($show as $tag) {
					$html .= '<span class="cbnexus-tag">' . esc_html($tag) . '</span>';
				}
				if (count($expertise) > 3) {
					$html .= '<span class="cbnexus-tag cbnexus-tag-more">+' . (count($expertise) - 3) . '</span>';
				}
				$html .= '</div>';
			}

			$html .= '</div>';
			$html .= '</a>';

			// Quick Contact bar + Request 1:1
			$html .= '<div class="cbnexus-member-actions">';
			$html .= '<div class="cbnexus-quick-contact">';
			if (!empty($m['user_email'])) {
				$html .= '<a href="mailto:' . esc_attr($m['user_email']) . '" class="cbnexus-contact-btn" title="' . esc_attr($m['user_email']) . '">✉️</a>';
			}
			if (!empty($m['cb_phone'])) {
				$html .= '<a href="tel:' . esc_attr($m['cb_phone']) . '" class="cbnexus-contact-btn" title="' . esc_attr($m['cb_phone']) . '">📱</a>';
			}
			if (!empty($m['cb_linkedin'])) {
				$html .= '<a href="' . esc_url($m['cb_linkedin']) . '" target="_blank" rel="noopener" class="cbnexus-contact-btn" title="LinkedIn">💼</a>';
			}
			$html .= '</div>';
			$html .= '<button type="button" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm cbnexus-request-meeting-btn" data-member-id="' . esc_attr($m['user_id']) . '" style="border-radius:10px;">' . esc_html__('Request 1:1', 'circleblast-nexus') . '</button>';
			$html .= '</div>';

			$html .= '</div>';
		}

		return $html;
	}

	private static function render_member_profile(int $member_id, array $viewer_profile): void {
		$member = CBNexus_Member_Repository::get_profile($member_id);

		if (!$member) {
			echo '<div class="cbnexus-card"><p>' . esc_html__('Member not found.', 'circleblast-nexus') . '</p></div>';
			return;
		}

		$portal_url  = CBNexus_Portal_Router::get_portal_url();
		$back_url    = add_query_arg('section', 'directory', $portal_url);
		$photo       = !empty($member['cb_photo_url']) ? $member['cb_photo_url'] : '';
		$initials    = self::get_initials($member);
		$color       = self::get_avatar_color($member_id);
		$expertise   = is_array($member['cb_expertise']) ? $member['cb_expertise'] : [];
		$looking_for = is_array($member['cb_looking_for']) ? $member['cb_looking_for'] : [];
		$can_help    = is_array($member['cb_can_help_with']) ? $member['cb_can_help_with'] : [];
		$is_self     = ($member_id === ($viewer_profile['user_id'] ?? 0));
		?>
		<div class="cbnexus-profile-page">
			<a href="<?php echo esc_url($back_url); ?>" class="cbnexus-back-link">
				&larr; <?php esc_html_e('Back to Directory', 'circleblast-nexus'); ?>
			</a>

			<div class="cbnexus-card" style="padding:26px;">
				<div class="cbnexus-profile-header" style="border:none;padding:0;margin:0;box-shadow:none;">
					<div class="cbnexus-profile-avatar-lg" style="background:<?php echo esc_attr($color); ?>14;">
						<?php if ($photo) : ?>
							<img src="<?php echo esc_url($photo); ?>" alt="<?php echo esc_attr($member['display_name']); ?>" />
						<?php else : ?>
							<span class="cbnexus-member-initials-lg" style="color:<?php echo esc_attr($color); ?>;"><?php echo esc_html($initials); ?></span>
						<?php endif; ?>
					</div>
					<div class="cbnexus-profile-header-info">
						<h2><?php echo esc_html($member['display_name']); ?></h2>
						<p class="cbnexus-profile-jobtitle"><?php echo esc_html($member['cb_title'] ?? ''); ?><?php if (!empty($member['cb_company'])) : ?> at <?php echo esc_html($member['cb_company']); ?><?php endif; ?></p>
						<?php if (!empty($member['cb_industry'])) : ?>
							<span class="cbnexus-tag"><?php echo esc_html($member['cb_industry']); ?></span>
						<?php endif; ?>
						<p class="cbnexus-profile-since"><?php printf(esc_html__('Member since %s', 'circleblast-nexus'), esc_html($member['cb_join_date'] ?? '—')); ?></p>
					</div>
					<div class="cbnexus-profile-header-actions">
						<?php if (!$is_self) : ?>
							<button type="button" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm cbnexus-request-meeting-btn" data-member-id="<?php echo esc_attr($member_id); ?>">
								<?php esc_html_e('Request 1:1', 'circleblast-nexus'); ?>
							</button>
						<?php endif; ?>
						<?php if (!empty($member['cb_linkedin'])) : ?>
							<a href="<?php echo esc_url($member['cb_linkedin']); ?>" target="_blank" rel="noopener" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm"><?php esc_html_e('LinkedIn', 'circleblast-nexus'); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="cbnexus-profile-columns">
				<?php if (!empty($member['cb_bio'])) : ?>
					<div class="cbnexus-card">
						<h3><?php esc_html_e('About', 'circleblast-nexus'); ?></h3>
						<p style="margin:0;font-size:14px;color:var(--cb-text-sec);line-height:1.7;"><?php echo nl2br(esc_html($member['cb_bio'])); ?></p>
					</div>
				<?php endif; ?>

				<div class="cbnexus-card">
					<?php if (!empty($expertise)) : ?>
						<h3><?php esc_html_e('Expertise', 'circleblast-nexus'); ?></h3>
						<div class="cbnexus-tag-list" style="margin-bottom:14px;">
							<?php foreach ($expertise as $tag) : ?>
								<span class="cbnexus-tag"><?php echo esc_html($tag); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if (!empty($looking_for)) : ?>
						<h3><?php esc_html_e('Looking For', 'circleblast-nexus'); ?></h3>
						<div class="cbnexus-tag-list" style="margin-bottom:14px;">
							<?php foreach ($looking_for as $tag) : ?>
								<span class="cbnexus-tag cbnexus-tag-looking"><?php echo esc_html($tag); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if (!empty($can_help)) : ?>
						<h3><?php esc_html_e('Can Help With', 'circleblast-nexus'); ?></h3>
						<div class="cbnexus-tag-list">
							<?php foreach ($can_help as $tag) : ?>
								<span class="cbnexus-tag cbnexus-tag-help"><?php echo esc_html($tag); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	private static function get_initials(array $m): string {
		$first = $m['first_name'] ?? '';
		$last  = $m['last_name'] ?? '';
		if ($first && $last) {
			return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
		}
		$display = $m['display_name'] ?? '?';
		return strtoupper(mb_substr($display, 0, 2));
	}

	/**
	 * Generate a consistent avatar color based on user ID.
	 */
	private static function get_avatar_color(int $user_id): string {
		$colors = ['#6366f1', '#059669', '#dc2626', '#2563eb', '#d946ef', '#0891b2', '#ea580c', '#65a30d', '#8b5cf6', '#0d9488'];
		return $colors[$user_id % count($colors)];
	}
}
