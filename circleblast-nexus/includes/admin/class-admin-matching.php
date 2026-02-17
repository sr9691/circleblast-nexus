<?php
/**
 * Admin Matching
 *
 * ITER-0010: Admin page for configuring matching rules and previewing
 * suggestions via dry-run mode.
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
			__('Matching Rules', 'circleblast-nexus'),
			__('Matching', 'circleblast-nexus'),
			'cbnexus_manage_members',
			'cbnexus-matching',
			[__CLASS__, 'render_page']
		);
	}

	public static function handle_actions(): void {
		// Save rules.
		if (isset($_POST['cbnexus_save_rules'])) {
			self::handle_save_rules();
		}
	}

	private static function handle_save_rules(): void {
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(wp_unslash($_POST['_wpnonce']), 'cbnexus_save_matching_rules')) {
			wp_die(__('Security check failed.', 'circleblast-nexus'));
		}
		if (!current_user_can('cbnexus_manage_members')) {
			wp_die(__('Permission denied.', 'circleblast-nexus'));
		}

		$rules = CBNexus_Matching_Engine::get_all_rules();
		foreach ($rules as $rule) {
			$id = (int) $rule->id;
			$weight    = isset($_POST['weight_' . $id]) ? (float) $_POST['weight_' . $id] : (float) $rule->weight;
			$is_active = isset($_POST['active_' . $id]) ? 1 : 0;

			CBNexus_Matching_Engine::update_rule($id, [
				'weight'    => $weight,
				'is_active' => $is_active,
			]);
		}

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-matching&cbnexus_notice=rules_saved'));
		exit;
	}

	public static function render_page(): void {
		if (!current_user_can('cbnexus_manage_members')) {
			wp_die(__('Permission denied.', 'circleblast-nexus'));
		}

		$rules      = CBNexus_Matching_Engine::get_all_rules();
		$dry_run    = isset($_GET['dry_run']);
		$suggestions = $dry_run ? CBNexus_Matching_Engine::dry_run(20) : [];
		$notice     = isset($_GET['cbnexus_notice']) ? sanitize_key($_GET['cbnexus_notice']) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Matching Rules', 'circleblast-nexus'); ?></h1>

			<?php if ($notice === 'rules_saved') : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Matching rules updated.', 'circleblast-nexus'); ?></p></div><?php endif; ?>
			<?php if ($notice === 'cycle_complete') : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Suggestion cycle completed. Emails sent.', 'circleblast-nexus'); ?></p></div><?php endif; ?>

			<!-- Rules Configuration -->
			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_save_matching_rules'); ?>
				<table class="wp-list-table widefat fixed striped">
					<thead><tr>
						<th style="width:40px;"><?php esc_html_e('Active', 'circleblast-nexus'); ?></th>
						<th style="width:200px;"><?php esc_html_e('Rule', 'circleblast-nexus'); ?></th>
						<th><?php esc_html_e('Description', 'circleblast-nexus'); ?></th>
						<th style="width:100px;"><?php esc_html_e('Weight', 'circleblast-nexus'); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ($rules as $rule) : ?>
							<tr>
								<td><input type="checkbox" name="active_<?php echo esc_attr($rule->id); ?>" value="1" <?php checked($rule->is_active, 1); ?> /></td>
								<td><strong><?php echo esc_html($rule->label); ?></strong><br><code><?php echo esc_html($rule->rule_type); ?></code></td>
								<td><?php echo esc_html($rule->description); ?></td>
								<td><input type="number" name="weight_<?php echo esc_attr($rule->id); ?>" value="<?php echo esc_attr($rule->weight); ?>" step="0.25" min="-5" max="10" style="width:80px;" /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="submit">
					<?php submit_button(__('Save Rules', 'circleblast-nexus'), 'primary', 'cbnexus_save_rules', false); ?>
					&nbsp;
					<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-matching&dry_run=1')); ?>" class="button"><?php esc_html_e('Preview Suggestions (Dry Run)', 'circleblast-nexus'); ?></a>
					&nbsp;
					<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cbnexus-matching&cbnexus_run_cycle=1'), 'cbnexus_run_suggestion_cycle')); ?>" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Run the suggestion cycle? This will create meeting suggestions and send emails to all matched members.', 'circleblast-nexus'); ?>');"><?php esc_html_e('Run Suggestion Cycle', 'circleblast-nexus'); ?></a>
				</p>
			</form>

			<!-- Cycle Status -->
			<?php
			$last_cycle = CBNexus_Suggestion_Generator::get_last_cycle();
			$cycle_stats = CBNexus_Suggestion_Generator::get_cycle_stats();
			?>
			<h2><?php esc_html_e('Suggestion Cycle Status', 'circleblast-nexus'); ?></h2>
			<table class="widefat" style="max-width:500px;">
				<tbody>
					<tr><th><?php esc_html_e('Last Cycle', 'circleblast-nexus'); ?></th><td><?php echo $last_cycle ? esc_html($last_cycle['timestamp'] . ' — ' . $last_cycle['generated'] . ' pairs, ' . $last_cycle['emailed'] . ' emails') : esc_html__('Never run', 'circleblast-nexus'); ?></td></tr>
					<tr><th><?php esc_html_e('Total Auto Suggestions', 'circleblast-nexus'); ?></th><td><?php echo esc_html($cycle_stats['total']); ?></td></tr>
					<tr><th><?php esc_html_e('Pending Response', 'circleblast-nexus'); ?></th><td><?php echo esc_html($cycle_stats['pending']); ?></td></tr>
					<tr><th><?php esc_html_e('Accepted', 'circleblast-nexus'); ?></th><td><?php echo esc_html($cycle_stats['accepted']); ?></td></tr>
					<tr><th><?php esc_html_e('Declined', 'circleblast-nexus'); ?></th><td><?php echo esc_html($cycle_stats['declined']); ?></td></tr>
					<?php if ($cycle_stats['total'] > 0) : ?>
					<tr><th><?php esc_html_e('Acceptance Rate', 'circleblast-nexus'); ?></th><td><strong><?php echo esc_html(round($cycle_stats['accepted'] / $cycle_stats['total'] * 100)) . '%'; ?></strong></td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ($dry_run) : ?>
				<h2><?php esc_html_e('Dry Run Preview', 'circleblast-nexus'); ?></h2>
				<?php if (empty($suggestions)) : ?>
					<p><?php esc_html_e('No suggestions generated. Check that you have at least 2 active members and active rules.', 'circleblast-nexus'); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr>
							<th style="width:40px;">#</th>
							<th><?php esc_html_e('Member A', 'circleblast-nexus'); ?></th>
							<th><?php esc_html_e('Member B', 'circleblast-nexus'); ?></th>
							<th style="width:80px;"><?php esc_html_e('Score', 'circleblast-nexus'); ?></th>
							<th><?php esc_html_e('Breakdown', 'circleblast-nexus'); ?></th>
						</tr></thead>
						<tbody>
							<?php $rank = 0; foreach ($suggestions as $s) : $rank++; ?>
								<tr>
									<td><?php echo esc_html($rank); ?></td>
									<td><?php echo esc_html($s['member_a_name']); ?></td>
									<td><?php echo esc_html($s['member_b_name']); ?></td>
									<td><strong><?php echo esc_html($s['score']); ?></strong></td>
									<td style="font-size:12px;">
										<?php foreach ($s['breakdown'] as $type => $bd) :
											if ((float) $bd['weighted'] == 0.0) { continue; }
										?>
											<span title="<?php echo esc_attr($type); ?>: raw=<?php echo esc_attr($bd['raw']); ?> × weight=<?php echo esc_attr($bd['weight']); ?>"
												  style="display:inline-block;padding:1px 6px;margin:1px 2px;background:#ebf4ff;border-radius:3px;">
												<?php echo esc_html($type); ?>:<?php echo esc_html($bd['weighted']); ?>
											</span>
										<?php endforeach; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
