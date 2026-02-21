<?php
/**
 * Portal Admin – Matching Tab
 *
 * Extracted from class-portal-admin.php for maintainability.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Matching {

	public static function render(): void {
		if (!current_user_can('cbnexus_manage_matching_rules')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

		$rules   = CBNexus_Matching_Engine::get_all_rules();
		$dry_run = isset($_GET['dry_run']);
		$suggestions = $dry_run ? CBNexus_Matching_Engine::dry_run(20) : [];
		$notice  = sanitize_key($_GET['pa_notice'] ?? '');

		$last_cycle  = CBNexus_Suggestion_Generator::get_last_cycle();
		$cycle_stats = CBNexus_Suggestion_Generator::get_cycle_stats();
		$tips = CBNexus_Portal_Help::get_tooltips_for('matching');
		?>
		<?php CBNexus_Portal_Admin::render_notice($notice); ?>

		<!-- Cycle Status -->
		<div class="cbnexus-card">
			<h2>Suggestion Cycle</h2>
			<div class="cbnexus-admin-stats-row">
				<?php CBNexus_Portal_Admin::stat_card('Last Run', $last_cycle ? esc_html($last_cycle['timestamp']) : 'Never', $tips['last_run'] ?? ''); ?>
				<?php CBNexus_Portal_Admin::stat_card('Total Suggestions', $cycle_stats['total'], $tips['total_suggestions'] ?? ''); ?>
				<?php CBNexus_Portal_Admin::stat_card('Pending', $cycle_stats['pending'], $tips['pending'] ?? ''); ?>
				<?php CBNexus_Portal_Admin::stat_card('Accepted', $cycle_stats['accepted'], $tips['accepted'] ?? ''); ?>
				<?php if ($cycle_stats['total'] > 0) : CBNexus_Portal_Admin::stat_card('Accept Rate', round($cycle_stats['accepted'] / $cycle_stats['total'] * 100) . '%', $tips['accept_rate'] ?? ''); endif; ?>
			</div>
			<div class="cbnexus-admin-button-row">
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('matching', ['dry_run' => 1])); ?>" class="cbnexus-btn">Preview Suggestions</a>
				<?php if (current_user_can('cbnexus_run_matching_cycle')) : ?>
					<a href="<?php echo esc_url(wp_nonce_url(
						CBNexus_Portal_Admin::admin_url('matching', ['cbnexus_portal_run_cycle' => 1]),
						'cbnexus_portal_run_cycle', '_panonce'
					)); ?>" class="cbnexus-btn cbnexus-btn-accent" onclick="return confirm('Run the suggestion cycle? This will send match emails to all paired members.');">Run Cycle</a>
				<?php endif; ?>
			</div>
		</div>

		<!-- Rules Config -->
		<div class="cbnexus-card">
			<h2>Matching Rules</h2>
			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_save_matching_rules'); ?>
				<div class="cbnexus-admin-table-wrap">
					<table class="cbnexus-admin-table">
						<thead><tr>
							<th style="width:50px;">Active</th>
							<th>Rule</th>
							<th>Description</th>
							<th style="width:90px;">Weight</th>
						</tr></thead>
						<tbody>
						<?php foreach ($rules as $rule) : ?>
							<tr>
								<td><input type="checkbox" name="active_<?php echo esc_attr($rule->id); ?>" value="1" <?php checked($rule->is_active, 1); ?> /></td>
								<td><strong><?php echo esc_html($rule->label); ?></strong></td>
								<td class="cbnexus-admin-meta"><?php echo esc_html($rule->description); ?></td>
								<td><input type="number" name="weight_<?php echo esc_attr($rule->id); ?>" value="<?php echo esc_attr($rule->weight); ?>" step="0.25" min="-5" max="10" class="cbnexus-input-sm" /></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<button type="submit" name="cbnexus_portal_save_rules" value="1" class="cbnexus-btn cbnexus-btn-accent">Save Rules</button>
			</form>
		</div>

		<?php if ($dry_run) : ?>
		<div class="cbnexus-card">
			<h2>Dry Run Preview</h2>
			<?php if (empty($suggestions)) : ?>
				<p class="cbnexus-text-muted">No suggestions generated. Ensure at least 2 active members and active rules.</p>
			<?php else : ?>
				<div class="cbnexus-admin-table-wrap">
					<table class="cbnexus-admin-table">
						<thead><tr>
							<th style="width:40px;">#</th>
							<th>Member A</th>
							<th>Member B</th>
							<th style="width:70px;">Score</th>
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
				</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<?php
	}

	public static function handle_save_rules(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_matching_rules')) { return; }
		if (!current_user_can('cbnexus_manage_matching_rules')) { return; }

		$rules = CBNexus_Matching_Engine::get_all_rules();
		foreach ($rules as $rule) {
			$id = (int) $rule->id;
			CBNexus_Matching_Engine::update_rule($id, [
				'weight'    => isset($_POST['weight_' . $id]) ? (float) $_POST['weight_' . $id] : (float) $rule->weight,
				'is_active' => isset($_POST['active_' . $id]) ? 1 : 0,
			]);
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('matching', ['pa_notice' => 'rules_saved']));
		exit;
	}

	public static function handle_run_cycle(): void {
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_run_cycle')) { return; }
		if (!current_user_can('cbnexus_manage_matching_rules')) { return; }

		$result = CBNexus_Suggestion_Generator::run_cycle(true);

		$notice = (!empty($result['skipped'])) ? 'cycle_skipped' : 'cycle_complete';
		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('matching', ['pa_notice' => $notice]));
		exit;
	}
}