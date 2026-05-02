<?php
/**
 * One-time fix: Force-convert Layne Pass from accepted candidate to member.
 *
 * Bypasses the stage-change event and directly calls the conversion logic.
 *
 * USAGE:
 *   wp eval-file dev/fix-layne-pass.php
 *
 * DELETE THIS FILE AFTER RUNNING.
 */

defined('ABSPATH') || exit;

global $wpdb;

$table = $wpdb->prefix . 'cb_candidates';
$candidate = $wpdb->get_row(
	$wpdb->prepare("SELECT * FROM {$table} WHERE name LIKE %s AND stage = 'accepted'", '%Layne Pass%')
);

if (!$candidate) {
	WP_CLI::error('Candidate "Layne Pass" not found in accepted stage.');
}

WP_CLI::log("Found candidate: {$candidate->name} (ID {$candidate->id}, email: {$candidate->email})");

if (empty($candidate->email)) {
	WP_CLI::error('Candidate has no email — cannot create member. Add an email first and re-run.');
}

// Check if already a member.
$existing_user_id = email_exists($candidate->email);
if ($existing_user_id && CBNexus_Member_Repository::is_member($existing_user_id)) {
	WP_CLI::success("Already a member (user ID {$existing_user_id}). No action needed — check directory visibility.");

	$profile = CBNexus_Member_Repository::get_profile($existing_user_id);
	WP_CLI::log("  Status: " . ($profile['cb_member_status'] ?? 'NOT SET'));
	WP_CLI::log("  Role:   " . implode(', ', get_userdata($existing_user_id)->roles));
	return;
}

// Run the same conversion logic used by the acceptance automation.
$name_parts = explode(' ', trim($candidate->name), 2);
$first_name = $name_parts[0] ?? '';
$last_name  = $name_parts[1] ?? '';

$profile_data = [
	'cb_company'       => $candidate->company ?: '',
	'cb_industry'      => $candidate->industry ?: '',
	'cb_referred_by'   => $candidate->referrer_id ? (get_userdata($candidate->referrer_id)->display_name ?? '') : '',
	'cb_ambassador_id' => $candidate->referrer_id ?: '',
];

if ($existing_user_id) {
	// Promote existing WP user to cb_member.
	$user = get_userdata($existing_user_id);
	$user->add_role('cb_member');
	$profile_data['cb_member_status']    = 'active';
	$profile_data['cb_join_date']        = gmdate('Y-m-d');
	$profile_data['cb_onboarding_stage'] = 'access_setup';
	CBNexus_Member_Repository::update_profile($existing_user_id, $profile_data);

	$user_id = $existing_user_id;
	WP_CLI::log("Promoted existing user {$user_id} to cb_member.");
} else {
	// Create brand-new WP user + member.
	$user_data = [
		'user_email'   => $candidate->email,
		'first_name'   => $first_name,
		'last_name'    => $last_name,
		'display_name' => trim($candidate->name),
	];

	$result = CBNexus_Member_Service::create_member($user_data, $profile_data, 'cb_member');

	if (!$result['success']) {
		WP_CLI::error('Member creation failed: ' . implode(', ', $result['errors'] ?? []));
	}

	$user_id = $result['user_id'];
	WP_CLI::log("Created new member (user ID {$user_id}).");
}

// Send welcome email.
$profile = CBNexus_Member_Repository::get_profile($user_id);
if ($profile) {
	CBNexus_Email_Service::send_welcome($user_id, $profile);
	WP_CLI::log("Welcome email sent.");
}

WP_CLI::success("Layne Pass is now an active member (user ID {$user_id}). They should appear in the Directory.");
