<?php
/**
 * CircleBlast Nexus — Database Seeder
 *
 * Seeds 1–2 rows per table with realistic demo data.
 *
 * USAGE (pick one):
 *   1. WP-CLI:  wp eval-file seed-data.php
 *   2. Browser: Place in plugin root, visit /wp-admin/ and add ?cbnexus_seed=1
 *               then delete the file afterward.
 *
 * Safe to run multiple times — checks for existing seed data before inserting.
 */

// If loaded via browser URL param, hook into admin_init.
if (defined('ABSPATH') && is_admin() && isset($_GET['cbnexus_seed'])) {
	add_action('admin_init', 'cbnexus_run_seed');
} elseif (defined('ABSPATH')) {
	// WP-CLI context — run immediately.
	cbnexus_run_seed();
}

function cbnexus_run_seed(): void {
	global $wpdb;

	if (!current_user_can('manage_options') && !defined('WP_CLI')) {
		wp_die('Admin access required.');
	}

	$now       = gmdate('Y-m-d H:i:s');
	$today     = gmdate('Y-m-d');
	$next_week = gmdate('Y-m-d', strtotime('+7 days'));
	$last_week = gmdate('Y-m-d', strtotime('-7 days'));
	$last_month = gmdate('Y-m-d', strtotime('-30 days'));
	$results   = [];

	// ─── 1. SEED MEMBERS (2 WP users with cb_member role + meta) ──────

	$member_a_id = username_exists('demo_alex');
	if (!$member_a_id) {
		$member_a_id = wp_insert_user([
			'user_login'   => 'demo_alex',
			'user_email'   => 'alex.demo@circleblast.test',
			'user_pass'    => wp_generate_password(16),
			'first_name'   => 'Alex',
			'last_name'    => 'Rivera',
			'display_name' => 'Alex Rivera',
			'role'         => 'cb_member',
		]);
		if (!is_wp_error($member_a_id)) {
			$meta_a = [
				'cb_company'         => 'Meridian Consulting',
				'cb_title'           => 'Managing Partner',
				'cb_industry'        => 'Consulting',
				'cb_expertise'       => json_encode(['Strategy', 'M&A', 'Leadership']),
				'cb_looking_for'     => json_encode(['Referral partners', 'Tech founders']),
				'cb_can_help_with'   => json_encode(['Business strategy', 'Growth planning']),
				'cb_phone'           => '317-555-0101',
				'cb_linkedin'        => 'https://linkedin.com/in/alexrivera-demo',
				'cb_website'         => 'https://meridianconsulting.example.com',
				'cb_bio'             => 'I help mid-market companies navigate strategic pivots and growth milestones. 15 years in management consulting.',
				'cb_photo_url'       => '',
				'cb_referred_by'     => '',
				'cb_join_date'       => $last_month,
				'cb_member_status'   => 'active',
				'cb_onboarding_stage'=> 'ambassador',
				'cb_ambassador_id'   => '',
				'cb_notes_admin'     => 'Founding member. Very active connector.',
			];
			foreach ($meta_a as $key => $val) {
				update_user_meta($member_a_id, $key, $val);
			}
			$results[] = "✅ Created member: Alex Rivera (ID: {$member_a_id})";
		}
	} else {
		$results[] = "⏭ Member Alex Rivera already exists (ID: {$member_a_id})";
	}

	$member_b_id = username_exists('demo_jordan');
	if (!$member_b_id) {
		$member_b_id = wp_insert_user([
			'user_login'   => 'demo_jordan',
			'user_email'   => 'jordan.demo@circleblast.test',
			'user_pass'    => wp_generate_password(16),
			'first_name'   => 'Jordan',
			'last_name'    => 'Patel',
			'display_name' => 'Jordan Patel',
			'role'         => 'cb_member',
		]);
		if (!is_wp_error($member_b_id)) {
			$meta_b = [
				'cb_company'         => 'Patel Wealth Advisors',
				'cb_title'           => 'Founder & CFP',
				'cb_industry'        => 'Financial Services',
				'cb_expertise'       => json_encode(['Wealth Management', 'Tax Strategy', 'Retirement Planning']),
				'cb_looking_for'     => json_encode(['Business owners', 'Real estate investors']),
				'cb_can_help_with'   => json_encode(['Financial planning', 'Tax optimization']),
				'cb_phone'           => '317-555-0202',
				'cb_linkedin'        => 'https://linkedin.com/in/jordanpatel-demo',
				'cb_website'         => 'https://patelwealth.example.com',
				'cb_bio'             => 'Certified Financial Planner helping entrepreneurs build and protect wealth. I believe every business decision is a financial decision.',
				'cb_photo_url'       => '',
				'cb_referred_by'     => 'Alex Rivera',
				'cb_join_date'       => $today,
				'cb_member_status'   => 'active',
				'cb_onboarding_stage'=> 'ignite',
				'cb_ambassador_id'   => (string) $member_a_id,
				'cb_notes_admin'     => 'Referred by Alex. Great energy at first visit.',
			];
			foreach ($meta_b as $key => $val) {
				update_user_meta($member_b_id, $key, $val);
			}
			$results[] = "✅ Created member: Jordan Patel (ID: {$member_b_id})";
		}
	} else {
		$results[] = "⏭ Member Jordan Patel already exists (ID: {$member_b_id})";
	}

	// ─── 2. cb_meetings (1 completed, 1 pending) ──────────────────────

	$t = $wpdb->prefix . 'cb_meetings';
	if (cbnexus_seed_table_empty($t)) {
		$wpdb->insert($t, [
			'member_a_id'  => $member_a_id,
			'member_b_id'  => $member_b_id,
			'status'       => 'completed',
			'source'       => 'manual',
			'completed_at' => $last_week . ' 14:00:00',
			'notes_status' => 'submitted',
			'created_at'   => $last_month . ' 10:00:00',
			'updated_at'   => $last_week . ' 14:00:00',
		]);
		$meeting_1_id = $wpdb->insert_id;

		$wpdb->insert($t, [
			'member_a_id'  => $member_b_id,
			'member_b_id'  => $member_a_id,
			'status'       => 'pending',
			'source'       => 'suggestion',
			'score'        => 8.50,
			'suggested_at' => $now,
			'notes_status' => 'none',
			'created_at'   => $now,
			'updated_at'   => $now,
		]);
		$results[] = "✅ Seeded cb_meetings (2 rows)";
	} else {
		$meeting_1_id = $wpdb->get_var("SELECT id FROM {$t} LIMIT 1");
		$results[] = "⏭ cb_meetings already has data";
	}

	// ─── 3. cb_meeting_notes (1 row) ──────────────────────────────────

	$t = $wpdb->prefix . 'cb_meeting_notes';
	if (cbnexus_seed_table_empty($t)) {
		$wpdb->insert($t, [
			'meeting_id'   => $meeting_1_id,
			'author_id'    => $member_a_id,
			'wins'         => 'Jordan introduced me to a potential client in real estate development.',
			'insights'     => 'We discovered overlapping networks in the Carmel business community.',
			'action_items' => 'Alex to send intro email to Marcus at Keystone Properties by Friday.',
			'rating'       => 5,
			'created_at'   => $last_week . ' 15:00:00',
		]);
		$results[] = "✅ Seeded cb_meeting_notes (1 row)";
	} else {
		$results[] = "⏭ cb_meeting_notes already has data";
	}

	// ─── 4. cb_meeting_responses (1 row) ──────────────────────────────

	$t = $wpdb->prefix . 'cb_meeting_responses';
	if (cbnexus_seed_table_empty($t)) {
		$wpdb->insert($t, [
			'meeting_id'   => $meeting_1_id,
			'responder_id' => $member_b_id,
			'response'     => 'accepted',
			'message'      => 'Looking forward to it! How about Thursday at Bru Burger?',
			'responded_at' => $last_month . ' 12:00:00',
		]);
		$results[] = "✅ Seeded cb_meeting_responses (1 row)";
	} else {
		$results[] = "⏭ cb_meeting_responses already has data";
	}

	// ─── 5. cb_email_log (2 rows) ─────────────────────────────────────

	$t = $wpdb->prefix . 'cb_email_log';
	if (cbnexus_seed_table_empty($t)) {
		$wpdb->insert($t, [
			'recipient_email' => 'alex.demo@circleblast.test',
			'template_id'     => 'welcome_member',
			'subject'         => 'Welcome to CircleBlast, Alex!',
			'status'          => 'sent',
			'sent_at'         => $last_month . ' 10:05:00',
		]);
		$wpdb->insert($t, [
			'recipient_email' => 'jordan.demo@circleblast.test',
			'template_id'     => 'meeting_request_received',
			'subject'         => 'New 1:1 request from Alex Rivera',
			'status'          => 'sent',
			'sent_at'         => $last_month . ' 10:30:00',
		]);
		$results[] = "✅ Seeded cb_email_log (2 rows)";
	} else {
		$results[] = "⏭ cb_email_log already has data";
	}

	// ─── 6. cb_circleup_meetings (1 row) ──────────────────────────────

	$t = $wpdb->prefix . 'cb_circleup_meetings';
	if (cbnexus_seed_table_empty($t)) {
		$admin_id = get_current_user_id() ?: 1;
		$wpdb->insert($t, [
			'meeting_date'     => $last_week,
			'title'            => 'February CircleUp',
			'full_transcript'  => "Alex Rivera: We closed the partnership with Keystone Properties this month — huge win.\nJordan Patel: I'm seeing a trend in clients asking about alternative investments. Anyone else?\nAlex Rivera: Action item — I'll prepare a short deck on cross-referral opportunities for next meeting.",
			'ai_summary'       => 'Discussion focused on recent partnership wins and emerging trends in alternative investments.',
			'curated_summary'  => '<p>Great energy this month. Alex shared a major partnership close, and Jordan flagged an emerging client trend around alternative investments that sparked a lively discussion.</p>',
			'duration_minutes' => 62,
			'status'           => 'published',
			'published_by'     => $admin_id,
			'published_at'     => $last_week . ' 18:00:00',
			'created_at'       => $last_week . ' 12:00:00',
			'updated_at'       => $last_week . ' 18:00:00',
		]);
		$circleup_id = $wpdb->insert_id;
		$results[] = "✅ Seeded cb_circleup_meetings (1 row)";
	} else {
		$circleup_id = $wpdb->get_var("SELECT id FROM {$t} LIMIT 1");
		$results[] = "⏭ cb_circleup_meetings already has data";
	}

	// ─── 7. cb_circleup_attendees (2 rows) ────────────────────────────

	$t = $wpdb->prefix . 'cb_circleup_attendees';
	if (cbnexus_seed_table_empty($t)) {
		$wpdb->insert($t, ['circleup_meeting_id' => $circleup_id, 'member_id' => $member_a_id, 'attendance_status' => 'present', 'created_at' => $last_week . ' 12:00:00']);
		$wpdb->insert($t, ['circleup_meeting_id' => $circleup_id, 'member_id' => $member_b_id, 'attendance_status' => 'present', 'created_at' => $last_week . ' 12:00:00']);
		$results[] = "✅ Seeded cb_circleup_attendees (2 rows)";
	} else {
		$results[] = "⏭ cb_circleup_attendees already has data";
	}

	// ─── 8. cb_circleup_items (3 rows: win, insight, action) ──────────

	$t = $wpdb->prefix . 'cb_circleup_items';
	if (cbnexus_seed_table_empty($t)) {
		$wpdb->insert($t, [
			'circleup_meeting_id' => $circleup_id,
			'item_type'   => 'win',
			'content'     => 'Closed the Keystone Properties partnership — first deal from a CircleBlast referral.',
			'speaker_id'  => $member_a_id,
			'status'      => 'published',
			'created_at'  => $last_week . ' 18:00:00',
			'updated_at'  => $last_week . ' 18:00:00',
		]);
		$wpdb->insert($t, [
			'circleup_meeting_id' => $circleup_id,
			'item_type'   => 'insight',
			'content'     => 'Growing client demand for alternative investment vehicles — potential group opportunity.',
			'speaker_id'  => $member_b_id,
			'status'      => 'published',
			'created_at'  => $last_week . ' 18:00:00',
			'updated_at'  => $last_week . ' 18:00:00',
		]);
		$wpdb->insert($t, [
			'circleup_meeting_id' => $circleup_id,
			'item_type'   => 'action',
			'content'     => 'Prepare cross-referral opportunity deck for next CircleUp.',
			'speaker_id'  => $member_a_id,
			'assigned_to' => $member_a_id,
			'due_date'    => $next_week,
			'status'      => 'pending',
			'created_at'  => $last_week . ' 18:00:00',
			'updated_at'  => $last_week . ' 18:00:00',
		]);
		$results[] = "✅ Seeded cb_circleup_items (3 rows)";
	} else {
		$results[] = "⏭ cb_circleup_items already has data";
	}

	// ─── 9. cb_analytics_snapshots (historical data for sparklines) ──

	$t = $wpdb->prefix . 'cb_analytics_snapshots';
	if (cbnexus_seed_table_empty($t)) {
		// 6 months of snapshot history for sparkline charts and trend arrows.
		$snapshot_data = [
			// [months_ago, total_members, meetings_total, network_density, wins_total]
			[5, 8,  5,  12, 3],
			[4, 10, 12, 18, 5],
			[3, 12, 20, 25, 8],
			[2, 14, 30, 32, 12],
			[1, 15, 38, 36, 15],
			[0, 16, 44, 42, 18],
		];

		$snap_count = 0;
		foreach ($snapshot_data as $row) {
			$snap_date = gmdate('Y-m-d', strtotime("-{$row[0]} months"));
			$snap_ts   = $snap_date . ' 23:59:00';
			$metrics = [
				'total_members'   => $row[1],
				'meetings_total'  => $row[2],
				'network_density' => $row[3],
				'wins_total'      => $row[4],
			];
			foreach ($metrics as $key => $val) {
				$wpdb->insert($t, [
					'snapshot_date' => $snap_date,
					'scope'         => 'club',
					'member_id'     => 0,
					'metric_key'    => $key,
					'metric_value'  => $val,
					'created_at'    => $snap_ts,
				]);
				$snap_count++;
			}
		}
		$results[] = "✅ Seeded cb_analytics_snapshots ({$snap_count} rows, 6 months history)";
	} else {
		$results[] = "⏭ cb_analytics_snapshots already has data";
	}

	// ─── 10. cb_candidates (1 row) ────────────────────────────────────

	$t = $wpdb->prefix . 'cb_candidates';
	if (cbnexus_seed_table_empty($t)) {
		$wpdb->insert($t, [
			'name'        => 'Morgan Chen',
			'email'       => 'morgan.chen@example.com',
			'company'     => 'Chen & Associates Law',
			'industry'    => 'Legal',
			'referrer_id' => $member_a_id,
			'stage'       => 'invited',
			'notes'       => 'Alex met Morgan at an IndyBar event. Business litigation focus — fills a gap in our group.',
			'created_at'  => $last_week . ' 09:00:00',
			'updated_at'  => $now,
		]);
		$results[] = "✅ Seeded cb_candidates (1 row)";
	} else {
		$results[] = "⏭ cb_candidates already has data";
	}

	// ─── 11. cb_events (2 rows) ───────────────────────────────────────

	$t = $wpdb->prefix . 'cb_events';
	if (cbnexus_seed_table_empty($t)) {
		$admin_id = get_current_user_id() ?: 1;
		$wpdb->insert($t, [
			'title'            => 'March CircleUp',
			'description'      => 'Our monthly gathering. Topic: Cross-referral strategies that actually work.',
			'event_date'       => gmdate('Y-m-d', strtotime('+14 days')),
			'event_time'       => '08:00:00',
			'end_time'         => '09:30:00',
			'location'         => 'Bru Burger Bar — Carmel',
			'location_url'     => 'https://maps.google.com/?q=Bru+Burger+Carmel',
			'audience'         => 'members',
			'category'         => 'networking',
			'reminder_notes'   => 'Breakfast is on the house. Bring a business card for the fishbowl draw.',
			'cost'             => 'Free',
			'organizer_id'     => $admin_id,
			'status'           => 'approved',
			'approved_by'      => $admin_id,
			'approved_at'      => $now,
			'created_at'       => $now,
			'updated_at'       => $now,
		]);
		$event_1_id = $wpdb->insert_id;

		$wpdb->insert($t, [
			'title'            => 'Entrepreneurs Happy Hour',
			'description'      => 'Casual networking over drinks. Bring a guest!',
			'event_date'       => gmdate('Y-m-d', strtotime('+21 days')),
			'event_time'       => '17:30:00',
			'end_time'         => '19:30:00',
			'location'         => 'Hotel Carmichael Rooftop',
			'audience'         => 'all',
			'category'         => 'social',
			'cost'             => 'Cash bar',
			'organizer_id'     => $member_a_id,
			'status'           => 'approved',
			'approved_by'      => $admin_id,
			'approved_at'      => $now,
			'created_at'       => $now,
			'updated_at'       => $now,
		]);
		$results[] = "✅ Seeded cb_events (2 rows)";
	} else {
		$event_1_id = $wpdb->get_var("SELECT id FROM {$t} LIMIT 1");
		$results[] = "⏭ cb_events already has data";
	}

	// ─── 12. cb_event_rsvps (2 rows) ──────────────────────────────────

	$t = $wpdb->prefix . 'cb_event_rsvps';
	if (cbnexus_seed_table_empty($t)) {
		$wpdb->insert($t, ['event_id' => $event_1_id, 'member_id' => $member_a_id, 'status' => 'going', 'created_at' => $now]);
		$wpdb->insert($t, ['event_id' => $event_1_id, 'member_id' => $member_b_id, 'status' => 'going', 'created_at' => $now]);
		$results[] = "✅ Seeded cb_event_rsvps (2 rows)";
	} else {
		$results[] = "⏭ cb_event_rsvps already has data";
	}

	// ─── 13. cb_recruitment_categories (2 rows) ───────────────────────

	$t = $wpdb->prefix . 'cb_recruitment_categories';
	if (cbnexus_seed_table_empty($t)) {
		$admin_id = get_current_user_id() ?: 1;
		$wpdb->insert($t, [
			'title'       => 'Business Attorney',
			'description' => 'Someone focused on business formation, contracts, and M&A. We have no legal representation in the group currently.',
			'industry'    => 'Legal',
			'priority'    => 'high',
			'sort_order'  => 1,
			'created_by'  => $admin_id,
			'created_at'  => $now,
			'updated_at'  => $now,
		]);
		$wpdb->insert($t, [
			'title'       => 'Commercial Real Estate Broker',
			'description' => 'Several members are expanding and need space. A CRE broker would get immediate referrals.',
			'industry'    => 'Real Estate',
			'priority'    => 'medium',
			'sort_order'  => 2,
			'created_by'  => $admin_id,
			'created_at'  => $now,
			'updated_at'  => $now,
		]);
		$results[] = "✅ Seeded cb_recruitment_categories (2 rows)";
	} else {
		$results[] = "⏭ cb_recruitment_categories already has data";
	}

	// ─── DONE ─────────────────────────────────────────────────────────

	$output = "\n╔══════════════════════════════════════════╗\n";
	$output .= "║   CircleBlast Nexus — Seed Complete     ║\n";
	$output .= "╚══════════════════════════════════════════╝\n\n";
	$output .= implode("\n", $results) . "\n";

	if (defined('WP_CLI')) {
		WP_CLI::log($output);
	} else {
		echo '<div class="notice notice-success" style="white-space:pre-wrap;font-family:monospace;padding:16px;">' . esc_html($output) . '</div>';
	}
}

/**
 * Helper: check if a table exists and is empty.
 */
function cbnexus_seed_table_empty(string $table): bool {
	global $wpdb;
	$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
	if (!$exists) { return false; }
	return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") === 0;
}