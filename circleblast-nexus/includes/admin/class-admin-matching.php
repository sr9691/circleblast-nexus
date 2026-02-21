<?php
/**
 * WP-Admin Matching
 *
 * Manages the 1:1 matching rules engine and suggestion cycles
 * from the WordPress admin dashboard (submenu under CircleBlast).
 */

defined('ABSPATH') || exit;

final class CBNexus_Admin_Matching {

	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'register_menu']);
		add_action('admin_init', [__CLASS__, 'handle_actions']);
	}

	public static function register_menu(): void {
		add_submenu_page(
			'cbnexus-members',
			__('Matching', 'circleblast-nexus'),
			__('Matching', 'circleblast-nexus'),
			'cbnexus_manage_matching_rules',
			'cbnexus-matching',
			[__CLASS__, 'render_page']
		);
	}

	// ─── Actions ───────────────────────────────────────────────────────

	public static function handle_actions(): void {
		if (isset($_POST['cbnexus_save_matching_rules'])) { self::handle_save_rules(); }
		if (isset($_GET['cbnexus_run_cycle']))             { self::handle_run_cycle(); }
	}

	private static function handle_save_rules(): void {
		check_admin_referer('cbnexus_save_matching_rules');
		if (!current_user_can('cbnexus_manage_matching_rules')) { wp_die('Permission denied.'); }

		$rules = CBNexus_Matching_Engine::get_all_rules();
		foreach ($rules as $rule) {
			$id = (int) $rule->id;
			CBNexus_Matching_Engine::update_rule($id, [
				'weight'    => isset($_POST['weight_' . $id]) ? (float) $_POST['weight_' . $id] : (float) $rule->weight,
				'is_active' => isset($_POST['active_' . $id]) ? 1 : 0,
			]);
		}

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-matching&cbnexus_notice=rules_saved'));
		exit;
	}

	private static function handle_run_cycle(): void {
		if (!wp_verify_nonce(wp_unslash($_GET['_wpnonce'] ?? ''), 'cbnexus_run_cycle')) { return; }
		if (!current_user_can('cbnexus_manage_matching_rules')) { return; }

		$result = CBNexus_Suggestion_Generator::run_cycle(true);

		$notice = (!empty($result['skipped'])) ? 'cycle_skipped' : 'cycle_complete';
		wp_safe_redirect(admin_url('admin.php?page=cbnexus-matching&cbnexus_notice=' . $notice));
		exit;
	}

	// ─── Render ────────────────────────────────────────────────────────

	public static function render_page(): void {
		if (!current_user_can('cbnexus_manage_matching_rules')) { wp_die('Permission denied.'); }

		$rules       = CBNexus_Matching_Engine::get_all_rules();
		$dry_run     = isset($_GET['dry_run']);
		$suggestions = $dry_run ? CBNexus_Matching_Engine::dry_run(20) : [];
		$notice      = sanitize_key($_GET['cbnexus_notice'] ?? '');
		$last_cycle  = CBNexus_Suggestion_Generator::get_last_cycle();
		$cycle_stats = CBNexus_Suggestion_Generator::get_cycle_stats();

		$notices = [
			'rules_saved'    => __('Matching rules saved.', 'circleblast-nexus'),
			'cycle_complete' => __('Suggestion cycle completed. Emails sent.', 'circleblast-nexus'),
			'cycle_skipped'  => __('Suggestion cycle skipped — a cycle already ran within the last 24 hours.', 'circleblast-nexus'),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e('1:1 Matching', 'circleblast-nexus'); ?></h1>

			<?php if ($notice && isset($notices[$notice])) : ?>
				<div class="notice notice-<?php echo str_contains($notice, 'skip') ? 'warning' : 'success'; ?> is-dismissible"><p><?php echo esc_html($notices[$notice]); ?></p></div>
			<?php endif; ?>

			<!-- Cycle Status -->
			<div class="card" style="max-width:none;padding:16px;">
				<h2><?php esc_html_e('Suggestion Cycle', 'circleblast-nexus'); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e('Last Run', 'circleblast-nexus'); ?></th><td><?php echo esc_html($last_cycle ? $last_cycle['timestamp'] : 'Never'); ?></td></tr>
					<tr><th><?php esc_html_e('Total Suggestions', 'circleblast-nexus'); ?></th><td><?php echo esc_html($cycle_stats['total']); ?></td></tr>
					<tr><th><?php esc_html_e('Pending', 'circleblast-nexus'); ?></th><td><?php echo esc_html($cycle_stats['pending']); ?></td></tr>
					<tr><th><?php esc_html_e('Accepted', 'circleblast-nexus'); ?></th><td><?php echo esc_html($cycle_stats['accepted']); ?></td></tr>
					<?php if ($cycle_stats['total'] > 0) : ?>
						<tr><th><?php esc_html_e('Accept Rate', 'circleblast-nexus'); ?></th><td><?php echo esc_html(round($cycle_stats['accepted'] / $cycle_stats['total'] * 100)); ?>%</td></tr>
					<?php endif; ?>
				</table>
				<p>
					<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-matching&dry_run=1')); ?>" class="button"><?php esc_html_e('Preview Suggestions', 'circleblast-nexus'); ?></a>
					<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cbnexus-matching&cbnexus_run_cycle=1'), 'cbnexus_run_cycle')); ?>" class="button button-primary" onclick="return confirm('<?php esc_attr_e('Run the suggestion cycle? This will send match emails to all paired members.', 'circleblast-nexus'); ?>');"><?php esc_html_e('Run Cycle', 'circleblast-nexus'); ?></a>
				</p>
			</div>

			<!-- Rules Config -->
			<div class="card" style="max-width:none;padding:16px;margin-top:16px;">
				<h2><?php esc_html_e('Matching Rules', 'circleblast-nexus'); ?></h2>
				<form method="post" action="">
					<?php wp_nonce_field('cbnexus_save_matching_rules'); ?>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr>
							<th style="width:50px;"><?php esc_html_e('Active', 'circleblast-nexus'); ?></th>
							<th><?php esc_html_e('Rule', 'circleblast-nexus'); ?></th>
							<th><?php esc_html_e('Description', 'circleblast-nexus'); ?></th>
							<th style="width:90px;"><?php esc_html_e('Weight', 'circleblast-nexus'); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ($rules as $rule) : ?>
							<tr>
								<td><input type="checkbox" name="active_<?php echo esc_attr($rule->id); ?>" value="1" <?php checked($rule->is_active, 1); ?> /></td>
								<td><strong><?php echo esc_html($rule->label); ?></strong></td>
								<td><?php echo esc_html($rule->description); ?></td>
								<td><input type="number" name="weight_<?php echo esc_attr($rule->id); ?>" value="<?php echo esc_attr($rule->weight); ?>" step="0.25" min="-5" max="10" style="width:80px;" /></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php submit_button(__('Save Rules', 'circleblast-nexus'), 'primary', 'cbnexus_save_matching_rules'); ?>
				</form>
			</div>

			<?php if ($dry_run) : ?>
			<div class="card" style="max-width:none;padding:16px;margin-top:16px;">
				<h2><?php esc_html_e('Dry Run Preview', 'circleblast-nexus'); ?></h2>
				<?php if (empty($suggestions)) : ?>
					<p><?php esc_html_e('No suggestions generated. Ensure at least 2 active members and active rules.', 'circleblast-nexus'); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr>
							<th style="width:40px;">#</th>
							<th><?php esc_html_e('Member A', 'circleblast-nexus'); ?></th>
							<th><?php esc_html_e('Member B', 'circleblast-nexus'); ?></th>
							<th style="width:70px;"><?php esc_html_e('Score', 'circleblast-nexus'); ?></th>
						</tr></thead>
						<tbody>
						<?php $rank = 0; foreach ($suggestions as $s) : $rank++; ?>
							<tr>
								<td><?php echo esc_html($rank); ?></td>
								<td><?php echo esc_html($s['member_a_name']); ?></td>
								<td><?php echo esc_html($s['member_b_name']); ?></td>
								<td><strong><?php echo esc_html($s['score']); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}
}