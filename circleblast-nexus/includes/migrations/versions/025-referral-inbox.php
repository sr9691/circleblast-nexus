<?php
/**
 * Migration: 024 - Referral Inbox Integration
 *
 * Registers the referral inbox settings option and schedules the
 * polling cron if settings are already configured (re-activation safety).
 * No table changes — settings stored in wp_options, password in wp-config.php.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_025_Referral_Inbox {

	public static function up(): bool {
		// Ensure the option exists with safe defaults (no-op if already set).
		if (!get_option(CBNexus_Referral_Email_Parser::OPTION_KEY)) {
			add_option(CBNexus_Referral_Email_Parser::OPTION_KEY, [
				'enabled'        => false,
				'imap_host'      => '',
				'imap_port'      => 993,
				'imap_user'      => '',
				'imap_ssl'       => true,
				'imap_folder'    => 'INBOX',
				'poll_frequency' => 'hourly',
				'bcc_address'    => '',
				'auto_mirror'    => true,
				'delete_after'   => false,
			]);
		}

		// Reschedule cron in case this is a reactivation with settings already saved.
		CBNexus_Referral_Email_Parser::reschedule();

		return true;
	}
}
