<?php
/**
 * Create CircleBlast Nexus member accounts.
 *
 * Usage:  wp eval-file create-members.php
 * Or:     Place in theme/plugin and load via browser with admin access.
 *
 * Each member gets a random password. The welcome email (with password
 * reset link) is sent automatically by CBNexus_Member_Service::create_member()
 * if you call CBNexus_Email_Service::send_welcome() after creation.
 */

if (!defined('ABSPATH')) {
	// Running via WP-CLI — ABSPATH is already defined.
	// If not, bail.
	echo "This script must be run within WordPress (wp eval-file).\n";
	exit(1);
}

$members = [
	[
		'first_name' => 'Ben',
		'last_name'  => 'Moser',
		'email'      => 'ben.moser@clubworks.us',
		'role'       => 'cb_super_admin',
	],
	[
		'first_name' => 'Bob',
		'last_name'  => 'Paden',
		'email'      => 'bob@bobpaden.com',
		'role'       => 'cb_admin',
	],
	[
		'first_name' => 'Lucas',
		'last_name'  => 'Woody',
		'email'      => 'lucas@nextbetter.com',
		'role'       => 'cb_super_admin',
	],
	[
		'first_name' => 'Noah',
		'last_name'  => 'Zahrn',
		'email'      => 'noah@highlinetechnologies.com',
		'role'       => 'cb_admin',
	],
	[
		'first_name' => 'Ryan',
		'last_name'  => 'Mcginley',
		'email'      => 'ryan.mcginley@clubworks.us',
		'role'       => 'cb_super_admin',
	],
	[
		'first_name' => 'Sundaresh',
		'last_name'  => 'Ramanathan',
		'email'      => 'sr@ansa.solutions',
		'role'       => 'cb_member',
	],
];

foreach ($members as $m) {
	$display = $m['first_name'] . ' ' . $m['last_name'];

	// Skip if user already exists.
	if (email_exists($m['email'])) {
		echo "SKIP: {$display} ({$m['email']}) — account already exists.\n";
		continue;
	}

	$result = CBNexus_Member_Service::create_member(
		[
			'user_login'   => $m['email'],
			'user_email'   => $m['email'],
			'first_name'   => $m['first_name'],
			'last_name'    => $m['last_name'],
			'display_name' => $display,
			'user_pass'    => wp_generate_password(16, true, true),
		],
		[
			'cb_member_status'   => 'active',
			'cb_join_date'       => gmdate('Y-m-d'),
			'cb_onboarding_stage' => 'access_setup',
		],
		$m['role']
	);

	if ($result['success']) {
		$uid = $result['user_id'];

		// Send welcome email with password reset link.
		$profile = CBNexus_Member_Repository::get_profile($uid);
		if ($profile) {
			CBNexus_Email_Service::send_welcome($profile);
		}

		$role_label = str_replace(['cb_super_admin', 'cb_admin', 'cb_member'], ['Super Admin', 'Admin', 'Member'], $m['role']);
		echo "OK:   {$display} ({$m['email']}) — created as {$role_label} (ID: {$uid}). Welcome email sent.\n";
	} else {
		$errors = implode(', ', $result['errors'] ?? ['Unknown error']);
		echo "FAIL: {$display} ({$m['email']}) — {$errors}\n";
	}
}

echo "\nDone.\n";
